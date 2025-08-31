<?php
header('Content-Type: application/json');

// Prevenir qualquer saída antes do JSON
ob_start();

// Incluir configuração de sessão do sistema principal
require_once __DIR__ . '/../../includes/config.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    configure_session();
    session_start();
}

// Função para log personalizado
function debug_log($message) {
    $log_file = __DIR__ . '/../../logs/gestao_interativa.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Log inicial
debug_log("=== INICIANDO API TESTE ALOCAÇÃO ===");
debug_log("URL: " . $_SERVER['REQUEST_URI']);
debug_log("Método: " . $_SERVER['REQUEST_METHOD']);
debug_log("Headers: " . print_r(getallheaders(), true));

try {
    // Verificar se o usuário está logado e obter empresa_id da sessão
    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Usuário não autenticado ou empresa não identificada');
    }
    
    $empresa_id = intval($_SESSION['empresa_id']);
    
    // Carregar configurações do banco usando caminho absoluto
    $config = require __DIR__ . '/../config/database.php';
    
    // Criar conexão PDO
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    // Teste: Buscar veículos da empresa logada
    $stmt = $pdo->prepare("SELECT id, placa, modelo FROM veiculos WHERE empresa_id = ? LIMIT 5");
    $stmt->execute([$empresa_id]);
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Teste: Buscar pneus disponíveis da empresa logada
    $stmt = $pdo->prepare("SELECT id, numero_serie, marca, modelo FROM pneus WHERE empresa_id = ? AND status_id IN (2, 5) LIMIT 5");
    $stmt->execute([$empresa_id]);
    $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Teste: Buscar posições de pneus
    $stmt = $pdo->prepare("SELECT id, nome FROM posicoes_pneus ORDER BY nome LIMIT 10");
    $stmt->execute();
    $posicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'empresa_id' => $empresa_id,
        'veiculos' => $veiculos,
        'pneus' => $pneus,
        'posicoes' => $posicoes,
        'message' => 'Teste de alocação funcionando corretamente'
    ]);
    
} catch (Exception $e) {
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

debug_log("=== FIM API TESTE ALOCAÇÃO ===");
?> 