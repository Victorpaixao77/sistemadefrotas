<?php
/**
 * Streaming token-a-token do LLM (OpenAI-compatible SSE).
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_ia_router.php';

/**
 * Chama chat com stream=true; $onToken recebe cada pedaço de texto.
 * @return array{content:string,tokens:int,model:string}|null
 */
function sf_ia_router_chat_stream(PDO $conn, int $empresaId, string $task, array $messages, callable $onToken, int $maxTokens = 120, float $temperature = 0.3): ?array
{
    $cfg = sf_ia_router_resolve($conn, $empresaId, $task);
    if (!$cfg) {
        return null;
    }
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'max_tokens' => $maxTokens,
        'temperature' => $temperature,
        'stream' => true,
    ];
    $buffer = '';
    $full = '';
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_WRITEFUNCTION => static function ($ch, $data) use (&$buffer, &$full, $onToken) {
            $buffer .= $data;
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);
                if ($line === '' || $line === 'data: [DONE]') {
                    continue;
                }
                if (strpos($line, 'data: ') !== 0) {
                    continue;
                }
                $json = json_decode(substr($line, 6), true);
                $delta = (string) ($json['choices'][0]['delta']['content'] ?? '');
                if ($delta !== '') {
                    $full .= $delta;
                    $onToken($delta);
                }
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($full === '' && $err !== '') {
        return null;
    }
    return [
        'content' => trim($full),
        'tokens' => (int) max(10, strlen($full) / 4),
        'model' => $cfg['model'],
        'task' => $task,
    ];
}
