<?php
/**
 * Detecção de inconsistências/anomalias em rotas.
 *
 * Varre as rotas da empresa (opcionalmente filtradas por ano/mês/motorista/veículo)
 * e sinaliza:
 *  ERROS  (provavelmente errados): KM chegada < saída; data chegada < saída;
 *          carga acima da capacidade do veículo.
 *  AVISOS (revisar): eficiência muito baixa; % vazio muito alto; frete/km fora da curva.
 *
 * GET: ano, mes, motorista_id, veiculo_id (todos opcionais).
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_insights_cache.php';

configure_session();
session_start();

require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$ano = isset($_GET['ano']) ? (int) $_GET['ano'] : 0;
$mes = isset($_GET['mes']) ? (int) $_GET['mes'] : 0;
$motorista_id = isset($_GET['motorista_id']) ? (int) $_GET['motorista_id'] : 0;
$veiculo_id = isset($_GET['veiculo_id']) ? (int) $_GET['veiculo_id'] : 0;
$cacheKeyExtra = ['ano' => $ano, 'mes' => $mes, 'motorista_id' => $motorista_id, 'veiculo_id' => $veiculo_id];

try {
    $conn = getConnection();

    sf_insights_json_cached($empresa_id, 'route_anomalies', 300, function () use ($conn, $empresa_id, $ano, $mes, $motorista_id, $veiculo_id) {
    $where = ['r.empresa_id = :empresa_id'];
    $params = [':empresa_id' => $empresa_id];
    if ($ano > 0) {
        $where[] = 'YEAR(r.data_rota) = :ano';
        $params[':ano'] = $ano;
    }
    if ($mes > 0) {
        $where[] = 'MONTH(r.data_rota) = :mes';
        $params[':mes'] = $mes;
    }
    if ($motorista_id > 0) {
        $where[] = 'r.motorista_id = :mid';
        $params[':mid'] = $motorista_id;
    }
    if ($veiculo_id > 0) {
        $where[] = 'r.veiculo_id = :vid';
        $params[':vid'] = $veiculo_id;
    }
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT r.id, r.data_rota, r.data_saida, r.data_chegada,
                   r.km_saida, r.km_chegada, r.distancia_km, r.total_km,
                   r.frete, r.eficiencia_viagem, r.percentual_vazio, r.peso_carga,
                   v.placa AS veiculo_placa, v.capacidade_carga,
                   m.nome AS motorista_nome,
                   co.nome AS origem, cd.nome AS destino,
                   r.estado_origem, r.estado_destino
            FROM rotas r
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE {$whereSql}
            ORDER BY r.data_rota DESC
            LIMIT 5000";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRotas = count($rotas);

    // Média de frete/km (base para outliers), só rotas com frete e km válidos.
    $somaFreteKm = 0.0;
    $nFreteKm = 0;
    foreach ($rotas as $r) {
        $km = max((float) $r['total_km'], (float) $r['distancia_km']);
        $frete = (float) $r['frete'];
        if ($frete > 0 && $km > 0) {
            $somaFreteKm += $frete / $km;
            $nFreteKm++;
        }
    }
    $avgFreteKm = $nFreteKm > 0 ? $somaFreteKm / $nFreteKm : 0.0;

    $itens = [];
    $totalErros = 0;
    $totalAvisos = 0;

    foreach ($rotas as $r) {
        $motivos = [];
        $temErro = false;

        $kmS = ($r['km_saida'] !== null && $r['km_saida'] !== '') ? (float) $r['km_saida'] : null;
        $kmC = ($r['km_chegada'] !== null && $r['km_chegada'] !== '') ? (float) $r['km_chegada'] : null;
        if ($kmS !== null && $kmC !== null && $kmC < $kmS) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'KM de chegada (' . number_format($kmC, 0, ',', '.') . ') menor que o de saída (' . number_format($kmS, 0, ',', '.') . ').'];
            $temErro = true;
        }

        $tS = !empty($r['data_saida']) ? strtotime($r['data_saida']) : false;
        $tC = !empty($r['data_chegada']) ? strtotime($r['data_chegada']) : false;
        if ($tS && $tC && $tC < $tS) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'Data de chegada anterior à de saída.'];
            $temErro = true;
        }

        $peso = (float) $r['peso_carga'];
        $cap = (float) $r['capacidade_carga'];
        if ($peso > 0 && $cap > 0 && $peso > $cap) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'Carga (' . number_format($peso, 0, ',', '.') . ' kg) acima da capacidade do veículo (' . number_format($cap, 0, ',', '.') . ' kg).'];
            $temErro = true;
        }

        $efi = ($r['eficiencia_viagem'] !== null && $r['eficiencia_viagem'] !== '') ? (float) $r['eficiencia_viagem'] : null;
        if ($efi !== null && $efi > 0 && $efi < 30) {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'Eficiência muito baixa (' . number_format($efi, 1, ',', '.') . '%).'];
        }

        $pv = ($r['percentual_vazio'] !== null && $r['percentual_vazio'] !== '') ? (float) $r['percentual_vazio'] : null;
        if ($pv !== null && $pv > 50) {
            $motivos[] = ['sev' => 'aviso', 'msg' => 'KM vazio alto (' . number_format($pv, 1, ',', '.') . '%).'];
        }

        $km = max((float) $r['total_km'], (float) $r['distancia_km']);
        $frete = (float) $r['frete'];
        if ($avgFreteKm > 0 && $frete > 0 && $km > 0) {
            $fk = $frete / $km;
            $ratio = $fk / $avgFreteKm;
            if ($ratio > 3.0) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Frete/km muito acima da média (R$ ' . number_format($fk, 2, ',', '.') . '/km vs média R$ ' . number_format($avgFreteKm, 2, ',', '.') . ').'];
            } elseif ($ratio < 0.20) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Frete/km muito abaixo da média (R$ ' . number_format($fk, 2, ',', '.') . '/km vs média R$ ' . number_format($avgFreteKm, 2, ',', '.') . ').'];
            }
        }

        if (!$motivos) {
            continue;
        }
        if ($temErro) {
            $totalErros++;
        } else {
            $totalAvisos++;
        }

        $origem = $r['origem'] ?: $r['estado_origem'] ?: '?';
        $destino = $r['destino'] ?: $r['estado_destino'] ?: '?';
        $itens[] = [
            'id' => (int) $r['id'],
            'data_rota' => $r['data_rota'],
            'motorista' => $r['motorista_nome'] ?: '—',
            'placa' => $r['veiculo_placa'] ?: '—',
            'rota' => $origem . ' → ' . $destino,
            'severidade' => $temErro ? 'erro' : 'aviso',
            'motivos' => $motivos,
        ];
    }

    // Erros antes dos avisos.
    usort($itens, function ($a, $b) {
        if ($a['severidade'] === $b['severidade']) {
            return strcmp($b['data_rota'], $a['data_rota']);
        }
        return $a['severidade'] === 'erro' ? -1 : 1;
    });

    return [
        'success' => true,
        'total_rotas' => $totalRotas,
        'total_inconsistencias' => count($itens),
        'erros' => $totalErros,
        'avisos' => $totalAvisos,
        'avg_frete_km' => round($avgFreteKm, 2),
        'itens' => $itens,
    ];
    }, $cacheKeyExtra);
} catch (Throwable $e) {
    error_log('api/route_anomalies.php: ' . $e->getMessage());
    api_json_error('Erro ao analisar inconsistências.', 500);
}
