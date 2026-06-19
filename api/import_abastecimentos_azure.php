<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/sf_openai_resolve.php';

function ia_parse_decimal($value): ?float
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
        if ((int) strrpos($s, ',') > (int) strrpos($s, '.')) {
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

function ia_parse_km($value): ?int
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
    $s = preg_replace('/[^\d]/', '', trim((string) $value));
    if (!is_string($s) || $s === '' || !ctype_digit($s)) {
        return null;
    }

    return (int) $s;
}

/**
 * @return array{y: int, m: int, d: int}|null
 */
function ia_parse_br_date(string $s): ?array
{
    $s = trim($s);

    // FIX 1: normaliza separadores — ponto e espaço viram barra
    // OCR frequentemente lê "22.1.25" ou "22 1 25" em vez de "22/1/25"
    $normalized = preg_replace('/(\d{1,2})[.\s](\d{1,2})[.\s](\d{2,4})/', '$1/$2/$3', $s) ?? $s;

    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2}|\d{4})\b/', $normalized, $m)) {
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
 * @param array<string,mixed> $item
 * @return array<string,mixed>|null
 */
function ia_normalize_row(array $item): ?array
{
    $dataRaw = isset($item['data']) ? trim((string) $item['data']) : '';
    $posto = isset($item['posto']) ? trim((string) $item['posto']) : '';
    $km = $item['km'] ?? null;
    $litros = $item['litros'] ?? ($item['l'] ?? null);
    $val = $item['valor_total'] ?? ($item['valor'] ?? ($item['total'] ?? null));
    $parts = ia_parse_br_date($dataRaw);
    if ($parts === null) {
        return null;
    }
    $kmNum = ia_parse_km($km);
    if ($kmNum === null || $kmNum < 100 || $kmNum > 99999999) {
        return null;
    }
    $litrosNum = ia_parse_decimal($litros);
    // FIX 2: limite superior realista — caminhão tanque duplo ~1200L, era 80000 (absurdo)
    if ($litrosNum === null || $litrosNum <= 0 || $litrosNum > 1500) {
        return null;
    }
    $valorTotal = ia_parse_decimal($val);
    if ($valorTotal === null || $valorTotal < 0) {
        return null;
    }
    if ($posto === '') {
        return null;
    }
    $ymd = sprintf('%04d-%02d-%02d', $parts['y'], $parts['m'], $parts['d']);
    $valorLitro = $litrosNum > 0 ? $valorTotal / $litrosNum : 0.0;

    return [
        'ymd' => $ymd,
        'datetimeLocal' => $ymd . 'T08:00',
        'km' => $kmNum,
        'posto' => $posto,
        'litros' => $litrosNum,
        'valorTotal' => $valorTotal,
        'valorLitro' => $valorLitro,
        'litrosBr' => number_format($litrosNum, 3, ',', '.'),
        'valorTotalBr' => number_format($valorTotal, 2, ',', '.'),
        'valorLitroBr' => number_format($valorLitro, 2, ',', '.'),
        '_label' => $dataRaw . ' · ' . $posto,
    ];
}

/**
 * @return array{0: string, 1: string}
 */
function ia_preprocess_image(string $binary, string $mime): array
{
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
    ob_start();
    imagejpeg($src, null, 88);
    $out = (string) ob_get_clean();
    imagedestroy($src);
    if ($out === '') {
        return [$binary, $mime];
    }

    return [$out, 'image/jpeg'];
}

/**
 * Recorta a área provável da tabela de abastecimento.
 * Variantes:
 * - default: recorte médio
 * - high: começa um pouco acima
 * - low: começa um pouco abaixo
 *
 * @return array{0: string, 1: string, 2: bool} [binary, mime, cropped]
 */
function ia_crop_abastecimento_roi(string $binary, string $mime, string $variant = 'default'): array
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagecrop') || !function_exists('imagejpeg')) {
        return [$binary, $mime, false];
    }
    $src = @imagecreatefromstring($binary);
    if ($src === false) {
        return [$binary, $mime, false];
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w < 200 || $h < 200) {
        imagedestroy($src);

        return [$binary, $mime, false];
    }
    $cropX = (int) round($w * 0.03);
    $cropW = (int) round($w * 0.94);
    // FIX 3: no formulário Altoé o bloco de abastecimento fica em ~62%-96% da altura.
    // O crop antigo (44-91%) incluía muito cabeçalho e cortava a última linha.
    if ($variant === 'high') {
        $cropY = (int) round($h * 0.57);
        $cropH = (int) round($h * 0.38);
    } elseif ($variant === 'low') {
        $cropY = (int) round($h * 0.67);
        $cropH = (int) round($h * 0.30);
    } else {
        $cropY = (int) round($h * 0.62);
        $cropH = (int) round($h * 0.34);
    }
    if ($cropX < 0 || $cropY < 0 || $cropW < 50 || $cropH < 50 || ($cropX + $cropW) > $w || ($cropY + $cropH) > $h) {
        imagedestroy($src);

        return [$binary, $mime, false];
    }
    $cropped = imagecrop($src, ['x' => $cropX, 'y' => $cropY, 'width' => $cropW, 'height' => $cropH]);
    imagedestroy($src);
    if ($cropped === false) {
        return [$binary, $mime, false];
    }
    imagefilter($cropped, IMG_FILTER_GRAYSCALE);
    imagefilter($cropped, IMG_FILTER_CONTRAST, -22);
    ob_start();
    imagejpeg($cropped, null, 90);
    $out = (string) ob_get_clean();
    imagedestroy($cropped);
    if ($out === '') {
        return [$binary, $mime, false];
    }

    return [$out, 'image/jpeg', true];
}

