<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

ob_start();

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['loggedin']) || !isset($_SESSION['empresa_id'])) {
        throw new Exception('Usuário não está logado ou empresa_id não definido');
    }
    
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    $metrics = [];
    
    // Total de pneus em uso (status_id = 1, ajuste conforme status_pneus da base)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM pneus 
        WHERE empresa_id = ? AND status_id = 1");
    $stmt->execute([$empresa_id]);
    $metrics['total_em_uso'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Total de pneus
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM pneus WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $metrics['total_pneus'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Vida útil média: baseada em sulco (0–10 mm → 0–100%). Sem sulco usa 100.
    $stmt = $pdo->prepare("
        SELECT COALESCE(AVG(LEAST(100, GREATEST(0, (sulco_inicial / 10.0) * 100))), 100) as vida_media
        FROM pneus
        WHERE empresa_id = ? AND sulco_inicial IS NOT NULL
    ");
    $stmt->execute([$empresa_id]);
    $metrics['vida_media'] = (int) round((float) $stmt->fetch(PDO::FETCH_ASSOC)['vida_media']);

    // Pneus em alerta: sulco <= 2 mm
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM pneus
        WHERE empresa_id = ? AND sulco_inicial IS NOT NULL AND sulco_inicial <= 2
    ");
    $stmt->execute([$empresa_id]);
    $metrics['pneus_alerta'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Custo total (valor médio estimado)
    $valor_medio_pneu = 2500;
    $metrics['custo_total'] = $metrics['total_pneus'] * $valor_medio_pneu;

    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $metrics
    ]);

} catch (Exception $e) {
    if (function_exists('error_log')) {
        error_log('get_tire_metrics.php: ' . $e->getMessage());
    }
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar métricas dos pneus',
        'message' => $e->getMessage()
    ]);
} 