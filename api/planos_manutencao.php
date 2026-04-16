<?php
/**
 * API – Planos de Manutenção Preventiva
 * GET: lista planos da empresa
 * POST: cria plano (veiculo_id, componente_id, tipo_manutencao_id, intervalo_km, intervalo_dias)
 * PUT: atualiza plano (id + campos opcionais)
 * DELETE: desativa ou remove plano
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');
require_authentication();

$conn = getConnection();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Protege mutações (POST/PUT/PATCH/DELETE) com CSRF.
api_require_csrf_json();

try {
    $conn->query("SELECT 1 FROM planos_manutencao LIMIT 1");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Tabela planos_manutencao não existe. Execute sql/create_planos_manutencao.sql']);
    exit;
}

switch ($method) {
    case 'GET':
        $sql = "SELECT p.*, v.placa, cm.nome as componente_nome, tm.nome as tipo_nome
                FROM planos_manutencao p
                JOIN veiculos v ON v.id = p.veiculo_id AND v.empresa_id = p.empresa_id
                JOIN componentes_manutencao cm ON cm.id = p.componente_id
                JOIN tipos_manutencao tm ON tm.id = p.tipo_manutencao_id
                WHERE p.empresa_id = :eid
                ORDER BY v.placa, cm.nome";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':eid', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $list]);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['veiculo_id']) || empty($data['componente_id']) || empty($data['tipo_manutencao_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'veiculo_id, componente_id e tipo_manutencao_id são obrigatórios']);
            exit;
        }
        $veiculo_id = (int)$data['veiculo_id'];
        $componente_id = (int)$data['componente_id'];
        $tipo_manutencao_id = (int)$data['tipo_manutencao_id'];
        $intervalo_km = isset($data['intervalo_km']) ? (int)$data['intervalo_km'] : null;
        $intervalo_dias = isset($data['intervalo_dias']) ? (int)$data['intervalo_dias'] : null;
        $ultimo_km = isset($data['ultimo_km']) ? (int)$data['ultimo_km'] : null;
        $ultima_data = !empty($data['ultima_data']) ? $data['ultima_data'] : null;
        if ($intervalo_km <= 0) $intervalo_km = null;
        if ($intervalo_dias <= 0) $intervalo_dias = null;
        if ($intervalo_km === null && $intervalo_dias === null) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Informe intervalo_km e/ou intervalo_dias']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO planos_manutencao (empresa_id, veiculo_id, componente_id, tipo_manutencao_id, intervalo_km, intervalo_dias, ultimo_km, ultima_data, ativo) VALUES (:eid, :vid, :cid, :tid, :ikm, :idias, :ukm, :udata, 1) ON DUPLICATE KEY UPDATE intervalo_km = COALESCE(VALUES(intervalo_km), intervalo_km), intervalo_dias = COALESCE(VALUES(intervalo_dias), intervalo_dias), ultimo_km = COALESCE(VALUES(ultimo_km), ultimo_km), ultima_data = COALESCE(VALUES(ultima_data), ultima_data), ativo = 1, updated_at = NOW()");
        $stmt->execute([
            'eid' => $empresa_id, 'vid' => $veiculo_id, 'cid' => $componente_id, 'tid' => $tipo_manutencao_id,
            'ikm' => $intervalo_km, 'idias' => $intervalo_dias, 'ukm' => $ultimo_km, 'udata' => $ultima_data
        ]);
        $id = $conn->lastInsertId() ?: null;
        echo json_encode(['success' => true, 'message' => 'Plano salvo', 'id' => $id]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || empty($data['id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id obrigatório']);
            exit;
        }
        $id = (int)$data['id'];
        $updates = [];
        $params = [':id' => $id, ':eid' => $empresa_id];
        if (array_key_exists('intervalo_km', $data)) { $updates[] = 'intervalo_km = :ikm'; $params[':ikm'] = $data['intervalo_km'] === null ? null : (int)$data['intervalo_km']; }
        if (array_key_exists('intervalo_dias', $data)) { $updates[] = 'intervalo_dias = :idias'; $params[':idias'] = $data['intervalo_dias'] === null ? null : (int)$data['intervalo_dias']; }
        if (array_key_exists('ultimo_km', $data)) { $updates[] = 'ultimo_km = :ukm'; $params[':ukm'] = $data['ultimo_km'] === null ? null : (int)$data['ultimo_km']; }
        if (array_key_exists('ultima_data', $data)) { $updates[] = 'ultima_data = :udata'; $params[':udata'] = $data['ultima_data'] ?: null; }
        if (array_key_exists('ativo', $data)) { $updates[] = 'ativo = :ativo'; $params[':ativo'] = (int)$data['ativo']; }
        if (empty($updates)) {
            echo json_encode(['success' => true, 'message' => 'Nada a atualizar']);
            exit;
        }
        $sql = "UPDATE planos_manutencao SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = :id AND empresa_id = :eid";
        $stmt = $conn->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Plano atualizado']);
        break;

    case 'DELETE':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'id obrigatório']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE planos_manutencao SET ativo = 0, updated_at = NOW() WHERE id = :id AND empresa_id = :eid");
        $stmt->execute(['id' => $id, 'eid' => $empresa_id]);
        echo json_encode(['success' => true, 'message' => 'Plano desativado']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido']);
}
