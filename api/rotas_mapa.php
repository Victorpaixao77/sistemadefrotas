<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Iniciar sessão para acessar empresa_id
session_start();

// Permitir acesso se houver empresa_id na sessão
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Empresa não identificada na sessão']);
    exit;
}

header('Content-Type: application/json');

$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    $sql = "
        SELECT 
            eo.map_x AS origem_x, eo.map_y AS origem_y,
            ed.map_x AS destino_x, ed.map_y AS destino_y,
            r.estado_origem, co.nome AS cidade_origem_nome, r.estado_destino, cd.nome AS cidade_destino_nome
        FROM rotas r
        JOIN estados eo ON r.estado_origem = eo.uf
        JOIN estados ed ON r.estado_destino = ed.uf
        JOIN cidades co ON r.cidade_origem_id = co.id
        JOIN cidades cd ON r.cidade_destino_id = cd.id
        WHERE MONTH(r.data_rota) = :mes 
        AND YEAR(r.data_rota) = :ano
        AND r.empresa_id = :empresa_id
        AND r.status = 'aprovado'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mes', $mes, PDO::PARAM_INT);
    $stmt->bindValue(':ano', $ano, PDO::PARAM_INT);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result ?: []);
} catch (Exception $e) {
    echo json_encode([]);
}
