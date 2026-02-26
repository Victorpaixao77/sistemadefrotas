<?php
/**
 * Gera e envia PDF do CT-e (DACTE) a partir do XML.
 * Se não houver xml_cte, gera o XML antes (via CteXmlHelper) e depois o PDF.
 */
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

$stmt = $conn->prepare("SELECT * FROM fiscal_cte WHERE id = ? AND empresa_id = ? LIMIT 1");
$stmt->execute([$id, $empresa_id]);
$cte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cte) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('CT-e não encontrado');
}

if (!empty($cte['pdf_cte'])) {
    $path = __DIR__ . '/../../' . $cte['pdf_cte'];
    if (file_exists($path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="cte_' . ($cte['numero_cte'] ?? $id) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

$xml_content = null;
if (!empty($cte['xml_cte'])) {
    $xml_content = $cte['xml_cte'];
}
if ($xml_content === null || $xml_content === '') {
    $stmtEmp = $conn->prepare("SELECT * FROM empresa_clientes WHERE id = ? LIMIT 1");
    $stmtEmp->execute([$empresa_id]);
    $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        $empresa = ['cnpj' => '', 'razao_social' => 'Transportadora', 'nome_fantasia' => '', 'inscricao_estadual' => '', 'endereco' => '', 'cep' => '', 'cidade' => '', 'estado' => 'PR'];
    }
    require_once __DIR__ . '/../includes/CteXmlHelper.php';
    $xml_content = montarCteProcXml($conn, $cte, $empresa);
}

if ($xml_content === null || $xml_content === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('Não foi possível obter o XML do CT-e para gerar o PDF.');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../includes/DactePdfHelper.php';

$dacte = montarDacteCompletoHtml($xml_content, $cte);
$html = $dacte['html'];
$numero = $dacte['numero'];

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 12,
        'margin_right' => 12,
        'margin_top' => 12,
        'margin_bottom' => 12,
    ]);
    $mpdf->SetTitle('CT-e ' . $numero);
    $mpdf->WriteHTML($html);
    $pdf_content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit('Erro ao gerar PDF.');
}

$chave = preg_replace('/[^0-9]/', '', $cte['chave_acesso'] ?? $cte['numero_cte'] ?? (string)$id);
$filename = 'cte_' . ($chave ?: $id) . '.pdf';
if (strlen($filename) < 10) {
    $filename = 'cte_' . $id . '.pdf';
}

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf_content));
header('Cache-Control: no-cache, must-revalidate');

echo $pdf_content;
