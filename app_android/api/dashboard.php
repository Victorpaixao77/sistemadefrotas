<?php
/**
 * API Dashboard - Resumo do motorista (App Android)
 * GET: retorna contadores, rotas do dia, últimas rotas/abastecimentos/checklists
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

try {
    $conn = getConnection();

    // Contadores (pendentes)
    $stmt = $conn->prepare('
        SELECT COUNT(*) FROM rotas
        WHERE empresa_id = :e AND motorista_id = :m AND status = "pendente"
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $rotas_pendentes = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare('
        SELECT COUNT(*) FROM abastecimentos
        WHERE empresa_id = :e AND motorista_id = :m AND status = "pendente"
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $abastecimentos_pendentes = (int) $stmt->fetchColumn();

    $stmt = $conn->prepare('
        SELECT COUNT(*) FROM checklist_viagem
        WHERE empresa_id = :e AND motorista_id = :m
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $checklists_pendentes = (int) $stmt->fetchColumn();

    // Rotas do dia
    $stmt = $conn->prepare('
        SELECT r.*, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome
        FROM rotas r
        LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
        LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
        WHERE r.empresa_id = :e AND r.motorista_id = :m AND DATE(r.data_rota) = CURDATE()
        ORDER BY r.data_rota ASC
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $rotas_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimas rotas
    $stmt = $conn->prepare('
        SELECT r.*, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome
        FROM rotas r
        LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
        LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
        WHERE r.empresa_id = :e AND r.motorista_id = :m
        ORDER BY r.data_rota DESC
        LIMIT 10
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $ultimas_rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimos abastecimentos
    $stmt = $conn->prepare('
        SELECT a.*, v.placa
        FROM abastecimentos a
        JOIN veiculos v ON a.veiculo_id = v.id
        WHERE a.empresa_id = :e AND a.motorista_id = :m
        ORDER BY a.data_abastecimento DESC
        LIMIT 10
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $ultimos_abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Últimos checklists (checklist_viagem)
    $stmt = $conn->prepare('
        SELECT cv.*, v.placa, c1.nome AS cidade_origem_nome, c2.nome AS cidade_destino_nome
        FROM checklist_viagem cv
        JOIN veiculos v ON cv.veiculo_id = v.id
        JOIN rotas r ON cv.rota_id = r.id
        LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
        LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
        WHERE cv.empresa_id = :e AND cv.motorista_id = :m
        ORDER BY cv.data_checklist DESC
        LIMIT 10
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $ultimos_checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Totais do mês atual (fretes, comissão, despesas, lucro)
    $stmt = $conn->prepare('
        SELECT
            COALESCE(SUM(r.frete), 0) AS total_frete_mes,
            COALESCE(SUM(r.comissao), 0) AS total_comissao_mes
        FROM rotas r
        WHERE r.empresa_id = :e AND r.motorista_id = :m
          AND MONTH(r.data_rota) = MONTH(CURDATE())
          AND YEAR(r.data_rota) = YEAR(CURDATE())
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_frete_mes = (float)($row['total_frete_mes'] ?? 0);
    $total_comissao_mes = (float)($row['total_comissao_mes'] ?? 0);

    $stmt = $conn->prepare('
        SELECT COALESCE(SUM(dv.total_despviagem), 0) AS total_despesas_mes
        FROM despesas_viagem dv
        INNER JOIN rotas r ON r.id = dv.rota_id AND r.empresa_id = dv.empresa_id
        WHERE dv.empresa_id = :e AND r.motorista_id = :m
          AND MONTH(r.data_rota) = MONTH(CURDATE())
          AND YEAR(r.data_rota) = YEAR(CURDATE())
    ');
    $stmt->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $total_despesas_mes = (float)($stmt->fetchColumn());

    $lucro_mes = $total_frete_mes - $total_comissao_mes - $total_despesas_mes;

    $gps_pontos_24h = 0;
    $gps_ultimo = null;
    try {
        $stG = $conn->prepare('
            SELECT COUNT(*) FROM gps_logs
            WHERE empresa_id = :e AND motorista_id = :m AND data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ');
        $stG->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $gps_pontos_24h = (int) $stG->fetchColumn();

        $stU = $conn->prepare('
            SELECT g.latitude, g.longitude, g.data_hora, g.veiculo_id, v.placa
            FROM gps_logs g
            INNER JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = g.empresa_id
            WHERE g.empresa_id = :e AND g.motorista_id = :m
            ORDER BY g.data_hora DESC
            LIMIT 1
        ');
        $stU->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        $gps_ultimo = $stU->fetch(PDO::FETCH_ASSOC) ?: null;
        if (is_array($gps_ultimo)) {
            if (isset($gps_ultimo['latitude'])) {
                $gps_ultimo['latitude'] = (float) $gps_ultimo['latitude'];
            }
            if (isset($gps_ultimo['longitude'])) {
                $gps_ultimo['longitude'] = (float) $gps_ultimo['longitude'];
            }
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'gps_logs') === false && strpos($e->getMessage(), "doesn't exist") === false) {
            error_log('API dashboard GPS: ' . $e->getMessage());
        }
    }

    api_success([
        'contadores' => [
            'rotas_pendentes' => $rotas_pendentes,
            'abastecimentos_pendentes' => $abastecimentos_pendentes,
            'checklists_pendentes' => $checklists_pendentes,
        ],
        'gps_resumo' => [
            'pontos_ultimas_24h' => $gps_pontos_24h,
            'ultimo_registro' => $gps_ultimo,
        ],
        'resumo_mes' => [
            'total_frete_mes' => round($total_frete_mes, 2),
            'total_comissao_mes' => round($total_comissao_mes, 2),
            'total_despesas_mes' => round($total_despesas_mes, 2),
            'lucro_mes' => round($lucro_mes, 2),
        ],
        'rotas_hoje' => $rotas_hoje,
        'ultimas_rotas' => $ultimas_rotas,
        'ultimos_abastecimentos' => $ultimos_abastecimentos,
        'ultimos_checklists' => $ultimos_checklists,
    ]);
} catch (PDOException $e) {
    error_log('API dashboard: ' . $e->getMessage());
    api_error('Erro ao carregar dashboard.', 500);
}
