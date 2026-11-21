<?php
/**
 * Manutenção preditiva por veículo — quando revisar, a partir de dados REAIS:
 *  - km rodado desde a última manutenção (veiculos.km_atual - km_ultima_manutencao)
 *  - km/dia médio real (rotas dos últimos 90 dias)
 *  - intervalo: média do próprio histórico do veículo (>=2 manutenções); senão intervalo padrão (rotulado)
 * Sem dado de KM ⇒ "sem_dados" (não inventa previsão).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../cron/atualizar_km_veiculos.php'; // fornece sf_atualizar_km_veiculos()

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=UTF-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Empresa não identificada.']);
    exit;
}

const INTERVALO_PADRAO_KM = 10000; // revisão preventiva típica — usado só quando não há histórico do veículo

try {
    $conn = getConnection();

    // Atualiza o km_atual da frota a partir do maior KM real (rotas/abastecimentos).
    if (($_POST['action'] ?? '') === 'atualizar_km') {
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
            exit;
        }
        $n = sf_atualizar_km_veiculos($conn, $empresa_id);
        echo json_encode(['success' => true, 'atualizados' => $n]);
        exit;
    }

    // Veículos ativos.
    $sv = $conn->prepare("SELECT id, placa, modelo, km_atual, km_ultima_manutencao, ultima_manutencao
        FROM veiculos WHERE empresa_id = :e AND (status_id = 1 OR status_id IS NULL) ORDER BY placa");
    $sv->execute([':e' => $empresa_id]);
    $veiculos = $sv->fetchAll(PDO::FETCH_ASSOC);

    // km/dia médio real (rotas últimos 90 dias) por veículo.
    $km90 = [];
    $sk = $conn->prepare("SELECT veiculo_id, COALESCE(SUM(total_km), 0) km FROM rotas
        WHERE empresa_id = :e AND COALESCE(data_rota, data_saida) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        GROUP BY veiculo_id");
    $sk->execute([':e' => $empresa_id]);
    foreach ($sk->fetchAll(PDO::FETCH_ASSOC) as $r) { $km90[(int) $r['veiculo_id']] = (float) $r['km']; }

    // Histórico de km das manutenções por veículo (para intervalo real do próprio veículo).
    $histKm = [];
    try {
        $sh = $conn->prepare("SELECT veiculo_id, km_atual FROM manutencoes
            WHERE empresa_id = :e AND km_atual IS NOT NULL AND km_atual > 0 ORDER BY veiculo_id, km_atual");
        $sh->execute([':e' => $empresa_id]);
        foreach ($sh->fetchAll(PDO::FETCH_ASSOC) as $r) { $histKm[(int) $r['veiculo_id']][] = (float) $r['km_atual']; }
    } catch (PDOException $e) { /* ignora */ }

    $itens = [];
    foreach ($veiculos as $v) {
        $vid = (int) $v['id'];
        $kmAtual = $v['km_atual'] !== null ? (float) $v['km_atual'] : null;
        $kmUlt = $v['km_ultima_manutencao'] !== null ? (float) $v['km_ultima_manutencao'] : null;

        // Intervalo: média do histórico do próprio veículo (>=2 pontos), senão padrão.
        $intervalo = INTERVALO_PADRAO_KM; $intervaloFonte = 'padrao';
        if (!empty($histKm[$vid]) && count($histKm[$vid]) >= 2) {
            $kms = $histKm[$vid]; sort($kms);
            $gaps = []; for ($i = 1; $i < count($kms); $i++) { $g = $kms[$i] - $kms[$i - 1]; if ($g > 0) { $gaps[] = $g; } }
            if ($gaps) { $intervalo = array_sum($gaps) / count($gaps); $intervaloFonte = 'historico'; }
        }

        $kmDia = ($km90[$vid] ?? 0) / 90.0;

        if ($kmAtual === null || $kmUlt === null || $kmAtual < $kmUlt) {
            $itens[] = [
                'placa' => $v['placa'], 'modelo' => $v['modelo'], 'status' => 'sem_dados',
                'detalhe' => 'Sem KM atual/última revisão cadastrado.', 'km_dia' => round($kmDia, 1),
            ];
            continue;
        }

        $kmDesde = $kmAtual - $kmUlt;
        $kmRestante = $intervalo - $kmDesde;
        $diasRestante = ($kmDia > 0) ? ($kmRestante / $kmDia) : null;

        $status = 'em_dia';
        if ($kmRestante <= 0) { $status = 'atrasada'; }
        elseif ($kmRestante <= 0.15 * $intervalo || ($diasRestante !== null && $diasRestante <= 15)) { $status = 'proxima'; }

        $itens[] = [
            'placa' => $v['placa'], 'modelo' => $v['modelo'], 'status' => $status,
            'km_atual' => round($kmAtual), 'km_desde' => round($kmDesde), 'intervalo' => round($intervalo),
            'intervalo_fonte' => $intervaloFonte, 'km_restante' => round($kmRestante),
            'km_dia' => round($kmDia, 1),
            'dias_restante' => $diasRestante !== null ? (int) round($diasRestante) : null,
            'data_prevista' => ($diasRestante !== null && $diasRestante > 0) ? date('Y-m-d', strtotime('+' . (int) round($diasRestante) . ' days')) : null,
            'ultima_manutencao' => $v['ultima_manutencao'],
        ];
    }

    // Ordena por urgência: atrasada, próxima, em dia, sem dados.
    $ordem = ['atrasada' => 0, 'proxima' => 1, 'em_dia' => 2, 'sem_dados' => 3];
    usort($itens, function ($a, $b) use ($ordem) {
        $c = ($ordem[$a['status']] ?? 9) - ($ordem[$b['status']] ?? 9);
        if ($c !== 0) { return $c; }
        return ($a['km_restante'] ?? 1e9) <=> ($b['km_restante'] ?? 1e9);
    });

    $resumo = ['atrasada' => 0, 'proxima' => 0, 'em_dia' => 0, 'sem_dados' => 0];
    foreach ($itens as $i) { $resumo[$i['status']] = ($resumo[$i['status']] ?? 0) + 1; }

    echo json_encode(['success' => true, 'itens' => $itens, 'resumo' => $resumo, 'intervalo_padrao' => INTERVALO_PADRAO_KM], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/manutencao_preditiva.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao calcular a manutenção preditiva.']);
}
