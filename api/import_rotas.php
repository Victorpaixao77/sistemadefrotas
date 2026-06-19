<?php
// Endpoint to import routes/abastecimentos/despesas from Excel
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
configure_session();
if (session_status() === PHP_SESSION_NONE) session_start();
require_authentication();
header('Content-Type: application/json');

// Erros graves viram exceção; avisos/deprecações só entram no log (PhpSpreadsheet + PHP 8.x em produção geram muitos E_DEPRECATED).
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    $light = [E_WARNING, E_NOTICE, E_USER_WARNING, E_USER_NOTICE, E_DEPRECATED, E_USER_DEPRECATED, E_STRICT];
    if (in_array($severity, $light, true)) {
        error_log("import_rotas.php [$severity] $message in $file:$line");
        return true;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function($e) {
    error_log('import_rotas.php exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error', 'message' => $e->getMessage()]);
    exit;
});

// Check upload
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'Arquivo não enviado ou erro no upload']);
    exit;
}

$tmp = $_FILES['file']['tmp_name'];
$name = $_FILES['file']['name'];
$commit = isset($_POST['commit']) && $_POST['commit'] == '1';

// Try to load with PhpSpreadsheet if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Erro ao ler o Excel: ' . $e->getMessage()]);
        exit;
    }
    $sheets = $spreadsheet->getSheetNames();
    $result = ['sheets' => [], 'sheet_skips' => []];
    foreach ($sheets as $sheetName) {
        if (import_rotas_sheet_should_skip((string) $sheetName)) {
            $result['sheet_skips'][$sheetName] = 'aba_nao_eh_envelope_viagem';
            $result['sheets'][$sheetName] = [];
            continue;
        }
        $sheet = $spreadsheet->getSheetByName($sheetName);
        $rows = $sheet->toArray(null, true, true, true);
        // Normalize: skip empty rows
        $clean = [];
        foreach ($rows as $r) {
            $allEmpty = true;
            foreach ($r as $c) {
                if ($c !== null && trim((string) $c) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if (!$allEmpty) {
                $clean[] = $r;
            }
        }
        $headerIdx = import_rotas_find_best_header_row($clean);
        if ($headerIdx === null) {
            $result['sheet_skips'][$sheetName] = 'cabeçalho_envelope_nao_identificado';
            $result['sheets'][$sheetName] = [];
            continue;
        }
        $headers = $clean[$headerIdx];
        $mapped = [];
        $n = count($clean);
        for ($i = $headerIdx + 1; $i < $n; $i++) {
            $row = $clean[$i];
            // Novo mês / bloco: linha que volta a ser cabeçalho de colunas
            if (import_rotas_route_header_score($row) >= 10) {
                $headers = $row;
                continue;
            }
            if (!import_rotas_row_looks_like_trip($row, $headers)) {
                continue;
            }
            $mapped[] = mapRowToEntities($sheetName, $headers, $row);
        }
        $result['sheets'][$sheetName] = $mapped;
    }
    // If commit requested, try to persist (basic implementation)
    if ($commit) {
        $conn = getConnection();
        $persisted = persistPreview($conn, $result['sheets']);
        $result['persisted'] = $persisted;
    }
    $result['ano_referencia_usado'] = import_rotas_reference_year();
    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    if (defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
        $jsonFlags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
    }
    $json = json_encode($result, $jsonFlags);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Falha ao serializar resposta', 'message' => json_last_error_msg()]);
        exit;
    }
    echo $json;
    exit;
}

// Fallback: try to parse as CSV (first sheet only)
$f = fopen($tmp, 'r');
if ($f === false) { http_response_code(500); echo json_encode(['error'=>'Falha ao abrir arquivo']); exit; }
$rows = [];
while (($line = fgetcsv($f, 0, ',')) !== false) $rows[] = $line;
fclose($f);
echo json_encode(['sheets' => ['sheet1' => $rows], 'warning' => 'PhpSpreadsheet não encontrado; usando parser simplificado']);

// --- helper functions ---

/**
 * Pontua uma linha como se fosse linha de cabeçalho de envelope (LOGÍSTICA, N.VIAG., KM, DIESEL…).
 * Linhas de viagem com valores costumam pontuar 0–2; cabeçalhos reais 8+.
 */
function import_rotas_route_header_score(array $row): int {
    $score = 0;
    foreach ($row as $value) {
        $trim = trim((string) $value);
        if ($trim === '') {
            continue;
        }
        $v = function_exists('mb_strtolower') ? mb_strtolower($trim, 'UTF-8') : strtolower($trim);
        if (preg_match('/log[ií]stica/ui', $trim)) {
            $score += 3;
        }
        if (preg_match('/n\.?\s*viag/i', $v)) {
            $score += 2;
        }
        if (strpos($v, 'data') !== false && strpos($v, 'valid') === false) {
            $score += 1;
        }
        if (strpos($v, 'km') !== false && strpos($v, 'inicial') !== false) {
            $score += 1;
        }
        if (strpos($v, 'km') !== false && (strpos($v, 'final') !== false || strpos($v, 'chegada') !== false)) {
            $score += 1;
        }
        if (strpos($v, 'litros') !== false) {
            $score += 1;
        }
        if (strpos($v, 'diesel') !== false) {
            $score += 1;
        }
        if (strpos($v, 'rodado') !== false) {
            $score += 1;
        }
        if (strpos($v, 'bruto') !== false || strpos($v, 'frete') !== false) {
            $score += 1;
        }
    }
    return $score;
}

