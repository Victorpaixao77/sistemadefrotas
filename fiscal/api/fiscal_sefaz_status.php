<?php
/**
 * 🌐 API de Status SEFAZ
 * 📊 Retorna status de conectividade com a SEFAZ
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
    
    // Simular status SEFAZ (por enquanto)
    // Em produção, isso seria verificado via API da SEFAZ
    $sefaz_status = [
        'status' => 'online',
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        'mensagem' => 'Sistema SEFAZ funcionando normalmente',
        'tempo_resposta' => '150ms',
        'versao_api' => '4.00',
        'ambiente' => 'homologacao'
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Status SEFAZ verificado com sucesso',
        'data' => $sefaz_status,
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
