<?php
/**
 * Resumo executivo de fornecedores por IA.
 * Recebe (POST JSON) os insights já carregados (qualidade + ranking) e pede à IA
 * configurada um parágrafo executivo em pt-BR.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_vision_ai.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
api_require_csrf_json();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$conn = getConnection();
$cfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id, 'text') : null;
if ($cfg === null) {
    api_json_error('Nenhuma IA configurada. Vá em Configurações → IA de Visão e cadastre uma chave (ex.: Gemini, gratuito).', 422, 'ia_nao_configurada');
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

$system = 'Você é um analista de compras/suprimentos no Brasil. Escreva um resumo executivo curto (3 a 5 frases) em português do Brasil, '
    . 'baseado SOMENTE nos dados fornecidos. Não invente números. Destaque: problemas de cadastro (documentos inválidos/duplicados, '
    . 'sem e-mail ou sem IBGE) e os fornecedores de maior gasto (concentração de dependência). Termine com uma recomendação prática. '
    . 'Sem markdown nem títulos, apenas o parágrafo.';

$payload = [
    'model' => $cfg['model'],
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => "Insights de fornecedores (JSON):\n{$dadosJson}"],
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
    echo json_encode(['success' => true, 'resumo' => $texto, 'provider' => $cfg['label'], 'model' => $cfg['model']]);
} catch (Throwable $e) {
    error_log('api/fornecedor_resumo_ia.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar o resumo.', 500);
}
