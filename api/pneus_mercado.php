<?php
/**
 * API — módulo de pneus nível mercado.
 * GET ?action=catalogos|carcaca|dot_alertas|stats_remocao|previsao_compra|abastecimento_insights|inventario_resumo|ficha_mercado
 * POST ?action=upload_foto|garantia|sucata|inspecao|transferir_filial|inventario_iniciar|inventario_scan|inventario_fechar|filial_criar
 */
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneus_repository.php';
require_once __DIR__ . '/../includes/pneus_mercado_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = pneus_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'catalogos':
                pneus_filial_garantir_matriz($conn, $empresa_id);
                echo json_encode([
                    'success' => true,
                    'motivos_remocao' => pneus_motivos_remocao_listar($conn, $empresa_id),
                    'filiais' => pneus_filiais_listar($conn, $empresa_id),
                    'tipos_rodado' => ['direcional', 'tracao', 'livre', 'implemento', 'qualquer'],
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'carcaca':
                $pneu_id = (int) ($_GET['pneu_id'] ?? 0);
                $res = pneus_carcaca_resumo($conn, $pneu_id, $empresa_id);
                if (!$res) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success' => true, 'carcaca' => $res], JSON_UNESCAPED_UNICODE);
                }
                break;

            case 'dot_alertas':
                echo json_encode(['success' => true, 'alertas' => pneus_dot_alertas_frota($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
                break;

            case 'stats_remocao':
                echo json_encode(['success' => true, 'estatisticas' => pneus_estatisticas_remocao($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
                break;

            case 'previsao_compra':
                $dias = max(7, min(365, (int) ($_GET['dias'] ?? 60)));
                echo json_encode(['success' => true, 'previsao' => pneus_previsao_compra_ia($conn, $empresa_id, $dias)], JSON_UNESCAPED_UNICODE);
                break;

            case 'abastecimento_insights':
                echo json_encode(['success' => true, 'insights' => pneus_abastecimento_insights($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
                break;

            case 'inventario_resumo':
                $sessao_id = (int) ($_GET['sessao_id'] ?? 0);
                echo json_encode(pneus_inventario_resumo($conn, $empresa_id, $sessao_id), JSON_UNESCAPED_UNICODE);
                break;

            case 'ficha_mercado':
                $pneu_id = (int) ($_GET['pneu_id'] ?? 0);
                $ref = trim((string) ($_GET['qr'] ?? ''));
                if ($pneu_id <= 0 && $ref !== '') {
                    $p = pneus_por_qr_ou_id($conn, $empresa_id, $ref);
                    $pneu_id = $p ? (int) $p['id'] : 0;
                }
                if ($pneu_id <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Informe pneu_id ou qr'], JSON_UNESCAPED_UNICODE);
                    break;
                }
                echo json_encode([
                    'success' => true,
                    'carcaca' => pneus_carcaca_resumo($conn, $pneu_id, $empresa_id),
                    'anexos' => pneus_anexos_listar($conn, $pneu_id, $empresa_id),
                    'garantias' => pneus_garantias_listar($conn, $pneu_id, $empresa_id),
                    'ficha' => pneus_get_ficha($conn, $pneu_id, $empresa_id),
                ], JSON_UNESCAPED_UNICODE);
                break;

            case 'buscar_qr':
                $ref = trim((string) ($_GET['qr'] ?? ''));
                $p = pneus_por_qr_ou_id($conn, $empresa_id, $ref);
                if (!$p) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode(['success' => true, 'pneu' => $p], JSON_UNESCAPED_UNICODE);
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ação GET inválida'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($method !== 'POST') {
        api_json_method_not_allowed('Use GET ou POST.');
    }

    api_require_csrf_json();

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    switch ($action) {
        case 'upload_foto':
            $pneu_id = (int) ($_POST['pneu_id'] ?? ($data['pneu_id'] ?? 0));
            $tipo = $_POST['tipo'] ?? ($data['tipo'] ?? 'outro');
            if ($pneu_id <= 0 || empty($_FILES['foto'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'pneu_id e arquivo foto obrigatórios'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $file = $_FILES['foto'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Falha no upload'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Formato não permitido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $dir = dirname(__DIR__) . '/uploads/pneus/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $nome = 'pneu_' . $pneu_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . $nome)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erro ao salvar arquivo'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $rel = 'uploads/pneus/' . $nome;
            echo json_encode(pneus_anexo_salvar_registro($conn, $empresa_id, [
                'pneu_id' => $pneu_id,
                'tipo' => $tipo,
                'arquivo' => $rel,
                'legenda' => $_POST['legenda'] ?? ($data['legenda'] ?? null),
            ]), JSON_UNESCAPED_UNICODE);
            break;

        case 'garantia':
            echo json_encode(pneus_garantia_salvar($conn, $empresa_id, $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'sucata':
            echo json_encode(pneus_registrar_sucata($conn, $empresa_id, $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'inspecao':
            if (!empty($_SESSION['motorista_id'])) {
                $data['motorista_id'] = (int) $_SESSION['motorista_id'];
                $data['origem'] = 'motorista';
            }
            echo json_encode(pneus_registrar_inspecao($conn, $empresa_id, $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'transferir_filial':
            $pneu_id = (int) ($data['pneu_id'] ?? 0);
            $filial_id = (int) ($data['filial_id'] ?? 0);
            echo json_encode(pneus_transferir_filial($conn, $empresa_id, $pneu_id, $filial_id, $data['observacoes'] ?? null), JSON_UNESCAPED_UNICODE);
            break;

        case 'inventario_iniciar':
            $filial_id = isset($data['filial_id']) ? (int) $data['filial_id'] : null;
            echo json_encode(pneus_inventario_iniciar($conn, $empresa_id, $filial_id, $data['titulo'] ?? null), JSON_UNESCAPED_UNICODE);
            break;

        case 'inventario_scan':
            $sessao_id = (int) ($data['sessao_id'] ?? 0);
            $qr = trim((string) ($data['qr'] ?? $data['token'] ?? ''));
            echo json_encode(pneus_inventario_scan($conn, $empresa_id, $sessao_id, $qr), JSON_UNESCAPED_UNICODE);
            break;

        case 'inventario_fechar':
            $sessao_id = (int) ($data['sessao_id'] ?? 0);
            $st = $conn->prepare('UPDATE pneu_inventario_sessoes SET status = \'fechada\', fechada_em = NOW() WHERE id = ? AND empresa_id = ?');
            $st->execute([$sessao_id, $empresa_id]);
            echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'filial_criar':
            if (!pneus_table_exists($conn, 'filiais')) {
                echo json_encode(['success' => false, 'error' => 'Migração não aplicada'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $nome = trim((string) ($data['nome'] ?? ''));
            if ($nome === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nome obrigatório'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $st = $conn->prepare('INSERT INTO filiais (empresa_id, nome, codigo) VALUES (?, ?, ?)');
            $st->execute([$empresa_id, $nome, $data['codigo'] ?? null]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()], JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação POST inválida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log('pneus_mercado.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação'], JSON_UNESCAPED_UNICODE);
}
