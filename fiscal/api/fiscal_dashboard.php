<?php
/**
 * 🏢 API do Dashboard Fiscal
 * 📊 Retorna KPIs e estatísticas do sistema fiscal
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
    
    // Simular dados do dashboard (por enquanto)
    // Em produção, isso viria do banco de dados
    $dashboard_data = [
        'nfe' => [
            'total' => 15,
            'pendentes' => 3,
            'autorizadas' => 12,
            'valor_total' => 125000.00
        ],
        'cte' => [
            'total' => 28,
            'pendentes' => 5,
            'autorizados' => 23,
            'em_transito' => 8
        ],
        'mdfe' => [
            'total' => 12,
            'pendentes' => 2,
            'autorizados' => 10,
            'em_transito' => 4,
            'encerrados' => 6
        ],
        'eventos' => [
            'total' => 45,
            'pendentes' => 7,
            'aceitos' => 35,
            'rejeitados' => 3
        ],
        'sefaz_status' => [
            'status' => 'online',
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            'mensagem' => 'Sistema SEFAZ funcionando normalmente'
        ],
        'alertas' => [
            'total' => 2,
            'criticos' => 0,
            'importantes' => 1,
            'informativos' => 1
        ]
    ];
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Dashboard carregado com sucesso',
        'data' => $dashboard_data,
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
