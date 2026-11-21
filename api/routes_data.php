<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/routes_dashboard.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Get database connection
try {
    $conn = getConnection();
} catch (PDOException $e) {
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Processa a ação solicitada
if ($action === 'dashboard') {
    try {
        $month = (isset($_GET['month']) && $_GET['month'] !== '' && intval($_GET['month']) > 0) ? intval($_GET['month']) : intval(date('n'));
        $year = (isset($_GET['year']) && $_GET['year'] !== '' && intval($_GET['year']) > 0) ? intval($_GET['year']) : intval(date('Y'));
        
        // Validação básica dos parâmetros
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new Exception('Mês inválido');
        }
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            throw new Exception('Ano inválido');
        }
        
        // Dashboard cacheado (APCu/arquivo) por 120s, por empresa+mês+ano. Só sucesso é cacheado.
        require_once __DIR__ . '/../includes/sf_cache.php';
        $ckey = 'dash:routes:' . (int) $empresa_id . ':' . $year . ':' . $month;
        $data = sf_cache_get($ckey);
        if ($data === null) {
            $data = getDashboardData($conn, $empresa_id, $month, $year);
            if (!(isset($data['error']) && $data['error'])) {
                sf_cache_set($ckey, $data, 120);
            }
        }

        // Verifica se houve erro na função getDashboardData
        if (isset($data['error']) && $data['error']) {
            throw new Exception($data['message'] ?? 'Erro ao buscar dados do dashboard');
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    } catch (Exception $e) {
        error_log("Erro no dashboard: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'Erro ao processar dados do dashboard: '
        ]);
        exit;
    }
}

