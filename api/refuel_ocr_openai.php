<?php
/**
 * OCR de fichas de viagem via DeepSeek (visão + JSON).
 * POST multipart: campo "image" (JPEG/PNG/WebP/GIF) + "csrf_token".
 * Alternativa: POST JSON { "image_base64": "...", "mime_type": "image/png", "csrf_token": "..." } com header X-CSRF-Token.
 *
 * Requer: SF_DEEPSEEK_API_KEY ou DEEPSEEK_API_KEY. Opcional: SF_DEEPSEEK_BASE_URL, SF_DEEPSEEK_VISION_MODEL (padrão deepseek-chat).
 */

declare(strict_types=1);

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>|null
 */
function refuel_ocr_parse_decimal_mixed($value): ?float
{
    if ($value === null) {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    $s = trim((string) $value);
    if ($s === '') {
        return null;
    }
    $s = preg_replace('/[^\d,.\-]/', '', $s);
    if (!is_string($s) || $s === '') {
        return null;
    }
    $hasComma = strpos($s, ',') !== false;
    $hasDot = strpos($s, '.') !== false;
    if ($hasComma && $hasDot) {
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    } elseif ($hasComma) {
        $s = str_replace(',', '.', $s);
    } elseif ($hasDot && preg_match('/^\d{1,3}(?:\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    if (!is_numeric($s)) {
        return null;
    }

    return (float) $s;
}

function refuel_ocr_parse_km($value): ?int
{
    if ($value === null) {
        return null;
    }
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return (int) round($value);
    }
    $s = trim((string) $value);
    if ($s === '') {
        return null;
    }
    $s = preg_replace('/[^\d]/', '', $s);
    if (!is_string($s) || $s === '' || !ctype_digit($s)) {
        return null;
    }

    return (int) $s;
}

function refuel_ocr_normalize_openai_item(array $item): ?array
{
    $dataRaw = isset($item['data']) ? trim((string) $item['data']) : '';
    $posto = isset($item['posto']) ? trim((string) $item['posto']) : '';
    $km = $item['km'] ?? null;
    $litros = $item['litros'] ?? ($item['l'] ?? null);
    $val = $item['valor_total'] ?? ($item['valor'] ?? ($item['total'] ?? null));

    $parts = refuel_ocr_parse_br_date_parts($dataRaw);
    if ($parts === null) {
        return null;
    }

    $kmNum = refuel_ocr_parse_km($km);
    if ($kmNum === null || $kmNum < 100 || $kmNum > 99999999) {
        return null;
    }

    $litrosNum = refuel_ocr_parse_decimal_mixed($litros);
    if ($litrosNum === null || $litrosNum <= 0 || $litrosNum > 80000) {
        return null;
    }

    $valorTotal = refuel_ocr_parse_decimal_mixed($val);
    if ($valorTotal === null || $valorTotal < 0) {
        return null;
    }

    $postoLen = function_exists('mb_strlen') ? mb_strlen($posto, 'UTF-8') : strlen($posto);
    if ($posto === '' || $postoLen > 120) {
        return null;
    }

    $ymd = sprintf('%04d-%02d-%02d', $parts['y'], $parts['m'], $parts['d']);
    $datetimeLocal = $ymd . 'T08:00';
    $valorLitro = $litrosNum > 0 ? $valorTotal / $litrosNum : 0.0;

    $fmtPt = static function (float $n, int $dec): string {
        return number_format($n, $dec, ',', '.');
    };

    return [
        'ymd' => $ymd,
        'datetimeLocal' => $datetimeLocal,
        'km' => $kmNum,
        'posto' => $posto,
        'litros' => $litrosNum,
        'valorTotal' => $valorTotal,
        'valorLitro' => $valorLitro,
        'litrosBr' => $fmtPt($litrosNum, 3),
        'valorTotalBr' => $fmtPt($valorTotal, 2),
        'valorLitroBr' => $fmtPt($valorLitro, 2),
        '_label' => $dataRaw . ' · ' . $posto,
    ];
}

/**
 * @return array{y: int, m: int, d: int}|null
 */
function refuel_ocr_parse_br_date_parts(string $s): ?array
{
    $s = trim($s);
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2}|\d{4})\b/', $s, $m)) {
        $d = (int) $m[1];
        $mo = (int) $m[2];
        $y = (int) $m[3];
        if ($y < 100) {
            $y += 2000;
        }
        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }
        return ['y' => $y, 'm' => $mo, 'd' => $d];
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})\b/', $s, $m2)) {
        $y = (int) $m2[1];
        $mo = (int) $m2[2];
        $d = (int) $m2[3];
        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }
        return ['y' => $y, 'm' => $mo, 'd' => $d];
    }
    return null;
}

