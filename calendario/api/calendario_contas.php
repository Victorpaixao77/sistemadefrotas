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
    
    // Buscar contas a pagar com vencimento
    $sql = "SELECT 
                cp.id,
                cp.descricao,
                cp.valor,
                cp.data_vencimento,
                cp.fornecedor,
                DATEDIFF(cp.data_vencimento, CURRENT_DATE) as dias_para_vencimento
            FROM contas_pagar cp 
            WHERE cp.empresa_id = :empresa_id 
            AND cp.data_vencimento IS NOT NULL 
            AND cp.data_vencimento >= CURRENT_DATE
            ORDER BY cp.data_vencimento ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    $events = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias_para_vencimento = $row['dias_para_vencimento'];
        $fornecedor = $row['fornecedor'] ?: 'Fornecedor não informado';
        
        // Criar eventos para diferentes alertas
        if ($dias_para_vencimento <= 7) {
            // Vencimento em 7 dias ou menos - ALERTA VERMELHO
            $events[] = [
                'id' => 'conta_' . $row['id'] . '_7',
                'title' => 'Conta Vence em 7 dias: ' . $row['descricao'],
                'start' => $row['data_vencimento'],
                'end' => $row['data_vencimento'],
                'allDay' => true,
                'backgroundColor' => '#ef4444',
                'borderColor' => '#ef4444',
                'extendedProps' => [
                    'category' => 'contas',
                    'description' => 'Conta "' . $row['descricao'] . '" do fornecedor ' . $fornecedor . ' vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'conta_id' => $row['id'],
                    'fornecedor' => $fornecedor,
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'high'
                ]
            ];
        } elseif ($dias_para_vencimento <= 15) {
            // Vencimento em 15 dias ou menos - ALERTA AMARELO
            $events[] = [
                'id' => 'conta_' . $row['id'] . '_15',
                'title' => 'Conta Vence em 15 dias: ' . $row['descricao'],
                'start' => $row['data_vencimento'],
                'end' => $row['data_vencimento'],
                'allDay' => true,
                'backgroundColor' => '#f59e0b',
                'borderColor' => '#f59e0b',
                'extendedProps' => [
                    'category' => 'contas',
                    'description' => 'Conta "' . $row['descricao'] . '" do fornecedor ' . $fornecedor . ' vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'conta_id' => $row['id'],
                    'fornecedor' => $fornecedor,
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'medium'
                ]
            ];
        } elseif ($dias_para_vencimento <= 30) {
            // Vencimento em 30 dias ou menos - ALERTA AZUL
            $events[] = [
                'id' => 'conta_' . $row['id'] . '_30',
                'title' => 'Conta Vence em 30 dias: ' . $row['descricao'],
                'start' => $row['data_vencimento'],
                'end' => $row['data_vencimento'],
                'allDay' => true,
                'backgroundColor' => '#3b82f6',
                'borderColor' => '#3b82f6',
                'extendedProps' => [
                    'category' => 'contas',
                    'description' => 'Conta "' . $row['descricao'] . '" do fornecedor ' . $fornecedor . ' vence em ' . $dias_para_vencimento . ' dias. Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'conta_id' => $row['id'],
                    'fornecedor_id' => $row['fornecedor_id'],
                    'fornecedor_nome' => $fornecedor,
                    'valor' => $row['valor'],
                    'dias_para_vencimento' => $dias_para_vencimento,
                    'priority' => 'low'
                ]
            ];
        }
        
        // Evento no dia exato do vencimento
        $events[] = [
            'id' => 'conta_' . $row['id'] . '_vencimento',
            'title' => 'Conta VENCE HOJE: ' . $row['descricao'],
            'start' => $row['data_vencimento'],
            'end' => $row['data_vencimento'],
            'allDay' => true,
            'backgroundColor' => '#dc2626',
            'borderColor' => '#dc2626',
                            'extendedProps' => [
                    'category' => 'contas',
                    'description' => 'ATENÇÃO: Conta "' . $row['descricao'] . '" do fornecedor ' . $fornecedor . ' vence hoje! Valor: R$ ' . number_format($row['valor'], 2, ',', '.'),
                    'conta_id' => $row['id'],
                    'fornecedor' => $fornecedor,
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