/** Índice da primeira linha de cabeçalho de envelope nas primeiras linhas da aba, ou null. */
function import_rotas_find_best_header_row(array $clean) {
    $lim = min(40, count($clean));
    if ($lim < 1) {
        return null;
    }
    $bestI = null;
    $bestS = -1;
    for ($i = 0; $i < $lim; $i++) {
        $s = import_rotas_route_header_score($clean[$i]);
        if ($s > $bestS) {
            $bestS = $s;
            $bestI = $i;
        }
    }
    if ($bestS < 8 || $bestI === null) {
        return null;
    }
    return $bestI;
}

/**
 * Coluna DATA da linha com valor que normaliza para data (inclui serial Excel); texto vazio ou “Percentual” falha.
 */
function import_rotas_row_has_parseable_route_date(array $row, array $headers) {
    $m = guessHeaderMap($headers);
    if (!isset($m['data'])) {
        return false;
    }
    $col = $m['data'];
    if (!isset($row[$col])) {
        return false;
    }
    $raw = $row[$col];
    if ($raw === null) {
        return false;
    }
    if (is_string($raw)) {
        $t = trim($raw);
        if ($t === '') {
            return false;
        }
        $dsl = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
        if (strpos($dsl, 'percentual') !== false || strpos($dsl, 'viagens') !== false) {
            return false;
        }
    }
    return import_rotas_normalize_route_datetime($raw) !== null;
}

/** Linha de dado que parece viagem: data válida + logística; N.VIAG. numérico quando existir no cabeçalho. */
function import_rotas_row_looks_like_trip(array $row, array $headers) {
    $m = guessHeaderMap($headers);
    if (isset($m['n_viagem'])) {
        $nv = getCellValue($row, $m['n_viagem']);
        if ($nv === null || $nv === '' || !preg_match('/^\s*\d+\s*$/', $nv)) {
            return false;
        }
    }
    if (!import_rotas_row_has_parseable_route_date($row, $headers)) {
        return false;
    }
    $logRaw = isset($m['logistica']) ? getCellValue($row, $m['logistica']) : null;
    if ($logRaw === null || trim($logRaw) === '') {
        return false;
    }
    $logTrim = trim($logRaw);
    if (preg_match('/^#(div\/0!|ref!|num!|n\/a)/i', $logTrim)) {
        return false;
    }
    return true;
}

/**
 * Abas de caixa / entregas (não são envelope de viagem). Inclui nome colado tipo ABMENTREGA, SFAENTREGA.
 */
function import_rotas_sheet_should_skip(string $sheetName): bool {
    $norm = preg_replace('/\s+/u', ' ', trim($sheetName));
    $lower = function_exists('mb_strtolower') ? mb_strtolower($norm, 'UTF-8') : strtolower($norm);
    $compact = preg_replace('/\s+/u', '', $lower);
    if (preg_match('/^(abm|sfa)\s+entrega[s]?$/iu', $norm)) {
        return true;
    }
    if (preg_match('/\b(abm|sfa)\s+entrega[s]?\b/iu', $norm)) {
        return true;
    }
    if (preg_match('/^(abm|sfa)entrega[s]?$/iu', $compact)) {
        return true;
    }
    if (strpos($lower, 'entregas') !== false) {
        return true;
    }
    if (preg_match('/entrega\s|lançamentos|lancamentos|fluxo\s+caixa|movimenta(ç|c)ao\s+caixa/i', $sheetName)) {
        return true;
    }
    return false;
}

/** Coluna Excel (A, B, …, AA) → índice 1-based para ordenar cabeçalhos. */
function import_rotas_excel_column_to_index($col) {
    $col = strtoupper(trim((string) $col));
    if ($col === '') {
        return 0;
    }
    if (class_exists(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::class)) {
        try {
            return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($col);
        } catch (\Throwable $e) {
        }
    }
    $n = 0;
    $len = strlen($col);
    for ($i = 0; $i < $len; $i++) {
        $c = $col[$i];
        if ($c < 'A' || $c > 'Z') {
            return 0;
        }
        $n = $n * 26 + (ord($c) - 64);
    }
    return $n;
}

