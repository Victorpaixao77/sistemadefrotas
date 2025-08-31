<?php
/**
 * 📋 API de MDF-e
 * 🚛 Gerencia operações de Manifesto de Documentos Fiscais Eletrônicos
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
    $action = $input['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Simular lista de MDF-e
            $mdfe_list = [
                [
                    'id' => 1,
                    'numero_mdfe' => '001',
                    'tipo_transporte' => 'Rodoviário',
                    'peso_total_carga' => 5000,
                    'data_emissao' => '2025-08-20',
                    'valor_total' => 1200.00,
                    'status' => 'autorizado'
                ],
                [
                    'id' => 2,
                    'numero_mdfe' => '002',
                    'tipo_transporte' => 'Rodoviário',
                    'peso_total_carga' => 8000,
                    'data_emissao' => '2025-08-21',
                    'valor_total' => 1800.00,
                    'status' => 'pendente'
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de MDF-e carregada',
                'data' => $mdfe_list,
                'total' => count($mdfe_list)
            ]);
            break;
            
        case 'emitir':
            // Simular emissão de MDF-e
            echo json_encode([
                'success' => true,
                'message' => 'MDF-e emitido com sucesso',
                'data' => [
                    'id' => rand(100, 999),
                    'numero_mdfe' => '003',
                    'status' => 'pendente'
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Ação não reconhecida'
            ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}
?>
