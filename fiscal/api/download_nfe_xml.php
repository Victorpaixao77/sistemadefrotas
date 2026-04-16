<?php
/**
 * Download do XML da NF-e por ID.
 * Usa a mesma resolução do PDF: banco (xml_nfe) ou Distribuição DFe quando XML vazio
 * ou sem protocolo (protNFe). Parâmetro opcional atualizar=1 força nova consulta à SEFAZ.
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

$row = null;
try {
    $stmt = $conn->prepare("
        SELECT id, empresa_id, xml_nfe, chave_acesso, numero_nfe
        FROM fiscal_nfe_clientes
        WHERE id = ? AND empresa_id = ? LIMIT 1
    ");
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

$forcarSefaz = (string)($_GET['atualizar'] ?? $_POST['atualizar'] ?? '') === '1';

require_once __DIR__ . '/../includes/FiscalNfeXmlParaDownload.php';
$xml_meta = [];
$xml_content = fiscal_nfe_obter_xml_para_download($conn, $empresa_id, $id, $row, $forcarSefaz, $xml_meta);

if ($xml_content === null || $xml_content === '') {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(404);
    exit('XML não disponível para esta NF-e. Verifique a chave e o certificado digital da empresa na Distribuição DFe.');
}

$filename = 'nfe_' . preg_replace('/[^0-9]/', '', $row['chave_acesso'] ?? $row['numero_nfe'] ?? (string)$id) . '.xml';
if (strlen($filename) < 10) {
    $filename = 'nfe_' . $id . '.xml';
}

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xml_content));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (!empty($xml_meta['source'])) {
    header('X-Fiscal-Xml-Source: ' . $xml_meta['source']);
}
if (!empty($xml_meta['detail']) && defined('DEBUG_MODE') && DEBUG_MODE) {
    header('X-Fiscal-Xml-Detail: ' . preg_replace('/[\r\n]+/', ' ', substr($xml_meta['detail'], 0, 200)));
}

echo $xml_content;
