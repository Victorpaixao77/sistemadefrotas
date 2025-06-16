<?php
// Incluir arquivos necessários
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Desabilitar exibição de erros para garantir resposta JSON limpa
error_reporting(0);
ini_set('display_errors', 0);

// Definir cabeçalho JSON
header('Content-Type: application/json');

// Log para debug
error_log("Session data in atualizar_dados.php: " . print_r($_SESSION, true));
error_log("Session ID: " . session_id());
error_log("Session name: " . session_name());
error_log("Session status: " . session_status());

require_once '../includes/db_connect.php';
require_once 'analise.php';
require_once 'alertas.php';
require_once 'recomendacoes.php';
require_once 'insights.php';

try {
    $pdo = getConnection();
    
    // Verificar se o usuário está logado usando a função isLoggedIn
    if (!isLoggedIn()) {
        error_log("Usuário não está logado. Session data: " . print_r($_SESSION, true));
        throw new Exception('Usuário não está logado');
    }
    
    $empresa_id = $_SESSION['empresa_id'] ?? null;
    error_log("Empresa ID from session: " . ($empresa_id ?? 'null'));

    if (!$empresa_id) {
        throw new Exception('Empresa não identificada');
    }

    $analise = new Analise($pdo, $empresa_id);
    $alertas = new Alertas($pdo, $empresa_id);
    $recomendacoes = new Recomendacoes($pdo, $empresa_id);
    $insights = new Insights($pdo, $empresa_id);

    $dados = [
        'analise' => $analise->obterTodasAnalises(),
        'alertas' => $alertas->obterTodosAlertas(),
        'recomendacoes' => $recomendacoes->obterTodasRecomendacoes(),
        'insights' => $insights->obterTodosInsights()
    ];

    echo json_encode(['success' => true, 'data' => $dados]);
} catch (Exception $e) {
    // Log do erro para debug
    error_log("Erro em atualizar_dados.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Retorna erro em formato JSON
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
} 