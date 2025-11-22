<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
session_start();

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}
if (!isset($_POST['rota_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'rota_id não informado']);
    exit;
}
$rota_id = $_POST['rota_id'];
$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();
// Verifica se já existe despesa para a rota
$stmt = $conn->prepare("SELECT id FROM despesas_viagem WHERE rota_id = ? LIMIT 1");
$stmt->execute([$rota_id]);
$exists = $stmt->fetch(PDO::FETCH_ASSOC);
$fields = ['descarga','pedagios','caixinha','estacionamento','lavagem','borracharia','eletrica_mecanica','adiantamento','total_despviagem'];
$data = [];
foreach ($fields as $f) {
    $data[$f] = isset($_POST[$f]) ? $_POST[$f] : null;
}
if ($exists) {
    // Update
    $sql = "UPDATE despesas_viagem SET descarga=?, pedagios=?, caixinha=?, estacionamento=?, lavagem=?, borracharia=?, eletrica_mecanica=?, adiantamento=?, total_despviagem=?, updated_at=NOW() WHERE rota_id=?";
    $stmt = $conn->prepare($sql);
    $ok = $stmt->execute([
        $data['descarga'], $data['pedagios'], $data['caixinha'], $data['estacionamento'], $data['lavagem'],
        $data['borracharia'], $data['eletrica_mecanica'], $data['adiantamento'], $data['total_despviagem'], $rota_id
    ]);
} else {
    // Insert
    $sql = "INSERT INTO despesas_viagem (empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento, lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($sql);
    $ok = $stmt->execute([
        $empresa_id, $rota_id,
        $data['descarga'], $data['pedagios'], $data['caixinha'], $data['estacionamento'], $data['lavagem'],
        $data['borracharia'], $data['eletrica_mecanica'], $data['adiantamento'], $data['total_despviagem']
    ]);
}
if ($ok) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao salvar despesas']);
} 