<?php
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Sessão expirada']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID não informado']);
    exit;
}

$id = intval($_GET['id']);
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    $sql = "SELECT a.*, 
                   v.placa as veiculo_placa,
                   m.nome as motorista_nome,
                   r.data_rota,
                   co.nome as cidade_origem_nome,
                   cd.nome as cidade_destino_nome
            FROM abastecimentos a
            LEFT JOIN veiculos v ON a.veiculo_id = v.id
            LEFT JOIN motoristas m ON a.motorista_id = m.id
            LEFT JOIN rotas r ON a.rota_id = r.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE a.id = :id AND a.empresa_id = :empresa_id
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($abastecimento) {
        echo json_encode(['success' => true, 'data' => $abastecimento]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Abastecimento não encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 