function ia_aggressive_roi_enabled(): bool
{
    $raw = strtolower(trim((string) getenv('SF_REFUEL_IMPORT_AGGRESSIVE_ROI')));

    return in_array($raw, ['1', 'true', 'yes', 'on'], true);
}

/**
 * @return array<int,array{binary:string,mime:string,tag:string}>
 */
function ia_build_passes_with_roi(string $binary, string $mime): array
{
    $passes = [];
    $variants = ia_aggressive_roi_enabled()
        ? ['default', 'high', 'low']
        : ['default'];
    foreach ($variants as $variant) {
        [$roiBinary, $roiMime, $roiApplied] = ia_crop_abastecimento_roi($binary, $mime, $variant);
        if ($roiApplied) {
            $passes[] = [
                'binary' => $roiBinary,
                'mime' => $roiMime,
                'tag' => $variant === 'default' ? 'roi' : ('roi-' . $variant),
            ];
        }
    }
    $passes[] = ['binary' => $binary, 'mime' => $mime, 'tag' => 'full'];

    return $passes;
}

/**
 * @param array<int,array<string,mixed>> $rowsA
 * @param array<int,array<string,mixed>> $rowsB
 * @return array<int,array<string,mixed>>
 */
function ia_merge_rows(array $rowsA, array $rowsB): array
{
    $rows = [];
    $seen = [];
    foreach ([$rowsA, $rowsB] as $src) {
        foreach ($src as $row) {
            if (!is_array($row)) {
                continue;
            }
            $k = ($row['ymd'] ?? '') . '|' . ($row['km'] ?? '') . '|' . ($row['posto'] ?? '') . '|' . ($row['litros'] ?? '') . '|' . ($row['valorTotal'] ?? '');
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $rows[] = $row;
        }
    }

    return $rows;
}

/**
 * @return array{rows: array<int,array<string,mixed>>, text: string, error: string}
 */
function ia_extract_tesseract_rows(string $binary): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'rfocr_');
    if ($tmp === false) {
        return ['rows' => [], 'text' => '', 'error' => 'temp_file'];
    }
    $in = $tmp . '.jpg';
    @rename($tmp, $in);
    if (@file_put_contents($in, $binary) === false) {
        @unlink($in);

        return ['rows' => [], 'text' => '', 'error' => 'write_input'];
    }
    $bin = trim((string) getenv('SF_TESSERACT_BIN'));
    if ($bin === '') {
        $bin = 'tesseract';
    }
    $cmd = escapeshellcmd($bin) . ' ' . escapeshellarg($in) . ' stdout -l por --oem 3 --psm 6 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    @unlink($in);
    $text = trim(implode("\n", $out));
    if ($code !== 0) {
        return ['rows' => [], 'text' => $text, 'error' => 'exec_' . $code];
    }
    $lines = preg_split('/\r?\n/', $text) ?: [];
    $rows = [];
    $seen = [];
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", '|'], ' ', (string) $line)));
        if (!preg_match('/^(\d{1,2}\/\d{1,2}\/\d{2,4})[\s.,:|\/_-]+(.+)$/', $line, $m)) {
            continue;
        }
        $parts = preg_split('/\s+/', trim((string) $m[2])) ?: [];
        if (count($parts) < 4) {
            continue;
        }
        $candidate = [
            'data' => $m[1],
            'km' => $parts[0] ?? '',
            'posto' => trim(implode(' ', array_slice($parts, 1, -2))),
            'litros' => $parts[count($parts) - 2] ?? '',
            'valor_total' => $parts[count($parts) - 1] ?? '',
        ];
        $row = ia_normalize_row($candidate);
        if ($row === null) {
            continue;
        }
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return ['rows' => $rows, 'text' => $text, 'error' => ''];
}

/**
 * OCR via Google Vision (DOCUMENT_TEXT_DETECTION).
 * Requer SF_GOOGLE_VISION_API_KEY.
 *
 * @return array{text: string, error: string, lines: array<int,string>}
 */