function guessHeaderMap(array $headers) {
    $map = [];
    $pctBareCols = [];
    foreach ($headers as $col => $value) {
        $trim = trim((string) $value);
        $v = function_exists('mb_strtolower') ? mb_strtolower($trim, 'UTF-8') : strtolower($trim);
        if (preg_match('/log[ií]stica/ui', $trim)) {
            $map['logistica'] = $col;
        }
        if (preg_match('/n\.?\s*viag/i', $v)) {
            $map['n_viagem'] = $col;
        }
        if (strpos($v, 'data') !== false && strpos($v, 'valid') === false) {
            $map['data'] = $col;
        }
        if (strpos($v, 'km') !== false && strpos($v, 'inicial') !== false) {
            $map['km_inicio'] = $col;
        }
        if (strpos($v, 'km') !== false && (strpos($v, 'final') !== false || strpos($v, 'chegada') !== false)) {
            $map['km_final'] = $col;
        }
        if (strpos($v, 'rodado') !== false || (strpos($v, 'dist') !== false && strpos($v, 'km') === false)) {
            $map['distancia'] = $col;
        }
        // Frete da viagem: preferir coluna FRETE; BRUTO só se não houver FRETE (evita misturar valores).
        if (strpos($v, 'frete') !== false) {
            $map['frete'] = $col;
        }
        if (strpos($v, 'comiss') !== false && strpos($v, '%') === false) {
            $map['comissao'] = $col;
        }
        if (preg_match('/%/u', $trim) && (stripos($trim, 'comiss') !== false || stripos($trim, 'motor') !== false || preg_match('/\bpct\b/i', $trim))) {
            $map['pct_motorista'] = $col;
        }
        if ($trim === '%' || $v === '%') {
            $pctBareCols[] = $col;
        }
        if (strpos($v, 'peso') !== false) {
            $map['peso'] = $col;
        }
        if (strpos($v, 'litros') !== false || preg_match('/^l( |$)/i', $v)) {
            $map['litros'] = $col;
        }
        if (strpos($v, 'motorista') !== false || strpos($v, 'condutor') !== false) {
            $map['motorista'] = $col;
        }
        if (strpos($v, 'empresa') !== false || strpos($v, 'contratante') !== false || strpos($v, 'prestador') !== false) {
            $map['empresa'] = $col;
        }
        if (strpos($v, 'ofic') !== false || strpos($v, 'elétrica') !== false || strpos($v, 'eletrica') !== false || strpos($v, 'mecanic') !== false) {
            $map['oficina'] = $col;
        }
        if (strpos($v, 'borr') !== false || strpos($v, 'borracharia') !== false) {
            $map['borracharia'] = $col;
        }
        if (strpos($v, 'cxnha') !== false || strpos($v, 'caixinha') !== false) {
            $map['caixinha'] = $col;
        }
        if (strpos($v, 'outros') !== false) {
            $map['outros'] = $col;
        }
        // Abastecimento: DIESEL / combustível total ≠ valor por litro (evita VLR LITRO como valor_total).
        if (strpos($v, 'diesel') !== false || (strpos($v, 'combust') !== false && strpos($v, 'litro') === false)) {
            $map['valor_abast'] = $col;
        }
        if ((strpos($v, 'vlr') !== false || strpos($v, 'valor') !== false) && strpos($v, 'litro') !== false) {
            $map['valor_litro'] = $col;
        }
        if (!isset($map['valor_abast']) && (strpos($v, 'valor') !== false || strpos($v, 'vlr') !== false) && strpos($v, 'litro') === false) {
            $map['valor'] = $col;
        }
        if (strpos($v, 'pedag') !== false || strpos($v, 'pedágio') !== false) {
            $map['pedagios'] = $col;
        }
    }
    if (!isset($map['frete'])) {
        foreach ($headers as $col => $value) {
            $v = function_exists('mb_strtolower') ? mb_strtolower(trim((string) $value), 'UTF-8') : strtolower(trim((string) $value));
            if (strpos($v, 'bruto') !== false) {
                $map['frete'] = $col;
                break;
            }
        }
    }
    if (!isset($map['pct_motorista']) && isset($map['comissao']) && $pctBareCols) {
        $cCom = import_rotas_excel_column_to_index($map['comissao']);
        $best = null;
        $bestIdx = PHP_INT_MAX;
        foreach ($pctBareCols as $pc) {
            $idx = import_rotas_excel_column_to_index($pc);
            if ($idx > $cCom && $idx < $bestIdx) {
                $bestIdx = $idx;
                $best = $pc;
            }
        }
        if ($best !== null) {
            $map['pct_motorista'] = $best;
        } elseif (count($pctBareCols) === 1) {
            $map['pct_motorista'] = $pctBareCols[0];
        }
    }
    return $map;
}

function mapRowToEntities($sheetName, $headers, $row) {
    // headers: associative A=>value, row: A=>value
    $hmap = guessHeaderMap($headers);
    $r = [];
    // Parse logística (descricao carga + origem/destino)
    $log_text = getCellValue($row, $hmap['logistica'] ?? null);
    $log_parsed = parseLogistica($log_text);
    $r['rota'] = [
        'placa' => $sheetName,
        'data_rota' => getCellValue($row, $hmap['data'] ?? null),
        'km_saida' => getCellValue($row, $hmap['km_inicio'] ?? null),
        'km_chegada' => getCellValue($row, $hmap['km_final'] ?? null),
        'distancia_km' => getCellValue($row, $hmap['distancia'] ?? null),
        'frete' => getCellValue($row, $hmap['frete'] ?? null),
        'comissao' => getCellValue($row, $hmap['comissao'] ?? null),
        'peso' => getCellValue($row, $hmap['peso'] ?? null),
        'observacoes' => getCellValue($row, $hmap['empresa'] ?? null),
        'motorista' => getCellValue($row, $hmap['motorista'] ?? null),
        'pct_comissao_motorista' => getCellValue($row, $hmap['pct_motorista'] ?? null),
        'descricao_carga' => $log_parsed['descricao'] ?? null,
        'origem' => $log_parsed['origem'] ?? null,
        'origem_uf' => $log_parsed['origem_uf'] ?? null,
        'destino' => $log_parsed['destino'] ?? null,
        'destino_uf' => $log_parsed['destino_uf'] ?? null,
    ];
    // ABASTECIMENTO
    $r['abastecimento'] = [
        'litros' => getCellValue($row, $hmap['litros'] ?? null),
        'valor_total' => getCellValue($row, $hmap['valor_abast'] ?? $hmap['valor'] ?? null),
        'valor_litro' => getCellValue($row, $hmap['valor_litro'] ?? null),
        'km_atual' => getCellValue($row, $hmap['km_final'] ?? null),
        'data' => getCellValue($row, $hmap['data'] ?? null),
    ];
    // DESPESAS (best-effort)
    $r['despesas'] = [
        'pedagios' => getCellValue($row, $hmap['pedagios'] ?? null),
        'borracharia' => getCellValue($row, $hmap['borracharia'] ?? null),
        'oficina' => getCellValue($row, $hmap['oficina'] ?? null),
        'caixinha' => getCellValue($row, $hmap['caixinha'] ?? null),
        'outros' => getCellValue($row, $hmap['outros'] ?? null),
    ];
    return $r;
}

function getCellValue($row, $col) {
    if ($col === null) return null;
    return isset($row[$col]) ? trim((string)$row[$col]) : null;
}

