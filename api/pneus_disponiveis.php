<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
require_authentication();

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Set content type to JSON
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php-error.log');

try {
    error_log("Iniciando busca de pneus disponíveis para empresa_id: " . $empresa_id);
    
    $conn = getConnection();
    error_log("Conexão com o banco de dados estabelecida");
    
    // Buscar pneus disponíveis (não alocados)
    $sql = "SELECT 
            p.id,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            p.sulco_inicial,
            p.dot,
            p.km_instalacao,
            p.data_instalacao,
            p.vida_util_km,
            p.numero_recapagens,
            p.data_ultima_recapagem,
            p.lote,
            p.data_entrada,
            p.observacoes,
            sp.nome as status_nome,
            CASE 
                WHEN p.status_id = 5 THEN 'bom'
                WHEN p.status_id = 4 THEN 'gasto'
                WHEN p.status_id = 1 THEN 'furado'
                WHEN p.status_id = 2 THEN 'reserva'
                WHEN p.status_id = 3 THEN 'descartado'
                ELSE 'gasto'
            END as status
            FROM pneus p
            LEFT JOIN status_pneus sp ON sp.id = p.status_id
            WHERE p.empresa_id = :empresa_id
            AND p.id NOT IN (
                SELECT pneu_id 
                FROM eixo_pneus 
                WHERE status = 'alocado'
            )
            AND p.status_id IN (2, 5) -- Apenas pneus em bom estado ou reserva
            ORDER BY p.numero_serie";
    
    error_log("SQL: " . $sql);
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Pneus encontrados: " . count($pneus));
    
    // Formatar dados para exibição
    foreach ($pneus as &$pneu) {
        $pneu['sulco_inicial'] = number_format($pneu['sulco_inicial'], 1);
        if ($pneu['data_ultima_recapagem']) {
            $pneu['data_ultima_recapagem'] = date('d/m/Y', strtotime($pneu['data_ultima_recapagem']));
        }
        if ($pneu['data_instalacao']) {
            $pneu['data_instalacao'] = date('d/m/Y', strtotime($pneu['data_instalacao']));
        }
        if ($pneu['data_entrada']) {
            $pneu['data_entrada'] = date('d/m/Y', strtotime($pneu['data_entrada']));
        }
    }
    
    error_log("Dados formatados e prontos para envio");
    
    echo json_encode([
        'success' => true,
        'pneus' => $pneus
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de pneus disponíveis: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 