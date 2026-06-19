<?php
/**
 * Helper de OCR por IA de visão (documento único), reutilizável.
 *
 * Resolve o provedor de visão na mesma precedência do web (empresa_vision_ai →
 * chave OpenAI por empresa/env → DeepSeek), pré-processa a imagem e faz a chamada
 * /chat/completions com image_url, devolvendo o conteúdo textual (esperado: JSON).
 *
 * Não imprime nada: quem chama decide o formato da resposta.
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/sf_vision_ai.php';
require_once __DIR__ . '/sf_openai_resolve.php';

if (!function_exists('sf_vision_ocr_resolve')) {
    /**
     * Config efetiva de visão para a empresa.
     * @return array{provider:string,label:string,key:string,model:string,url:string}|null
     */
    function sf_vision_ocr_resolve(PDO $conn, int $empresa_id): ?array
    {
        // 1) Config por empresa (Gemini/OpenAI/OpenRouter/custom).
        $cfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id) : null;
        if ($cfg !== null) {
            return $cfg;
        }
        // 2) Chave OpenAI (env ou por empresa no banco).
        $openaiKey = function_exists('sf_openai_key_resolved') ? sf_openai_key_resolved($conn, $empresa_id) : '';
        if ($openaiKey !== '') {
            $model = function_exists('sf_openai_vision_model_resolved')
                ? sf_openai_vision_model_resolved($conn, $empresa_id)
                : (function_exists('sf_openai_vision_model') ? sf_openai_vision_model() : 'gpt-4o-mini');
            return [
                'provider' => 'openai',
                'label' => 'OpenAI',
                'key' => $openaiKey,
                'model' => $model,
                'url' => 'https://api.openai.com/v1/chat/completions',
            ];
        }
        // 3) DeepSeek legado.
        $deepseekKey = function_exists('sf_deepseek_api_key') ? sf_deepseek_api_key() : '';
        if ($deepseekKey !== '') {
            return [
                'provider' => 'deepseek',
                'label' => 'DeepSeek',
                'key' => $deepseekKey,
                'model' => function_exists('sf_deepseek_refuel_model') ? sf_deepseek_refuel_model() : 'deepseek-chat',
                'url' => function_exists('sf_deepseek_chat_completions_url') ? sf_deepseek_chat_completions_url() : 'https://api.deepseek.com/v1/chat/completions',
            ];
        }
        return null;
    }
}

if (!function_exists('sf_vision_ocr_prepare_image')) {
    /**
     * Escala/normaliza a imagem para a Vision API (menos tokens). Saída JPEG.
     * Se GD não estiver disponível, devolve o original.
     * @return array{0:string,1:string} [binary, mime]
     */
    function sf_vision_ocr_prepare_image(string $binary, string $mime): array
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
        $maxEdge = 1600;
        $long = max($w, $h);
        if ($long > $maxEdge) {
            $scale = $maxEdge / $long;
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
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
}

if (!function_exists('sf_vision_ocr_call')) {
    /**
     * Chama o provedor de visão com prompt system + user + imagem.
     * @return array{ok:bool,content:string,error:string,http:int,usage:array{0:int,1:int}}
     */
    function sf_vision_ocr_call(array $cfg, string $dataUrl, string $system, string $userText): array
    {
        $jsonFlags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $payload = [
            'model' => $cfg['model'],
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
        $ch = curl_init($cfg['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, $jsonFlags),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 90,
        ]);
        $raw = curl_exec($ch);
        $curlErr = curl_error($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            return ['ok' => false, 'content' => '', 'error' => $curlErr !== '' ? $curlErr : 'resposta vazia', 'http' => $http, 'usage' => [0, 0]];
        }
        $response = json_decode((string) $raw, true);
        if (!is_array($response)) {
            return ['ok' => false, 'content' => '', 'error' => 'resposta inválida do provedor', 'http' => $http, 'usage' => [0, 0]];
        }
        if ($http < 200 || $http >= 300) {
            $errObj = $response['error'] ?? ($response[0]['error'] ?? null);
            $apiErrMsg = is_array($errObj) ? (string) ($errObj['message'] ?? '') : '';
            return ['ok' => false, 'content' => '', 'error' => $apiErrMsg !== '' ? $apiErrMsg : ('HTTP ' . $http), 'http' => $http, 'usage' => [0, 0]];
        }
        $content = trim((string) ($response['choices'][0]['message']['content'] ?? ''));
        if ($content === '') {
            return ['ok' => false, 'content' => '', 'error' => 'provedor não retornou conteúdo', 'http' => $http, 'usage' => [0, 0]];
        }
        $u = is_array($response['usage'] ?? null) ? $response['usage'] : [];
        return [
            'ok' => true,
            'content' => $content,
            'error' => '',
            'http' => $http,
            'usage' => [(int) ($u['prompt_tokens'] ?? 0), (int) ($u['completion_tokens'] ?? 0)],
        ];
    }
}

if (!function_exists('sf_vision_ocr_decode_json')) {
    /** Decodifica o conteúdo da IA, tolerando cercas markdown ```json. */
    function sf_vision_ocr_decode_json(string $content): ?array
    {
        $c = trim($content);
        if (strncmp($c, '```', 3) === 0) {
            $c = preg_replace('/^```[a-zA-Z]*\s*/', '', $c);
            $c = preg_replace('/\s*```$/', '', (string) $c);
        }
        $parsed = json_decode((string) $c, true);
        return is_array($parsed) ? $parsed : null;
    }
}

if (!function_exists('sf_vision_ocr_to_float')) {
    /** "1.234,56" / "1234.56" / "428" → float, ou null. */
    function sf_vision_ocr_to_float($value): ?float
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
        return is_numeric($s) ? (float) $s : null;
    }
}

if (!function_exists('sf_vision_ocr_to_int')) {
    /** Inteiro só com dígitos (remove separador de milhar). */
    function sf_vision_ocr_to_int($value): ?int
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
}

if (!function_exists('sf_vision_ocr_to_ymd')) {
    /** "dd/mm/aa(aa)" ou "aaaa-mm-dd" → "Y-m-d", ou null. */
    function sf_vision_ocr_to_ymd($value): ?string
    {
        $s = trim((string) ($value ?? ''));
        if ($s === '') {
            return null;
        }
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2}|\d{4})/', $s, $m)) {
            $d = (int) $m[1];
            $mo = (int) $m[2];
            $y = (int) $m[3];
            if ($y < 100) {
                $y += 2000;
            }
            if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $s, $m2)) {
            return sprintf('%04d-%02d-%02d', (int) $m2[1], (int) $m2[2], (int) $m2[3]);
        }
        return null;
    }
}
