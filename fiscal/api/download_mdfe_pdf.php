<?php
/**
 * Gera e envia PDF do MDF-e (DAMDFE).
 * Preferência: sped-da (layout oficial). Fallback: mPDF resumido.
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

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(400);
    exit('ID inválido');
}
$empresa_id = (int) $_SESSION['empresa_id'];

try {
    $conn = getConnection();
} catch (Throwable $e) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    exit('Erro ao conectar.');
}

$stmt = $conn->prepare('SELECT * FROM fiscal_mdfe WHERE id = ? AND empresa_id = ? LIMIT 1');
$stmt->execute([$id, $empresa_id]);
$mdfe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mdfe) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('MDF-e não encontrado');
}

$status = strtolower(trim((string) ($mdfe['status'] ?? '')));
if (!in_array($status, ['autorizado', 'encerrado', 'emitido', 'em_viagem'], true)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(422);
    exit('DAMDFE disponível apenas para MDF-e autorizado ou encerrado.');
}

if (!empty($mdfe['pdf_mdfe'])) {
    $path = __DIR__ . '/../../' . ltrim((string) $mdfe['pdf_mdfe'], '/');
    if (is_file($path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="mdfe_' . ($mdfe['numero_mdfe'] ?? $id) . '.pdf"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

$xml = trim((string) ($mdfe['xml_mdfe'] ?? ''));
if ($xml === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('XML do MDF-e não encontrado. Emita/autorize o manifesto antes de gerar o DAMDFE.');
}

$protocolo = (string) ($mdfe['protocolo_autorizacao'] ?? '');
$chave = (string) ($mdfe['chave_acesso'] ?? '');
$pdf_content = null;
$engine = 'mpdf';

$logoPath = null;
if (is_readable(__DIR__ . '/../includes/mdfe_wizard_helpers.php')) {
    require_once __DIR__ . '/../includes/mdfe_wizard_helpers.php';
    $logoPath = fiscal_mdfe_logo_path($empresa_id);
}

if (is_readable(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../includes/MdfeDamdfeSped.php';
    $pdf_content = fiscal_gerar_damdfe_pdf_sped_da($xml, $protocolo !== '' ? $protocolo : null, $chave, $logoPath);
    if ($pdf_content !== null) {
        $engine = 'sped-da';
    }
}

if ($pdf_content === null) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../includes/MpdfTempDir.php';
    require_once __DIR__ . '/../includes/DamdfePdfHelper.php';
    $dam = montarDamdfeResumoHtml($xml, $mdfe, $logoPath);
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
        $mpdf->SetTitle('MDF-e ' . $dam['numero']);
        $mpdf->WriteHTML($dam['html']);
        $pdf_content = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    } catch (Throwable $e) {
        error_log('download_mdfe_pdf: ' . $e->getMessage());
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(500);
        exit('Erro ao gerar DAMDFE. Verifique composer install e extensão gd.');
    }
}

$numero = preg_replace('/\D/', '', (string) ($mdfe['numero_mdfe'] ?? ''));
$filename = 'mdfe_' . ($numero !== '' ? $numero : (string) $id) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Fiscal-Damdfe-Engine: ' . $engine);
header('Content-Length: ' . strlen((string) $pdf_content));
echo $pdf_content;
