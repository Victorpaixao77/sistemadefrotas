<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

header('Content-Type: application/json');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$action = $_GET['action'] ?? '';

require_once __DIR__ . '/../includes/sf_cache.php';

try {
    if ($action === 'all') {
        $ckey = 'multas:analytics:all:' . $empresa_id;
        if (($cached = sf_cache_get($ckey)) !== null) {
            echo json_encode($cached, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $payload = [
            'success' => true,
            'data' => [
                'multas_por_mes' => getMultasPorMes($conn, $empresa_id),
                'valor_por_mes' => getValorPorMes($conn, $empresa_id),
                'multas_por_motorista' => getMultasPorMotorista($conn, $empresa_id),
                'pontos_por_motorista' => getPontosPorMotorista($conn, $empresa_id),
            ],
        ];
        sf_cache_set($ckey, $payload, 180);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ckey = 'multas:analytics:' . $empresa_id . ':' . $action;
    if (($cached = sf_cache_get($ckey)) !== null) {
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    switch ($action) {
        case 'multas_por_mes':
            $data = getMultasPorMes($conn, $empresa_id);
            break;
            
        case 'valor_por_mes':
            $data = getValorPorMes($conn, $empresa_id);
            break;
            
        case 'multas_por_motorista':
            $data = getMultasPorMotorista($conn, $empresa_id);
            break;
            
        case 'pontos_por_motorista':
            $data = getPontosPorMotorista($conn, $empresa_id);
            break;
            
        default:
            throw new Exception('Ação não especificada', 400);
    }
    
    $payload = [
        'success' => true,
        'data' => $data,
    ];
    sf_cache_set($ckey, $payload, 180);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    // Ação inválida (400) → mensagem clara; erro real → log + genérica (500).
    if ($e->getCode() === 400) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        error_log("Erro na API de analytics de multas: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao carregar dados de multas.'], JSON_UNESCAPED_UNICODE);
    }
}

function getMultasPorMes($conn, $empresa_id) {
    $sql = "SELECT 
                DATE_FORMAT(data_infracao, '%Y-%m') as mes,
                COUNT(*) as quantidade
            FROM multas 
            WHERE empresa_id = :empresa_id 
            AND data_infracao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_infracao, '%Y-%m')
            ORDER BY mes";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($result as $row) {
        $mes = DateTime::createFromFormat('Y-m', $row['mes']);
        if ($mes) {
            $labels[] = $mes->format('M/Y');
            $values[] = (int)$row['quantidade'];
        }
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

function getValorPorMes($conn, $empresa_id) {
    $sql = "SELECT 
                DATE_FORMAT(data_infracao, '%Y-%m') as mes,
                SUM(valor) as valor_total
            FROM multas 
            WHERE empresa_id = :empresa_id 
            AND data_infracao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(data_infracao, '%Y-%m')
            ORDER BY mes";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($result as $row) {
        $mes = DateTime::createFromFormat('Y-m', $row['mes']);
        if ($mes) {
            $labels[] = $mes->format('M/Y');
            $values[] = (float)$row['valor_total'];
        }
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

function getMultasPorMotorista($conn, $empresa_id) {
    $sql = "SELECT 
                m.nome as motorista,
                COUNT(*) as quantidade
            FROM multas mu
            LEFT JOIN motoristas m ON mu.motorista_id = m.id
            WHERE mu.empresa_id = :empresa_id 
            AND mu.data_infracao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY mu.motorista_id, m.nome
            ORDER BY quantidade DESC
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($result as $row) {
        $labels[] = $row['motorista'] ?: 'Motorista não identificado';
        $values[] = (int)$row['quantidade'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}

function getPontosPorMotorista($conn, $empresa_id) {
    $sql = "SELECT 
                m.nome as motorista,
                SUM(mu.pontos) as pontos_total
            FROM multas mu
            LEFT JOIN motoristas m ON mu.motorista_id = m.id
            WHERE mu.empresa_id = :empresa_id 
            AND mu.data_infracao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            AND mu.pontos > 0
            GROUP BY mu.motorista_id, m.nome
            ORDER BY pontos_total DESC
            LIMIT 10";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $labels = [];
    $values = [];
    
    foreach ($result as $row) {
        $labels[] = $row['motorista'] ?: 'Motorista não identificado';
        $values[] = (int)$row['pontos_total'];
    }
    
    return [
        'labels' => $labels,
        'values' => $values
    ];
}
?> 
