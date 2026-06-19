<?php
/**
 * Abastecimento Inteligente - App Android.
 * GET: análise de consumo (km/l) + tendência + alertas antifraude do motorista,
 *      a partir dos abastecimentos já lançados (sem configuração extra).
 * Params: dias (padrão 90, máx 365).
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

try {
    $conn = getConnection();
    $dias = min(365, max(7, (int) ($_GET['dias'] ?? 90)));

    $stmt = $conn->prepare('
        SELECT a.id, a.veiculo_id, a.posto, a.data_abastecimento,
               a.km_atual, a.litros, a.valor_litro, a.valor_total, v.placa
        FROM abastecimentos a
        LEFT JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.empresa_id = :e AND a.motorista_id = :m
          AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL :d DAY)
        ORDER BY a.veiculo_id, a.km_atual ASC, a.data_abastecimento ASC
    ');
    $stmt->bindValue(':e', $empresa_id, PDO::PARAM_INT);
    $stmt->bindValue(':m', $motorista_id, PDO::PARAM_INT);
    $stmt->bindValue(':d', $dias, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mediana de litros por veículo (para detectar volume muito acima do normal).
    $litrosPorVeic = [];
    foreach ($rows as $r) {
        $vid = (int) $r['veiculo_id'];
        $l = (float) $r['litros'];
        if ($l > 0) {
            $litrosPorVeic[$vid][] = $l;
        }
    }
    $medianaLitros = [];
    foreach ($litrosPorVeic as $vid => $arr) {
        sort($arr);
        $n = count($arr);
        $medianaLitros[$vid] = $n ? ($n % 2 ? $arr[intdiv($n, 2)] : ($arr[$n / 2 - 1] + $arr[$n / 2]) / 2) : 0;
    }

    $consumos = [];   // consumos válidos (km/l)
    $alertas = [];
    $prevByVeic = [];

    foreach ($rows as $r) {
        $vid = (int) $r['veiculo_id'];
        $km = (float) $r['km_atual'];
        $litros = (float) $r['litros'];
        $vl = (float) $r['valor_litro'];
        $quando = (string) $r['data_abastecimento'];
        $label = ($r['placa'] ?? '') . ' · ' . substr($quando, 0, 10);

        // Preço por litro fora da faixa.
        if ($vl > 0 && ($vl < 2.0 || $vl > 12.0)) {
            $alertas[] = ['data' => $quando, 'posto' => $r['posto'], 'placa' => $r['placa'], 'motivo' => 'Preço por litro fora da faixa (R$ ' . number_format($vl, 2, ',', '.') . ').', 'severidade' => 'media'];
        }
        // Litros absurdos.
        if ($litros > 1000) {
            $alertas[] = ['data' => $quando, 'posto' => $r['posto'], 'placa' => $r['placa'], 'motivo' => 'Volume muito alto (' . number_format($litros, 0, ',', '.') . ' L).', 'severidade' => 'alta'];
        }
        // Volume muito acima da média do veículo.
        $med = $medianaLitros[$vid] ?? 0;
        if ($med > 0 && $litros > $med * 2.5 && $litros > 50) {
            $alertas[] = ['data' => $quando, 'posto' => $r['posto'], 'placa' => $r['placa'], 'motivo' => 'Volume ' . round($litros / $med, 1) . '× acima do normal deste veículo.', 'severidade' => 'media'];
        }

        if (isset($prevByVeic[$vid])) {
            $prev = $prevByVeic[$vid];
            $kmDiff = $km - (float) $prev['km_atual'];
            if ($kmDiff < 0) {
                $alertas[] = ['data' => $quando, 'posto' => $r['posto'], 'placa' => $r['placa'], 'motivo' => 'Quilometragem menor que o abastecimento anterior.', 'severidade' => 'alta'];
            } elseif ($kmDiff > 0 && $litros > 0) {
                $cons = $kmDiff / $litros;
                if ($cons >= 0.3 && $cons <= 8.0) {
                    $consumos[] = $cons;
                } elseif ($cons > 12.0 || $cons < 0.2) {
                    $alertas[] = ['data' => $quando, 'posto' => $r['posto'], 'placa' => $r['placa'], 'motivo' => 'Consumo fora do esperado (' . number_format($cons, 1, ',', '.') . ' km/l) — verifique KM e litros.', 'severidade' => 'media'];
                }
            }
        }
        $prevByVeic[$vid] = $r;
    }

    $n = count($consumos);
    $media = $n ? array_sum($consumos) / $n : null;
    $ultimo = $n ? $consumos[$n - 1] : null;
    $tendencia = ($media && $media > 0 && $ultimo !== null) ? (($ultimo - $media) / $media) * 100 : null;

    // Insight textual (heurístico, sem chamar IA externa).
    if ($media === null) {
        $insight = 'Ainda não há abastecimentos suficientes para calcular seu consumo. Registre ao menos dois abastecimentos do mesmo veículo com KM correto.';
    } else {
        $base = 'Seu consumo médio é ' . number_format($media, 2, ',', '.') . ' km/l.';
        if ($tendencia === null) {
            $insight = $base;
        } elseif ($tendencia <= -10) {
            $insight = $base . ' O último caiu ' . number_format(abs($tendencia), 0, ',', '.') . '% — verifique calibragem dos pneus, peso da carga e o estilo de direção (acelerações bruscas gastam mais).';
        } elseif ($tendencia >= 10) {
            $insight = $base . ' O último melhorou ' . number_format($tendencia, 0, ',', '.') . '% em relação à sua média. Continue assim! 👏';
        } else {
            $insight = $base . ' Seu consumo está estável.';
        }
    }

    // Mantém alertas mais recentes primeiro e limita.
    usort($alertas, function ($a, $b) {
        return strcmp((string) $b['data'], (string) $a['data']);
    });
    $alertas = array_slice($alertas, 0, 30);

    api_success([
        'dias' => $dias,
        'consumo_medio' => $media !== null ? round($media, 2) : null,
        'consumo_ultimo' => $ultimo !== null ? round($ultimo, 2) : null,
        'tendencia_pct' => $tendencia !== null ? round($tendencia, 1) : null,
        'amostras' => $n,
        'total_abastecimentos' => count($rows),
        'insight' => $insight,
        'alertas' => array_values($alertas),
    ]);
} catch (PDOException $e) {
    error_log('abastecimento_insights.php: ' . $e->getMessage());
    api_error('Erro ao analisar abastecimentos.', 500);
}