function ia_extract_google_vision_text(string $binary, string $apiKey): array
{
    if ($apiKey === '') {
        return ['text' => '', 'error' => 'no_key', 'lines' => []];
    }
    $url = 'https://vision.googleapis.com/v1/images:annotate?key=' . rawurlencode($apiKey);
    $payload = [
        'requests' => [[
            'image' => ['content' => base64_encode($binary)],
            'features' => [
                ['type' => 'DOCUMENT_TEXT_DETECTION', 'maxResults' => 1],
                ['type' => 'TEXT_DETECTION', 'maxResults' => 1],
            ],
            'imageContext' => ['languageHints' => ['pt-BR', 'pt']],
        ]],
    ];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = (string) curl_error($ch);
    curl_close($ch);
    if (!is_string($raw) || $raw === '') {
        return ['text' => '', 'error' => 'transport:' . ($curlErr !== '' ? $curlErr : 'empty'), 'lines' => []];
    }
    $resp = json_decode($raw, true);
    if (!is_array($resp) || $http < 200 || $http >= 300) {
        $msg = '';
        if (is_array($resp['error'] ?? null)) {
            $msg = (string) ($resp['error']['message'] ?? '');
        }

        return ['text' => '', 'error' => 'http_' . $http . ($msg !== '' ? ':' . $msg : ''), 'lines' => []];
    }
    $r0 = is_array($resp['responses'][0] ?? null) ? $resp['responses'][0] : [];
    $text = trim((string) (($r0['fullTextAnnotation']['text'] ?? '') ?: ($r0['textAnnotations'][0]['description'] ?? '')));
    if ($text === '') {
        return ['text' => '', 'error' => 'empty_text', 'lines' => []];
    }

    // Monta linhas por coordenada Y com base em textAnnotations (palavras + bounding box).
    $lineTexts = [];
    $anns = is_array($r0['textAnnotations'] ?? null) ? $r0['textAnnotations'] : [];
    if (count($anns) > 1) {
        $words = [];
        for ($i = 1; $i < count($anns); $i++) {
            $a = is_array($anns[$i]) ? $anns[$i] : null;
            if ($a === null) {
                continue;
            }
            $w = trim((string) ($a['description'] ?? ''));
            if ($w === '') {
                continue;
            }
            $verts = is_array($a['boundingPoly']['vertices'] ?? null) ? $a['boundingPoly']['vertices'] : [];
            $xs = [];
            $ys = [];
            foreach ($verts as $v) {
                if (is_array($v)) {
                    $xs[] = (int) ($v['x'] ?? 0);
                    $ys[] = (int) ($v['y'] ?? 0);
                }
            }
            if ($xs === [] || $ys === []) {
                continue;
            }
            $x = min($xs);
            $y = (int) round(array_sum($ys) / count($ys));
            $h = max($ys) - min($ys);
            $words[] = ['t' => $w, 'x' => $x, 'y' => $y, 'h' => max(1, $h)];
        }
        if ($words !== []) {
            usort($words, static function (array $a, array $b): int {
                if ($a['y'] === $b['y']) {
                    return $a['x'] <=> $b['x'];
                }

                return $a['y'] <=> $b['y'];
            });
            $avgH = 0;
            foreach ($words as $w) {
                $avgH += (int) $w['h'];
            }
            $avgH = (int) max(8, round($avgH / max(1, count($words))));
            $yTol = max(10, (int) round($avgH * 0.9));

            $lines = [];
            foreach ($words as $w) {
                $placed = false;
                for ($li = 0; $li < count($lines); $li++) {
                    if (abs((int) $w['y'] - (int) $lines[$li]['y']) <= $yTol) {
                        $lines[$li]['y'] = (int) round(($lines[$li]['y'] + $w['y']) / 2);
                        $lines[$li]['words'][] = $w;
                        $placed = true;
                        break;
                    }
                }
                if (!$placed) {
                    $lines[] = ['y' => (int) $w['y'], 'words' => [$w]];
                }
            }

            usort($lines, static function (array $a, array $b): int {
                return $a['y'] <=> $b['y'];
            });
            foreach ($lines as $line) {
                $lw = $line['words'];
                usort($lw, static function (array $a, array $b): int {
                    return $a['x'] <=> $b['x'];
                });
                $chunks = [];
                foreach ($lw as $w) {
                    $chunks[] = (string) $w['t'];
                }
                $lt = trim(implode(' ', $chunks));
                if ($lt !== '') {
                    $lineTexts[] = $lt;
                }
            }
        }
    }

    return ['text' => $text, 'error' => '', 'lines' => $lineTexts];
}

/**
 * @return array{rows: array<int,array<string,mixed>>, text: string}
 */
