<?php
/**
 * API unificada de pneus.
 * GET ?action=summary|executive|timeline|cost_km|previsao_compras|disponiveis|ficha|medicoes_sulco|ranking_marcas|qr
 */
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneus_repository.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = pneus_empresa_id();
$action = $_GET['action'] ?? 'summary';

try {
    switch ($action) {
        case 'summary':
            echo json_encode(['success' => true, 'data' => pneus_get_summary($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'executive':
            echo json_encode(['success' => true, 'data' => pneus_get_executive($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'timeline':
            $pneu_id = (int) ($_GET['pneu_id'] ?? $_GET['id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $limit = min(100, max(5, (int) ($_GET['limit'] ?? 50)));
            echo json_encode([
                'success' => true,
                'pneu_id' => $pneu_id,
                'timeline' => pneus_get_timeline($conn, $pneu_id, $empresa_id, $limit),
                'custo_km' => pneus_get_custo_km($conn, $pneu_id, $empresa_id),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'cost_km':
            $pneu_id = (int) ($_GET['pneu_id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['success' => true, 'data' => pneus_get_custo_km($conn, $pneu_id, $empresa_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'previsao_compras':
            $dias = (int) ($_GET['dias'] ?? 90);
            echo json_encode([
                'success' => true,
                'data' => pneus_previsao_compras($conn, $empresa_id, $dias),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'disponiveis':
            $idsDisp = pneus_status_ids_disponiveis($conn);
            $inPh = implode(',', array_fill(0, count($idsDisp), '?'));
            $naoAlocado = pneus_sql_nao_alocado('p.id');
            $sql = "SELECT p.id, p.numero_serie, p.marca, p.modelo, p.medida, p.sulco_inicial,
                    sp.nome AS status_nome
                FROM pneus p
                LEFT JOIN status_pneus sp ON sp.id = p.status_id
                WHERE p.empresa_id = ? AND p.status_id IN ({$inPh}) AND {$naoAlocado}
                ORDER BY p.numero_serie";
            $st = $conn->prepare($sql);
            $st->execute(array_merge([$empresa_id], $idsDisp));
            echo json_encode(['success' => true, 'pneus' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
            break;

        case 'ficha':
            $pneu_id = (int) ($_GET['pneu_id'] ?? $_GET['id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ficha = pneus_get_ficha($conn, $pneu_id, $empresa_id);
            if (!$ficha) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            require_once __DIR__ . '/../includes/sf_paths.php';
            if (!empty($ficha['qr_token'])) {
                $ficha['qr_url'] = sf_app_url('pages/pneu_ficha.php?t=' . urlencode($ficha['qr_token']));
            }
            echo json_encode(['success' => true, 'data' => $ficha], JSON_UNESCAPED_UNICODE);
            break;

        case 'ficha_qr':
            $token = trim((string) ($_GET['t'] ?? ''));
            if ($token === '' || !pneus_column_exists($conn, 'pneus', 'qr_token')) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'token inválido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $st = $conn->prepare('SELECT id FROM pneus WHERE qr_token = ? AND empresa_id = ? LIMIT 1');
            $st->execute([$token, $empresa_id]);
            $pneu_id = (int) ($st->fetchColumn() ?: 0);
            if ($pneu_id <= 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(['success' => true, 'data' => pneus_get_ficha($conn, $pneu_id, $empresa_id)], JSON_UNESCAPED_UNICODE);
            break;

        case 'medicoes_sulco':
            $pneu_id = (int) ($_GET['pneu_id'] ?? 0);
            if ($pneu_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode([
                'success' => true,
                'medicoes' => pneus_get_medicoes_sulco($conn, $pneu_id, $empresa_id),
                'previsao' => pneus_previsao_troca_sulco($conn, $pneu_id, $empresa_id),
                'sulco_atual_mm' => pneus_get_sulco_atual($conn, $pneu_id),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'ranking_marcas':
            echo json_encode([
                'success' => true,
                'data' => pneus_ranking_marcas($conn, $empresa_id, (int) ($_GET['limit'] ?? 15)),
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log('api/pneus.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação de pneus'], JSON_UNESCAPED_UNICODE);
}
