<?php
/**
 * Consulta CNPJ na BrasilAPI (gratuita, sem chave).
 * https://brasilapi.com.br/docs#tag/CNPJ
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/doc_validators.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$cnpj = preg_replace('/\D/', '', (string) ($_GET['cnpj'] ?? $_POST['cnpj'] ?? ''));
if (strlen($cnpj) !== 14) {
    echo json_encode(['success' => false, 'message' => 'CNPJ deve ter 14 dígitos (pontos, barra e traço são ignorados).']);
    exit;
}
if (!doc_validar_cnpj($cnpj)) {
    echo json_encode(['success' => false, 'message' => 'CNPJ inválido (dígitos verificadores).']);
    exit;
}

function brasilapi_log_line(string $msg): void {
    $path = __DIR__ . '/../logs/brasilapi.log';
    @file_put_contents($path, date('c') . ' ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}

$url = 'https://brasilapi.com.br/api/cnpj/v1/' . $cnpj;

/**
 * @return array{ok: bool, status: int, body: string}
 */
function brasilapi_http_get(string $url): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: sistema-frotas/1.0',
            ],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false) {
            return ['ok' => false, 'status' => $status ?: 0, 'body' => ''];
        }
        return ['ok' => true, 'status' => $status, 'body' => (string) $body];
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 25,
            'header' => "Accept: application/json\r\nUser-Agent: sistema-frotas/1.0\r\n",
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'status' => 0, 'body' => ''];
    }
    $status = 200;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $status = (int) $m[1];
    }
    return ['ok' => true, 'status' => $status, 'body' => (string) $body];
}

/**
 * Até 3 tentativas com backoff simples (429/5xx/timeout).
 *
 * @return array{ok: bool, status: int, body: string}
 */
function brasilapi_http_get_retry(string $url, int $max = 3): array {
    $delayUs = 400000;
    $last = ['ok' => false, 'status' => 0, 'body' => ''];
    for ($i = 0; $i < $max; $i++) {
        $last = brasilapi_http_get($url);
        $st = (int) ($last['status'] ?? 0);
        $retry = (!$last['ok'] || $st === 0 || $st === 429 || ($st >= 500 && $st < 600));
        if (!$retry) {
            return $last;
        }
        if ($i < $max - 1) {
            brasilapi_log_line('retry ' . ($i + 1) . ' url=' . $url . ' status=' . $st);
            usleep($delayUs);
            $delayUs = (int) min(3000000, $delayUs * 1.5);
        }
    }
    return $last;
}

$res = brasilapi_http_get_retry($url, 3);
if (!$res['ok'] || $res['status'] === 0) {
    brasilapi_log_line('fail cnpj=' . $cnpj . ' connect/status=0');
    echo json_encode(['success' => false, 'message' => 'Não foi possível conectar à BrasilAPI. Tente de novo em instantes.']);
    exit;
}

if ($res['status'] === 404) {
    brasilapi_log_line('404 cnpj=' . $cnpj);
    echo json_encode(['success' => false, 'message' => 'CNPJ não encontrado na base pública.']);
    exit;
}

if ($res['status'] !== 200) {
    brasilapi_log_line('http ' . $res['status'] . ' cnpj=' . $cnpj);
    echo json_encode(['success' => false, 'message' => 'BrasilAPI retornou erro HTTP ' . $res['status'] . '.']);
    exit;
}

$data = json_decode($res['body'], true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Resposta inválida da BrasilAPI.']);
    exit;
}

if (!empty($data['type']) && $data['type'] === 'error') {
    $msg = $data['message'] ?? 'Erro na consulta.';
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

/**
 * Formata telefone BR (10 ou 11 dígitos).
 */
function cnpj_brasilapi_format_telefone_digits(string $digits): string {
    $digits = preg_replace('/\D/', '', $digits);
    if (strlen($digits) === 11) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7));
    }
    if (strlen($digits) === 10) {
        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6));
    }
    return '';
}

/**
 * Monta telefone a partir dos campos da BrasilAPI (aceita DDD+aparelho separados ou número colado em ddd_telefone_*).
 */
function cnpj_brasilapi_telefone_from_row(array $row, string $dddKey, string $telKey): string {
    $ddd = trim((string) ($row[$dddKey] ?? ''));
    $tel = trim((string) ($row[$telKey] ?? ''));
    $joined = preg_replace('/\D/', '', $ddd . $tel);
    if (strlen($joined) >= 10 && strlen($joined) <= 11) {
        $fmt = cnpj_brasilapi_format_telefone_digits($joined);
        if ($fmt !== '') {
            return $fmt;
        }
    }
    if ($ddd !== '' && $tel !== '') {
        return '(' . preg_replace('/\D/', '', $ddd) . ') ' . $tel;
    }
    if ($tel !== '') {
        return $tel;
    }
    return '';
}