function ia_parse_rows_from_ocr_text(string $text): array
{
    // FIX 4a: remove labels pré-impressos do formulário Altoé que o OCR captura
    // "DATA:" "KM:" "POSTO:" "L" "R$" aparecem no texto mas não são dados
    $text = (string) preg_replace('/\bDATA[:\s]*/i', '', $text);
    $text = (string) preg_replace('/\bKM[:\s]*/i', 'KM:', $text);   // mantém KM: como âncora
    $text = (string) preg_replace('/\bPOSTO[:\s]*/i', '', $text);
    $text = (string) preg_replace('/\bABASTECIMENTO[:\s]*(?:\(AV\))?(?:\(AP\))?/i', '', $text);

    $lines = preg_split('/\r?\n/', $text) ?: [];

    // FIX 4b: agrupa linhas consecutivas quando o OCR quebrou uma entrada em 2+ linhas
    // Estratégia: se a linha não começa com data e a anterior sim, concatena
    $merged = [];
    $buffer = '';
    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', str_replace(["\xC2\xA0", '|'], ' ', (string) $line)));
        if ($line === '') {
            if ($buffer !== '') {
                $merged[] = $buffer;
                $buffer = '';
            }
            continue;
        }
        // FIX 4c: aceita ponto como separador de data (OCR lê "22.1.25" em vez de "22/1/25")
        $startsWithDate = (bool) preg_match('/^\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{2,4}\b/', $line);
        if ($startsWithDate) {
            if ($buffer !== '') {
                $merged[] = $buffer;
            }
            $buffer = $line;
        } else {
            $buffer = $buffer !== '' ? $buffer . ' ' . $line : $line;
        }
    }
    if ($buffer !== '') {
        $merged[] = $buffer;
    }

    $rows = [];
    $seen = [];
    foreach ($merged as $line) {
        // Normaliza separador de data para barra
        $line = (string) preg_replace('/^(\d{1,2})[.\s](\d{1,2})[.\s](\d{2,4})\b/', '$1/$2/$3', $line);

        if (!preg_match('/^(\d{1,2}\/\d{1,2}\/\d{2,4})[\s.,:|\/_-]+(.+)$/', $line, $m)) {
            continue;
        }
        // Remove prefixo "KM:" que mantivemos como âncora
        $rest = (string) preg_replace('/\bKM:\s*/i', '', trim((string) $m[2]));
        $parts = preg_split('/\s+/', $rest) ?: [];
        if (count($parts) < 4) {
            continue;
        }
        $candidate = [
            'data' => $m[1],
            'km' => $parts[0] ?? '',
            'posto' => trim(implode(' ', array_slice($parts, 1, -2))),
            'litros' => $parts[count($parts) - 2] ?? '',
            'valor_total' => $parts[count($parts) - 1] ?? '',
        ];
        $row = ia_normalize_row($candidate);
        if ($row === null) {
            continue;
        }
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return ['rows' => $rows, 'text' => $text];
}

/**
 * Fallback tolerante para OCR ruim:
 * - aceita data no meio da linha
 * - corrige confusões comuns (O->0, I/l->1, S->5)
 * - tenta capturar km/litros/valor por proximidade
 *
 * @return array{rows: array<int,array<string,mixed>>, text: string}
 */
function ia_parse_rows_from_ocr_text_relaxed(string $text): array
{
    $norm = strtoupper((string) $text);
    $norm = str_replace(['O', 'I', 'L', 'S'], ['0', '1', '1', '5'], $norm);
    $norm = str_replace(["\r"], "\n", $norm);
    $lines = preg_split('/\n+/', $norm) ?: [];
    $rows = [];
    $seen = [];

    foreach ($lines as $line) {
        $line = trim((string) preg_replace('/\s+/', ' ', $line));
        if ($line === '') {
            continue;
        }

        if (!preg_match('/(\d{1,2})[\/\.\-](\d{1,2})[\/\.\-](\d{2,4})/', $line, $dm)) {
            continue;
        }
        $date = $dm[1] . '/' . $dm[2] . '/' . $dm[3];

        $km = null;
        if (preg_match('/\bKM[:\s]*([0-9\.\,]{3,10})\b/', $line, $kmM)) {
            $km = $kmM[1];
        } elseif (preg_match('/\b([0-9\.\,]{4,10})\b/', $line, $kmM2)) {
            $km = $kmM2[1];
        }

        $litros = null;
        if (preg_match('/\b([0-9\.\,]{1,6})\s*(?:L|LT|LITROS?)\b/', $line, $lM)) {
            $litros = $lM[1];
        }

        $valor = null;
        if (preg_match('/(?:R\$|RS)\s*([0-9\.\,]{2,12})/', $line, $vM)) {
            $valor = $vM[1];
        } else {
            // último número da linha costuma ser o valor total
            if (preg_match('/([0-9\.\,]{3,12})\s*$/', $line, $vM2)) {
                $valor = $vM2[1];
            }
        }

        if ($km === null || $litros === null || $valor === null) {
            continue;
        }

        // tenta capturar texto entre KM e litros como posto
        $posto = '';
        if (preg_match('/KM[:\s]*[0-9\.\,]+\s+(.*?)\s+[0-9\.\,]+\s*(?:L|LT|LITROS?)/', $line, $pM)) {
            $posto = trim($pM[1]);
        }
        if ($posto === '' || preg_match('/^(POSTO|KM|DATA)$/', $posto)) {
            $posto = 'Posto não identificado';
        }

        $row = ia_normalize_row([
            'data' => $date,
            'km' => $km,
            'posto' => $posto,
            'litros' => $litros,
            'valor_total' => $valor,
        ]);
        if ($row === null) {
            continue;
        }
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return ['rows' => $rows, 'text' => $text];
}

/**
 * Fallback ultra-tolerante em texto corrido:
 * divide o bloco por ocorrências de data e tenta montar linha em cada segmento.
 *
 * @return array{rows: array<int,array<string,mixed>>, text: string}
 */
function ia_parse_rows_from_ocr_text_chunked(string $text): array
{
    $compact = trim((string) preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $text)));
    if ($compact === '') {
        return ['rows' => [], 'text' => $text];
    }
    $parts = preg_split('/(?=(\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4}))/i', $compact) ?: [];
    $rows = [];
    $seen = [];
    foreach ($parts as $chunk) {
        $chunk = trim((string) $chunk);
        if ($chunk === '') {
            continue;
        }
        $parsed = ia_parse_rows_from_ocr_text_relaxed($chunk);
        foreach ($parsed['rows'] as $row) {
            $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $rows[] = $row;
        }
    }

    return ['rows' => $rows, 'text' => $text];
}