/**
 * Reduz a maior dimensão da imagem antes da Vision API (menos tokens → menos 429 por TPM).
 * Saída em JPEG; se GD falhar, devolve o original.
 *
 * @return array{0: string, 1: string} [binary, mime]
 */
function refuel_ocr_downscale_for_vision(string $binary, string $mime): array
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return [$binary, $mime];
    }
    $src = @imagecreatefromstring($binary);
    if ($src === false) {
        return [$binary, $mime];
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 1 || $h < 1) {
        imagedestroy($src);

        return [$binary, $mime];
    }
    $maxEdge = 1536;
    $env = getenv('SF_REFUEL_VISION_MAX_EDGE');
    if (is_string($env) && ctype_digit(trim($env))) {
        $me = (int) trim($env);
        if ($me >= 640 && $me <= 4096) {
            $maxEdge = $me;
        }
    }
    $long = max($w, $h);
    if ($long <= $maxEdge) {
        imagedestroy($src);

        return [$binary, $mime];
    }
    $scale = $maxEdge / $long;
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
        imagedestroy($src);

        return [$binary, $mime];
    }
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    ob_start();
    imagejpeg($dst, null, 86);
    $out = (string) ob_get_clean();
    imagedestroy($dst);
    if ($out === '') {
        return [$binary, $mime];
    }

    return [$out, 'image/jpeg'];
}

/**
 * Pré-processamento para OCR em foto de ficha:
 * - tenta auto-orientação e melhoria de contraste/nitidez;
 * - converte saída em JPEG para reduzir ruído de compressão e padronizar.
 *
 * @return array{0: string, 1: string} [binary, mime]
 */
function refuel_ocr_preprocess_for_vision(string $binary, string $mime): array
{
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImageBlob($binary);
            if (method_exists($im, 'autoOrient')) {
                $im->autoOrient();
            }
            $im->setImageColorspace(Imagick::COLORSPACE_GRAY);
            if (method_exists($im, 'claheImage')) {
                $im->claheImage(12, 12, 3, 3);
            } else {
                $im->contrastStretchImage(0.07 * $im->getQuantum(), 0.98 * $im->getQuantum());
            }
            if (method_exists($im, 'despeckleImage')) {
                $im->despeckleImage();
            }
            $im->adaptiveSharpenImage(0, 1.0);
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality(88);
            $out = $im->getImageBlob();
            $im->clear();
            $im->destroy();
            if (is_string($out) && $out !== '') {
                return [$out, 'image/jpeg'];
            }
        } catch (Throwable $e) {
            // Silencioso: cai no fallback GD/original.
        }
    }
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) {
        return [$binary, $mime];
    }
    $src = @imagecreatefromstring($binary);
    if ($src === false) {
        return [$binary, $mime];
    }
    imagefilter($src, IMG_FILTER_GRAYSCALE);
    imagefilter($src, IMG_FILTER_CONTRAST, -18);
    imagefilter($src, IMG_FILTER_BRIGHTNESS, 8);
    if (defined('IMG_FILTER_SMOOTH')) {
        imagefilter($src, IMG_FILTER_SMOOTH, -2);
    }
    ob_start();
    imagejpeg($src, null, 88);
    $out = (string) ob_get_clean();
    imagedestroy($src);
    if ($out === '') {
        return [$binary, $mime];
    }

    return [$out, 'image/jpeg'];
}

