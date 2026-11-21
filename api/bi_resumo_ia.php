<?php
/**
 * Resumo executivo do painel BI gerado por IA.
 *
 * Recebe (POST JSON) os números já carregados no front + contexto (visão/ano/mês)
 * e pede à IA configurada (Configurações → IA de Visão: Gemini/OpenAI/OpenRouter)
 * um parágrafo executivo em pt-BR. Não recomputa nada no servidor.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/sf_vision_ai.php';
require_once '../includes/bi_api_helpers.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }
if (function_exists('require_permission')) {
    require_permission('access_advanced_reports');
}
api_require_csrf_json();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if (!$empresa_id) {
    api_json_error('Não autorizado.', 401);
}

$force = !empty($body['force']);
if (!$force) {
    bi_resumo_ia_rate_limit($empresa_id);
}

$conn = getConnection();
$cfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id, 'text') : null;
if ($cfg === null) {
    api_json_error('Nenhuma IA configurada. Vá em Configurações → IA de Visão e cadastre uma chave (ex.: Gemini, gratuito).', 422, 'ia_nao_configurada');
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    api_json_error('Corpo inválido.', 422);
}

$visao = preg_replace('/[^a-z_]/', '', strtolower((string) ($body['visao'] ?? 'geral')));
$ano = preg_replace('/[^0-9]/', '', (string) ($body['ano'] ?? ''));
$mes = preg_replace('/[^0-9]/', '', (string) ($body['mes'] ?? ''));

// DTO compacto (não confia em campos arbitrários do cliente).
$dadosRaw = is_array($body['dados'] ?? null) ? $body['dados'] : [];
$dadosDto = bi_ia_dto_from_payload($dadosRaw);
$dadosJson = json_encode($dadosDto, JSON_UNESCAPED_UNICODE);
if (!is_string($dadosJson)) {
    $dadosJson = '{}';
}

$contexto = 'Visão: ' . ($visao ?: 'geral')
    . ($ano !== '' ? ' | Ano: ' . $ano : '')
    . ($mes !== '' ? ' | Mês: ' . $mes : ' | Período: ano todo');

$system = 'Você é um analista de gestão de frotas no Brasil. Escreva um resumo executivo curto e objetivo em português do Brasil, '
    . 'baseado SOMENTE nos dados fornecidos. Não invente números. Use 3 a 5 frases. Aponte tendências, variações relevantes '
    . '(vs mês/ano anterior se houver), destaques (melhor/pior veículo ou rota) e, se fizer sentido, uma recomendação prática. '
    . 'Formate valores em reais e quilômetros de forma legível. Não use markdown nem títulos, apenas o parágrafo.';

$userMsg = "Contexto do painel: {$contexto}\n\nDados (JSON):\n{$dadosJson}";

$payload = [
    'model' => $cfg['model'],
    'messages' => [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $userMsg],
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
        $detail = $msg !== '' ? ': ' . $msg : '.';
        if ($http === 429) {
            api_json_error(
                'Limite de requisições da IA atingido' . $detail . ' Aguarde alguns minutos ou revise o plano em Configurações → IA de Visão.',
                429,
                'ia_rate_limit'
            );
        }
        if ($http === 402) {
            api_json_error(
                'Créditos da IA insuficientes' . $detail . ' Verifique o saldo do provedor em Configurações → IA de Visão.',
                402,
                'ia_payment_required'
            );
        }
        if ($http === 401 || $http === 403) {
            api_json_error(
                'Chave de API da IA inválida ou sem permissão' . $detail . ' Atualize em Configurações → IA de Visão.',
                401,
                'ia_auth'
            );
        }
        api_json_error($cfg['label'] . ' retornou HTTP ' . $http . $detail, 502, 'ia_upstream');
    }

    $texto = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
    if ($texto === '') {
        api_json_error('A IA não retornou texto.', 502);
    }

    echo json_encode([
        'success' => true,
        'resumo' => $texto,
        'provider' => $cfg['label'],
        'model' => $cfg['model'],
    ]);
} catch (Throwable $e) {
    error_log('api/bi_resumo_ia.php: ' . $e->getMessage());
    api_json_error('Erro ao gerar o resumo.', 500);
}