function ia_digits_to_date_guess(string $raw): ?string
{
    $d = preg_replace('/\D/', '', $raw);
    if (!is_string($d) || $d === '') {
        return null;
    }
    if (strlen($d) === 6) {
        return substr($d, 0, 2) . '/' . substr($d, 2, 2) . '/' . substr($d, 4, 2);
    }
    if (strlen($d) === 5) {
        return substr($d, 0, 2) . '/' . substr($d, 2, 1) . '/' . substr($d, 3, 2);
    }
    if (strlen($d) >= 7) {
        return substr($d, 0, 2) . '/' . substr($d, 2, 2) . '/' . substr($d, 4, 2);
    }

    return null;
}

/**
 * Parser dedicado ao bloco ABASTECIMENTO para OCR ruidoso.
 *
 * @return array{rows: array<int,array<string,mixed>>, text: string}
 */
function ia_parse_rows_from_abastecimento_block(string $text): array
{
    $u = strtoupper((string) $text);
    $start = strpos($u, 'ABASTECIMENTO');
    if ($start === false) {
        return ['rows' => [], 'text' => $text];
    }
    $tail = substr($u, $start);
    if (!is_string($tail) || $tail === '') {
        return ['rows' => [], 'text' => $text];
    }
    $endPos = false;
    foreach (['ANOTACOES', 'ANOTAÇÕES', 'M / R $ KM', 'LIQUIDO R'] as $mk) {
        $p = strpos($tail, $mk);
        if ($p !== false) {
            $endPos = $p;
            break;
        }
    }
    $block = ($endPos !== false) ? substr($tail, 0, (int) $endPos) : $tail;
    if (!is_string($block) || $block === '') {
        return ['rows' => [], 'text' => $text];
    }
    $block = (string) preg_replace('/\s+/', ' ', $block);
    $segments = preg_split('/\bDATA\b/i', $block) ?: [];
    $rows = [];
    $seen = [];

    foreach ($segments as $seg) {
        $seg = trim((string) $seg, " :;\t\r\n");
        if ($seg === '') {
            continue;
        }
        // data
        $date = null;
        if (preg_match('/(\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4})/', $seg, $dm)) {
            $date = str_replace(['.', '-'], '/', $dm[1]);
        } elseif (preg_match('/\b(\d{5,8})\b/', $seg, $dm2)) {
            $date = ia_digits_to_date_guess($dm2[1]);
        }
        if ($date === null) {
            continue;
        }

        // km
        $km = null;
        if (preg_match('/KM\s*[:\-]?\s*([0-9\.\,\s]{3,12})/i', $seg, $kmM)) {
            $km = preg_replace('/\s+/', '', $kmM[1]);
        }
        if ($km === null || trim((string) $km) === '') {
            continue;
        }

        // valor
        $valor = null;
        if (preg_match('/R\s*\$?\s*([0-9\.\,]{2,12})/i', $seg, $vM)) {
            $valor = preg_replace('/[^0-9\.\,]/', '', $vM[1]);
        }
        if ($valor === null || trim((string) $valor) === '') {
            continue;
        }

        // litros
        $litros = null;
        if (preg_match('/\bL\s*([0-9\.\,]{1,6})\b/i', $seg, $l1)) {
            $litros = $l1[1];
        } elseif (preg_match('/\b([0-9\.\,]{1,6})\s*L\b/i', $seg, $l2)) {
            $litros = $l2[1];
        } else {
            // número antes de R$ (quando L sumiu no OCR)
            if (preg_match('/\b([0-9\.\,]{1,6})\b\s*R\s*\$?/i', $seg, $l3)) {
                $litros = $l3[1];
            }
        }
        if ($litros === null || trim((string) $litros) === '') {
            continue;
        }

        // posto
        $posto = '';
        if (preg_match('/POSTO\s*[:\-]?\s*(.*?)\s*(?:\bL\b|[0-9\.\,]{1,6}\s*R\s*\$?)/i', $seg, $pM)) {
            $posto = trim((string) $pM[1]);
        }
        if ($posto === '' || preg_match('/^(TO|POSTO|KM|DATA)$/i', $posto)) {
            $posto = 'Posto não identificado';
        }

        $row = ia_normalize_row([
            'data' => $date,
            'km' => $km,
            'posto' => $posto,
            'litros' => $litros,
            'valor_total' => $valor,
        ]);
        if ($row === null) {
            continue;
        }
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return ['rows' => $rows, 'text' => $text];
}

/**
 * Cria linhas rascunho quando OCR não fecha validação completa.
 *
 * @return array<int,array<string,mixed>>
 */
function ia_build_draft_rows_from_text(string $text): array
{
    $u = strtoupper((string) $text);
    $start = strpos($u, 'ABASTECIMENTO');
    if ($start === false) {
        return [];
    }
    $tail = substr($u, $start);
    if (!is_string($tail) || $tail === '') {
        return [];
    }
    $block = preg_replace('/\s+/', ' ', $tail);
    if (!is_string($block) || $block === '') {
        return [];
    }
    $segs = preg_split('/\bDATA\b/i', $block) ?: [];
    $rows = [];
    $seen = [];
    $today = date('Y-m-d');

    foreach ($segs as $seg) {
        $seg = trim((string) $seg, " :;\t\r\n");
        if ($seg === '') {
            continue;
        }
        $date = null;
        if (preg_match('/(\d{1,2}[\/\.\-]\d{1,2}[\/\.\-]\d{2,4})/', $seg, $dm)) {
            $date = str_replace(['.', '-'], '/', $dm[1]);
        } elseif (preg_match('/\b(\d{5,8})\b/', $seg, $dm2)) {
            $date = ia_digits_to_date_guess($dm2[1]);
        }

        $kmRaw = '';
        if (preg_match('/KM\s*[:\-]?\s*([0-9\.\,\s]{2,12})/i', $seg, $kmM)) {
            $kmRaw = trim((string) $kmM[1]);
        }
        $litrosRaw = '';
        if (preg_match('/\bL\s*([0-9\.\,]{1,6})\b/i', $seg, $l1)) {
            $litrosRaw = $l1[1];
        } elseif (preg_match('/\b([0-9\.\,]{1,6})\s*L\b/i', $seg, $l2)) {
            $litrosRaw = $l2[1];
        } elseif (preg_match('/\b([0-9\.\,]{1,6})\b\s*R\s*\$?/i', $seg, $l3)) {
            $litrosRaw = $l3[1];
        }
        $valorRaw = '';
        if (preg_match('/R\s*\$?\s*([0-9\.\,]{2,12})/i', $seg, $vM)) {
            $valorRaw = $vM[1];
        }
        $posto = '';
        if (preg_match('/POSTO\s*[:\-]?\s*(.*?)\s*(?:\bL\b|[0-9\.\,]{1,6}\s*R\s*\$?)/i', $seg, $pM)) {
            $posto = trim((string) $pM[1]);
        }
        if ($posto === '') {
            $posto = 'Posto não identificado';
        }

        $kmNum = ia_parse_km($kmRaw);
        $litrosNum = ia_parse_decimal($litrosRaw);
        $valorNum = ia_parse_decimal($valorRaw);
        if ($kmNum === null && $litrosNum === null && $valorNum === null) {
            continue;
        }
        $ymd = $today;
        if ($date !== null) {
            $parts = ia_parse_br_date($date);
            if ($parts !== null) {
                $ymd = sprintf('%04d-%02d-%02d', $parts['y'], $parts['m'], $parts['d']);
            }
        }
        $kmNum = $kmNum ?? 0;
        $litrosNum = $litrosNum ?? 0.0;
        $valorNum = $valorNum ?? 0.0;
        $vl = ($litrosNum > 0) ? ($valorNum / $litrosNum) : 0.0;
        $row = [
            'ymd' => $ymd,
            'datetimeLocal' => $ymd . 'T08:00',
            'km' => $kmNum,
            'posto' => $posto,
            'litros' => $litrosNum,
            'valorTotal' => $valorNum,
            'valorLitro' => $vl,
            'litrosBr' => number_format((float) $litrosNum, 3, ',', '.'),
            'valorTotalBr' => number_format((float) $valorNum, 2, ',', '.'),
            'valorLitroBr' => number_format((float) $vl, 2, ',', '.'),
            '_label' => 'Rascunho OCR',
            '_draft' => true,
        ];
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return $rows;
}

/**
 * @return array{rows: array<int,array<string,mixed>>, error: string, usage: array{0:int,1:int}}
 */
function ia_extract_deepseek_rows(string $key, string $dataUrl, string $ocrText): array
{
    $model = function_exists('sf_deepseek_refuel_model') ? sf_deepseek_refuel_model() : 'deepseek-chat';
    // FIX 5: system prompt mais preciso sobre o layout do formulário Altoé Transportes
    $system = <<<'SYS'
Você é um extrator especializado em formulários de frete da empresa Altoé Transportes.
Retorne SOMENTE JSON válido, sem markdown, sem texto extra.
SYS;
    $ocrSnippet = trim($ocrText);
    if (function_exists('mb_substr')) {
        $ocrSnippet = mb_substr($ocrSnippet, 0, 12000, 'UTF-8');
    } else {
        $ocrSnippet = substr($ocrSnippet, 0, 12000);
    }
    // FIX 5: prompt descreve estrutura real do formulário e previne confusão de labels
    $userText = <<<PROMPT
Este é um formulário de frete da Altoé Transportes. Extraia APENAS as linhas da seção "ABASTECIMENTO (AV)(AP)".

Estrutura da tabela (campos pré-impressos — ignore eles, leia só os valores manuscritos ao lado):
  DATA | KM | POSTO | L (litros) | R$ (valor total)

Regras importantes:
- "DATA:", "KM:", "POSTO:", "L", "R$" são labels pré-impressos — NÃO são dados
- Datas no formato dd/mm/aa ou dd/mm/aaaa
- KM é o odômetro (número inteiro, pode ter ponto separador de milhar ex: 84.971 = 84971)
- Litros: número decimal sem unidade (ex: 100, 68.6, 428)
- Valor total em reais: número decimal (ex: 575.00, 4106.78)
- Se uma linha estiver em branco no formulário, ignore
- NÃO invente linhas que não existem na imagem

OCR de apoio (pode conter erros):
PROMPT
        . "\n" . $ocrSnippet
        . "\n\nRetorne JSON único com este schema exato:\n"
        . '{"abastecimentos":[{"data":"dd/mm/aaaa","km":0,"posto":"nome do posto","litros":0.0,"valor_total":0.00}]}';
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
        'temperature' => 0.05,
        'max_tokens' => 4096,
    ];
    $ch = curl_init(sf_deepseek_chat_completions_url());
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($raw) || $raw === '') {
        return ['rows' => [], 'error' => 'empty_response', 'usage' => [0, 0]];
    }
    $resp = json_decode($raw, true);
    if (!is_array($resp) || $http < 200 || $http >= 300) {
        return ['rows' => [], 'error' => 'http_' . $http, 'usage' => [0, 0]];
    }
    $usage = is_array($resp['usage'] ?? null)
        ? [(int) ($resp['usage']['prompt_tokens'] ?? 0), (int) ($resp['usage']['completion_tokens'] ?? 0)]
        : [0, 0];
    $content = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        return ['rows' => [], 'error' => 'json_parse', 'usage' => $usage];
    }
    $list = $parsed['abastecimentos'] ?? $parsed['rows'] ?? (isset($parsed[0]) ? $parsed : null);
    if (!is_array($list)) {
        return ['rows' => [], 'error' => 'shape', 'usage' => $usage];
    }
    $rows = [];
    $seen = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $row = ia_normalize_row($item);
        if ($row === null) {
            continue;
        }
        $k = $row['ymd'] . '|' . $row['km'] . '|' . $row['posto'] . '|' . $row['litros'] . '|' . $row['valorTotal'];
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $rows[] = $row;
    }

    return ['rows' => $rows, 'error' => '', 'usage' => $usage];
}

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');
require_authentication();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_json_method_not_allowed('Use POST.');
}
api_require_csrf_json();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    api_json_error('ID da empresa não encontrado', 400);
}
$conn = getConnection();
$googleVisionApiKey = trim((string) getenv('SF_GOOGLE_VISION_API_KEY'));
if ($googleVisionApiKey === '') {
    try {
        $conn->exec('ALTER TABLE configuracoes ADD COLUMN google_vision_api_key TEXT NULL DEFAULT NULL');
    } catch (Throwable $e) {
    }
    try {
        $stmtGv = $conn->prepare('SELECT google_vision_api_key FROM configuracoes WHERE empresa_id = :e LIMIT 1');
        $stmtGv->execute([':e' => (int) $empresa_id]);
        $rowGv = $stmtGv->fetch(PDO::FETCH_ASSOC) ?: [];
        $googleVisionApiKey = trim((string) ($rowGv['google_vision_api_key'] ?? ''));
    } catch (Throwable $e) {
        $googleVisionApiKey = '';
    }
}

