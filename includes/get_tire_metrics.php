<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start output buffering
ob_start();

try {
    session_start();
    
    // Log session state
    error_log("Session state - loggedin: " . (isset($_SESSION['loggedin']) ? 'true' : 'false'));
    error_log("Session state - empresa_id: " . (isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : 'not set'));
    
    // Check if user is logged in
    if (!isset($_SESSION['loggedin']) || !isset($_SESSION['empresa_id'])) {
        throw new Exception('Usuário não está logado ou empresa_id não definido');
    }
    
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    $metrics = [];
    
    try {
        // Total de pneus em uso
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM pneus 
            WHERE empresa_id = ? AND status_id = 1");
        $stmt->execute([$empresa_id]);
        $metrics['total_em_uso'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Query 1 (total_em_uso) executada com sucesso: " . $metrics['total_em_uso']);
    } catch (Exception $e) {
        error_log("Erro na query 1 (total_em_uso): " . $e->getMessage());
        throw $e;
    }

    try {
        // Total de pneus
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pneus WHERE empresa_id = ?");
        $stmt->execute([$empresa_id]);
        $metrics['total_pneus'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
        error_log("Query 2 (total_pneus) executada com sucesso: " . $metrics['total_pneus']);
    } catch (Exception $e) {
        error_log("Erro na query 2 (total_pneus): " . $e->getMessage());
        throw $e;
    }

    // Remover métricas que dependem de veiculo_id
    $metrics['pneus_alerta'] = 0;
    $metrics['vida_media'] = 100;

    // Custo total
    $valor_medio_pneu = 2500;
    $metrics['custo_total'] = $metrics['total_pneus'] * $valor_medio_pneu;

    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }

    // Set proper JSON header
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'data' => $metrics
    ]);

} catch (Exception $e) {
    error_log("Erro em get_tire_metrics.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clear any output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper JSON header
    header('Content-Type: application/json');
    http_response_code(500);
    
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar métricas dos pneus',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
} 