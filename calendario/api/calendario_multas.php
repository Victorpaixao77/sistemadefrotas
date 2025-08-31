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
    
    // Buscar multas com vencimento
    $sql = "SELECT 
                m.id,
                m.tipo_infracao,
                m.valor,
                m.data_infracao,
                m.vencimento,
                m.veiculo_id,
                COALESCE(v.placa, 'N/A') as placa,
                DATEDIFF(m.vencimento, CURRENT_DATE) as dias_para_vencimento
            FROM multas m 
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id 
            AND m.vencimento IS NOT NULL 
            AND m.vencimento >= CURRENT_DATE
            ORDER BY m.vencimento ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $events = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias_para_vencimento = $row['dias_para_vencimento'];
        
        // Criar eventos para diferentes alertas
        if ($dias_para_vencimento <= 7) {
            // Vencimento em 7 dias ou menos - ALERTA VERMELHO
            $events[] = [
                'id' => 'multa_' . $row['id'] . '_7',
                'title' => 'Multa Vence em 7 dias: ' . $row['placa'],
                'start' => $row['vencimento'],
                'end' => $row['vencimento'],
                'allDay' => true,
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'extendedProps' => [
                    'category' => 'multas',
                    'description' => 'Multa do veículo ' . $row['placa'] . ' (Tipo: ' . $row['tipo_infracao'] . ') vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'multa_id' => $row['id'],
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'high'
                ]
            ];
        } elseif ($dias_para_vencimento <= 15) {
            // Vencimento em 15 dias ou menos - ALERTA AMARELO
            $events[] = [
                'id' => 'multa_' . $row['id'] . '_15',
                'title' => 'Multa Vence em 15 dias: ' . $row['placa'],
                'start' => $row['vencimento'],
                'end' => $row['vencimento'],
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'extendedProps' => [
                    'category' => 'multas',
                    'description' => 'Multa do veículo ' . $row['placa'] . ' (Tipo: ' . $row['tipo_infracao'] . ') vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'multa_id' => $row['id'],
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'medium'
                ]
            ];
        } elseif ($dias_para_vencimento <= 30) {
            // Vencimento em 30 dias ou menos - ALERTA AZUL
            $events[] = [
                'id' => 'multa_' . $row['id'] . '_30',
                'title' => 'Multa Vence em 30 dias: ' . $row['placa'],
                'start' => $row['vencimento'],
                'end' => $row['vencimento'],
                'allDay' => true,
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#3b82f6',
                'extendedProps' => [
                    'category' => 'multas',
                    'description' => 'Multa do veículo ' . $row['placa'] . ' (Tipo: ' . $row['tipo_infracao'] . ') vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'multa_id' => $row['id'],
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'low'
                ]
            ];
        }
        
        // Evento no dia exato do vencimento
        $events[] = [
            'id' => 'multa_' . $row['id'] . '_vencimento',
            'title' => 'Multa VENCE HOJE: ' . $row['placa'],
            'start' => $row['vencimento'],
            'end' => $row['vencimento'],
            'allDay' => true,
            'backgroundColor' => '#dc2626',
            'borderColor' => '#dc2626',
                            'extendedProps' => [
                    'category' => 'multas',
                    'description' => 'ATENÇÃO: Multa do veículo ' . $row['placa'] . ' (Tipo: ' . $row['tipo_infracao'] . ') vence hoje! Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'multa_id' => $row['id'],
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'valor' => $row['valor'],
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
