<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
if (!isset($_GET['rota_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'rota_id não informado']);
    exit;
}
$rota_id = $_GET['rota_id'];
$conn = getConnection();
$stmt = $conn->prepare("SELECT * FROM despesas_viagem WHERE rota_id = ? LIMIT 1");
$stmt->execute([$rota_id]);
$despesa = $stmt->fetch(PDO::FETCH_ASSOC);
if ($despesa) {
    echo json_encode(['success' => true, 'data' => $despesa]);
} else {
    echo json_encode(['success' => true, 'data' => null]);
} 