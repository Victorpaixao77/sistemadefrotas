<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auto_reports.php';

configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/** Gera os dados do relatório automático a partir do tipo. */
function auto_report_build(AutoReportManager $mgr, string $type, $empresa_id) {
    switch ($type) {
        case 'gamificacao_semanal':
            return $mgr->generateWeeklyGamificationReport($empresa_id);
        case 'ranking_mensal':
            return $mgr->generateMonthlyRankingReport($empresa_id);
        case 'performance_geral':
            return $mgr->generatePerformanceReport($empresa_id);
        default:
            throw new Exception('Tipo de relatório inválido');
    }
}

/** Achata os dados do relatório em linhas (para Excel/CSV). */
function auto_report_rows(array $rd): array {
    $type = $rd['type'] ?? '';
    if ($type === 'performance_geral') {
        $rows = [];
        foreach (($rd['summary'] ?? []) as $k => $v) {
            $rows[] = ['indicador' => ucfirst(str_replace('_', ' ', $k)), 'valor' => $v];
        }
        return $rows;
    }
    return $rd['data'] ?? [];
}

try {
    $conn = getConnection();
    $report_manager = new AutoReportManager($conn);

    if ($action === 'generate_report') {
        header('Content-Type: application/json');
        $report_type = $_POST['report_type'] ?? $_GET['report_type'] ?? '';
        $report_data = auto_report_build($report_manager, $report_type, $empresa_id);
        if ($report_data) {
            echo json_encode([
                'success' => true,
                'html' => $report_manager->generateReportHTML($report_data),
                'data' => $report_data,
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao gerar relatório']);
        }
        exit;
    }

    if ($action === 'download' || $action === 'export') {
        $report_type = $_GET['report_type'] ?? $_POST['report_type'] ?? '';
        $formato = strtolower($_GET['formato'] ?? $_POST['formato'] ?? 'pdf');
        if (!in_array($formato, ['pdf', 'excel', 'csv'], true)) {
            $formato = 'pdf';
        }
        $report_data = auto_report_build($report_manager, $report_type, $empresa_id);
        if (!$report_data) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Erro ao gerar relatório para download']);
            exit;
        }

        $fileBase = 'relatorio_' . $report_type . '_' . date('Y-m-d');

        if ($formato === 'csv') {
            $rows = auto_report_rows($report_data);
            if (empty($rows)) { $rows = [['mensagem' => 'Nenhum registro no período.']]; }
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment;filename="' . $fileBase . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(function ($h) { return ucfirst(str_replace('_', ' ', $h)); }, array_keys($rows[0])), ';');
            foreach ($rows as $r) {
                fputcsv($out, array_map(function ($v) { return is_scalar($v) ? $v : ''; }, $r), ';');
            }
            fclose($out);
            exit;
        }

        if ($formato === 'excel') {
            require_once '../vendor/autoload.php';
            $rows = auto_report_rows($report_data);
            if (empty($rows)) { $rows = [['mensagem' => 'Nenhum registro no período.']]; }
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $col = 'A';
            foreach (array_keys($rows[0]) as $h) {
                $sheet->setCellValue($col . '1', ucfirst(str_replace('_', ' ', $h)));
                $col++;
            }
            $rowIdx = 2;
            foreach ($rows as $r) {
                $col = 'A';
                foreach ($r as $v) {
                    $sheet->setCellValue($col . $rowIdx, is_scalar($v) ? $v : '');
                    $col++;
                }
                $rowIdx++;
            }
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $fileBase . '.xlsx"');
            header('Cache-Control: max-age=0');
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
            exit;
        }

        // PDF (padrão)
        require_once '../vendor/autoload.php';
        $tempDir = dirname(__DIR__) . '/tmp';
        if (!file_exists($tempDir)) { mkdir($tempDir, 0755, true); }
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => $tempDir, 'mode' => 'utf-8', 'format' => 'A4',
            'margin_left' => 15, 'margin_right' => 15, 'margin_top' => 16, 'margin_bottom' => 16,
        ]);
        $mpdf->SetTitle('Relatório Automático - ' . ucfirst(str_replace('_', ' ', $report_type)));
        $mpdf->WriteHTML($report_manager->generateReportHTML($report_data));
        $mpdf->Output($fileBase . '.pdf', 'D');
        exit;
    }

    header('Content-Type: application/json');
    throw new Exception('Ação inválida');
} catch (Throwable $e) {
    error_log('api/auto_reports.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Erro interno.']);
}
