<?php
/**
 * OCR de nota fiscal de manutenção (oficina).
 * POST multipart: campo "image" (JPEG/PNG/WebP) + CSRF.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/sf_vision_ai.php';
require_once __DIR__ . '/../includes/sf_openai_resolve.php';

configure_session();
session_start();

header('Content-Type: application/json; charset=utf-8');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
api_require_csrf_json();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_error('Não autorizado.', 401);
}

$conn = getConnection();
sf_ia_enforce_quota($conn, $empresa_id);

$binary = '';
$mime = 'image/jpeg';

if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $binary = (string) file_get_contents($_FILES['image']['tmp_name']);
    $mime = strtolower((string) ($_FILES['image']['type'] ?? ''));
} else {
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($input) && !empty($input['image_base64'])) {
        $b64 = preg_replace('#^data:image/[^;]+;base64,#', '', (string) $input['image_base64']);
        $decoded = base64_decode($b64, true);
        if ($decoded !== false && strlen($decoded) > 50) {
            $binary = $decoded;
            $mime = isset($input['mime_type']) ? strtolower((string) $input['mime_type']) : 'image/jpeg';
        }
    }
}

if ($binary === '') {
    api_json_error('Envie a imagem no campo "image" (foto da NF).', 400);
}

if (strlen($binary) > 12 * 1024 * 1024) {
    api_json_error('Arquivo muito grande (máx. 12 MB).', 400);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    $ext = strtolower(pathinfo((string) ($_FILES['image']['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        default => 'image/jpeg',
    };
}

$key = '';
$model = 'gpt-4o';
$url = 'https://api.openai.com/v1/chat/completions';
$label = 'IA';

$vision = sf_vision_ai_resolve($conn, $empresa_id);
if ($vision) {
    $key = $vision['key'];
    $model = $vision['model'];
    $url = $vision['url'];
    $label = $vision['label'];
} elseif (function_exists('sf_openai_key_resolved')) {
    $oa = sf_openai_key_resolved($conn, $empresa_id);
    if ($oa !== '') {
        $key = $oa;
        $model = function_exists('sf_openai_vision_model_resolved') ? sf_openai_vision_model_resolved($conn, $empresa_id) : 'gpt-4o';
        $url = 'https://api.openai.com/v1/chat/completions';
        $label = 'OpenAI';
    }
}

if ($key === '' && defined('SF_DEEPSEEK_API_KEY') && SF_DEEPSEEK_API_KEY !== '') {
    $key = SF_DEEPSEEK_API_KEY;
    $model = 'deepseek-chat';
    $url = 'https://api.deepseek.com/v1/chat/completions';
    $label = 'DeepSeek';
}

if ($key === '') {
    api_json_error('Nenhuma chave de IA configurada para leitura de NF.', 503);
}

$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

$system = 'Você extrai dados de notas fiscais de serviços automotivos/oficina no Brasil. Responda somente JSON válido, sem markdown.';
$userText = <<<'TXT'
Na imagem, extraia os dados da nota fiscal de serviço/oficina.

Responda com um ÚNICO JSON:
{"nota_fiscal":"","fornecedor":"","valor_total":0,"data_emissao":"dd/mm/aaaa","descricao":"","cnpj_emitente":""}

Regras:
- nota_fiscal: número da NF/NFS-e como aparece (sem inventar).
- fornecedor: razão social ou nome fantasia do emitente.
- valor_total: número com ponto decimal (ex.: 1250.50).
- data_emissao: data de emissão como na nota.
- descricao: resumo curto dos serviços (máx. 200 caracteres).
- Se não ler com clareza, deixe string vazia ou 0 — não invente.
TXT;

$payload = [
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => $system],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $userText],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ],
        ],
    ],
    'response_format' => ['type' => 'json_object'],
    'max_tokens' => 1024,
    'temperature' => 0.05,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
]);
$raw = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($raw === false || $curlErr !== '') {
    error_log('maintenance_ocr_nf curl: ' . $curlErr);
    api_json_error('Falha ao contactar ' . $label . '.', 502);
}

$response = json_decode((string) $raw, true);
if ($http >= 400 || !is_array($response)) {
    $msg = is_array($response) ? (string) ($response['error']['message'] ?? '') : '';
    api_json_error($label . ' retornou erro HTTP ' . $http . ($msg !== '' ? ': ' . $msg : '.'), $http >= 500 ? 502 : 400);
}

$content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
if (strncmp($content, '```', 3) === 0) {
    $content = preg_replace('/^```[a-zA-Z]*\s*/', '', $content);
    $content = preg_replace('/\s*```$/', '', (string) $content);
}

$parsed = json_decode($content, true);
if (!is_array($parsed)) {
    api_json_error('Resposta da IA inválida.', 422);
}

$valor = null;
if (isset($parsed['valor_total'])) {
    $v = $parsed['valor_total'];
    if (is_numeric($v)) {
        $valor = (float) $v;
    } else {
        $s = preg_replace('/[^\d,.\-]/', '', (string) $v);
        if (is_string($s) && $s !== '') {
            if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
                $s = strrpos($s, ',') > strrpos($s, '.')
                    ? str_replace(['.', ','], ['', '.'], $s)
                    : str_replace(',', '', $s);
            } elseif (strpos($s, ',') !== false) {
                $s = str_replace(',', '.', $s);
            }
            if (is_numeric($s)) {
                $valor = (float) $s;
            }
        }
    }
}

$dataEmissao = trim((string) ($parsed['data_emissao'] ?? ''));
$dataIso = null;
if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dataEmissao, $m)) {
    $dataIso = $m[3] . '-' . $m[2] . '-' . $m[1];
} elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dataEmissao, $m)) {
    $dataIso = '20' . $m[3] . '-' . $m[2] . '-' . $m[1];
}

echo json_encode([
    'success' => true,
    'provider' => $label,
    'data' => [
        'nota_fiscal' => trim((string) ($parsed['nota_fiscal'] ?? '')),
        'fornecedor' => trim((string) ($parsed['fornecedor'] ?? '')),
        'valor' => $valor,
        'data_manutencao' => $dataIso,
        'descricao' => trim((string) ($parsed['descricao'] ?? '')),
        'cnpj_emitente' => trim((string) ($parsed['cnpj_emitente'] ?? '')),
    ],
], JSON_UNESCAPED_UNICODE);