function refuel_ocr_parts_count_words(string $line): int
{
    $trim = trim($line);
    if ($trim === '') {
        return 0;
    }

    return count(preg_split('/\s+/', $trim) ?: []);
}

/**
 * @return array<string,mixed>|null
 */
function refuel_ocr_try_parse_line(string $rawLine): ?array
{
    $line = trim((string) preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", '|'], ' ', $rawLine)));
    if (strlen($line) < 14) {
        return null;
    }
    if (preg_match('/^data\s+km\s/i', $line) && !preg_match('/\d{1,2}\/\d{1,2}\/\d{2}/', $line)) {
        return null;
    }
    if (preg_match('/^abastecimento/i', $line) && refuel_ocr_parts_count_words($line) < 4) {
        return null;
    }
    if (!preg_match('/^(\d{1,2}\/\d{1,2}\/\d{2,4})[\s.,:|\/_-]+(.+)$/', $line, $dm)) {
        return null;
    }
    $tail = trim((string) ($dm[2] ?? ''));
    $parts = preg_split('/\s+/', $tail) ?: [];
    if (count($parts) < 4) {
        return null;
    }
    $toNumToken = static function (string $s): string {
        $s = str_replace(['O', 'o'], '0', $s);
        $s = str_replace(['I', 'l'], '1', $s);

        return trim($s);
    };
    $valorStr = $toNumToken((string) $parts[count($parts) - 1]);
    $litrosStr = $toNumToken((string) $parts[count($parts) - 2]);
    $kmStr = $toNumToken((string) $parts[0]);
    $posto = trim(implode(' ', array_slice($parts, 1, -2)));
    if ($posto === '' || (function_exists('mb_strlen') ? mb_strlen($posto, 'UTF-8') : strlen($posto)) > 80) {
        return null;
    }
    $item = [
        'data' => (string) $dm[1],
        'km' => $kmStr,
        'posto' => $posto,
        'litros' => $litrosStr,
        'valor_total' => $valorStr,
    ];
    $row = refuel_ocr_normalize_openai_item($item);
    if ($row === null) {
        return null;
    }
    if ($row['km'] < 500 || $row['km'] > 9999999) {
        return null;
    }
    if ($row['litros'] < 1 || $row['litros'] > 80000) {
        return null;
    }

    return $row;
}

/**
 * @return array<int,array<string,mixed>>
 */
function refuel_ocr_parse_text_rows(string $text): array
{
    $lines = preg_split('/\r?\n/', $text) ?: [];
    $rows = [];
    $seen = [];
    foreach ($lines as $line) {
        $parsed = refuel_ocr_try_parse_line((string) $line);
        if ($parsed === null) {
            continue;
        }
        $key = $parsed['ymd'] . '|' . $parsed['km'] . '|' . $parsed['posto'] . '|' . $parsed['litros'] . '|' . $parsed['valorTotal'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $rows[] = $parsed;
    }

    return $rows;
}

/**
 * @return array{rows: array<int,array<string,mixed>>, text: string, error: string}
 */
function refuel_ocr_try_tesseract_fallback(string $binary): array
{
    $tmpBase = tempnam(sys_get_temp_dir(), 'rfocr_');
    if ($tmpBase === false) {
        return ['rows' => [], 'text' => '', 'error' => 'temp_file'];
    }
    $in = $tmpBase . '.jpg';
    @rename($tmpBase, $in);
    if (@file_put_contents($in, $binary) === false) {
        @unlink($in);

        return ['rows' => [], 'text' => '', 'error' => 'write_input'];
    }
    $bin = trim((string) getenv('SF_TESSERACT_BIN'));
    if ($bin === '') {
        $bin = 'tesseract';
    }
    $lang = trim((string) getenv('SF_TESSERACT_LANG'));
    if ($lang === '') {
        $lang = 'por';
    }
    $oem = (int) getenv('SF_TESSERACT_OEM');
    if ($oem < 0 || $oem > 3) {
        $oem = 3;
    }
    $psm = (int) getenv('SF_TESSERACT_PSM');
    if ($psm < 3 || $psm > 13) {
        $psm = 6;
    }
    $cmd = escapeshellcmd($bin)
        . ' ' . escapeshellarg($in)
        . ' stdout -l ' . escapeshellarg($lang)
        . ' --oem ' . $oem
        . ' --psm ' . $psm
        . ' 2>&1';
    $output = [];
    $exitCode = 0;
    exec($cmd, $output, $exitCode);
    @unlink($in);
    $text = trim(implode("\n", $output));
    if ($exitCode !== 0) {
        return ['rows' => [], 'text' => $text, 'error' => 'exec_' . $exitCode];
    }
    $rows = refuel_ocr_parse_text_rows($text);

    return ['rows' => $rows, 'text' => $text, 'error' => ''];
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/sf_openai_resolve.php';

function refuel_ocr_deepseek_throw_if_http_error(int $http, array $response): void
{
    $detail = '';
    $errCode = '';
    if (is_array($response['error'] ?? null)) {
        $detail = trim((string) ($response['error']['message'] ?? ''));
        $errCode = strtolower(trim((string) ($response['error']['code'] ?? '')));
    }
    $clientStatus = 502;
    $extraErr = [];
    if ($http === 401) {
        $clientStatus = 401;
        $msg = 'Chave DeepSeek inválida ou revogada (HTTP 401). Confira SF_DEEPSEEK_API_KEY em platform.deepseek.com.';
    } elseif ($http === 403) {
        $clientStatus = 403;
        $msg = 'Acesso negado pela API DeepSeek (HTTP 403).';
    } elseif ($http === 429) {
        $clientStatus = 429;
        if ($errCode === 'insufficient_quota') {
            $msg = 'Saldo ou cota DeepSeek insuficiente (HTTP 429). Recarregue créditos em platform.deepseek.com.';
        } else {
            $msg = 'Limite de uso da API DeepSeek (HTTP 429). Aguarde e tente de novo; confira limites na sua conta.';
        }
        if ($detail !== '' && (!defined('DEBUG_MODE') || !DEBUG_MODE)) {
            $snippet = function_exists('mb_substr') ? mb_substr($detail, 0, 200, 'UTF-8') : substr($detail, 0, 200);
            $len = function_exists('mb_strlen') ? mb_strlen($detail, 'UTF-8') : strlen($detail);
            if ($len > 200) {
                $snippet .= '…';
            }
            $msg .= ' Resposta: ' . $snippet;
        }
        if (is_array($response['error'] ?? null) && isset($response['error']['retry_after'])) {
            $ra = (int) $response['error']['retry_after'];
            if ($ra > 0 && $ra < 600) {
                $extraErr['retry_after_seconds'] = $ra;
            }
        }
    } elseif ($http === 503) {
        $clientStatus = 503;
        $msg = 'Serviço DeepSeek temporariamente indisponível (HTTP 503). Tente em instantes.';
    } else {
        $msg = 'DeepSeek retornou erro (HTTP ' . $http . '). Verifique a chave, o modelo e o saldo da conta.';
    }
    if (defined('DEBUG_MODE') && DEBUG_MODE && $detail !== '' && $http !== 429) {
        $msg .= ' Detalhe: ' . $detail;
    }
    if (defined('DEBUG_MODE') && DEBUG_MODE && $detail !== '' && $http === 429 && $errCode !== '') {
        $msg .= ' [code=' . $errCode . ']';
    }
    api_json_error($msg, $clientStatus, 'deepseek_http_error', $extraErr);
}

/**
 * @return array{0: int, 1: int} [prompt_tokens, completion_tokens] para sf_openai_log_usage
 */
function refuel_ocr_chat_usage_tokens(array $response): array
{
    $u = is_array($response['usage'] ?? null) ? $response['usage'] : [];

    return [(int) ($u['prompt_tokens'] ?? 0), (int) ($u['completion_tokens'] ?? 0)];
}

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_error('ID da empresa não encontrado', 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_method_not_allowed('Use POST.');
}

api_require_csrf_json();

$conn = getConnection();
$deepseekKey = function_exists('sf_deepseek_api_key') ? sf_deepseek_api_key() : '';
// Chave OpenAI: env/constante OU credencial por empresa salva no banco (UI Configurações).
$openaiKey = function_exists('sf_openai_key_resolved')
    ? sf_openai_key_resolved($conn, $empresa_id)
    : (function_exists('sf_openai_api_key') ? sf_openai_api_key() : '');
$enableTesseractFallback = !in_array(
    strtolower(trim((string) getenv('SF_REFUEL_TESSERACT_FALLBACK'))),
    ['0', 'false', 'no', 'off'],
    true
);

// Provedor de visão configurável por empresa (Gemini/OpenAI/OpenRouter/custom).
require_once __DIR__ . '/../includes/sf_vision_ai.php';
$visionCfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id) : null;

if ($visionCfg !== null) {
    $visionProvider = $visionCfg['provider'];
    $visionLabel = $visionCfg['label'];
    $key = $visionCfg['key'];
    $visionModel = $visionCfg['model'];
    $visionUrl = $visionCfg['url'];
    $visionSource = $visionCfg['provider'] . '-vision';
} elseif ($openaiKey !== '') {
    // Legado: chave OpenAI no banco/ambiente.
    $visionProvider = 'openai';
    $visionLabel = 'OpenAI';
    $key = $openaiKey;
    $visionModel = function_exists('sf_openai_vision_model_resolved')
        ? sf_openai_vision_model_resolved($conn, $empresa_id)
        : (function_exists('sf_openai_vision_model') ? sf_openai_vision_model() : 'gpt-4o-mini');
    $visionUrl = 'https://api.openai.com/v1/chat/completions';
    $visionSource = 'openai-vision';
} else {
    $visionProvider = 'deepseek';
    $visionLabel = 'DeepSeek';
    $key = $deepseekKey;
    $visionModel = function_exists('sf_deepseek_refuel_model') ? sf_deepseek_refuel_model() : 'deepseek-chat';
    $visionUrl = function_exists('sf_deepseek_chat_completions_url') ? sf_deepseek_chat_completions_url() : 'https://api.deepseek.com/v1/chat/completions';
    $visionSource = 'deepseek-vision';
}

$binary = '';
$mime = 'image/jpeg';

if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $binary = (string) file_get_contents($_FILES['image']['tmp_name']);
    $mime = strtolower((string) ($_FILES['image']['type'] ?? ''));
    if ($mime === '' || $mime === 'application/octet-stream') {
        $ext = strtolower(pathinfo((string) ($_FILES['image']['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        } else {
            $mime = 'image/jpeg';
        }
    }
} else {
    $rawBody = (string) file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    if (is_array($input) && !empty($input['image_base64'])) {
        $b64in = preg_replace('#^data:image/[^;]+;base64,#', '', (string) $input['image_base64']);
        $decoded = base64_decode($b64in, true);
        if ($decoded !== false && strlen($decoded) > 50) {
            $binary = $decoded;
            $mime = isset($input['mime_type']) && is_string($input['mime_type']) && $input['mime_type'] !== ''
                ? strtolower($input['mime_type'])
                : 'image/jpeg';
        }
    }
}

if ($binary === '') {
    api_json_error(
        'Envie a imagem no campo multipart "image" (foto ou PNG da 1ª página do PDF) ou "image_base64" no JSON.',
        400
    );
}

$maxBytes = 18 * 1024 * 1024;
if (strlen($binary) > $maxBytes) {
    api_json_error('Arquivo muito grande (máx. 18 MB).', 400);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    api_json_error('Use JPEG, PNG, WebP ou GIF.', 400);
}

[$binary, $mime] = refuel_ocr_preprocess_for_vision($binary, $mime);
[$binary, $mime] = refuel_ocr_downscale_for_vision($binary, $mime);

$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);
$model = $visionModel;

$system = 'Você transcreve fichas de transporte no Brasil. Responda somente JSON válido, sem markdown, sem texto fora do JSON. Não invente dados.';

$userText = <<<'TXT'
Na imagem, localize APENAS a tabela ou bloco rotulado como ABASTECIMENTO (colunas típicas: Data, KM, Posto, L ou litros, R$ ou valor).

Extraia somente linhas que são claramente um registro de abastecimento nessa tabela. Ignore: totais de frete, KM saída/chegada do cabeçalho, pedágio, estacionamento, "anotações gerais", valores soltos fora da tabela de abastecimento.

NUNCA preencha com suposições: se não ler com clareza data, km, posto, litros e valor da mesma linha, omita essa linha. Não use texto genérico como posto "Pequena" ou números copiados de outras colunas.

Responda com um ÚNICO JSON:
{"abastecimentos":[{"data":"dd/mm/aaaa","km":0,"posto":"nome","litros":0,"valor_total":0}]}

Aceitamos também array na raiz ou campo "rows" com o mesmo formato de objetos.

Regras de formato:
- "data": exatamente como na célula (ex.: 22/02/25 ou 22/02/2025).
- "km": hodômetro inteiro da coluna KM da linha; separador de milhar com ponto vira dígitos (84.971 → 84971).
- "litros" e "valor_total": números com ponto decimal (ex.: 428 e 2523.33).
- "posto": nome do posto como escrito.
- Se não houver linhas legíveis na tabela de abastecimento: {"abastecimentos":[]}.
TXT;

$jsonFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

$response = [];
$content = '';
$curlErr = '';
$rows = [];
$warnings = [];
$source = $visionSource;
$provider = $visionProvider;
$ocrText = '';
$deepseekFailure = false;
$deepseekFailureMsg = '';
$http = 0;
if ($key !== '') {
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
        'max_tokens' => 4096,
        'temperature' => 0.05,
    ];
    $url = $visionUrl;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, $jsonFlags),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);

    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $provLabel = $visionLabel;
    if ($raw === false || $raw === '') {
        $deepseekFailure = true;
        $deepseekFailureMsg = 'Falha ao contatar ' . $provLabel . ': ' . ($curlErr !== '' ? $curlErr : 'resposta vazia');
    } else {
        $response = json_decode($raw, true);
        if (!is_array($response)) {
            $deepseekFailure = true;
            $deepseekFailureMsg = 'Resposta inválida da API ' . $provLabel . '.';
        } elseif ($http < 200 || $http >= 300) {
            $deepseekFailure = true;
            // Extrai detalhe do erro do provedor (motivo real: cota, limite de taxa, etc.).
            // Alguns provedores (Gemini) embrulham em array: [{"error":{...}}].
            $errObj = $response['error'] ?? ($response[0]['error'] ?? null);
            $apiErrMsg = is_array($errObj) ? (string) ($errObj['message'] ?? '') : '';
            $apiErrType = is_array($errObj)
                ? (string) ($errObj['status'] ?? ($errObj['type'] ?? ($errObj['code'] ?? '')))
                : '';
            if ($http === 429) {
                $probe = strtolower($apiErrType . ' ' . $apiErrMsg);
                $isExhausted = preg_match('/quota|billing|exhaust|insufficient|free tier|per day|requests per/', $probe) === 1;
                $detail = $apiErrMsg !== '' ? ' Detalhe do provedor: ' . $apiErrMsg : '';
                if ($isExhausted) {
                    $deepseekFailureMsg = $provLabel . ': cota/créditos esgotados ou indisponíveis (HTTP 429). Verifique cota/billing da conta ' . $provLabel . '.' . $detail;
                } else {
                    $deepseekFailureMsg = $provLabel . ': limite de requisições (HTTP 429). Aguarde e tente novamente.' . $detail;
                }
            } else {
                $deepseekFailureMsg = $provLabel . ' retornou erro HTTP ' . $http . ($apiErrMsg !== '' ? ': ' . $apiErrMsg : '.');
            }
        } else {
            $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
            if ($content === '') {
                $deepseekFailure = true;
                $deepseekFailureMsg = $provLabel . ' não retornou conteúdo.';
            }
        }
    }
    if (!$deepseekFailure) {
        $pair = refuel_ocr_chat_usage_tokens($response);
        try {
            sf_openai_log_usage($conn, $empresa_id, $model, $pair[0], $pair[1]);
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('deepseek_ocr_usage log: ' . $e->getMessage());
            }
        }
    }
} else {
    $deepseekFailure = true;
    $deepseekFailureMsg = 'Nenhuma chave de IA de visão configurada (SF_OPENAI_API_KEY ou SF_DEEPSEEK_API_KEY).';
}