/**
 * Número decimal a partir de texto (planilha BR 9.000,00 ou export US 5,968.80).
 *
 * @param mixed $raw
 * @return float|null
 */
function import_rotas_parse_decimal($raw) {
    if ($raw === null) {
        return null;
    }
    if (is_int($raw) || is_float($raw)) {
        return (float) $raw;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    $s = str_replace(["\xc2\xa0", ' '], '', $s);
    $s = preg_replace('/[%R\$€£]/u', '', $s);
    $s = trim($s);
    if ($s === '' || strcasecmp($s, 'null') === 0) {
        return null;
    }
    $hasComma = strpos($s, ',') !== false;
    $hasDot = strpos($s, '.') !== false;
    if ($hasComma && $hasDot) {
        $lastComma = strrpos($s, ',');
        $lastDot = strrpos($s, '.');
        if ($lastComma > $lastDot) {
            // BR: 1.735.672,00
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } else {
            // US: 126,271.00
            $s = str_replace(',', '', $s);
        }
    } elseif ($hasComma) {
        if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
    } elseif ($hasDot) {
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }
    }
    if ($s === '' || !is_numeric($s)) {
        return null;
    }
    return (float) $s;
}

/** @param mixed $raw @return float|null Percentual (ex.: 11% → 11,0) */
function import_rotas_parse_percent($raw) {
    if ($raw === null || $raw === '') {
        return null;
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    $hadPct = strpos($s, '%') !== false;
    $s = str_replace('%', '', $s);
    $n = import_rotas_parse_decimal($s);
    if ($n === null) {
        return null;
    }
    if (!$hadPct && $n > 0 && $n <= 1) {
        return round($n * 100, 4);
    }
    return round($n, 4);
}

/**
 * Odômetro / KM inteiro (evita 1265: INT não aceita "50.000" como texto nem milhar BR mal interpretado).
 *
 * @param mixed $raw
 * @return int|null
 */
function import_rotas_parse_integer_km($raw) {
    if ($raw === null || $raw === '') {
        return null;
    }
    if (is_int($raw)) {
        return min($raw, 999999999);
    }
    if (is_float($raw)) {
        return min((int) round($raw), 999999999);
    }
    $s = trim((string) $raw);
    $s = str_replace(["\xc2\xa0", ' '], '', $s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d+$/', $s)) {
        return min((int) $s, 999999999);
    }
    if (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        return min((int) str_replace('.', '', $s), 999999999);
    }
    if (preg_match('/^\d{1,3}(,\d{3})+$/', $s)) {
        return min((int) str_replace(',', '', $s), 999999999);
    }
    $n = import_rotas_parse_decimal($s);
    if ($n === null) {
        return null;
    }
    return min((int) round($n), 999999999);
}

/** @param mixed $raw @return float|null Arredondado para colunas DECIMAL(10,2) */
function import_rotas_parse_decimal_money($raw) {
    $n = import_rotas_parse_decimal($raw);
    if ($n === null) {
        return null;
    }
    return round($n, 2);
}

/**
 * Ano usado quando a célula traz só dia e mês (ex.: 6-Jan): POST ano_referencia, senão 20xx no nome do arquivo,
 * senão ano anterior ao atual (evita gravar tudo em 2026 ao importar planilha de 2025 sem ano no nome).
 *
 * @return int
 */
function import_rotas_reference_year() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (!empty($_POST['ano_referencia']) && $_POST['ano_referencia'] !== '') {
        $y = (int) $_POST['ano_referencia'];
        if ($y >= 1990 && $y <= 2100) {
            return $cached = $y;
        }
    }
    $name = isset($_FILES['file']['name']) ? (string) $_FILES['file']['name'] : '';
    if ($name !== '' && preg_match_all('/\b(19|20)\d{2}\b/', $name, $mm)) {
        $last = (int) end($mm[0]);
        if ($last >= 1990 && $last <= 2100) {
            return $cached = $last;
        }
    }
    return $cached = max(2000, (int) date('Y') - 1);
}

