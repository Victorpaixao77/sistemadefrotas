<?php
/**
 * Gera e envia PDF da NF-e (DANFE simplificado) a partir do XML.
 * Quando há xml_nfe, gera o PDF on-the-fly com mPDF.
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

// Se já tem arquivo PDF armazenado, enviar ele
if (!empty($row['pdf_nfe'])) {
    $path = __DIR__ . '/../../' . $row['pdf_nfe'];
    if (file_exists($path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="nfe_' . ($row['numero_nfe'] ?? $id) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// Obter XML para gerar PDF
$xml_content = null;
if (!empty($row['xml_nfe'])) {
    $xml_content = $row['xml_nfe'];
} elseif (!empty($row['xml_path'])) {
    $path = __DIR__ . '/../../uploads/nfe_xml/' . basename($row['xml_path']);
    if (file_exists($path)) {
        $xml_content = file_get_contents($path);
    }
}

if ($xml_content === null || $xml_content === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('XML não disponível. Não é possível gerar o PDF.');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/DanfePdfHelper.php';

$danfe = montarDanfeCompletoHtml($xml_content, $row);
$html = $danfe['html'];
$numero = $danfe['numero'];
$chave = $row['chave_acesso'] ?? '';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);
    $mpdf->SetTitle('NF-e ' . $numero);
    $mpdf->WriteHTML($html);
    $pdf_content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit('Erro ao gerar PDF.');
}

$filename = 'nfe_' . preg_replace('/[^0-9]/', '', $chave ?: (string)$id) . '.pdf';
if (strlen($filename) < 12) {
    $filename = 'nfe_' . $id . '.pdf';
}

// Garantir que nenhuma saída anterior seja enviada antes do arquivo
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf_content));
header('Cache-Control: no-cache, must-revalidate');

echo $pdf_content;
