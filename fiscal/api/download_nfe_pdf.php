<?php
/**
 * Gera e envia PDF da NF-e (DANFE).
 * Com XML completo: layout oficial NFePHP (sped-da) — protocolo, totais, impostos, infAdic, pagamento, etc.
 * Sem XML ou se sped-da falhar: resumo em mPDF; se existir arquivo em pdf_nfe, usa após tentar XML.
 */
// Limpar qualquer buffer para não misturar com a resposta de download
while (ob_get_level()) {
    ob_end_clean();
}

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/db_connect.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['empresa_id']) || (!isset($_GET['id']) && !isset($_POST['id']))) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(403);
    exit('Acesso negado');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit('ID inválido');
}
$empresa_id = (int)$_SESSION['empresa_id'];

try {
    $conn = getConnection();
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit('Erro ao conectar.');
}

// Um único SELECT para não falhar se colunas opcionais não existirem
$row = null;
try {
    $stmt = $conn->prepare("SELECT * FROM fiscal_nfe_clientes WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmt->execute([$id, $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit('Erro ao buscar NF-e.');
}

if (!$row) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('NF-e não encontrada');
}

require_once __DIR__ . '/../includes/FiscalNfeXmlParaDownload.php';
$xml_content = fiscal_nfe_obter_xml_para_download($conn, $empresa_id, $id, $row, false);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/NFeDanfeSped.php';

$chave = $row['chave_acesso'] ?? '';
$filename = 'nfe_' . preg_replace('/[^0-9]/', '', $chave ?: (string)$id) . '.pdf';
if (strlen($filename) < 12) {
    $filename = 'nfe_' . $id . '.pdf';
}

$has_xml = is_string($xml_content) && $xml_content !== '';

if ($has_xml) {
    $pdf_sped = fiscal_gerar_danfe_pdf_sped_da($xml_content);
    if ($pdf_sped !== null && $pdf_sped !== '') {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf_sped));
        header('Cache-Control: no-cache, must-revalidate');
        header('X-Fiscal-Danfe-Engine: sped-da');
        echo $pdf_sped;
        exit;
    }
    // Com XML no banco mas sped-da indisponível ou erro: NÃO enviar PDF antigo em disco
    // (senão produção fica presa no layout velho mesmo após deploy).
    error_log('download_nfe_pdf: sped-da não gerou PDF; usando mPDF com XML (id=' . $id . '). Verifique composer install e ext-gd.');
} else {
    // Sem XML: único caso em que faz sentido servir arquivo PDF já armazenado
    if (!empty($row['pdf_nfe'])) {
        $path = __DIR__ . '/../../' . $row['pdf_nfe'];
        if (file_exists($path)) {
            while (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($path));
            header('X-Fiscal-Danfe-Engine: arquivo');
            readfile($path);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/MpdfTempDir.php';
require_once __DIR__ . '/../includes/DanfePdfHelper.php';

$danfe = montarDanfeCompletoHtml(is_string($xml_content) ? $xml_content : '', $row);
$html = $danfe['html'];
$numero = $danfe['numero'];

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'tempDir' => fiscal_mpdf_temp_dir(),
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);
    $mpdf->SetTitle('NF-e ' . $numero);
    $mpdf->WriteHTML($html);
    $pdf_content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
} catch (Throwable $e) {
    error_log('download_nfe_pdf: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    $msg = 'Erro ao gerar PDF. Verifique permissão em uploads/mpdf_tmp e extensões (gd, mbstring).';
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $msg .= ' ' . $e->getMessage();
    }
    exit($msg);
}

// Garantir que nenhuma saída anterior seja enviada antes do arquivo
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf_content));
header('Cache-Control: no-cache, must-revalidate');
header('X-Fiscal-Danfe-Engine: mpdf');

echo $pdf_content;
