<?php
/**
 * Assistente IA do motorista (perguntas sobre viagem/jornada).
 * POST { "pergunta": "...", "rota_id": 123? }
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    api_error('Método não permitido.', 405);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    api_error('JSON inválido.');
}
$pergunta = trim((string) ($body['pergunta'] ?? ''));
if ($pergunta === '') {
    api_error('Informe a pergunta.');
}
$rota_id = !empty($body['rota_id']) ? (int) $body['rota_id'] : null;

try {
    $conn = getConnection();
    $ctx = [];

    $jr = $conn->prepare('
        SELECT tipo, data_hora FROM jornada_motorista
        WHERE empresa_id = :e AND motorista_id = :m AND DATE(data_hora) = CURDATE()
        ORDER BY data_hora DESC LIMIT 20
    ');
    $jr->execute([':e' => $empresa_id, ':m' => $motorista_id]);
    $ctx['jornada_hoje'] = $jr->fetchAll(PDO::FETCH_ASSOC);

    if ($rota_id) {
        $sr = $conn->prepare('
            SELECT r.id, c1.nome AS origem, c2.nome AS destino, r.total_km, r.frete_valor, v.placa
            FROM rotas r
            LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
            LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            WHERE r.id = :id AND r.empresa_id = :e AND r.motorista_id = :m
        ');
        $sr->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
        $ctx['rota'] = $sr->fetch(PDO::FETCH_ASSOC) ?: null;

        $sd = $conn->prepare('
            SELECT COALESCE(SUM(total_despviagem), 0) AS total
            FROM despesas_viagem WHERE rota_id = :r AND empresa_id = :e
        ');
        $sd->execute([':r' => $rota_id, ':e' => $empresa_id]);
        $ctx['despesas_rota'] = (float) ($sd->fetchColumn() ?: 0);

        $sa = $conn->prepare('SELECT COUNT(*) FROM abastecimentos WHERE rota_id = :r AND empresa_id = :e');
        $sa->execute([':r' => $rota_id, ':e' => $empresa_id]);
        $ctx['abastecimentos_rota'] = (int) $sa->fetchColumn();
    }

    $resposta = motorista_assistente_responder($pergunta, $ctx, $empresa_id);
    api_success(['resposta' => $resposta]);
} catch (PDOException $e) {
    error_log('motorista_assistente.php: ' . $e->getMessage());
    api_error('Erro ao consultar assistente.', 500);
}

function motorista_assistente_responder(string $pergunta, array $ctx, int $empresa_id): string
{
    $p = mb_strtolower($pergunta, 'UTF-8');
    $rota = $ctx['rota'] ?? null;

    if ($rota && (str_contains($p, 'km') || str_contains($p, 'distância') || str_contains($p, 'distancia'))) {
        $km = $rota['total_km'] ?? null;
        return $km ? 'Distância prevista da rota: ' . round((float) $km) . ' km.' : 'KM previsto não informado na rota.';
    }
    if ($rota && (str_contains($p, 'gastei') || str_contains($p, 'despesa'))) {
        $t = $ctx['despesas_rota'] ?? 0;
        return 'Despesas registradas nesta rota: R$ ' . number_format($t, 2, ',', '.') . '.';
    }
    if (str_contains($p, 'abastec')) {
        $n = $ctx['abastecimentos_rota'] ?? 0;
        return $n > 0 ? "Abastecimentos nesta rota: $n." : 'Nenhum abastecimento registrado nesta rota ainda.';
    }
    if (str_contains($p, 'jornada') || str_contains($p, 'descanso') || str_contains($p, 'dirig')) {
        $ev = $ctx['jornada_hoje'] ?? [];
        return empty($ev)
            ? 'Nenhum evento de jornada registrado hoje.'
            : 'Hoje: ' . count($ev) . ' evento(s) de jornada. Último: ' . ($ev[0]['tipo'] ?? '—') . ' às ' . ($ev[0]['data_hora'] ?? '—') . '.';
    }
    if ($rota && (str_contains($p, 'destino') || str_contains($p, 'para onde'))) {
        return 'Destino: ' . ($rota['destino'] ?? '—') . ' · Origem: ' . ($rota['origem'] ?? '—') . '.';
    }

    $root = dirname(__DIR__, 2);
    $openai = $root . '/includes/sf_openai_resolve.php';
    if (is_file($openai)) {
        require_once $openai;
        $prompt = "Você é assistente de um motorista de frota no Brasil. Responda em português, curto e prático.\n"
            . "Contexto JSON: " . json_encode($ctx, JSON_UNESCAPED_UNICODE) . "\n"
            . "Pergunta: $pergunta";
        if (function_exists('sf_openai_chat_completion')) {
            $ia = sf_openai_chat_completion(
                $prompt,
                200,
                $empresa_id,
                'Você é assistente de um motorista de frota no Brasil. Responda em português, curto e prático.'
            );
            if (is_string($ia) && trim($ia) !== '') {
                return trim($ia);
            }
        }
    }

    return 'Posso ajudar com km, despesas, abastecimentos, jornada e destino da viagem.';
}
