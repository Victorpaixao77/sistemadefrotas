<?php
/**
 * Configuração do provedor de IA de visão (leitura de fichas de abastecimento).
 *
 * GET  action=get   -> provider, model, base_url, has_key, key_masked, providers
 * POST action=save  -> salva provider/model/base_url/api_key (chave em branco = mantém)
 * POST action=test  -> faz uma chamada mínima ao provedor para validar a chave
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_vision_ai.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
require_permission('manage_system_settings');
api_require_csrf_json();

$empresa_id = (int) $_SESSION['empresa_id'];
$conn = getConnection();
sf_vision_ai_ensure_schema($conn);
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$purpose = sf_vision_ai_purpose_norm((string) ($_POST['purpose'] ?? $_GET['purpose'] ?? 'vision'));

function sf_vision_mask_key(string $k): string
{
    if ($k === '') {
        return '';
    }
    $tail = strlen($k) > 4 ? substr($k, -4) : '****';
    return '…' . $tail;
}

try {
    switch ($action) {
        case 'get':
            $row = sf_vision_ai_get($conn, $empresa_id, $purpose) ?: [];
            $key = trim((string) ($row['api_key'] ?? ''));
            echo json_encode([
                'success' => true,
                'data' => [
                    'purpose' => $purpose,
                    'provider' => $row['provider'] ?? ($purpose === 'text' ? 'deepseek' : 'gemini'),
                    'model' => $row['model'] ?? '',
                    'base_url' => $row['base_url'] ?? '',
                    'has_key' => $key !== '',
                    'key_masked' => sf_vision_mask_key($key),
                ],
                'providers' => sf_vision_ai_providers(),
            ]);
            break;

        case 'save':
            $providers = sf_vision_ai_providers();
            $provider = (string) ($_POST['provider'] ?? 'gemini');
            if (!isset($providers[$provider])) {
                api_json_error('Provedor inválido.', 422);
            }
            $model = trim((string) ($_POST['model'] ?? ''));
            $base_url = trim((string) ($_POST['base_url'] ?? ''));
            $newKey = trim((string) ($_POST['api_key'] ?? ''));
            $clear = isset($_POST['clear_key']) && in_array((string) $_POST['clear_key'], ['1', 'true', 'on'], true);

            if ($provider === 'custom' && $base_url === '') {
                api_json_error('Para "Personalizado" informe a Base URL (compatível com OpenAI).', 422);
            }
            if ($purpose === 'vision' && empty($providers[$provider]['vision'])) {
                api_json_error($providers[$provider]['label'] . ' não lê imagens; escolha outro provedor para a IA de Visão.', 422);
            }

            $existing = sf_vision_ai_get($conn, $empresa_id, $purpose);
            $keyToStore = $existing['api_key'] ?? '';
            if ($clear) {
                $keyToStore = '';
            } elseif ($newKey !== '') {
                $keyToStore = $newKey;
            }

            $stmt = $conn->prepare(
                'INSERT INTO empresa_vision_ai (empresa_id, purpose, provider, api_key, model, base_url)
                 VALUES (:e, :pu, :p, :k, :m, :b)
                 ON DUPLICATE KEY UPDATE provider = VALUES(provider), api_key = VALUES(api_key),
                    model = VALUES(model), base_url = VALUES(base_url)'
            );
            $stmt->execute([
                ':e' => $empresa_id,
                ':pu' => $purpose,
                ':p' => $provider,
                ':k' => $keyToStore !== '' ? $keyToStore : null,
                ':m' => $model !== '' ? $model : null,
                ':b' => $base_url !== '' ? $base_url : null,
            ]);
            echo json_encode(['success' => true]);
            break;

        case 'test':
            // Usa valores do formulário; se a chave vier vazia, usa a salva.
            $providers = sf_vision_ai_providers();
            $provider = (string) ($_POST['provider'] ?? 'gemini');
            if (!isset($providers[$provider])) {
                api_json_error('Provedor inválido.', 422);
            }
            $def = $providers[$provider];
            $key = trim((string) ($_POST['api_key'] ?? ''));
            if ($key === '') {
                $saved = sf_vision_ai_get($conn, $empresa_id, $purpose);
                $key = trim((string) ($saved['api_key'] ?? ''));
            }
            if ($key === '') {
                api_json_error('Informe a chave da API para testar.', 422);
            }
            $model = trim((string) ($_POST['model'] ?? '')) ?: ($def['model'] ?: 'gpt-4o');
            $base = trim((string) ($_POST['base_url'] ?? '')) ?: $def['base'];
            $base = rtrim($base, '/');
            if ($base === '') {
                api_json_error('Base URL ausente.', 422);
            }
            $url = (substr($base, -16) === '/chat/completions') ? $base : $base . '/chat/completions';

            $payload = [
                'model' => $model,
                'messages' => [['role' => 'user', 'content' => 'ping']],
                'max_tokens' => 1,
            ];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);
            $out = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($out === false || $out === '') {
                api_json_error('Falha de conexão: ' . ($curlErr !== '' ? $curlErr : 'sem resposta'), 502);
            }
            $j = json_decode($out, true);
            if ($http >= 200 && $http < 300) {
                echo json_encode(['success' => true, 'message' => 'Conexão OK com ' . $def['label'] . ' (modelo ' . $model . ').']);
            } else {
                $msg = '';
                $status = '';
                if (is_array($j)) {
                    $msg = (string) ($j['error']['message'] ?? ($j['error']['status'] ?? ''));
                    $status = (string) ($j['error']['status'] ?? '');
                }
                if ($msg === '') {
                    // Sem mensagem estruturada: devolve um trecho do corpo bruto para diagnóstico.
                    $snippet = trim(preg_replace('/\s+/', ' ', substr((string) $out, 0, 400)));
                    $msg = $snippet !== '' ? $snippet : '(corpo de resposta vazio)';
                }
                api_json_error(
                    $def['label'] . ' retornou HTTP ' . $http . ' [' . ($status !== '' ? $status : 'sem status') . ']: ' . $msg,
                    $http === 429 ? 402 : 400
                );
            }
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('api/vision_ai_config.php: ' . $e->getMessage());
    api_json_error('Erro ao processar a solicitação.', 500);
}