$binary = '';
$mime = 'image/jpeg';
if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
    $binary = (string) file_get_contents($_FILES['image']['tmp_name']);
    $mime = strtolower((string) ($_FILES['image']['type'] ?? 'image/jpeg'));
} else {
    $rawBody = (string) file_get_contents('php://input');
    $input = json_decode($rawBody, true);
    if (is_array($input) && !empty($input['image_base64'])) {
        $decoded = base64_decode((string) preg_replace('#^data:image/[^;]+;base64,#', '', (string) $input['image_base64']), true);
        if ($decoded !== false) {
            $binary = $decoded;
            $mime = isset($input['mime_type']) ? strtolower((string) $input['mime_type']) : 'image/jpeg';
        }
    }
}
if ($binary === '') {
    api_json_error('Envie imagem em multipart "image" ou JSON com "image_base64".', 400);
}
[$binary, $mime] = ia_preprocess_image($binary, $mime);
$passes = ia_build_passes_with_roi($binary, $mime);

$warnings = [];
$rows = [];
$ocrText = '';
$bestOcrTextLen = 0;
$googleErrors = [];
$deepseekError = 'no_key';
$key = function_exists('sf_deepseek_api_key') ? sf_deepseek_api_key() : '';
$bestSource = 'tesseract-server';