if (!$deepseekFailure) {
    // Alguns modelos (ex.: Gemini) envolvem o JSON em cerca markdown ```json … ```
    $contentClean = trim($content);
    if (strncmp($contentClean, '```', 3) === 0) {
        $contentClean = preg_replace('/^```[a-zA-Z]*\s*/', '', $contentClean);
        $contentClean = preg_replace('/\s*```$/', '', $contentClean);
    }
    $parsed = json_decode($contentClean, true);
    if (!is_array($parsed)) {
        $deepseekFailure = true;
        $deepseekFailureMsg = 'JSON retornado pela IA inválido.';
    } else {
        $list = $parsed['abastecimentos'] ?? $parsed['rows'] ?? null;
        if (!is_array($list) && isset($parsed[0]) && is_array($parsed[0])) {
            $list = $parsed;
        }
        if (!is_array($list)) {
            $deepseekFailure = true;
            $deepseekFailureMsg = 'A IA não retornou a lista de abastecimentos.';
        } else {
            foreach ($list as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $normalized = refuel_ocr_normalize_openai_item($item);
                if ($normalized !== null) {
                    if ($normalized['valorLitro'] < 1.5 || $normalized['valorLitro'] > 12.5) {
                        $warnings[] = 'Linha "' . $normalized['_label'] . '" com valor/litro fora da faixa típica. Revise litros e valor total.';
                    }
                    $rows[] = $normalized;
                }
            }
        }
    }
}

