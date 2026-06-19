<?php
/**
 * Detecção de anomalias/fraude em abastecimentos.
 *
 * Varre todos os abastecimentos da empresa (sequência por veículo) e sinaliza:
 *  ERROS:  KM regressivo (km menor que o do abastecimento anterior do veículo).
 *  AVISOS: preço/litro fora da faixa esperada; consumo implausível
 *          (muito combustível para a distância rodada desde o último abastecimento).
 *
 * Obs.: a regra "abastecimento duplicado no mesmo dia" fica de fora por padrão
 * porque exige dados limpos (timestamps reais); pode ser ativada depois.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();

require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

// Faixa plausível de preço por litro (R$). Fora disso = quase certo erro de digitação.
$PRECO_MIN = 1.5;
$PRECO_MAX = 12.0;
// Consumo: litros mínimos para considerar e km/L mínimo aceitável (abaixo = muito combustível p/ pouca distância).
$CONS_LITROS_MIN = 20.0;
$CONS_KML_MIN = 0.8;

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'refuel_anomalies', 300, function () use ($conn, $empresa_id, $PRECO_MIN, $PRECO_MAX, $CONS_LITROS_MIN, $CONS_KML_MIN) {
    $sql = "SELECT a.id, a.veiculo_id, a.data_abastecimento, a.km_atual, a.litros,
                   a.valor_litro, a.posto, a.tipo_combustivel,
                   v.placa AS veiculo_placa, m.nome AS motorista_nome
            FROM abastecimentos a
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            LEFT JOIN motoristas m ON a.motorista_id = m.id
            WHERE a.empresa_id = :empresa_id
            ORDER BY a.veiculo_id, a.data_abastecimento, a.id
            LIMIT 10000";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $abast = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($abast);
    $prevKm = [];   // último km por veículo
    $itens = [];
    $totalErros = 0;
    $totalAvisos = 0;

    foreach ($abast as $a) {
        $vid = (int) $a['veiculo_id'];
        $km = (float) $a['km_atual'];
        $lit = (float) $a['litros'];
        $vl = (float) $a['valor_litro'];
        $motivos = [];
        $temErro = false;

        // KM regressivo
        if (isset($prevKm[$vid]) && $prevKm[$vid] > 0 && $km > 0 && $km < $prevKm[$vid]) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'KM informado (' . number_format($km, 0, ',', '.') . ') menor que o abastecimento anterior do veículo (' . number_format($prevKm[$vid], 0, ',', '.') . ').'];
            $temErro = true;
        }

        // Consumo implausível (muito combustível para pouca distância)
        if (isset($prevKm[$vid]) && $prevKm[$vid] > 0 && $km > $prevKm[$vid] && $lit >= $CONS_LITROS_MIN) {
            $kmL = ($km - $prevKm[$vid]) / $lit;
            if ($kmL < $CONS_KML_MIN) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Consumo implausível: ' . number_format($kmL, 2, ',', '.') . ' km/L (' . number_format($lit, 0, ',', '.') . ' L para ' . number_format($km - $prevKm[$vid], 0, ',', '.') . ' km).'];
            }
        }

        // Preço/litro fora da faixa
        if ($vl > 0 && ($vl < $PRECO_MIN || $vl > $PRECO_MAX)) {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Preço/litro fora da faixa esperada (R$ ' . number_format($vl, 2, ',', '.') . '/L).'];
        }

        if ($km > 0) {
            $prevKm[$vid] = $km;
        }

        if (!$motivos) {
            continue;
        }
        if ($temErro) {
            $totalErros++;
        } else {
            $totalAvisos++;
        }

        $itens[] = [
            'id' => (int) $a['id'],
            'data' => $a['data_abastecimento'],
            'veiculo' => $a['veiculo_placa'] ?: '—',
            'motorista' => $a['motorista_nome'] ?: '—',
            'posto' => $a['posto'] ?: '—',
            'severidade' => $temErro ? 'erro' : 'aviso',
            'motivos' => $motivos,
        ];
    }

    usort($itens, function ($x, $y) {
        if ($x['severidade'] === $y['severidade']) {
            return strcmp((string) $y['data'], (string) $x['data']);
        }
        return $x['severidade'] === 'erro' ? -1 : 1;
    });

    return [
        'success' => true,
        'total_abastecimentos' => $total,
        'total_inconsistencias' => count($itens),
        'erros' => $totalErros,
        'avisos' => $totalAvisos,
        'itens' => $itens,
    ];
    });
} catch (Throwable $e) {
    error_log('api/refuel_anomalies.php: ' . $e->getMessage());
    api_json_error('Erro ao analisar inconsistências.', 500);
}
