<?php
/**
 * 📄 API de NF-e
 * 🏢 Gerencia operações de Nota Fiscal Eletrônica
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
    $empresa_id = $_SESSION['empresa_id'];
    $action = $input['action'] ?? 'list';
    
    switch ($action) {
        case 'list':
            // Simular lista de NF-e
            $nfe_list = [
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
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de NF-e carregada',
                'data' => $nfe_list,
                'total' => count($nfe_list)
            ]);
            break;
            
        case 'importar_xml':
            // Simular importação de XML
            echo json_encode([
                'success' => true,
                'message' => 'NF-e importada com sucesso',
                'data' => [
                    'id' => rand(100, 999),
                    'numero_nfe' => '003',
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
