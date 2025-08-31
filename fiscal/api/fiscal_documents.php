<?php
/**
 * 📋 API de Documentos Fiscais
 * 📄 Retorna documentos recentes e por tipo
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
    $action = $input['action'] ?? 'get_recent';
    
    // Simular dados de documentos (por enquanto)
    // Em produção, isso viria do banco de dados
    $documents_data = [
        'nfe' => [
            [
                'id' => 1,
                'numero_nfe' => '001',
                'cliente_razao_social' => 'Empresa ABC Ltda',
                'data_emissao' => '2025-08-20',
                'valor_total' => 1500.00,
                'status' => 'autorizado'
            ],
            [
                'id' => 2,
                'numero_nfe' => '002',
                'cliente_razao_social' => 'Empresa XYZ Ltda',
                'data_emissao' => '2025-08-21',
                'valor_total' => 2300.00,
                'status' => 'pendente'
            ]
        ],
        'cte' => [
            [
                'id' => 1,
                'numero_cte' => '001',
                'origem_cidade' => 'São Paulo',
                'destino_cidade' => 'Rio de Janeiro',
                'data_emissao' => '2025-08-20',
                'valor_total' => 800.00,
                'status' => 'autorizado'
            ]
        ],
        'mdfe' => [
            [
                'id' => 1,
                'numero_mdfe' => '001',
                'tipo_transporte' => 'Rodoviário',
                'peso_total_carga' => 5000,
                'data_emissao' => '2025-08-20',
                'valor_total' => 1200.00,
                'status' => 'autorizado'
            ]
        ]
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Documentos carregados com sucesso',
        'data' => $documents_data,
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
