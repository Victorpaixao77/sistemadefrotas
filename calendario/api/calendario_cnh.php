<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    // Buscar motoristas com CNH vencendo
    $sql = "SELECT 
                m.id,
                m.nome,
                COALESCE(m.cnh, 'N/A') as cnh_numero,
                m.data_validade_cnh,
                DATEDIFF(m.data_validade_cnh, CURRENT_DATE) as dias_para_vencimento
            FROM motoristas m 
            WHERE m.empresa_id = :empresa_id 
            AND m.data_validade_cnh IS NOT NULL 
            AND m.data_validade_cnh >= CURRENT_DATE
            ORDER BY m.data_validade_cnh ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $events = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias_para_vencimento = $row['dias_para_vencimento'];
        
        // Criar eventos para diferentes alertas
        if ($dias_para_vencimento <= 30) {
            // Vencimento em 30 dias ou menos - ALERTA VERMELHO
            $events[] = [
                'id' => 'cnh_' . $row['id'] . '_30',
                'title' => 'CNH Vence em 30 dias: ' . $row['nome'],
                'start' => $row['data_validade_cnh'],
                'end' => $row['data_validade_cnh'],
                'allDay' => true,
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'extendedProps' => [
                    'category' => 'cnh',
                    'description' => 'CNH do motorista ' . $row['nome'] . ' (Número: ' . $row['cnh_numero'] . ') vence em ' . $dias_para_vencimento . ' dias.',
                    'motorista_id' => $row['id'],
                    'cnh_numero' => $row['cnh_numero'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'high'
                ]
            ];
        } elseif ($dias_para_vencimento <= 60) {
            // Vencimento em 60 dias ou menos - ALERTA AMARELO
            $events[] = [
                'id' => 'cnh_' . $row['id'] . '_60',
                'title' => 'CNH Vence em 60 dias: ' . $row['nome'],
                'start' => $row['data_validade_cnh'],
                'end' => $row['data_validade_cnh'],
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'extendedProps' => [
                    'category' => 'cnh',
                    'description' => 'CNH do motorista ' . $row['nome'] . ' (Número: ' . $row['cnh_numero'] . ') vence em ' . $dias_para_vencimento . ' dias.',
                    'motorista_id' => $row['id'],
                    'cnh_numero' => $row['cnh_numero'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'medium'
                ]
            ];
        } elseif ($dias_para_vencimento <= 90) {
            // Vencimento em 90 dias ou menos - ALERTA AZUL
            $events[] = [
                'id' => 'cnh_' . $row['id'] . '_90',
                'title' => 'CNH Vence em 90 dias: ' . $row['nome'],
                'start' => $row['data_validade_cnh'],
                'end' => $row['data_validade_cnh'],
                'allDay' => true,
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#3b82f6',
                'extendedProps' => [
                    'category' => 'cnh',
                    'description' => 'CNH do motorista ' . $row['nome'] . ' (Número: ' . $row['cnh_numero'] . ') vence em ' . $dias_para_vencimento . ' dias.',
                    'motorista_id' => $row['id'],
                    'cnh_numero' => $row['cnh_numero'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'low'
                ]
            ];
        }
        
        // Evento no dia exato do vencimento
        $events[] = [
            'id' => 'cnh_' . $row['id'] . '_vencimento',
            'title' => 'CNH VENCE HOJE: ' . $row['nome'],
            'start' => $row['data_validade_cnh'],
            'end' => $row['data_validade_cnh'],
            'allDay' => true,
            'backgroundColor' => '#dc2626',
            'borderColor' => '#dc2626',
            'extendedProps' => [
                'category' => 'cnh',
                'description' => 'ATENÇÃO: CNH do motorista ' . $row['nome'] . ' (Número: ' . $row['cnh_numero'] . ') vence hoje!',
                'motorista_id' => $row['id'],
                'cnh_numero' => $row['cnh_numero'],
                'dias_para_vencimento' => 0,
                'priority' => 'critical'
            ]
        ];
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>