foreach ($passes as $pass) {
    $passBinary = (string) $pass['binary'];
    $passMime = (string) $pass['mime'];
    $passTag = (string) $pass['tag'];
    $dataUrl = 'data:' . $passMime . ';base64,' . base64_encode($passBinary);

    $google = ia_extract_google_vision_text($passBinary, $googleVisionApiKey);
    $rowsGoogle = [];
    $passOcrText = '';
    if ($google['text'] !== '') {
        $googleParsed = ia_parse_rows_from_ocr_text($google['text']);
        if ($googleParsed['rows'] === []) {
            $googleParsed = ia_parse_rows_from_ocr_text_relaxed($google['text']);
        }
        if ($googleParsed['rows'] === []) {
            $googleParsed = ia_parse_rows_from_ocr_text_chunked($google['text']);
        }
        if ($googleParsed['rows'] === []) {
            $googleParsed = ia_parse_rows_from_abastecimento_block($google['text']);
        }
        if ($googleParsed['rows'] === [] && !empty($google['lines'])) {
            $lineText = implode("\n", $google['lines']);
            $googleParsed = ia_parse_rows_from_ocr_text($lineText);
            if ($googleParsed['rows'] === []) {
                $googleParsed = ia_parse_rows_from_ocr_text_relaxed($lineText);
            }
            if ($googleParsed['rows'] === []) {
                $googleParsed = ia_parse_rows_from_ocr_text_chunked($lineText);
            }
            if ($googleParsed['rows'] === []) {
                $googleParsed = ia_parse_rows_from_abastecimento_block($lineText);
            }
        }
        $rowsGoogle = $googleParsed['rows'];
        $passOcrText = $googleParsed['text'];
    }

    $tesseract = ia_extract_tesseract_rows($passBinary);
    $rowsTesseract = $tesseract['rows'];
    if ($passOcrText === '' || strlen($tesseract['text']) > strlen($passOcrText)) {
        $passOcrText = $tesseract['text'];
    }
    $rowsDeepseek = [];
    $deepseekErrPass = 'no_key';
    if ($key !== '') {
        $deepseek = ia_extract_deepseek_rows($key, $dataUrl, $passOcrText);
        $rowsDeepseek = $deepseek['rows'];
        $deepseekErrPass = $deepseek['error'];
        try {
            sf_openai_log_usage($conn, $empresa_id, function_exists('sf_deepseek_refuel_model') ? sf_deepseek_refuel_model() : 'deepseek-chat', $deepseek['usage'][0], $deepseek['usage'][1]);
        } catch (Throwable $e) {
        }
    }
    $candidateRows = ia_merge_rows($rowsDeepseek, ia_merge_rows($rowsGoogle, $rowsTesseract));
    if ($passOcrText !== '' && strlen($passOcrText) > $bestOcrTextLen) {
        $ocrText = $passOcrText;
        $bestOcrTextLen = strlen($passOcrText);
    }
    if (count($candidateRows) > count($rows)) {
        $rows = $candidateRows;
        $ocrText = $passOcrText;
        $bestOcrTextLen = strlen($passOcrText);
        $deepseekError = $deepseekErrPass;
        if ($rowsDeepseek !== []) {
            $bestSource = 'deepseek-hybrid';
        } elseif ($rowsGoogle !== []) {
            $bestSource = 'google-vision';
        } else {
            $bestSource = 'tesseract-server';
        }
    }
    if (strpos($passTag, 'roi') === 0 && $candidateRows !== []) {
        if ($passTag === 'roi') {
            $warnings[] = 'Recorte automático da área de abastecimento aplicado para melhorar leitura.';
        } else {
            $warnings[] = 'Recorte automático variante "' . $passTag . '" aplicado para melhorar leitura.';
        }
    }
    if ($google['error'] !== '' && $google['error'] !== 'no_key') {
        $warnings[] = 'Google Vision indisponível nesta tentativa: ' . $google['error'];
        $googleErrors[] = $google['error'];
    }
}

