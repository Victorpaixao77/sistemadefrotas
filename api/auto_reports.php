<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auto_reports.php';

configure_session();
session_start();

header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $conn = getConnection();
    $report_manager = new AutoReportManager($conn);
    
    if ($action === 'generate_report') {
        $report_type = $_POST['report_type'] ?? $_GET['report_type'] ?? '';
        
        switch ($report_type) {
            case 'gamificacao_semanal':
                $report_data = $report_manager->generateWeeklyGamificationReport($empresa_id);
                break;
            case 'ranking_mensal':
                $report_data = $report_manager->generateMonthlyRankingReport($empresa_id);
                break;
            case 'performance_geral':
                $report_data = $report_manager->generatePerformanceReport($empresa_id);
                break;
            default:
                throw new Exception('Tipo de relatório inválido');
        }
        
        if ($report_data) {
            $html = $report_manager->generateReportHTML($report_data);
            echo json_encode([
                'success' => true,
                'html' => $html,
                'data' => $report_data
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao gerar relatório'
            ]);
        }
        
    } elseif ($action === 'download') {
        $report_type = $_GET['report_type'] ?? '';
        
        switch ($report_type) {
            case 'gamificacao_semanal':
                $report_data = $report_manager->generateWeeklyGamificationReport($empresa_id);
                break;
            case 'ranking_mensal':
                $report_data = $report_manager->generateMonthlyRankingReport($empresa_id);
                break;
            case 'performance_geral':
                $report_data = $report_manager->generatePerformanceReport($empresa_id);
                break;
            default:
                throw new Exception('Tipo de relatório inválido');
        }
        
        if ($report_data) {
            $html = $report_manager->generateReportHTML($report_data);
            
            // Gerar PDF
            require_once '../vendor/autoload.php';
            
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 15,
                'margin_right' => 15,
                'margin_top' => 16,
                'margin_bottom' => 16
            ]);
            
            $mpdf->SetTitle('Relatório Automático - ' . ucfirst(str_replace('_', ' ', $report_type)));
            $mpdf->WriteHTML($html);
            
            $filename = 'relatorio_' . $report_type . '_' . date('Y-m-d') . '.pdf';
            $mpdf->Output($filename, 'D');
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Erro ao gerar relatório para download'
            ]);
        }
        
    } else {
        throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
