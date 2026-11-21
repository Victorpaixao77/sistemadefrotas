<?php
/**
 * Resumo executivo dos pneus de um veículo (Gestão Interativa Pro), gerado por IA.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/api_json.php';
require_once __DIR__ . '/../includes/sf_vision_ai.php';

configure_session();
session_start();

header('Content-Type: application/json; charset=utf-8');
require_authentication();
api_require_csrf_json();

if (!can_access_tire_management()) {
    api_json_error('Sem permissão.', 403);
}

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    api_json_error('Não autorizado.', 401);
}

// Libera o lock de sessão antes da chamada à IA (que pode levar dezenas de segundos),
// senão todas as outras requisições da página ficam bloqueadas nesse período.
if (function_exists('session_write_close')) {
    session_write_close();
}

$body = json_decode(file_get_contents('php://input'), true);
$veiculoId = is_array($body) ? (int) ($body['veiculo_id'] ?? 0) : 0;
if ($veiculoId < 1) {
    api_json_error('veiculo_id obrigatório.', 422);
}

$conn = getConnection();
$cfg = function_exists('sf_vision_ai_resolve') ? sf_vision_ai_resolve($conn, $empresa_id, 'text') : null;
if ($cfg === null) {
    api_json_error('Nenhuma IA configurada. Vá em Configurações → IA de Visão e cadastre uma chave.', 422, 'ia_nao_configurada');
}

try {
    // Veículo
    $sv = $conn->prepare("SELECT placa, modelo, km_atual FROM veiculos WHERE id = :v AND empresa_id = :e LIMIT 1");
    $sv->execute([':v' => $veiculoId, ':e' => $empresa_id]);
    $veic = $sv->fetch(PDO::FETCH_ASSOC);
    if (!$veic) {
        api_json_error('Veículo não encontrado.', 404);
    }

    // Pneus instalados no veículo + último sulco
    $sql = "SELECT p.numero_serie, p.marca, p.modelo, p.medida, p.dot, p.numero_recapagens, p.sulco_inicial,
                ev.tipo_veiculo, ev.numero_eixo,
                (SELECT s.sulco_mm FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS sulco_atual,
                (SELECT s.data_medicao FROM pneu_medicoes_sulco s WHERE s.pneu_id = p.id ORDER BY s.data_medicao DESC LIMIT 1) AS ult_medicao
            FROM pneus p
            JOIN alocacoes_pneus_flexiveis apf ON apf.pneu_id = p.id AND apf.ativo = 1 AND apf.empresa_id = :e2
            JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id AND ev.veiculo_id = :v
            WHERE p.empresa_id = :e AND p.status_id <> 3
            ORDER BY ev.tipo_veiculo, ev.numero_eixo";
    $st = $conn->prepare($sql);
    $st->execute([':e' => $empresa_id, ':e2' => $empresa_id, ':v' => $veiculoId]);

    $pneus = [];
    $criticos = $atencao = $semMedicao = 0;
    $somaSulco = 0.0; $comSulco = 0;
    $limMed = (new DateTime())->modify('-60 days');
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $sulco = $r['sulco_atual'] !== null ? (float) $r['sulco_atual'] : null;
        if ($sulco !== null) { $somaSulco += $sulco; $comSulco++; }
        if ($sulco !== null && $sulco <= 3.0) $criticos++;
        elseif ($sulco !== null && $sulco <= 5.0) $atencao++;
        $um = $r['ult_medicao'] ? new DateTime($r['ult_medicao']) : null;
        if ($um === null || $um < $limMed) $semMedicao++;
        $pneus[] = [
            'serie' => $r['numero_serie'],
            'pos' => (($r['tipo_veiculo'] === 'caminhao') ? 'Cavalo' : 'Carreta') . ' eixo ' . (int) $r['numero_eixo'],
            'medida' => $r['medida'],
            'sulco_mm' => $sulco,
            'vida' => ((int) $r['numero_recapagens']) + 1,
            'dot' => $r['dot'],
        ];
    }

    if (!count($pneus)) {
        api_json_error('Este veículo não tem pneus instalados para analisar.', 422);
    }

    $dados = [
        'veiculo' => ['placa' => $veic['placa'], 'modelo' => $veic['modelo'], 'km_atual' => (int) ($veic['km_atual'] ?? 0)],
        'resumo' => [
            'instalados' => count($pneus),
            'criticos' => $criticos,
            'em_atencao' => $atencao,
            'sem_medicao' => $semMedicao,
            'sulco_medio_mm' => $comSulco > 0 ? round($somaSulco / $comSulco, 1) : null,
        ],
        'pneus' => $pneus,
    ];

    $dadosJson = json_encode($dados, JSON_UNESCAPED_UNICODE) ?: '{}';
    if (strlen($dadosJson) > 12000) {
        $dadosJson = substr($dadosJson, 0, 12000) . ' …(truncado)';
    }

    $system = 'Você é um especialista em gestão de pneus de frotas no Brasil. Analise os pneus de UM veículo, baseado SOMENTE nos dados fornecidos. Não invente números. '
        . 'Responda EXATAMENTE neste formato em português do Brasil, sem markdown:' . "\n"
        . 'RESUMO: <2 a 3 frases sobre o estado geral dos pneus do veículo>' . "\n"
        . 'ACOES:' . "\n"
        . '- <ação prática e específica, ex.: "Trocar o pneu 123 (sulco crítico)" ou "Calibrar os pneus do eixo de tração">' . "\n"
        . '- <outra ação>' . "\n"
        . '(liste de 2 a 6 ações concretas, priorizando críticos, sem medição recente, rodízio para equilibrar desgaste e idade DOT. Se não houver ação necessária, escreva "- Nenhuma ação urgente.")';

    $payload = [
        'model' => $cfg['model'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => "Dados dos pneus do veículo (JSON):\n{$dadosJson}"],
        ],
        'max_tokens' => 500,
        'temperature' => 0.3,
    ];

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
    $text = trim((string) ($resp['choices'][0]['message']['content'] ?? ''));
    if ($text === '') {
        api_json_error('A IA não retornou texto.', 502);
    }

    // Separa o RESUMO das AÇÕES (tolerante a variações de acento/maiúsculas).
    $resumo = $text;
    $acoes = [];
    if (preg_match('/\bA[ÇC][ÕO]ES\s*:/iu', $text, $mm, PREG_OFFSET_CAPTURE)) {
        $pos = $mm[0][1];
        $resumo = trim((string) substr($text, 0, $pos));
        $bloco = substr($text, $pos + strlen($mm[0][0]));
        foreach (preg_split('/\r?\n/', $bloco) as $linha) {
            $linha = trim($linha);
            $linha = preg_replace('/^[-•*\d\.\)\s]+/u', '', $linha);
            if ($linha !== '' && mb_stripos($linha, 'nenhuma ação') === false) $acoes[] = $linha;
        }
    }
    $resumo = trim(preg_replace('/^RESUMO\s*:/iu', '', $resumo));

    echo json_encode(['success' => true, 'resumo' => $resumo, 'acoes' => $acoes, 'meta' => $dados['resumo']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('gestao_pneu_resumo_ia: ' . $e->getMessage());
    api_json_error('Não foi possível gerar o resumo.', 500);
}