/** @param mixed $raw @return string|null Datetime MySQL Y-m-d H:i:s */
function import_rotas_normalize_route_datetime($raw) {
    if ($raw === null) {
        return null;
    }
    if ($raw instanceof \DateTimeInterface) {
        return $raw->format('Y-m-d H:i:s');
    }
    if (is_int($raw) || is_float($raw)) {
        $n = (float) $raw;
        if ($n > 20000 && $n < 80000 && class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
    $s = trim((string) $raw);
    if ($s === '') {
        return null;
    }
    static $monAbbr = null;
    if ($monAbbr === null) {
        $monAbbr = [
            'jan' => 1, 'feb' => 2, 'fev' => 2, 'mar' => 3, 'apr' => 4, 'abr' => 4,
            'may' => 5, 'mai' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'ago' => 8,
            'sep' => 9, 'set' => 9, 'out' => 10, 'oct' => 10, 'nov' => 11, 'dez' => 12,
            'dec' => 12,
        ];
    }
    if (preg_match('#^(\d{1,2})[/\-\.\s]+([A-Za-zÀ-ÿ]{3,9})(?:[/\-\.\s]+(\d{2,4}))?\s*$#u', $s, $m)) {
        $day = (int) $m[1];
        $letters = preg_replace('/[^A-Za-zÀ-ÿ]/u', '', $m[2]);
        $key = strtolower(substr($letters, 0, 3));
        if ($letters !== '' && isset($monAbbr[$key])) {
            $month = $monAbbr[$key];
            $year = (isset($m[3]) && $m[3] !== '') ? (int) $m[3] : import_rotas_reference_year();
            if ($year < 100) {
                $year += 2000;
            }
            if ($day >= 1 && $day <= 31 && $month >= 1 && $month <= 12) {
                return sprintf('%04d-%02d-%02d 08:00:00', $year, $month, $day);
            }
        }
    }
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}:\d{2}(?::\d{2})?))?$/', $s, $m)) {
        $t = isset($m[2]) && $m[2] !== '' ? $m[2] : '08:00:00';
        if (substr_count($t, ':') === 1) {
            $t .= ':00';
        }
        return $m[1] . ' ' . $t;
    }
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?\s*$#', $s, $m)) {
        $d = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        $t = isset($m[4]) ? sprintf('%02d:%02d:%02d', (int) $m[4], (int) $m[5], isset($m[6]) ? (int) $m[6] : 0) : '08:00:00';
        return $d . ' ' . $t;
    }
    if (is_numeric($s)) {
        $n = (float) $s;
        if ($n > 20000 && $n < 80000 && class_exists(\PhpOffice\PhpSpreadsheet\Shared\Date::class)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($n)->format('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }
    $ts = strtotime(preg_replace('#(\d{1,2})/(\d{1,2})/(\d{4})#', '$3-$2-$1', $s));
    if ($ts !== false) {
        return date('Y-m-d H:i:s', $ts);
    }
    return null;
}

/**
 * Garante data_saida / data_chegada / data_rota (NOT NULL no banco).
 *
 * @return array{data_saida: string, data_chegada: string, data_rota: string}
 */
function import_rotas_resolve_route_datetimes(array $rota, array $ab): array {
    foreach ([$rota['data_rota'] ?? null, $ab['data'] ?? null] as $c) {
        $n = import_rotas_normalize_route_datetime($c);
        if ($n !== null) {
            $dateOnly = substr($n, 0, 10);
            return [
                'data_saida' => $n,
                'data_chegada' => $n,
                'data_rota' => $dateOnly,
            ];
        }
    }
    $y = import_rotas_reference_year();
    $n = sprintf('%04d-01-01 08:00:00', $y);
    return [
        'data_saida' => $n,
        'data_chegada' => $n,
        'data_rota' => sprintf('%04d-01-01', $y),
    ];
}

/** @return int Tamanho máximo da coluna veiculos.placa (padrão 12). */
function import_rotas_veiculo_placa_max_len(PDO $conn) {
    static $len = null;
    if ($len !== null) {
        return $len;
    }
    $len = 12;
    try {
        $stmt = $conn->query(
            "SELECT CHARACTER_MAXIMUM_LENGTH AS m FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos' AND COLUMN_NAME = 'placa'"
        );
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['m']) && (int) $row['m'] > 0) {
                $len = (int) $row['m'];
            }
        }
    } catch (\Throwable $e) {
    }
    return $len;
}

/**
 * Nome da aba do Excel vira placa: só A–Z, 0–9 e hífen, respeitando o limite do banco (evita 1406).
 */
function import_rotas_normalize_placa_from_sheet(PDO $conn, $sheetName) {
    $max = import_rotas_veiculo_placa_max_len($conn);
    $raw = trim((string) $sheetName);
    if ($raw === '') {
        $raw = 'IMP';
    }
    $s = strtoupper($raw);
    $s = preg_replace('/[^A-Z0-9\-]/u', '', $s);
    if ($s === '') {
        $body = str_pad((string) (abs(crc32($raw)) % 1000000000), min(9, max(1, $max - 1)), '0', STR_PAD_LEFT);
        $s = 'X' . substr($body, 0, $max - 1);
    }
    if (strlen($s) > $max) {
        $s = substr($s, 0, $max);
    }
    return $s;
}

