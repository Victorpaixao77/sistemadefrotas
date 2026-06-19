<?php
require_once '../config.php';
require_once '../functions.php';
require_once '../db.php';
require_once __DIR__ . '/../../includes/pneus_mercado_helpers.php';

validar_sessao_motorista();

header('Content-Type: application/json; charset=utf-8');

$motorista_id = (int) $_SESSION['motorista_id'];
$empresa_id = (int) $_SESSION['empresa_id'];
$conn = getConnection();
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    if ($method === 'GET') {
        switch ($action) {
            case 'veiculos':
                $st = $conn->prepare(
                    "SELECT DISTINCT v.id, v.placa, v.modelo FROM veiculos v
                     INNER JOIN rotas r ON r.veiculo_id = v.id
                     WHERE r.motorista_id = ? AND v.empresa_id = ?
                     ORDER BY v.placa LIMIT 20"
                );
                $st->execute([$motorista_id, $empresa_id]);
                echo json_encode(['success' => true, 'veiculos' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
                break;

            case 'buscar_qr':
                $ref = trim((string) ($_GET['qr'] ?? ''));
                $p = pneus_por_qr_ou_id($conn, $empresa_id, $ref);
                if (!$p) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Pneu não encontrado'], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => true,
                        'pneu' => $p,
                        'carcaca' => pneus_carcaca_resumo($conn, (int) $p['id'], $empresa_id),
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;

            case 'pneus_veiculo':
                $vid = (int) ($_GET['veiculo_id'] ?? 0);
                echo json_encode([
                    'success' => true,
                    'pneus' => pneus_pneus_do_veiculo($conn, $vid, $empresa_id),
                ], JSON_UNESCAPED_UNICODE);
                break;

            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    switch ($action) {
        case 'inspecao':
            $data['motorista_id'] = $motorista_id;
            $data['origem'] = 'motorista';
            echo json_encode(pneus_registrar_inspecao($conn, $empresa_id, $data), JSON_UNESCAPED_UNICODE);
            break;

        case 'upload_foto':
            $pneu_id = (int) ($_POST['pneu_id'] ?? 0);
            if ($pneu_id <= 0 || empty($_FILES['foto'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Dados inválidos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Formato inválido'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $dir = dirname(__DIR__, 2) . '/uploads/pneus/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $nome = 'pneu_m_' . $pneu_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nome)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Falha no upload'], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo json_encode(pneus_anexo_salvar_registro($conn, $empresa_id, [
                'pneu_id' => $pneu_id,
                'tipo' => $_POST['tipo'] ?? 'inspecao',
                'arquivo' => 'uploads/pneus/' . $nome,
            ]), JSON_UNESCAPED_UNICODE);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    error_log('pages_motorista/api/pneus.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno'], JSON_UNESCAPED_UNICODE);
}
