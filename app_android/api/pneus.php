<?php
/**
 * API Pneus — App Android Motoristas
 * GET ?action=buscar_qr&qr=TOKEN|ID
 * GET ?action=veiculos
 * GET ?action=pneus_veiculo&veiculo_id=
 * POST ?action=inspecao (JSON)
 * POST ?action=upload_foto (multipart: pneu_id, tipo, foto)
 */

require_once __DIR__ . '/../config.php';

$root = dirname(__DIR__, 2);
require_once $root . '/includes/pneus_helpers.php';
require_once $root . '/includes/pneus_mercado_helpers.php';

require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? '';

try {
    $conn = getConnection();

    if ($method === 'GET') {
        switch ($action) {
            case 'buscar_qr':
                $ref = trim((string) ($_GET['qr'] ?? ''));
                if ($ref === '') {
                    api_error('Informe o código QR ou ID do pneu.', 400);
                }
                $p = pneus_por_qr_ou_id($conn, $empresa_id, $ref);
                if (!$p) {
                    api_error('Pneu não encontrado.', 404);
                }
                api_success([
                    'pneu' => $p,
                    'carcaca' => pneus_carcaca_resumo($conn, (int) $p['id'], $empresa_id),
                ]);
                break;

            case 'veiculos':
                $st = $conn->prepare(
                    "SELECT DISTINCT v.id, v.placa, v.modelo FROM veiculos v
                     INNER JOIN rotas r ON r.veiculo_id = v.id
                     WHERE r.motorista_id = :m AND v.empresa_id = :e
                     ORDER BY v.placa LIMIT 30"
                );
                $st->execute([':m' => $motorista_id, ':e' => $empresa_id]);
                api_success(['veiculos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            case 'pneus_veiculo':
                $vid = (int) ($_GET['veiculo_id'] ?? 0);
                if ($vid <= 0) {
                    api_error('veiculo_id obrigatório.', 400);
                }
                api_success(['pneus' => pneus_pneus_do_veiculo($conn, $vid, $empresa_id)]);
                break;

            default:
                api_error('Ação inválida.', 400);
        }
    }

    if ($method !== 'POST') {
        api_error('Método não permitido.', 405);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    switch ($action) {
        case 'inspecao':
            $data['motorista_id'] = $motorista_id;
            $data['origem'] = 'motorista';
            $result = pneus_registrar_inspecao($conn, $empresa_id, $data);
            if (empty($result['success'])) {
                api_error($result['error'] ?? 'Erro ao registrar inspeção.', 400);
            }
            api_success(['id' => $result['id'] ?? null], 'Inspeção registrada');
            break;

        case 'upload_foto':
            $pneu_id = (int) ($_POST['pneu_id'] ?? ($data['pneu_id'] ?? 0));
            if ($pneu_id <= 0 || empty($_FILES['foto'])) {
                api_error('pneu_id e arquivo foto são obrigatórios.', 400);
            }
            if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
                api_error('Pneu não encontrado.', 404);
            }
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                api_error('Formato de imagem não permitido.', 400);
            }
            $dir = $root . '/uploads/pneus/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $nome = 'pneu_app_' . $pneu_id . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $nome)) {
                api_error('Falha ao salvar foto.', 500);
            }
            $result = pneus_anexo_salvar_registro($conn, $empresa_id, [
                'pneu_id' => $pneu_id,
                'tipo' => $_POST['tipo'] ?? ($data['tipo'] ?? 'inspecao'),
                'arquivo' => 'uploads/pneus/' . $nome,
            ]);
            if (empty($result['success'])) {
                api_error($result['error'] ?? 'Erro ao registrar anexo.', 500);
            }
            api_success(['id' => $result['id'] ?? null], 'Foto enviada');
            break;

        default:
            api_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('app_android/api/pneus.php: ' . $e->getMessage());
    api_error('Erro interno.', 500);
}
