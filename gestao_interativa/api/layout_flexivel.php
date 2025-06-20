<?php
header('Content-Type: application/json');

$DATA_FILE = __DIR__ . '/layout_flexivel_data.json';

function load_data() {
    global $DATA_FILE;
    if (!file_exists($DATA_FILE)) return [];
    $json = file_get_contents($DATA_FILE);
    return json_decode($json, true) ?: [];
}

function save_data($data) {
    global $DATA_FILE;
    file_put_contents($DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $veiculo_id = isset($_GET['veiculo_id']) ? $_GET['veiculo_id'] : null;
    if (!$veiculo_id) {
        echo json_encode(['success'=>false,'error'=>'veiculo_id obrigatório']);
        exit;
    }
    $data = load_data();
    $layout = isset($data[$veiculo_id]) ? $data[$veiculo_id] : null;
    echo json_encode(['success'=>true,'layout'=>$layout]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $veiculo_id = isset($input['veiculo_id']) ? $input['veiculo_id'] : null;
    $layout = isset($input['layout']) ? $input['layout'] : null;
    if (!$veiculo_id || !$layout) {
        echo json_encode(['success'=>false,'error'=>'veiculo_id e layout obrigatórios']);
        exit;
    }
    $data = load_data();
    $data[$veiculo_id] = $layout;
    save_data($data);
    echo json_encode(['success'=>true]);
    exit;
}

echo json_encode(['success'=>false,'error'=>'Método não suportado']); 