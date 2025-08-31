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
    
    // TEMPORÁRIO: Bypass da autenticação para desenvolvimento
    // if (!isset($_SESSION['user_id'])) {
    //     http_response_code(401);
    //     echo json_encode([
    //         'success' => false,
    //         'message' => 'Usuário não autenticado'
    //     ]);
    //     exit();
    // }
    
    // Obter dados do POST
    $input = json_decode(file_get_contents('php://input'), true);
    $empresa_id = $input['empresa_id'] ?? 1; // Usar empresa_id padrão se não fornecido
    
    // Simular dados de motoristas e veículos (por enquanto)
    // Em produção, isso viria do banco de dados
    $data = [
        'motoristas' => [
            [
                'id' => 1,
                'nome' => 'João Silva',
                'cpf' => '123.456.789-00',
                'cnh' => '12345678901',
                'categoria_cnh' => 'E',
                'validade_cnh' => '2026-12-31',
                'status' => 'ativo'
            ],
            [
                'id' => 2,
                'nome' => 'Maria Santos',
                'cpf' => '987.654.321-00',
                'cnh' => '98765432109',
                'categoria_cnh' => 'E',
                'validade_cnh' => '2027-06-30',
                'status' => 'ativo'
            ]
        ],
        'veiculos' => [
            [
                'id' => 1,
                'placa' => 'ABC-1234',
                'modelo' => 'Mercedes-Benz Actros',
                'ano' => 2020,
                'capacidade' => 30000,
                'tipo' => 'Truck',
                'status' => 'ativo'
            ],
            [
                'id' => 2,
                'placa' => 'XYZ-5678',
                'modelo' => 'Volkswagen Delivery',
                'ano' => 2021,
                'capacidade' => 15000,
                'tipo' => 'Truck',
                'status' => 'ativo'
            ]
        ]
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
