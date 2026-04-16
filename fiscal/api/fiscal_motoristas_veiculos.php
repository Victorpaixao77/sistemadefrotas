<?php
/**
 * 👥 API de Motoristas e Veículos
 * 🚛 Retorna lista de motoristas e veículos para o sistema fiscal
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Permitir requisições OPTIONS (CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar se é POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido. Use POST.'
    ]);
    exit();
}

// Incluir configurações
require_once '../../includes/config.php';
require_once '../../includes/functions.php';

try {
    // Configurar sessão
    configure_session();
    session_start();
    
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = $input['empresa_id'] ?? 1; // Usar empresa_id padrão se não fornecido
    
    $conn = getConnection();

    // Motoristas
    $stmtM = $conn->prepare("
        SELECT id, nome, cpf, cnh, data_validade_cnh
        FROM motoristas
        WHERE empresa_id = ?
        ORDER BY nome
    ");
    $stmtM->execute([$empresa_id]);
    $motoristas = $stmtM->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Veículos
    $stmtV = $conn->prepare("
        SELECT id, placa, modelo
        FROM veiculos
        WHERE empresa_id = ?
        ORDER BY placa
    ");
    $stmtV->execute([$empresa_id]);
    $veiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $data = [
        'motoristas' => $motoristas,
        'veiculos' => $veiculos,
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Motoristas e veículos carregados com sucesso',
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
