<?php
/**
 * Resumo executivo de comissões gerado por IA.
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
api_require_csrf_json();

if (!can_view_financial_data()) {
    api_json_error('Sem permissão.', 403);
}

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$conn = getConnection();
$cfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id) : null;
if ($cfg === null) {
    api_json_error('Nenhuma IA configurada. Vá em Configurações → IA de Visão e cadastre uma chave.', 422, 'ia_nao_configurada');
}

$body = json_decode(file_get_contents('php://input'), true);
$dados = is_array($body) && isset($body['dados']) ? $body['dados'] : [];
$dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE);
if (!is_string($dadosJson)) {
    $dadosJson = '{}';
}
if (strlen($dadosJson) > 12000) {
    $dadosJson = substr($dadosJson, 0, 12000) . ' …(truncado)';
}

$system = 'Você é um analista financeiro de frotas no Brasil. Escreva um resumo executivo curto (3 a 5 frases) em português do Brasil, '
    . 'baseado SOMENTE nos dados fornecidos sobre comissões de motoristas. Não invente números. '
    . 'Destaque: valor total pendente, comissões antigas (muitos dias) e motoristas com maior valor em aberto. '
    . 'Termine com uma recomendação prática de priorização de pagamentos. Sem markdown, apenas o parágrafo.';

$payload = [
    'model' => $cfg['model'],
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => "Insights de comissões (JSON):\n{$dadosJson}"],
    ],
    'max_tokens' => 500,
    'temperature' => 0.3,
];

try {
    $ch = curl_init($cfg['url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $raw === '') {
        api_json_error('Falha ao contatar a IA (' . $cfg['label'] . '): ' . ($curlErr ?: 'sem resposta'), 502);
    }
    $resp = json_decode($raw, true);
    if ($http < 200 || $http >= 300) {
        $errObj = $resp['error'] ?? ($resp[0]['error'] ?? null);
        $msg = is_array($errObj) ? (string) ($errObj['message'] ?? '') : '';
        api_json_error($cfg['label'] . ' retornou HTTP ' . $http . ($msg !== '' ? ': ' . $msg : '.'), ($http === 429) ? 402 : 502);
    }
    $texto = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
    if ($texto === '') {
        api_json_error('A IA não retornou texto.', 502);
    }
    echo json_encode(['success' => true, 'resumo' => $texto], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('comissoes_resumo_ia: ' . $e->getMessage());
    api_json_error('Não foi possível gerar o resumo.', 500);
}
