<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/db_connect.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}
if (!isset($_POST['rota_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'rota_id não informado']);
    exit;
}

$rota_id = (int)$_POST['rota_id'];
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();

    // Isolamento: rota deve pertencer à empresa do usuário
    $stmtRota = $conn->prepare("SELECT id FROM rotas WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmtRota->execute([$rota_id, $empresa_id]);
    if (!$stmtRota->fetch(PDO::FETCH_ASSOC)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Rota não encontrada']);
        exit;
    }

    // Verifica se já existe despesa para a rota (da mesma empresa)
    $stmt = $conn->prepare("SELECT id FROM despesas_viagem WHERE rota_id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$rota_id, $empresa_id]);
    $exists = $stmt->fetch(PDO::FETCH_ASSOC);

    $fields = ['descarga','pedagios','caixinha','estacionamento','lavagem','borracharia','eletrica_mecanica','adiantamento','total_despviagem'];
    $data = [];
    foreach ($fields as $f) {
        $data[$f] = isset($_POST[$f]) ? $_POST[$f] : null;
    }

    if ($exists) {
        $sql = "UPDATE despesas_viagem SET descarga=?, pedagios=?, caixinha=?, estacionamento=?, lavagem=?, borracharia=?, eletrica_mecanica=?, adiantamento=?, total_despviagem=?, updated_at=NOW() WHERE rota_id=? AND empresa_id=?";
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([
            $data['descarga'], $data['pedagios'], $data['caixinha'], $data['estacionamento'], $data['lavagem'],
            $data['borracharia'], $data['eletrica_mecanica'], $data['adiantamento'], $data['total_despviagem'], $rota_id, $empresa_id
        ]);
    } else {
        $sql = "INSERT INTO despesas_viagem (empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento, lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $stmt = $conn->prepare($sql);
        $ok = $stmt->execute([
            $empresa_id, $rota_id,
            $data['descarga'], $data['pedagios'], $data['caixinha'], $data['estacionamento'], $data['lavagem'],
            $data['borracharia'], $data['eletrica_mecanica'], $data['adiantamento'], $data['total_despviagem']
        ]);
    }

    if ($ok) {
        echo json_encode(['success' => true, 'message' => 'Despesas salvas com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao salvar despesas']);
    }
} catch (Exception $e) {
    error_log("despesas_viagem/update.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar despesas']);
}
