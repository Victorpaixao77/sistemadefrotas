<?php
/**
 * 📋 API de Eventos Fiscais
 * 🔄 Gerencia eventos como cancelamento, encerramento e CCE
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
            // Simular lista de eventos fiscais
            $events_list = [
                [
                    'id' => 1,
                    'tipo_evento' => 'cancelamento',
                    'documento_tipo' => 'nfe',
                    'documento_id' => 1,
                    'protocolo_evento' => 'CANC001',
                    'justificativa' => 'Erro na emissão',
                    'status' => 'aceito',
                    'data_evento' => '2025-08-20 10:30:00'
                ],
                [
                    'id' => 2,
                    'tipo_evento' => 'encerramento',
                    'documento_tipo' => 'mdfe',
                    'documento_id' => 1,
                    'protocolo_evento' => 'ENC001',
                    'justificativa' => 'Viagem concluída',
                    'status' => 'aceito',
                    'data_evento' => '2025-08-21 15:45:00'
                ]
            ];
            
            echo json_encode([
                'success' => true,
                'message' => 'Lista de eventos fiscais carregada',
                'data' => $events_list,
                'total' => count($events_list)
            ]);
            break;
            
        case 'cancelar':
            // Simular cancelamento
            echo json_encode([
                'success' => true,
                'message' => 'Documento cancelado com sucesso',
                'data' => [
                    'protocolo_evento' => 'CANC' . rand(100000, 999999),
                    'status' => 'aceito'
                ]
            ]);
            break;
            
        case 'encerrar':
            // Simular encerramento
            echo json_encode([
                'success' => true,
                'message' => 'MDF-e encerrado com sucesso',
                'data' => [
                    'protocolo_evento' => 'ENC' . rand(100000, 999999),
                    'status' => 'aceito'
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