$provLabel = $visionProvider === 'openai' ? 'OpenAI' : 'DeepSeek';
$isQuota = $deepseekFailure && (
    stripos($deepseekFailureMsg, 'cota') !== false
    || stripos($deepseekFailureMsg, 'crédito') !== false
    || stripos($deepseekFailureMsg, 'billing') !== false
);

// 429 de cota esgotada: não adianta OCR local nem re-tentar — avisa direto.
if ($deepseekFailure && $http === 429 && $isQuota) {
    api_json_error($deepseekFailureMsg, 402, 'insufficient_quota');
}

if ($deepseekFailure && $enableTesseractFallback) {
    $tess = refuel_ocr_try_tesseract_fallback($binary);
    if ($tess['rows'] !== []) {
        $rows = $tess['rows'];
        $ocrText = $tess['text'];
        $source = 'tesseract-server';
        $provider = 'local-ocr';
        $warnings[] = 'Fallback local ativado (Tesseract) porque ' . $provLabel . ' falhou: ' . $deepseekFailureMsg;
    } else {
        $warnings[] = $provLabel . ' falhou e o fallback Tesseract não encontrou linhas válidas.';
        if ($tess['error'] !== '') {
            $warnings[] = 'Tesseract erro: ' . $tess['error'];
        }
    }
}

if ($deepseekFailure && $rows === []) {
    if ($key === '') {
        api_json_error(
            'Leitura por IA indisponível (configure SF_OPENAI_API_KEY ou SF_DEEPSEEK_API_KEY) e o OCR local não encontrou linhas válidas.',
            503,
            'vision_not_configured'
        );
    }
    // 429 de limite de taxa (transitório): devolve 429 para o front re-tentar com espera.
    if ($http === 429) {
        api_json_send([
            'success' => false,
            'error' => $deepseekFailureMsg,
            'retry_after_seconds' => 30,
        ], 429);
    }
    api_json_error('Falha na leitura da ficha. Detalhe: ' . $deepseekFailureMsg, 502, 'ocr_read_failed');
}

api_json_send([
    'success' => true,
    'rows' => $rows,
    'source' => $source,
    'model' => $model,
    'provider' => $provider,
    'warnings' => $warnings,
    'ocr_text' => $ocrText,
]);