function cnpj_brasilapi_pick_telefone(array $data): string {
    $pairs = [
        ['ddd_telefone_1', 'telefone_1'],
        ['ddd_telefone_2', 'telefone_2'],
    ];
    foreach ($pairs as $p) {
        $t = cnpj_brasilapi_telefone_from_row($data, $p[0], $p[1]);
        if ($t !== '') {
            return $t;
        }
    }
    $fax = trim((string) ($data['ddd_fax'] ?? ''));
    $faxDigits = preg_replace('/\D/', '', $fax);
    if (strlen($faxDigits) >= 10 && strlen($faxDigits) <= 11) {
        $fmt = cnpj_brasilapi_format_telefone_digits($faxDigits);
        if ($fmt !== '') {
            return $fmt;
        }
    }
    return '';
}

/**
 * @param mixed $v
 */
function cnpj_brasilapi_is_true($v): bool {
    return $v === true || $v === 1 || $v === '1' || $v === 'S' || $v === 's' || $v === 'true';
}

/**
 * Regime tributário legível para o cadastro (MEI / Simples / forma do histórico mais recente).
 */
function cnpj_brasilapi_regime_texto(array $data): string {
    if (cnpj_brasilapi_is_true($data['opcao_pelo_mei'] ?? false)) {
        return 'MEI';
    }
    if (cnpj_brasilapi_is_true($data['opcao_pelo_simples'] ?? false)) {
        return 'Simples Nacional';
    }
    $lista = $data['regime_tributario'] ?? null;
    if (!is_array($lista) || count($lista) === 0) {
        return '';
    }
    $melhor = null;
    $anoMax = -1;
    foreach ($lista as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ano = isset($item['ano']) ? (int) $item['ano'] : 0;
        $forma = trim((string) ($item['forma_de_tributacao'] ?? ''));
        if ($forma === '') {
            continue;
        }
        if ($ano >= $anoMax) {
            $anoMax = $ano;
            $melhor = $forma;
        }
    }
    if ($melhor === null || $melhor === '') {
        return '';
    }
    $melhor = strtolower($melhor);
    return function_exists('mb_convert_case')
        ? mb_convert_case($melhor, MB_CASE_TITLE, 'UTF-8')
        : ucwords($melhor);
}

$munIbge = $data['codigo_municipio_ibge'] ?? null;
if ($munIbge !== null && $munIbge !== '') {
    $munIbge = str_pad(preg_replace('/\D/', '', (string) $munIbge), 7, '0', STR_PAD_LEFT);
    if (strlen($munIbge) !== 7) {
        $munIbge = '';
    }
} else {
    $munIbge = '';
}

$cep = preg_replace('/\D/', '', (string) ($data['cep'] ?? ''));

$tel = cnpj_brasilapi_pick_telefone($data);

$cnae = (string) ($data['cnae_fiscal'] ?? '');
$cnaeDesc = (string) ($data['cnae_fiscal_descricao'] ?? '');
$cnaeResumo = '';
if ($cnae !== '' || $cnaeDesc !== '') {
    $cnaeResumo = trim('CNAE ' . $cnae . ($cnaeDesc !== '' ? ' — ' . $cnaeDesc : ''));
    if (strlen($cnaeResumo) > 200) {
        $cnaeResumo = substr($cnaeResumo, 0, 197) . '...';
    }
}

$situacao = trim((string) ($data['descricao_situacao_cadastral'] ?? ''));
$fantasia = trim((string) ($data['nome_fantasia'] ?? ''));

$regimeForm = cnpj_brasilapi_regime_texto($data);

$ie = trim((string) ($data['inscricao_estadual'] ?? $data['inscricao_estadual_ie'] ?? ''));
$im = trim((string) ($data['inscricao_municipal'] ?? $data['inscricao_municipal_im'] ?? ''));

$out = [
    'nome' => trim((string) ($data['razao_social'] ?? '')),
    'nome_fantasia' => $fantasia,
    'endereco' => trim((string) ($data['logradouro'] ?? '')),
    'numero' => trim((string) ($data['numero'] ?? '')),
    'complemento' => trim((string) ($data['complemento'] ?? '')),
    'bairro' => trim((string) ($data['bairro'] ?? '')),
    'cep' => $cep,
    'cidade' => trim((string) ($data['municipio'] ?? '')),
    'uf' => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string) ($data['uf'] ?? '')), 0, 2)),
    'codigo_municipio_ibge' => $munIbge,
    'telefone' => $tel,
    'email' => trim((string) ($data['email'] ?? '')),
    'regime' => $regimeForm,
    'cnae_resumo' => $cnaeResumo,
    'regime_sugerido' => $cnaeResumo,
    'inscricao_estadual' => $ie,
    'inscricao_municipal' => $im,
    'situacao_cadastral' => $situacao,
];

$sitTrim = trim($situacao);
$sitUp = function_exists('mb_strtoupper')
    ? mb_strtoupper($sitTrim, 'UTF-8')
    : strtoupper($sitTrim);
if ($sitTrim === '') {
    $out['hint'] = '';
} elseif ($sitUp === 'ATIVA') {
    $out['hint'] = 'Situação cadastral ativa na Receita Federal.';
} else {
    $out['hint'] = 'Situação cadastral não é ativa: ' . $sitTrim . '.';
}

echo json_encode([
    'success' => true,
    'data' => $out,
    'fonte' => 'BrasilAPI (https://brasilapi.com.br)',
]);
