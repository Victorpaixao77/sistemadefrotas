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
    
    // Buscar manutenções agendadas
    $sql = "SELECT 
                m.id,
                m.veiculo_id,
                v.placa,
                v.modelo,
                v.marca,
                v.ano,
                m.data_manutencao,
                DATEDIFF(m.data_manutencao, CURRENT_DATE) as dias_para_manutencao
            FROM manutencoes m 
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id 
            AND m.data_manutencao IS NOT NULL
            AND m.data_manutencao >= CURRENT_DATE
            ORDER BY m.data_manutencao ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $events = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias_para_manutencao = $row['dias_para_manutencao'];
        $status_nome = 'Agendada';
        
        // Criar eventos para diferentes alertas
        if ($dias_para_manutencao <= 7) {
            // Manutenção em 7 dias ou menos - ALERTA VERMELHO
            $events[] = [
                'id' => 'manutencao_' . $row['id'] . '_7',
                'title' => 'Manutenção em 7 dias: ' . $row['placa'],
                'start' => $row['data_manutencao'],
                'end' => $row['data_manutencao'],
                'allDay' => true,
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'extendedProps' => [
                    'category' => 'manutencao',
                    'description' => 'Veículo ' . $row['placa'] . ' (' . $row['marca'] . ' ' . $row['modelo'] . ' ' . $row['ano'] . ') tem manutenção agendada em ' . $dias_para_manutencao . ' dias. Status: ' . $status_nome,
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'dias_para_manutencao' => $dias_para_manutencao,
                    'status' => $status_nome,
                    'priority' => 'high'
                ]
            ];
        } elseif ($dias_para_manutencao <= 15) {
            // Manutenção em 15 dias ou menos - ALERTA AMARELO
            $events[] = [
                'id' => 'manutencao_' . $row['id'] . '_15',
                'title' => 'Manutenção em 15 dias: ' . $row['placa'],
                'start' => $row['data_manutencao'],
                'end' => $row['data_manutencao'],
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'extendedProps' => [
                    'category' => 'manutencao',
                    'description' => 'Veículo ' . $row['placa'] . ' (' . $row['marca'] . ' ' . $row['modelo'] . ' ' . $row['ano'] . ') tem manutenção agendada em ' . $dias_para_manutencao . ' dias. Status: ' . $status_nome,
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'dias_para_manutencao' => $dias_para_manutencao,
                    'status' => $status_nome,
                    'priority' => 'medium'
                ]
            ];
        } elseif ($dias_para_manutencao <= 30) {
            // Manutenção em 30 dias ou menos - ALERTA AZUL
            $events[] = [
                'id' => 'manutencao_' . $row['id'] . '_30',
                'title' => 'Manutenção em 30 dias: ' . $row['placa'],
                'start' => $row['data_manutencao'],
                'end' => $row['data_manutencao'],
                'allDay' => true,
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#3b82f6',
                'extendedProps' => [
                    'category' => 'manutencao',
                    'description' => 'Veículo ' . $row['placa'] . ' (' . $row['marca'] . ' ' . $row['modelo'] . ' ' . $row['ano'] . ') tem manutenção agendada em ' . $dias_para_manutencao . ' dias. Status: ' . $status_nome,
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'dias_para_manutencao' => $dias_para_manutencao,
                    'status' => $status_nome,
                    'priority' => 'low'
                ]
            ];
        }
        
        // Evento para manutenções agendadas para hoje
        if ($dias_para_manutencao == 0) {
            $events[] = [
                'id' => 'manutencao_' . $row['id'] . '_hoje',
                'title' => 'MANUTENÇÃO HOJE: ' . $row['placa'],
                'start' => $row['data_manutencao'],
                'end' => $row['data_manutencao'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'extendedProps' => [
                    'category' => 'manutencao',
                    'description' => 'URGENTE: Manutenção do veículo ' . $row['placa'] . ' (' . $row['marca'] . ' ' . $row['modelo'] . ' ' . $row['ano'] . ') está agendada para hoje! Status: ' . $status_nome,
                    'veiculo_id' => $row['veiculo_id'],
                    'placa' => $row['placa'],
                    'status' => $status_nome,
                    'priority' => 'critical'
                ]
            ];
        }
    }
    
    echo json_encode($events);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}
?>
