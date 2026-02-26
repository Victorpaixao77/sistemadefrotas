<?php
/**
 * Download do XML da NF-e por ID.
 * Retorna o conteúdo do campo xml_nfe ou o arquivo em uploads/nfe_xml/ (xml_path).
 * Não envia HTML em caso de erro (evita download ser salvo como .htm).
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

// Um único SELECT para não falhar se xml_nfe ou xml_path não existirem
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
    exit('XML não disponível para esta NF-e.');
}

$filename = 'nfe_' . preg_replace('/[^0-9]/', '', $row['chave_acesso'] ?? $row['numero_nfe'] ?? (string)$id) . '.xml';
if (strlen($filename) < 10) {
    $filename = 'nfe_' . $id . '.xml';
}

// Garantir que nenhuma saída anterior seja enviada antes do arquivo
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($xml_content));
header('Cache-Control: no-cache, must-revalidate');

echo $xml_content;