if ($rows === []) {
    // última tentativa no melhor texto OCR consolidado
    if ($ocrText !== '') {
        $lastTry = ia_parse_rows_from_ocr_text_chunked($ocrText);
        if ($lastTry['rows'] === []) {
            $lastTry = ia_parse_rows_from_ocr_text_relaxed($ocrText);
        }
        if ($lastTry['rows'] === []) {
            $lastTry = ia_parse_rows_from_abastecimento_block($ocrText);
        }
        if ($lastTry['rows'] !== []) {
            $rows = $lastTry['rows'];
            $bestSource = $bestSource === 'tesseract-server' ? 'ocr-relaxed' : $bestSource;
            $warnings[] = 'Aplicado parser tolerante em texto OCR corrido.';
        }
    }
}

if ($rows === []) {
    if ($ocrText !== '') {
        $draftRows = ia_build_draft_rows_from_text($ocrText);
        if ($draftRows !== []) {
            $rows = $draftRows;
            $bestSource = 'ocr-draft';
            $warnings[] = 'Linhas em modo rascunho (OCR incerto). Revise e edite os campos antes de usar.';
        }
    }
}

if ($rows === []) {
    $msg = 'Não foi possível extrair abastecimentos com confiança. Tente foto mais nítida ou CSV/JSON.';
    if ($googleErrors !== []) {
        $msg .= ' Detalhe Google: ' . implode(' | ', array_values(array_unique($googleErrors)));
    }
    api_json_error(
        $msg,
        422,
        'import_no_rows',
        [
            'ocr_text' => $ocrText,
            'deepseek_error' => $deepseekError,
            'google_errors' => array_values(array_unique($googleErrors)),
            'warnings' => array_values(array_unique($warnings)),
        ]
    );
}
foreach ($rows as $r) {
    if (($r['valorLitro'] ?? 0) < 1.5 || ($r['valorLitro'] ?? 0) > 12.5) {
        $warnings[] = 'Linha "' . ($r['_label'] ?? 'sem rótulo') . '" com valor/litro fora da faixa típica.';
    }
}

api_json_send([
    'success' => true,
    'rows' => $rows,
    'source' => $bestSource,
    'provider' => $bestSource === 'deepseek-hybrid'
        ? 'deepseek'
        : ($bestSource === 'google-vision' ? 'google-vision' : 'local-ocr'),
    'warnings' => array_values(array_unique($warnings)),
    'ocr_text' => $ocrText,
]);