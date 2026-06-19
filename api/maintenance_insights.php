<?php
/**
 * Insights de manutenção:
 *  - preventivos: veículos com manutenção vencida ou a vencer (por tempo e/ou km),
 *                 e veículos sem nenhuma manutenção registrada.
 *  - anomalias:   data de conclusão antes da abertura; KM regressivo; custo muito
 *                 acima da média do tipo de serviço.
 *
 * Intervalos padrão: 6 meses ou 10.000 km (configuráveis via ?meses= e ?km=).
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

$intervaloMeses = isset($_GET['meses']) ? max(1, (int) $_GET['meses']) : 6;
$intervaloKm = isset($_GET['km']) ? max(500, (int) $_GET['km']) : 10000;
$cacheKeyExtra = ['meses' => $intervaloMeses, 'km' => $intervaloKm];

try {
    $conn = getConnection();
    $hoje = new DateTime('today');

    sf_insights_json_cached($empresa_id, 'maintenance', 300, function () use ($conn, $empresa_id, $hoje, $intervaloMeses, $intervaloKm) {
    // ---------- PREVENTIVOS ----------
    $sqlPrev = "SELECT v.id, v.placa, v.km_atual AS km_veiculo,
                       MAX(m.data_manutencao) AS ult_data,
                       (SELECT m2.km_atual FROM manutencoes m2
                        WHERE m2.veiculo_id = v.id AND m2.empresa_id = v.empresa_id
                        ORDER BY m2.data_manutencao DESC, m2.id DESC LIMIT 1) AS ult_km,
                       COUNT(m.id) AS n
                FROM veiculos v
                LEFT JOIN manutencoes m ON m.veiculo_id = v.id AND m.empresa_id = v.empresa_id
                WHERE v.empresa_id = :e
                GROUP BY v.id, v.placa, v.km_atual";
    $stmt = $conn->prepare($sqlPrev);
    $stmt->execute([':e' => $empresa_id]);
    $veics = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $preventivos = [];
    $vencidos = 0;
    $aVencer = 0;
    foreach ($veics as $v) {
        $placa = $v['placa'] ?: ('#' . $v['id']);
        if ((int) $v['n'] === 0) {
            $preventivos[] = ['placa' => $placa, 'severidade' => 'aviso', 'motivo' => 'Nenhuma manutenção registrada.'];
            $aVencer++;
            continue;
        }
        $motivos = [];
        $sev = 'aviso';

        if (!empty($v['ult_data'])) {
            try {
                $d = new DateTime($v['ult_data']);
                $meses = ($hoje->diff($d)->y * 12) + $hoje->diff($d)->m + ($hoje->diff($d)->d > 0 ? ($hoje->diff($d)->d / 30) : 0);
                if ($meses >= $intervaloMeses) {
                    $motivos[] = 'Última manutenção há ' . round($meses) . ' meses (limite ' . $intervaloMeses . ').';
                    $sev = 'erro';
                } elseif ($meses >= $intervaloMeses * 0.8) {
                    $motivos[] = 'Manutenção a vencer (há ' . round($meses) . ' de ' . $intervaloMeses . ' meses).';
                }
            } catch (Throwable $e) {
            }
        }

        $kmVeic = (float) $v['km_veiculo'];
        $kmUlt = (float) $v['ult_km'];
        if ($kmVeic > 0 && $kmUlt > 0 && $kmVeic > $kmUlt) {
            $kmSince = $kmVeic - $kmUlt;
            if ($kmSince >= $intervaloKm) {
                $motivos[] = 'Rodou ' . number_format($kmSince, 0, ',', '.') . ' km desde a última (limite ' . number_format($intervaloKm, 0, ',', '.') . ').';
                $sev = 'erro';
            } elseif ($kmSince >= $intervaloKm * 0.8) {
                $motivos[] = 'A ' . number_format($intervaloKm - $kmSince, 0, ',', '.') . ' km do limite de revisão.';
            }
        }

        if ($motivos) {
            $preventivos[] = ['placa' => $placa, 'severidade' => $sev, 'motivo' => implode(' ', $motivos)];
            if ($sev === 'erro') {
                $vencidos++;
            } else {
                $aVencer++;
            }
        }
    }
    usort($preventivos, function ($a, $b) {
        return $a['severidade'] === $b['severidade'] ? 0 : ($a['severidade'] === 'erro' ? -1 : 1);
    });

    // ---------- ANOMALIAS ----------
    $sqlAnom = "SELECT m.id, m.veiculo_id, m.data_manutencao, m.data_conclusao, m.km_atual,
                       COALESCE(NULLIF(m.custo_total, 0), m.valor, 0) AS custo,
                       m.tipo_manutencao_id, v.placa, tm.nome AS tipo_nome
                FROM manutencoes m
                LEFT JOIN veiculos v ON m.veiculo_id = v.id
                LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                WHERE m.empresa_id = :e
                ORDER BY m.veiculo_id, m.data_manutencao, m.id
                LIMIT 10000";
    $stmt = $conn->prepare($sqlAnom);
    $stmt->execute([':e' => $empresa_id]);
    $manuts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Média de custo por tipo (para outliers).
    $somaTipo = [];
    $countTipo = [];
    foreach ($manuts as $m) {
        $t = (int) $m['tipo_manutencao_id'];
        $c = (float) $m['custo'];
        if ($c > 0) {
            $somaTipo[$t] = ($somaTipo[$t] ?? 0) + $c;
            $countTipo[$t] = ($countTipo[$t] ?? 0) + 1;
        }
    }

    $anomalias = [];
    $anErros = 0;
    $anAvisos = 0;
    $prevKm = [];
    foreach ($manuts as $m) {
        $vid = (int) $m['veiculo_id'];
        $km = (float) $m['km_atual'];
        $motivos = [];
        $temErro = false;

        if (!empty($m['data_conclusao']) && !empty($m['data_manutencao'])
            && strtotime($m['data_conclusao']) < strtotime($m['data_manutencao'])) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'Data de conclusão anterior à de abertura.'];
            $temErro = true;
        }

        if (isset($prevKm[$vid]) && $prevKm[$vid] > 0 && $km > 0 && $km < $prevKm[$vid]) {
            $motivos[] = ['sev' => 'erro', 'msg' => 'KM (' . number_format($km, 0, ',', '.') . ') menor que o da manutenção anterior do veículo (' . number_format($prevKm[$vid], 0, ',', '.') . ').'];
            $temErro = true;
        }

        $t = (int) $m['tipo_manutencao_id'];
        $c = (float) $m['custo'];
        if ($c > 0 && ($countTipo[$t] ?? 0) >= 3) {
            $avg = $somaTipo[$t] / $countTipo[$t];
            if ($avg > 0 && $c > $avg * 3) {
                $motivos[] = ['sev' => 'aviso', 'msg' => 'Custo muito acima da média do tipo "' . ($m['tipo_nome'] ?: 'serviço') . '" (R$ ' . number_format($c, 2, ',', '.') . ' vs média R$ ' . number_format($avg, 2, ',', '.') . ').'];
            }
        }

        if ($km > 0) {
            $prevKm[$vid] = $km;
        }
        if (!$motivos) {
            continue;
        }
        if ($temErro) {
            $anErros++;
        } else {
            $anAvisos++;
        }
        $anomalias[] = [
            'id' => (int) $m['id'],
            'data' => $m['data_manutencao'],
            'veiculo' => $m['placa'] ?: '—',
            'tipo' => $m['tipo_nome'] ?: '—',
            'severidade' => $temErro ? 'erro' : 'aviso',
            'motivos' => $motivos,
        ];
    }
    usort($anomalias, function ($a, $b) {
        if ($a['severidade'] === $b['severidade']) {
            return strcmp((string) $b['data'], (string) $a['data']);
        }
        return $a['severidade'] === 'erro' ? -1 : 1;
    });

    // ---------- CUSTO POR VEÍCULO (R$/km) ----------
    $sqlCusto = "SELECT v.placa,
                        SUM(COALESCE(NULLIF(m.custo_total,0), m.valor, 0)) AS total,
                        MAX(m.km_atual) AS km
                 FROM manutencoes m
                 JOIN veiculos v ON m.veiculo_id = v.id
                 WHERE m.empresa_id = :e
                 GROUP BY v.id, v.placa
                 HAVING total > 0
                 ORDER BY total DESC
                 LIMIT 8";
    $stmt = $conn->prepare($sqlCusto);
    $stmt->execute([':e' => $empresa_id]);
    $custoVeiculos = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $total = (float) $row['total'];
        $km = (float) $row['km'];
        $custoVeiculos[] = [
            'placa' => $row['placa'] ?: '—',
            'total' => round($total, 2),
            'por_km' => $km > 0 ? round($total / $km, 2) : null,
        ];
    }

    return [
        'success' => true,
        'intervalo_meses' => $intervaloMeses,
        'intervalo_km' => $intervaloKm,
        'preventivos' => $preventivos,
        'prev_vencidos' => $vencidos,
        'prev_a_vencer' => $aVencer,
        'anomalias' => $anomalias,
        'anom_erros' => $anErros,
        'anom_avisos' => $anAvisos,
        'custo_veiculos' => $custoVeiculos,
    ];
    }, $cacheKeyExtra);
} catch (Throwable $e) {
    error_log('api/maintenance_insights.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar insights de manutenção.', 500);
}
