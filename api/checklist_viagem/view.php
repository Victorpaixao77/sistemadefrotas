<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'id não informado']);
    exit;
}
$id = $_GET['id'];
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT c.*, 
           v.placa as veiculo_placa,
           m.nome as motorista_nome
    FROM checklist_viagem c
    LEFT JOIN veiculos v ON c.veiculo_id = v.id
    LEFT JOIN motoristas m ON c.motorista_id = m.id
    WHERE c.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$checklist = $stmt->fetch(PDO::FETCH_ASSOC);
if ($checklist) {
    echo json_encode(['success' => true, 'data' => $checklist]);
} else {
    echo json_encode(['success' => false, 'error' => 'Checklist não encontrado']);
} 