function persistPreview($conn, $sheets) {
    $summary = ['rotas' => 0, 'abastecimentos' => 0, 'despesas' => 0, 'veiculos_criados' => 0, 'cidades_criadas' => 0];
    $dvHasOutros = false;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM despesas_viagem LIKE 'outros'");
        $dvHasOutros = $chk && $chk->rowCount() > 0;
    } catch (Exception $e) {
        $dvHasOutros = false;
    }
    foreach ($sheets as $sheetName => $rows) {
        if (import_rotas_sheet_should_skip((string) $sheetName)) {
            continue;
        }
        if (!is_array($rows) || count($rows) === 0) {
            continue;
        }
        $placa = import_rotas_normalize_placa_from_sheet($conn, $sheetName);
        // Ensure vehicle exists (basic)
        $st = $conn->prepare('SELECT id FROM veiculos WHERE placa = :placa AND empresa_id = :eid');
        $st->execute(['placa' => $placa, 'eid' => $_SESSION['empresa_id']]);
        $vid = $st->fetchColumn();
        if (!$vid) {
            $statusId = 1;
            try {
                $rSt = $conn->query('SELECT id FROM status_veiculos ORDER BY id ASC LIMIT 1');
                if ($rSt) {
                    $sidRow = $rSt->fetchColumn();
                    if ($sidRow) {
                        $statusId = (int) $sidRow;
                    }
                }
            } catch (\Throwable $e) {
                // mantém 1
            }
            $paramsV = [
                'eid' => $_SESSION['empresa_id'],
                'placa' => $placa,
                'mod' => 'Importação Excel',
                'marca' => 'N/D',
                'ano' => import_rotas_reference_year(),
                'sid' => $statusId,
            ];
            $ins = $conn->prepare('INSERT INTO veiculos (empresa_id, placa, modelo, marca, ano, status_id) VALUES (:eid, :placa, :mod, :marca, :ano, :sid)');
            try {
                $ins->execute($paramsV);
            } catch (\PDOException $eVe) {
                $tid = null;
                try {
                    $tid = $conn->query('SELECT id FROM tipos_combustivel ORDER BY id ASC LIMIT 1')->fetchColumn();
                } catch (\Throwable $t) {
                }
                if ($tid) {
                    $ins2 = $conn->prepare('INSERT INTO veiculos (empresa_id, placa, modelo, marca, ano, status_id, tipo_combustivel_id, km_atual) VALUES (:eid, :placa, :mod, :marca, :ano, :sid, :tid, 0)');
                    $ins2->execute($paramsV + ['tid' => (int) $tid]);
                } else {
                    throw $eVe;
                }
            }
            $vid = $conn->lastInsertId();
            $summary['veiculos_criados']++;
        }
        // Ensure motorista exists for this placa (create minimal motorista if missing)
        $motorista_id = null;
        // Prefer a motorista name provided in the sheet rows; if not, create a generic motorista per placa
        // We'll create on-demand when persisting rows below using the row-level motorista value if present.
        foreach ($rows as $r) {
            // Insert rota (best-effort mapping)
            $rota = $r['rota'];
            // Resolve origem/destino city ids when available
            $cidade_origem_id = null; $cidade_destino_id = null;
            $estado_origem = isset($rota['origem_uf']) ? strtoupper(substr(trim($rota['origem_uf']),0,2)) : null;
            $estado_destino = isset($rota['destino_uf']) ? strtoupper(substr(trim($rota['destino_uf']),0,2)) : null;
            $nome_origem = $rota['origem'] ?? null;
            $nome_destino = $rota['destino'] ?? null;
            try {
                if ($nome_origem && $estado_origem) {
                    $stc = $conn->prepare('SELECT id FROM cidades WHERE UPPER(TRIM(nome)) = UPPER(:nome) AND UPPER(uf) = :uf LIMIT 1');
                    $stc->execute([':nome' => $nome_origem, ':uf' => $estado_origem]);
                    $cidade_origem_id = $stc->fetchColumn();
                    if (!$cidade_origem_id) {
                        // insert city fallback
                        $insc = $conn->prepare('INSERT INTO cidades (nome, uf, created_at) VALUES (:nome, :uf, NOW())');
                        $insc->execute([':nome' => $nome_origem, ':uf' => $estado_origem]);
                        $cidade_origem_id = $conn->lastInsertId();
                        $summary['cidades_criadas']++;
                    }
                }
                if ($nome_destino && $estado_destino) {
                    $stc = $conn->prepare('SELECT id FROM cidades WHERE UPPER(TRIM(nome)) = UPPER(:nome) AND UPPER(uf) = :uf LIMIT 1');
                    $stc->execute([':nome' => $nome_destino, ':uf' => $estado_destino]);
                    $cidade_destino_id = $stc->fetchColumn();
                    if (!$cidade_destino_id) {
                        $insc = $conn->prepare('INSERT INTO cidades (nome, uf, created_at) VALUES (:nome, :uf, NOW())');
                        $insc->execute([':nome' => $nome_destino, ':uf' => $estado_destino]);
                        $cidade_destino_id = $conn->lastInsertId();
                        $summary['cidades_criadas']++;
                    }
                }
            } catch (Exception $e) {
                // ignore city resolution errors, fallback to nulls
            }

            // Motorista: create or find. Use motorista name from row if provided, otherwise a generic name per placa
            $motorista_id = null;
            $driverName = null;
            if (!empty($r['rota']['motorista'])) {
                $driverName = trim($r['rota']['motorista']);
            } else {
                // Uma aba = um conjunto placa+motorista do envelope; sem coluna MOTORISTA usa o código da placa (evita "Motorista ABM" genérico).
                $driverName = $placa;
            }
            if ($driverName) {
                $stm = $conn->prepare('SELECT id FROM motoristas WHERE UPPER(TRIM(nome)) = UPPER(:nome) AND empresa_id = :eid LIMIT 1');
                $stm->execute([':nome' => $driverName, ':eid' => $_SESSION['empresa_id']]);
                $motorista_id = $stm->fetchColumn();
                if (!$motorista_id) {
                    $eid = (int) $_SESSION['empresa_id'];
                    $dnKey = function_exists('mb_strtolower')
                        ? mb_strtolower($driverName, 'UTF-8')
                        : strtolower($driverName);
                    $cpf = str_pad((string) (abs(crc32($dnKey . '|' . $eid)) % 100000000000), 11, '0', STR_PAD_LEFT);
                    if (strlen($cpf) > 11) {
                        $cpf = substr($cpf, 0, 11);
                    }
                    $tel = '00000000000';
                    try {
                        $insm = $conn->prepare('INSERT INTO motoristas (empresa_id, nome, cpf, telefone) VALUES (:eid, :nome, :cpf, :tel)');
                        $insm->execute([':eid' => $eid, ':nome' => $driverName, ':cpf' => $cpf, ':tel' => $tel]);
                        $motorista_id = $conn->lastInsertId();
                    } catch (\PDOException $e) {
                        try {
                            $insm = $conn->prepare('INSERT INTO motoristas (empresa_id, nome, cpf, telefone, data_cadastro) VALUES (:eid, :nome, :cpf, :tel, NOW())');
                            $insm->execute([':eid' => $eid, ':nome' => $driverName, ':cpf' => $cpf, ':tel' => $tel]);
                            $motorista_id = $conn->lastInsertId();
                        } catch (\PDOException $e2) {
                            error_log('import_rotas.php motorista insert: ' . $e2->getMessage());
                        }
                    }
                }
            }
            if (!$motorista_id) {
                $stm = $conn->prepare('SELECT id FROM motoristas WHERE empresa_id = :eid ORDER BY id ASC LIMIT 1');
                $stm->execute([':eid' => $_SESSION['empresa_id']]);
                $motorista_id = $stm->fetchColumn();
            }
            if (!$motorista_id) {
                throw new \RuntimeException(
                    'Não há motorista cadastrado na empresa e não foi possível criar um automaticamente. Cadastre um motorista e tente novamente.'
                );
            }

            $pctMotorista = import_rotas_parse_percent($rota['pct_comissao_motorista'] ?? null);
            if ($pctMotorista !== null) {
                try {
                    $chkPct = $conn->query("SHOW COLUMNS FROM motoristas LIKE 'porcentagem_comissao'");
                    if ($chkPct && $chkPct->rowCount() > 0) {
                        $pctClamped = min(100.0, max(0.0, (float) $pctMotorista));
                        $upPct = $conn->prepare('UPDATE motoristas SET porcentagem_comissao = :p WHERE id = :id AND empresa_id = :eid');
                        $upPct->execute([
                            'p' => $pctClamped,
                            'id' => (int) $motorista_id,
                            'eid' => (int) $_SESSION['empresa_id'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    error_log('import_rotas.php porcentagem_comissao: ' . $e->getMessage());
                }
            }

            $ab = $r['abastecimento'] ?? [];
            $dts = import_rotas_resolve_route_datetimes($rota, $ab);

            $insR = $conn->prepare('INSERT INTO rotas (
                empresa_id, veiculo_id, motorista_id,
                estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
                data_saida, data_chegada, km_saida, km_chegada, distancia_km,
                observacoes, data_rota, no_prazo, frete, comissao,
                km_vazio, total_km, percentual_vazio, eficiencia_viagem,
                peso_carga, descricao_carga, status, fonte
            ) VALUES (
                :eid, :vid, :mid, :estado_origem, :cid_origem, :estado_destino, :cid_destino,
                :data_saida, :data_chegada, :km_saida, :km_chegada, :dist,
                :obs, :data_rota, :no_prazo, :frete, :com,
                :km_vazio, :total_km, :pct_vazio, :efic,
                :peso_carga, :descricao_carga, :status, :fonte
            )');
            $insR->execute([
                'eid' => $_SESSION['empresa_id'],
                'vid' => $vid,
                'mid' => $motorista_id,
                'estado_origem' => $estado_origem,
                'cid_origem' => $cidade_origem_id,
                'estado_destino' => $estado_destino,
                'cid_destino' => $cidade_destino_id,
                'data_saida' => $dts['data_saida'],
                'data_chegada' => $dts['data_chegada'],
                'km_saida' => import_rotas_parse_integer_km($rota['km_saida'] ?? null),
                'km_chegada' => import_rotas_parse_integer_km($rota['km_chegada'] ?? null),
                'dist' => import_rotas_parse_decimal_money($rota['distancia_km'] ?? null),
                'obs' => $rota['observacoes'] ?? null,
                'data_rota' => $dts['data_rota'],
                'no_prazo' => 1,
                'frete' => import_rotas_parse_decimal_money($rota['frete'] ?? null),
                'com' => import_rotas_parse_decimal_money($rota['comissao'] ?? null),
                'km_vazio' => null,
                'total_km' => null,
                'pct_vazio' => null,
                'efic' => null,
                'peso_carga' => import_rotas_parse_decimal_money($rota['peso'] ?? null),
                'descricao_carga' => $rota['descricao_carga'] ?? null,
                'status' => 'aprovado',
                'fonte' => 'gestor',
            ]);
            $summary['rotas']++;
            $rota_id = $conn->lastInsertId();
            // Um abastecimento por rota (1:1 com o envelope); litros/valor zero se a linha não tiver diesel/litros.
            $litros = import_rotas_parse_decimal($ab['litros'] ?? null);
            $vt = import_rotas_parse_decimal($ab['valor_total'] ?? null);
            $vl = import_rotas_parse_decimal($ab['valor_litro'] ?? null);
            if ($vl === null && $litros !== null && $litros > 0 && $vt !== null) {
                $vl = round($vt / $litros, 4);
            }
            $kmAtual = import_rotas_parse_integer_km($ab['km_atual'] ?? null);
            if ($kmAtual === null) {
                $kmAtual = import_rotas_parse_integer_km($rota['km_chegada'] ?? null);
            }
            if ($kmAtual === null) {
                $kmAtual = import_rotas_parse_integer_km($rota['km_saida'] ?? null);
            }
            if ($kmAtual === null) {
                $kmAtual = 0;
            }
            $dataAbNorm = import_rotas_normalize_route_datetime($ab['data'] ?? null);
            $dataAb = $dataAbNorm ? substr($dataAbNorm, 0, 10) : $dts['data_rota'];
            if ($litros === null) {
                $litros = 0.0;
            }
            if ($vt === null) {
                $vt = 0.0;
            }
            if ($vl === null) {
                $vl = ($litros > 0 && $vt > 0) ? round($vt / $litros, 4) : 0.0;
            }
            $paramsA = [
                'eid' => $_SESSION['empresa_id'],
                'vid' => $vid,
                'mid' => $motorista_id,
                'rid' => $rota_id,
                'data_ab' => $dataAb,
                'litros' => $litros,
                'vl' => $vl,
                'vt' => $vt,
                'km' => $kmAtual,
                'posto' => 'Importação Excel',
                'st_ab' => 'aprovado',
                'fonte_ab' => 'gestor',
                'obs_ab' => 'Importação Excel',
            ];
            try {
                $insA = $conn->prepare('INSERT INTO abastecimentos (
                    empresa_id, veiculo_id, motorista_id, rota_id,
                    data_abastecimento, litros, valor_litro, valor_total,
                    km_atual, posto, status, fonte, observacoes
                ) VALUES (
                    :eid, :vid, :mid, :rid,
                    :data_ab, :litros, :vl, :vt,
                    :km, :posto, :st_ab, :fonte_ab, :obs_ab
                )');
                $insA->execute($paramsA);
            } catch (\PDOException $eAb) {
                $insA = $conn->prepare('INSERT INTO abastecimentos (
                    empresa_id, veiculo_id, motorista_id, rota_id,
                    posto, data_abastecimento, litros, valor_litro, valor_total,
                    km_atual, tipo_combustivel, forma_pagamento, observacoes,
                    status, fonte
                ) VALUES (
                    :eid, :vid, :mid, :rid,
                    :posto, :data_ab, :litros, :vl, :vt,
                    :km, :tipo_comb, :forma_pag, :obs_ab,
                    :st_ab, :fonte_ab
                )');
                $insA->execute($paramsA + [
                    'tipo_comb' => 'Diesel',
                    'forma_pag' => 'Não informado',
                ]);
            }
            $summary['abastecimentos']++;
            // Despesas de viagem (uma linha por rota, colunas do sistema)
            $desp = $r['despesas'];
            $ped = import_rotas_parse_decimal($desp['pedagios'] ?? null);
            $bor = import_rotas_parse_decimal($desp['borracharia'] ?? null);
            $em = import_rotas_parse_decimal($desp['oficina'] ?? null);
            $cai = import_rotas_parse_decimal($desp['caixinha'] ?? null);
            $out = import_rotas_parse_decimal($desp['outros'] ?? null);
            if ($ped !== null || $bor !== null || $em !== null || $cai !== null || $out !== null) {
                if ($dvHasOutros) {
                    $insD = $conn->prepare('INSERT INTO despesas_viagem (
                        empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento, lavagem,
                        borracharia, eletrica_mecanica, adiantamento, outros, created_at, updated_at
                    ) VALUES (
                        :eid, :rid, NULL, :pedagios, :caixinha, NULL, NULL,
                        :borracharia, :em, NULL, :outros, NOW(), NOW()
                    )');
                    $insD->execute([
                        'eid' => $_SESSION['empresa_id'],
                        'rid' => $rota_id,
                        'pedagios' => $ped,
                        'caixinha' => $cai,
                        'borracharia' => $bor,
                        'em' => $em,
                        'outros' => $out,
                    ]);
                } else {
                    $insD = $conn->prepare('INSERT INTO despesas_viagem (
                        empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento, lavagem,
                        borracharia, eletrica_mecanica, adiantamento, created_at, updated_at
                    ) VALUES (
                        :eid, :rid, NULL, :pedagios, :caixinha, NULL, NULL,
                        :borracharia, :em, NULL, NOW(), NOW()
                    )');
                    $insD->execute([
                        'eid' => $_SESSION['empresa_id'],
                        'rid' => $rota_id,
                        'pedagios' => $ped,
                        'caixinha' => $cai,
                        'borracharia' => $bor,
                        'em' => $em,
                    ]);
                }
                $summary['despesas']++;
            }
        }
    }
    return $summary;
}

function parseLogistica($text) {
    $res = ['descricao' => null, 'origem' => null, 'origem_uf' => null, 'destino' => null, 'destino_uf' => null];
    if (!$text) return $res;
    $t = str_replace('\/', '/', trim((string) $text));
    $t = trim(preg_replace('/\s+/', ' ', $t));
    $res['descricao'] = $t;
    // Normalize arrows and separators
    $norm = str_replace(['→', '–', '—', '=>'], '->', $t);
    // Try to split by arrow first, then P/ (comum em planilhas), then hyphen, then ' / ' with spaces
    if (stripos($norm, '->') !== false) {
        $parts = preg_split('/->/', $norm, 2);
    } elseif (preg_match('/\bP\//i', $norm)) {
        $parts = preg_split('/\bP\//i', $norm, 2);
    } elseif (strpos($norm, ' - ') !== false) {
        $parts = explode(' - ', $norm, 2);
    } elseif (strpos($norm, ' / ') !== false) {
        $parts = explode(' / ', $norm, 2);
    } else {
        // Fallback: try single slash but only if there is exactly two parts like City/UF → City/UF handled earlier
        $parts = preg_split('/\s*\/\s*/', $norm, 2);
    }
    if (count($parts) >= 2) {
        $orig = trim($parts[0]);
        $dest = trim($parts[1]);
        list($orig_name, $orig_uf) = splitCityState($orig);
        list($dest_name, $dest_uf) = splitCityState($dest);
        $res['origem'] = $orig_name;
        $res['origem_uf'] = $orig_uf;
        $res['destino'] = $dest_name;
        $res['destino_uf'] = $dest_uf;
    }
    return $res;
}

function splitCityState($text) {
    $t = trim($text);
    // Try patterns: "City, UF" or "City - UF" or "City (UF)" or ending with UF
    // Allow Unicode letters and accents, handle separators including slash
    if (preg_match('/^(.*?)\s*[,\-\(]+\s*([A-Za-z]{2})\)?$/u', $t, $m)) {
        return [trim($m[1]), strtoupper($m[2])];
    }
    if (preg_match('/^(.*)\s+([A-Za-z]{2})$/u', $t, $m)) {
        return [trim($m[1]), strtoupper($m[2])];
    }
    // Match City/UF or City / UF
    if (preg_match('/^(.*?)\s*\/\s*([A-Za-z]{2})$/u', $t, $m)) {
        return [trim($m[1]), strtoupper($m[2])];
    }
    // If no UF, return text and null
    return [$t, null];
}
