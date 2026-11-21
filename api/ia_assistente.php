<?php
/**
 * IA Conversacional (assistente interno) — "ChatGPT da operação".
 *
 * SEGURANÇA: o modelo de linguagem NUNCA executa SQL. A pergunta é classificada numa
 * INTENÇÃO pré-definida e o backend roda a query PARAMETRIZADA correspondente, sempre
 * filtrada por empresa_id (só leitura). Duas camadas:
 *   (1) matcher por palavra-chave (sempre; funciona SEM IA);
 *   (2) classificador LLM (quando a empresa tem chave): roda em toda pergunta e PREVALECE
 *       quando devolve uma intenção válida — entende qualquer frase. Se o LLM não decidir
 *       (ou não houver chave), vale o resultado do matcher.
 * Assim funciona dos dois jeitos: sem IA (palavra-chave) e com IA (muito melhor).
 *
 * "Aprender sempre": perguntas não entendidas são registradas em
 * logs/ia_assistente_naoentendidas.log para você ver o que expandir.
 *
 * GET/POST: pergunta=<texto>
 * Retorno: { success, intent, periodo, answer, rows[], via }
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_vision_ai.php';
require_once __DIR__ . '/../includes/sf_ia_copilot.php';
require_once __DIR__ . '/../includes/sf_ia_platform.php';
require_once __DIR__ . '/../includes/sf_ia_tools.php';
require_once __DIR__ . '/../includes/sf_ia_metrics.php';
require_once __DIR__ . '/../includes/sf_ia_help.php';
require_once __DIR__ . '/../includes/sf_ia_router.php';
require_once __DIR__ . '/../includes/sf_ia_hybrid.php';
require_once __DIR__ . '/../includes/sf_ia_skills.php';
require_once __DIR__ . '/../includes/sf_ia_stream.php';
require_once __DIR__ . '/../includes/sf_ia_actions.php';
require_once __DIR__ . '/../includes/sf_saas.php';

configure_session();
session_start();
require_authentication();

if (defined('SF_IA_SSE') && SF_IA_SSE) {
    sf_ia_sse_headers();
} else {
    header('Content-Type: application/json; charset=utf-8');
}

$t0 = microtime(true);

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'pergunta'));
$pergunta = trim((string) ($_GET['pergunta'] ?? $_POST['pergunta'] ?? ''));

$moeda = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$num   = static fn($v) => number_format((float) $v, 0, ',', '.');

/* ---------- ações do Copiloto (sem pergunta) ---------- */
if ($action === 'insights') {
    try {
        $conn = getConnection();
        sf_ia_enforce_quota($conn, $empresa_id);
        $nome = (string) ($_SESSION['nome'] ?? 'gestor');
        echo json_encode(['success' => true, 'data' => sf_ia_copilot_insights($conn, $empresa_id, $nome)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente insights: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar insights.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'executivo') {
    try {
        $conn = getConnection();
        sf_ia_enforce_quota($conn, $empresa_id);
        $data = sf_ia_copilot_executivo($conn, $empresa_id, $moeda, $num);
        echo json_encode(['success' => true, 'intent' => 'executivo', 'answer' => $data['resumo'] ?? '', 'cards' => $data['cards'] ?? [], 'alertas' => $data['alertas'] ?? 0, 'fontes' => sf_ia_copilot_fontes('executivo')], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente executivo: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro no resumo executivo.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'feedback') {
    $util = ($_POST['util'] ?? '') === '1' ? 'sim' : 'nao';
    $intentFb = preg_replace('/[^a-z_]/', '', (string) ($_POST['intent'] ?? ''));
    $coment = substr(trim((string) ($_POST['comentario'] ?? '')), 0, 500);
    @file_put_contents(
        __DIR__ . '/../logs/ia_assistente_feedback.log',
        date('c') . "\tempresa={$empresa_id}\tutil={$util}\tintent={$intentFb}\t" . str_replace(["\n", "\t"], ' ', $coment) . "\n",
        FILE_APPEND
    );
    echo json_encode(['success' => true]);
    exit;
}

$usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);

if ($action === 'previsoes') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $data = sf_ia_previsoes($conn, $empresa_id, $moeda);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente previsoes: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar previsões.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'auditoria') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $lim = max(1, min(200, (int) ($_GET['limit'] ?? 50)));
        echo json_encode(['success' => true, 'data' => sf_ia_auditoria_list($conn, $empresa_id, $lim)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente auditoria: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar auditoria.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'historico') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        echo json_encode(['success' => true, 'data' => sf_ia_historico_list($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente historico: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar histórico.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'memoria') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/../includes/csrf.php';
            if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $prefs = [
                'foco' => substr(trim((string) ($_POST['foco'] ?? '')), 0, 200),
                'periodo_padrao' => substr(trim((string) ($_POST['periodo_padrao'] ?? '')), 0, 80),
                'notas' => substr(trim((string) ($_POST['notas'] ?? '')), 0, 1000),
            ];
            $ok = sf_ia_memoria_save($conn, $empresa_id, $prefs);
            echo json_encode(['success' => $ok], JSON_UNESCAPED_UNICODE);
            exit;
        }
        echo json_encode(['success' => true, 'data' => sf_ia_memoria_get($conn, $empresa_id)], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente memoria: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro na memória da empresa.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'sugerir_acoes') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $prev = sf_ia_previsoes($conn, $empresa_id, $moeda);
        $ids = sf_ia_sugerir_acoes_manutencao($conn, $empresa_id, $prev);
        $acoes = [];
        foreach ($ids as $id) {
            $a = sf_ia_acao_get($conn, $empresa_id, (int) $id);
            if ($a) {
                $acoes[] = [
                    'id' => (int) $a['id'],
                    'tipo' => $a['tipo'],
                    'titulo' => $a['titulo'],
                    'label' => sf_ia_acao_label_confirm((string) $a['tipo']),
                ];
            }
        }
        echo json_encode(['success' => true, 'criadas' => $ids, 'total' => count($ids), 'acoes' => $acoes], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente sugerir_acoes: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao sugerir ações.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'reindex_embeddings') {
    try {
        require_once __DIR__ . '/../includes/csrf.php';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_token_validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $lim = (int) ($_POST['limit'] ?? $_GET['limit'] ?? 500);
        $res = sf_ia_reindex_embeddings($conn, $empresa_id, $lim);
        echo json_encode(['success' => $res['ok'], 'data' => $res], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente reindex: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao reindexar documentos.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'saude') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        $data = sf_ia_metrics_saude($conn, $empresa_id);
        $data['uso_mes_empresa'] = sf_ia_metrics_uso_empresa($conn, $empresa_id);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente saude: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao carregar saúde da IA.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'playground') {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $pergPlay = trim((string) ($_GET['pergunta'] ?? $_POST['pergunta'] ?? ''));
        $suite = ($_GET['suite'] ?? $_POST['suite'] ?? '') === '1';
        $modelos = array_filter(array_map('trim', explode(',', (string) ($_GET['modelos'] ?? $_POST['modelos'] ?? 'gpt-4o-mini,gpt-4o'))));
        if ($modelos === []) {
            $modelos = ['gpt-4o-mini'];
        }
        if ($suite) {
            $bench = sf_ia_router_benchmark_suite_run($conn, $empresa_id, $modelos);
        } elseif ($pergPlay === '') {
            echo json_encode(['success' => false, 'error' => 'Informe uma pergunta.'], JSON_UNESCAPED_UNICODE);
            exit;
        } else {
            $bench = sf_ia_metrics_benchmark($conn, $empresa_id, $pergPlay, $modelos);
        }
        echo json_encode(['success' => true, 'data' => $bench], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente playground: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro no playground.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'skills') {
    try {
        echo json_encode(['success' => true, 'data' => sf_ia_skills_list_ui()], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao listar skills.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if ($action === 'propor_acao') {
    try {
        require_once __DIR__ . '/../includes/csrf.php';
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        $tipo = preg_replace('/[^a-z_]/', '', (string) ($_POST['tipo'] ?? ''));
        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $resumo = trim((string) ($_POST['resumo'] ?? ''));
        $params = json_decode((string) ($_POST['params'] ?? '{}'), true) ?: [];
        $allowed = ['exportar_relatorio', 'abrir_whatsapp', 'enviar_email', 'criar_ot', 'notificar_motorista', 'agendar_manutencao'];
        if (!in_array($tipo, $allowed, true) || $titulo === '') {
            echo json_encode(['success' => false, 'error' => 'Tipo inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = sf_ia_propor_acao($conn, $empresa_id, $usuario_id ?: null, $tipo, $titulo, $resumo, $params);
        echo json_encode(['success' => $id !== null, 'id' => $id], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        error_log('ia_assistente propor_acao: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erro ao propor ação.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* Multimodal: imagem no chat → OCR + pergunta */
if (!empty($_FILES['imagem']['tmp_name']) && is_uploaded_file($_FILES['imagem']['tmp_name'])) {
    try {
        $conn = getConnection();
        sf_ia_ensure_schema($conn);
        sf_ia_enforce_quota($conn, $empresa_id);
        $mime = (string) ($_FILES['imagem']['type'] ?? 'image/jpeg');
        $ocr = sf_ia_ocr_arquivo($conn, $empresa_id, $_FILES['imagem']['tmp_name'], $mime);
        $perguntaOcr = trim((string) ($_POST['pergunta'] ?? $_GET['pergunta'] ?? ''));
        if ($perguntaOcr === '') {
            $perguntaOcr = 'O que aparece nesta imagem? Resuma em português.';
        }
        $pergunta = $perguntaOcr . "\n\n[Texto extraído da imagem]\n" . mb_substr($ocr, 0, 3000);
        $action = 'pergunta';
    } catch (Throwable $e) {
        error_log('ia_assistente multimodal: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Não consegui ler a imagem.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($pergunta === '' && $action === 'pergunta') {
    if (defined('SF_IA_SSE') && SF_IA_SSE) {
        sf_ia_sse_send('error', ['error' => 'Pergunta vazia.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Pergunta vazia.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/* ---------- helpers ---------- */
$norm = static function (string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
    return strtr($s, $map);
};

/* ---------- catálogo de intenções (id => descrição p/ IA e ajuda) ---------- */
$INTENCOES = [
    'faturamento'    => 'faturamento, receita, lucro, quanto faturei/ganhei no período',
    'motorista_top'  => 'motorista que mais faturou, melhor motorista, ranking de motoristas',
    'no_prazo'       => 'quantas rotas chegaram no prazo, entregas pontuais, dentro do prazo',
    'atrasos'        => 'quem mais atrasou, rotas fora do prazo, quantas rotas atrasaram',
    'cnh'            => 'CNH vencendo/vencida, habilitação, documento do motorista vencendo',
    'consumo'        => 'consumo médio, km por litro, km/L da frota, eficiência de combustível',
    'km'             => 'quantos km rodei, quilometragem total no período',
    'rota_top'       => 'rota mais rentável, rota de maior frete, melhor rota',
    'cliente_top'    => 'cliente que mais gera frete, melhor cliente, cliente mais lucrativo',
    'veiculo_top'    => 'veículo/caminhão que mais rodou no período',
    'veiculo_parado' => 'veículo mais parado, ocioso, que menos rodou',
    'rotas_ativas'   => 'quantas rotas ativas/em andamento agora, viagens rodando neste momento',
    'rotas_mes'      => 'quantas rotas no mês/período, total de rotas, rotas aprovadas',
    'abastecimentos' => 'últimos abastecimentos, gastos com combustível recentes',
    'pedagio'        => 'gasto com pedágio no período',
    'despesas'       => 'despesas de viagem, quanto gastei em despesas',
    'multas'         => 'multas no período, quantas multas, valor de multas',
    'frota'          => 'quantos veículos/caminhões tenho, tamanho da frota',
    'manutencao'     => 'gasto com manutenção, quantas manutenções, manutenções abertas/em andamento, conserto, oficina, revisão',
    'contas_pagar'   => 'contas a pagar, quanto tenho a pagar, contas vencendo, boletos em aberto',
    'pneus'          => 'quantos pneus, pneus em uso, estoque de pneus',
    'implementos'    => 'quantos implementos, carretas, reboques',
    'analise_empresa'=> 'analise minha empresa, oportunidades, problemas, auditoria operacional',
    'glossario'      => 'o que significa custo/km, explicar indicador, como calcula',
    'previsoes'      => 'previsao, prever, forecast, tendencia, proximos 30 dias, manutencao preditiva',
    'documentos'     => 'documento enviado, nota fiscal anexada, arquivo que subi, contrato pdf',
    'mapa_frota'     => 'mapa da frota, onde estão os caminhões, posição GPS, localização dos veículos agora',
    'ajuda_sistema'  => 'como cadastrar, como funciona, manual, ajuda do sistema, onde fica, tutorial',
    'analise_completa'=> 'análise completa, scorecard, saúde da operação, diagnóstico geral, visão 360',
    'executivo'      => 'resumo executivo, visão executiva, painel executivo de hoje',
    'exportar_relatorio' => 'exportar relatório excel csv, baixar relatório de faturamento',
    'notificar_whatsapp' => 'enviar whatsapp para motorista, avisar motorista whatsapp',
    'criar_ot' => 'criar ordem de transporte, nova OT, cotação de frete',
    'notificar_motorista' => 'notificar motorista, avisar motorista no sistema, alertar motorista',
    'agendar_manutencao' => 'agendar manutenção, agendar revisão, marcar manutenção preventiva',
];

/**
 * Detecta o período a partir de um texto já normalizado (minúsculo, sem acento).
 * Entende: hoje/ontem, semana (passada), últimos N dias/meses, trimestre/semestre,
 * mês por nome, mês passado/atual, ano/ano passado/em YYYY. Padrão: últimos 12 meses.
 * @return array{0:string,1:string,2:string} [ini, fim, label]
 */
function sf_ia_periodo(string $q): array
{
    $meses = ['janeiro'=>1,'fevereiro'=>2,'marco'=>3,'abril'=>4,'maio'=>5,'junho'=>6,'julho'=>7,'agosto'=>8,'setembro'=>9,'outubro'=>10,'novembro'=>11,'dezembro'=>12];
    $anoCitado = preg_match('/\b(20\d{2})\b/', $q, $m) ? (int) $m[1] : null;
    $ano = $anoCitado ?: (int) date('Y');

    if (strpos($q, 'hoje') !== false) { $d = date('Y-m-d'); return [$d, $d, 'hoje']; }
    if (strpos($q, 'ontem') !== false) { $d = date('Y-m-d', strtotime('-1 day')); return [$d, $d, 'ontem']; }
    if (strpos($q, 'semana passada') !== false) return [date('Y-m-d', strtotime('monday last week')), date('Y-m-d', strtotime('sunday last week')), 'semana passada'];
    if (strpos($q, 'semana') !== false) return [date('Y-m-d', strtotime('monday this week')), date('Y-m-d', strtotime('sunday this week')), 'esta semana'];

    if (preg_match('/ultim[oa]s?\s+(\d{1,3})\s+dias/', $q, $m)) { $n = max(1, (int) $m[1]); return [date('Y-m-d', strtotime("-$n days")), date('Y-m-d'), "últimos $n dias"]; }
    if (preg_match('/ultim[oa]s?\s+(\d{1,2})\s+(meses|mes)/', $q, $m)) { $n = max(1, (int) $m[1]); return [date('Y-m-d', strtotime("-$n months")), date('Y-m-d'), "últimos $n meses"]; }

    if (strpos($q, 'trimestre') !== false) {
        $tri = 0;
        if (preg_match('/(primeiro|1)\D{0,10}trimestre/', $q)) $tri = 1;
        elseif (preg_match('/(segundo|2)\D{0,10}trimestre/', $q)) $tri = 2;
        elseif (preg_match('/(terceiro|3)\D{0,10}trimestre/', $q)) $tri = 3;
        elseif (preg_match('/(quarto|4)\D{0,10}trimestre/', $q)) $tri = 4;
        if ($tri) {
            $mi = ($tri - 1) * 3 + 1;
            $ini = sprintf('%04d-%02d-01', $ano, $mi);
            $fim = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $ano, $mi + 2)));
            return [$ini, $fim, "{$tri}º trimestre de $ano"];
        }
    }
    if (strpos($q, 'semestre') !== false) {
        if (preg_match('/(primeiro|1)\D{0,10}semestre/', $q)) return [sprintf('%04d-01-01', $ano), sprintf('%04d-06-30', $ano), "1º semestre de $ano"];
        if (preg_match('/(segundo|2)\D{0,10}semestre/', $q)) return [sprintf('%04d-07-01', $ano), sprintf('%04d-12-31', $ano), "2º semestre de $ano"];
    }
    foreach ($meses as $nome => $mn) {
        if (strpos($q, $nome) !== false) { $ini = sprintf('%04d-%02d-01', $ano, $mn); return [$ini, date('Y-m-t', strtotime($ini)), ucfirst($nome) . " de $ano"]; }
    }

    if (strpos($q, 'mes passado') !== false) return [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month')), 'mês passado'];
    if (strpos($q, 'mes atual') !== false || strpos($q, 'este mes') !== false || strpos($q, 'neste mes') !== false || strpos($q, 'do mes') !== false || strpos($q, 'no mes') !== false || strpos($q, 'mensal') !== false)
        return [date('Y-m-01'), date('Y-m-t'), 'mês atual'];

    if (strpos($q, 'ano passado') !== false) { $y = (int) date('Y') - 1; return ["$y-01-01", "$y-12-31", "ano $y"]; }
    if ($anoCitado || strpos($q, 'ano') !== false || strpos($q, 'anual') !== false)
        return [sprintf('%04d-01-01', $ano), sprintf('%04d-12-31', $ano), $anoCitado ? "ano $ano" : 'ano atual'];

    // Sem período dito: últimos 12 meses (evita "0" quando o mês corrente ainda não tem dados)
    return [date('Y-m-d', strtotime('-12 months')), date('Y-m-d'), 'últimos 12 meses'];
}

/**
 * Roda uma consulta de série (retornando [rótulo, valor] por linha) e devolve o formato
 * de gráfico para o front. Só retorna gráfico com >= 2 pontos (senão não vale a pena).
 * @return array{type:string,label:string,labels:array,values:array}|null
 */
function sf_ia_serie(PDO $conn, string $sql, array $params, string $label, string $type): ?array
{
    try {
        $st = $conn->prepare($sql);
        $st->execute($params);
        $labels = []; $values = [];
        foreach ($st->fetchAll(PDO::FETCH_NUM) as $row) { $labels[] = (string) $row[0]; $values[] = (float) $row[1]; }
        if (count($labels) < 2) return null;
        return ['type' => $type, 'label' => $label, 'labels' => $labels, 'values' => $values];
    } catch (Throwable $e) {
        return null;
    }
}

/* ---------- período (parser acima; a IA pode refinar depois) ---------- */
$q = $norm($pergunta);
[$ini, $fim, $periodoLabel] = sf_ia_periodo($q);

/* ---------- confirmação contextual: ok / sim / confirmar / cancelar ---------- */
if ($action === 'pergunta' && $pergunta !== '') {
    $confirmCmd = sf_ia_detect_confirmacao($q);
    if ($confirmCmd !== null) {
        try {
            $conn = getConnection();
            sf_ia_ensure_schema($conn);
            $acaoIdReq = (int) ($_POST['acao_id'] ?? $_GET['acao_id'] ?? 0);
            if ($acaoIdReq < 1 && !empty($_SESSION['sf_ia_ultima_acao_id'])) {
                $acaoIdReq = (int) $_SESSION['sf_ia_ultima_acao_id'];
            }
            $pend = sf_ia_ultima_acao_pendente($conn, $empresa_id, $usuario_id ?: null, $acaoIdReq);
            if ($confirmCmd === 'cancel') {
                if ($pend && ($pend['status'] ?? '') === 'pendente') {
                    $conn->prepare("UPDATE ia_acoes_pendentes SET status = 'cancelada' WHERE id = :id AND empresa_id = :e")
                        ->execute([':id' => (int) $pend['id'], ':e' => $empresa_id]);
                    $answer = 'Ação #' . (int) $pend['id'] . ' cancelada.';
                } else {
                    $answer = 'Nenhuma ação pendente para cancelar.';
                }
                $payload = ['success' => true, 'intent' => 'cancelar_acao', 'answer' => $answer, 'via' => 'contexto'];
            } else {
                if (!$pend || ($pend['status'] ?? '') !== 'pendente') {
                    $answer = 'Não há ação pendente para confirmar. Peça para criar uma OT, agendar manutenção ou exportar relatório.';
                    $payload = ['success' => true, 'intent' => 'confirmar_acao', 'answer' => $answer, 'via' => 'contexto'];
                } else {
                    $res = sf_ia_confirmar_acao($conn, $empresa_id, $usuario_id ?: null, (int) $pend['id']);
                    unset($_SESSION['sf_ia_ultima_acao_id']);
                    $answer = $res['success']
                        ? ('✔ ' . ($res['message'] ?? 'Ação executada.'))
                        : ('⚠ ' . ($res['error'] ?? 'Não foi possível executar.'));
                    $payload = [
                        'success' => $res['success'],
                        'intent' => 'confirmar_acao',
                        'answer' => $answer,
                        'via' => 'contexto',
                        'acao_tela' => !empty($res['redirect']) ? ['tipo' => 'abrir_tela', 'titulo' => 'Abrir', 'url' => $res['redirect']] : null,
                        'rows' => [['Ação' => $pend['titulo'] ?? '—', 'Status' => $res['success'] ? 'executada' : 'erro']],
                    ];
                }
            }
            if (defined('SF_IA_SSE') && SF_IA_SSE) {
                sf_ia_sse_send('done', $payload);
                exit;
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            error_log('ia_assistente confirmar: ' . $e->getMessage());
            if (defined('SF_IA_SSE') && SF_IA_SSE) {
                sf_ia_sse_send('error', ['error' => 'Erro ao processar confirmação.']);
                exit;
            }
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erro ao processar confirmação.'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
}

/* ---------- 1) classificação por palavra-chave (rápida, sem IA) ---------- */
$has = static function (array $words) use ($q): bool {
    foreach ($words as $w) { if (strpos($q, $w) !== false) return true; }
    return false;
};

$intent = 'ajuda';
$temRota = $has(['rota','rotas','viagem','viagens','frete']);
if     ($has(['rotas ativas','rota ativa','em andamento','andamento','rodando agora','em viagem','viagens ativas','rodando neste'])) $intent = 'rotas_ativas';
elseif ($has(['no prazo','chegou no prazo','chegaram no prazo','dentro do prazo','na data','no prazo certo','pontual','pontuais','entregas no prazo','chegou na data'])) $intent = 'no_prazo';
elseif ($has(['atras','atrasou','atrasadas','atrasaram','fora do prazo','atrasada'])) $intent = 'atrasos';
// Rankings "qual X" ANTES do faturamento genérico (senão 'faturou' captura tudo)
elseif ($has(['rota mais','melhor rota','rota rentavel','rota lucr','maior frete','qual rota','quais rotas','rota que mais','rota faturou','rota mais faturou','ultima rota','ultimas rotas','rota recente'])) $intent = 'rota_top';
elseif ($has(['melhor motorista','motorista mais','mais lucrativo','ranking motorista','qual motorista','motorista que mais','top motorista'])) $intent = 'motorista_top';
elseif ($has(['cliente mais','melhor cliente','cliente lucr','qual cliente','cliente que mais'])) $intent = 'cliente_top';
elseif ($has(['parado','mais parado','menos rodou','ocioso','sem rodar','parados'])) $intent = 'veiculo_parado';
elseif ($has(['veiculo mais','caminhao mais','mais rodou','veiculo top','caminhao que mais','qual veiculo','qual caminhao'])) $intent = 'veiculo_top';
elseif ($has(['quantas rotas','total de rotas','rotas no mes','rotas do mes','rotas este mes','numero de rotas','quantas viagens'])) $intent = 'rotas_mes';
elseif ($has(['cnh','habilitac','vencendo','vencida','vencer','validade'])) $intent = 'cnh';
elseif ($has(['consumo','km/l','km por litro','media de consumo','eficiencia','quilometro por litro'])) $intent = 'consumo';
elseif ($has(['km rodado','quilometr','quantos km','distancia','rodei'])) $intent = 'km';
elseif ($has(['cliente'])) $intent = 'cliente_top';
elseif ($has(['faturamento','faturei','faturou','receita','fatura','lucro','ganhei','ganho'])) $intent = ($temRota && $has(['qual','quais','maior','mais'])) ? 'rota_top' : 'faturamento';
elseif ($has(['manutenc','revisao','oficina','conserto','reparo','consertos'])) $intent = 'manutencao';
elseif ($has(['conta a pagar','contas a pagar','a pagar','contas vencendo','boleto','boletos','pagar fornecedor'])) $intent = 'contas_pagar';
elseif ($has(['pneu','pneus','borracha'])) $intent = 'pneus';
elseif ($has(['implemento','implementos','carreta','carretas','reboque'])) $intent = 'implementos';
elseif ($has(['mapa da frota','mapa frota','onde estao os caminhoes','onde estao','localizacao gps','posicao gps','gps agora','caminhoes agora','mostre o mapa'])) $intent = 'mapa_frota';
elseif ($has(['analise completa','scorecard','saude da operacao','diagnostico completo','analise 360','visao 360'])) $intent = 'analise_completa';
elseif ($has(['como cadastrar','como emitir','como funciona','onde fica','manual do sistema','ajuda do sistema','tutorial','como uso o sistema'])) $intent = 'ajuda_sistema';
elseif ($has(['analise minha empresa','analisa minha empresa','analise a empresa','auditoria da empresa','oportunidades','problemas na operacao','descubra'])) $intent = 'analise_empresa';
elseif ($has(['resumo executivo','modo executivo','visao executiva','painel executivo'])) $intent = 'executivo';
elseif ($has(['previsao','prever','forecast','tendencia','preditiv','proximos 30'])) $intent = 'previsoes';
elseif ($has(['documento enviado','nota anexada','arquivo que subi','meu pdf','contrato enviado'])) $intent = 'documentos';
elseif ($has(['o que significa','o que e','como calcula','explica']) && $has(['custo/km','custo por km','custo km','km/l','km por litro'])) $intent = 'glossario';
elseif ($has(['abastec','combustivel gasto','ultimos abastec','gasto com combustivel'])) $intent = 'abastecimentos';
elseif ($has(['pedagio','pedagios'])) $intent = 'pedagio';
elseif ($has(['despesa','gastei','gastos de viagem'])) $intent = 'despesas';
elseif ($has(['multa'])) $intent = 'multas';
elseif ($has(['exportar excel','exportar relatorio','gerar excel','baixar relatorio','exportar csv'])) $intent = 'exportar_relatorio';
elseif ($has(['criar ordem','criar ot','nova ordem de transporte','nova ot','cotacao de frete','abrir cotacao'])) $intent = 'criar_ot';
elseif ($has(['agendar manutencao','agendar revisao','marcar manutencao','agendar conserto'])) $intent = 'agendar_manutencao';
elseif ($has(['notificar motorista','alertar motorista','aviso para motorista','notificacao motorista']) && !$has(['whatsapp'])) $intent = 'notificar_motorista';
elseif ($has(['whatsapp','enviar whatsapp'])) $intent = 'notificar_whatsapp';
elseif ($has(['avisar motorista'])) $intent = 'notificar_whatsapp';
elseif ($has(['frota','quantos veiculos','quantos caminhoes','veiculos ativos','tamanho da frota','quantos carros'])) $intent = 'frota';

$via = 'palavra-chave';
$cfg = null; // config de IA da empresa (resolvida 1x; reusada na classificação e na narrativa)
$navAcao = sf_ia_tools_detectar_navegacao($q);
if ($navAcao && $intent === 'ajuda') {
    $intent = 'ajuda_sistema';
    $via = 'navegacao';
}

/* Intenções custom mapeadas pelo admin (empresa ou global). */
try {
    $connCustom = getConnection();
    sf_ia_ensure_schema($connCustom);
    $customHit = sf_ia_tools_match_custom($connCustom, $empresa_id, $q);
    if ($customHit !== null && isset($INTENCOES[$customHit])) {
        $intent = $customHit;
        $via = 'custom';
    }
} catch (Throwable $e) {
    error_log('ia_assistente custom intent: ' . $e->getMessage());
}

/* ---------- 2) classificação por IA (principal quando há chave; prevalece sobre a
   palavra-chave quando devolve uma intenção válida). Sem chave, fica só o matcher acima. ---------- */
if ($pergunta !== '') {
    try {
        $conn = getConnection();
        $cfg = sf_ia_router_resolve($conn, $empresa_id, 'narrate');
        $classCfg = sf_ia_router_resolve($conn, $empresa_id, 'classify');
        if ($classCfg) {
            $lista = '';
            foreach ($INTENCOES as $id => $desc) { $lista .= "- $id: $desc\n"; }
            $hoje = date('Y-m-d');
            $sys = "Você é o roteador de um assistente de transportadora. Hoje é $hoje.\n"
                . "Dada a pergunta do gestor, escolha UMA intenção da lista e extraia o período citado.\n"
                . "Responda SOMENTE um JSON válido, sem texto fora dele, no formato:\n"
                . '{"intent":"<id da lista>","periodo":"<frase curta do período em português, ex: mês passado, primeiro trimestre de 2025, últimos 3 meses, em 2024, janeiro; ou string vazia se não citar período>"}' . "\n"
                . "Se nenhuma intenção servir, use \"ajuda\". Não invente dados.\n\nINTENÇÕES:\n$lista";
            $llmClass = sf_ia_router_chat($conn, $empresa_id, 'classify', [
                ['role' => 'system', 'content' => $sys],
                ['role' => 'user', 'content' => $pergunta],
            ], 60, 0);
            if ($llmClass && $llmClass['content'] !== '') {
                $content = $llmClass['content'];
                if (preg_match('/\{.*\}/s', $content, $mm)) {
                    $parsed = json_decode($mm[0], true);
                    if (is_array($parsed)) {
                        $outIntent = preg_replace('/[^a-z_]/', '', $norm((string) ($parsed['intent'] ?? '')));
                        if ($outIntent && isset($INTENCOES[$outIntent])) {
                            $intent = $outIntent;
                            $via = 'ia';
                        }
                        $periodoIa = trim((string) ($parsed['periodo'] ?? ''));
                        if ($periodoIa !== '') {
                            [$ini, $fim, $periodoLabel] = sf_ia_periodo($norm($periodoIa));
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {
        error_log('ia_assistente classificador: ' . $e->getMessage());
    }
}

/* ---------- memória de contexto: follow-up só de período reaproveita a última intenção
   (ex.: "e no mês passado?" logo após "qual o faturamento?") ---------- */
$contexto = preg_replace('/[^a-z_]/', '', (string) ($_GET['contexto'] ?? $_POST['contexto'] ?? ''));
$periodoExplicito = ($periodoLabel !== 'últimos 12 meses');
$comparaveis = ['faturamento', 'manutencao', 'despesas', 'multas', 'km', 'pedagio'];
$pedeComparacao = $has(['comparar', 'compara ', 'comparacao', 'periodo anterior', 'versus', ' vs ']);

if ($intent === 'ajuda' && $contexto !== '' && isset($INTENCOES[$contexto])
    && ($periodoExplicito || $pedeComparacao || preg_match('/^\s*e\b/', $q) || $has(['somente','so ','apenas','filtra','volvo','scania','mercedes','iveco']))) {
    $intent = $contexto;
    $via = 'contexto';
}

/* "Comparar com mês passado" sem contexto útil (ou contexto que não compara):
   usa faturamento como base natural de comparação, em vez de cair em "não entendi". */
if ($intent === 'ajuda' && $pedeComparacao) {
    $intent = (in_array($contexto, $comparaveis, true)) ? $contexto : 'faturamento';
    $via = 'contexto';
}

/* Se pediu comparação mas a intenção reaproveitada não suporta comparação,
   troca para faturamento (a métrica financeira mais comparável). */
if ($pedeComparacao && $intent !== 'ajuda' && !in_array($intent, $comparaveis, true)) {
    $intent = 'faturamento';
    $via = 'contexto';
}

/* "Comparar com mês passado": se nenhum período específico foi dito além disso,
   usa o mês atual como base — o bloco de comparação já mostra o anterior equivalente. */
if ($pedeComparacao && strpos($q, 'mes passado') !== false && $periodoLabel === 'mês passado') {
    [$ini, $fim, $periodoLabel] = [date('Y-m-01'), date('Y-m-t'), 'mês atual'];
}

$_SESSION['sf_ia_ctx'] = [
    'intent' => $intent !== 'ajuda' ? $intent : ($contexto ?: 'ajuda'),
    'periodo' => $periodoLabel,
    'ini' => $ini,
    'fim' => $fim,
];

if (defined('SF_IA_SSE') && SF_IA_SSE) {
    sf_ia_sse_send('phase', ['step' => 'classified', 'intent' => $intent, 'agente' => sf_ia_agente_for_intent($intent)]);
}

if ($intent !== 'ajuda' && !sf_ia_intent_permitido($intent)) {
    $bloqueio = $intent === 'mapa_frota' && function_exists('sf_ia_bloqueio_mapa')
        ? sf_ia_bloqueio_mapa() : sf_ia_bloqueio_financeiro();
    $bloqueio['periodo'] = $periodoLabel;
    $bloqueio['via'] = $via;
    if (defined('SF_IA_SSE') && SF_IA_SSE) {
        sf_ia_sse_send('done', $bloqueio);
        exit;
    }
    echo json_encode($bloqueio, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- "aprender sempre": registra o que não foi entendido ---------- */
if ($intent === 'ajuda' && $pergunta !== '') {
    @file_put_contents(
        __DIR__ . '/../logs/ia_assistente_naoentendidas.log',
        date('c') . "\tempresa={$empresa_id}\t" . str_replace(["\n", "\t"], ' ', $pergunta) . "\n",
        FILE_APPEND
    );
    try {
        $connLog = $conn ?? getConnection();
        sf_ia_tools_queue_unanswered($connLog, $empresa_id, $pergunta);
    } catch (Throwable $e) {
        error_log('ia_assistente queue unanswered: ' . $e->getMessage());
    }
}

/* ---------- execução (queries parametrizadas) ---------- */
$answer = '';
$rows = [];
$chart = null;
$analise = [];
$recomendacoes = [];
$causasManut = [];
$acaoPendenteId = null;
$acaoPendenteLabel = 'Confirmar ação';

try {
    $conn = $conn ?? getConnection();
    sf_ia_ensure_schema($conn);
    sf_ia_enforce_quota($conn, $empresa_id);

    $ctx = new SfIaRunContext();
    $ctx->timeline('classificado', ['intent' => $intent, 'via' => $via, 'periodo' => $periodoLabel]);
    if ($navAcao) {
        $ctx->acaoTela = $navAcao;
    }

    $noCacheIntents = ['ajuda', 'documentos', 'mapa_frota', 'rotas_ativas', 'ajuda_sistema'];
    $cacheKey = sf_ia_cache_key($intent, $ini, $fim, $empresa_id);
    $cached = (!defined('SF_IA_SSE') || !SF_IA_SSE) && !in_array($intent, $noCacheIntents, true)
        ? sf_ia_cache_get($conn, $empresa_id, $cacheKey) : null;
    if ($cached && !empty($cached['answer'])) {
        $cached['from_cache'] = true;
        $ctx->cacheHit = true;
        sf_ia_metrics_track($conn, $empresa_id, ['cache_hit' => true, 'llm' => false]);
        echo json_encode($cached, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (defined('SF_IA_SSE') && SF_IA_SSE) {
        sf_ia_sse_send('phase', ['step' => 'querying', 'msg' => 'Consultando dados…']);
    }

    $P = [':e' => $empresa_id, ':ini' => $ini, ':fim' => $fim];
    $periodoRotas = 'COALESCE(r.data_rota, r.data_saida) BETWEEN :ini AND :fim';

    switch ($intent) {
        case 'faturamento':
            $st = $conn->prepare("SELECT COALESCE(SUM(r.frete),0) fat, COALESCE(SUM(r.comissao),0) com, COUNT(*) qtd
                FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $lucroAprox = (float)$d['fat'] - (float)$d['com'];
            $answer = "No $periodoLabel: faturamento de {$moeda($d['fat'])} em " . (int)$d['qtd'] . " rota(s), comissões de {$moeda($d['com'])} (lucro aprox. frete − comissão: {$moeda($lucroAprox)}).";
            $rows = [['Faturamento'=>$moeda($d['fat']), 'Comissões'=>$moeda($d['com']), 'Rotas'=>(int)$d['qtd']]];
            $chart = sf_ia_serie($conn, "SELECT DATE_FORMAT(COALESCE(r.data_rota,r.data_saida),'%Y-%m') ym, SUM(r.frete) v FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas GROUP BY ym ORDER BY ym", $P, 'Faturamento por mês (R$)', 'bar');
            break;

        case 'motorista_top':
            $st = $conn->prepare("SELECT m.nome, ROUND(SUM(r.frete),2) fat, COUNT(*) rotas
                FROM rotas r JOIN motoristas m ON m.id=r.motorista_id
                WHERE r.empresa_id=:e AND $periodoRotas GROUP BY m.id ORDER BY fat DESC LIMIT 5");
            $st->execute($P); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            $answer = $rs ? "No $periodoLabel, o motorista que mais faturou foi {$rs[0]['nome']} com {$moeda($rs[0]['fat'])} em " . (int)$rs[0]['rotas'] . " rota(s)." : "Não há rotas com motorista no $periodoLabel.";
            foreach ($rs as $r) $rows[] = ['Motorista'=>$r['nome'], 'Faturamento'=>$moeda($r['fat']), 'Rotas'=>(int)$r['rotas']];
            break;

        case 'no_prazo':
            $st = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(r.no_prazo=1),0) prazo, COALESCE(SUM(r.no_prazo=0),0) atras
                FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $tot = (int)$d['total']; $ok = (int)$d['prazo'];
            $pct = $tot > 0 ? round($ok / $tot * 100) : 0;
            $answer = $tot > 0
                ? "No $periodoLabel, {$ok} de {$tot} rota(s) chegaram no prazo ({$pct}%). Fora do prazo: " . (int)$d['atras'] . "."
                : "Não há rotas registradas no $periodoLabel para avaliar prazo. (Tente 'mês passado' ou 'ano'.)";
            $rows = [['Total'=>$tot, 'No prazo'=>$ok, 'Atrasadas'=>(int)$d['atras'], '% no prazo'=>$pct.'%']];
            break;

        case 'atrasos':
            $sc = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(r.no_prazo=0),0) atras FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $sc->execute($P); $c = $sc->fetch(PDO::FETCH_ASSOC);
            $st = $conn->prepare("SELECT m.nome, COUNT(*) atrasos
                FROM rotas r JOIN motoristas m ON m.id=r.motorista_id
                WHERE r.empresa_id=:e AND $periodoRotas AND r.no_prazo=0 GROUP BY m.id ORDER BY atrasos DESC LIMIT 5");
            $st->execute($P); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            if ((int)$c['total'] === 0) {
                $answer = "Não há rotas registradas no $periodoLabel. (Tente 'mês passado' ou 'ano'.)";
            } elseif ($rs) {
                $answer = "No $periodoLabel houve " . (int)$c['atras'] . " rota(s) fora do prazo (de " . (int)$c['total'] . "). Quem mais atrasou: {$rs[0]['nome']} (" . (int)$rs[0]['atrasos'] . ").";
            } else {
                $answer = "Nenhuma rota fora do prazo no $periodoLabel. 👏";
            }
            foreach ($rs as $r) $rows[] = ['Motorista'=>$r['nome'], 'Atrasos'=>(int)$r['atrasos']];
            break;

        case 'cnh':
            $st = $conn->prepare("SELECT nome, data_validade_cnh, DATEDIFF(data_validade_cnh, CURDATE()) dias
                FROM motoristas WHERE empresa_id=:e AND data_validade_cnh IS NOT NULL
                AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY data_validade_cnh ASC LIMIT 20");
            $st->execute([':e'=>$empresa_id]); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            $answer = $rs ? count($rs) . " motorista(s) com CNH vencida ou vencendo em até 30 dias. Mais urgente: {$rs[0]['nome']} ({$rs[0]['dias']} dia(s))." : "Nenhuma CNH vencendo nos próximos 30 dias. ✔";
            foreach ($rs as $r) $rows[] = ['Motorista'=>$r['nome'], 'Validade CNH'=>$r['data_validade_cnh'], 'Dias'=>(int)$r['dias']];
            break;

        case 'consumo':
            $sk = $conn->prepare("SELECT COALESCE(SUM(r.distancia_km),0) km FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $sk->execute($P); $km = (float)$sk->fetchColumn();
            $sl = $conn->prepare("SELECT COALESCE(SUM(litros),0) l FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :ini AND :fim");
            $sl->execute($P); $lit = (float)$sl->fetchColumn();
            $kml = $lit > 0 ? $km / $lit : 0;
            $answer = $lit > 0 ? "Consumo médio da frota no $periodoLabel: " . number_format($kml,2,',','.') . " km/L ({$num($km)} km / {$num($lit)} L)." : "Sem abastecimentos no $periodoLabel para calcular o consumo.";
            $rows = [['KM'=>$num($km), 'Litros'=>$num($lit), 'km/L'=>number_format($kml,2,',','.')]];
            break;

        case 'km':
            $st = $conn->prepare("SELECT COALESCE(SUM(r.distancia_km),0) km, COUNT(*) qtd FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = "No $periodoLabel a frota rodou {$num($d['km'])} km em " . (int)$d['qtd'] . " rota(s).";
            $rows = [['KM rodados'=>$num($d['km']), 'Rotas'=>(int)$d['qtd']]];
            $chart = sf_ia_serie($conn, "SELECT DATE_FORMAT(COALESCE(r.data_rota,r.data_saida),'%Y-%m') ym, SUM(r.distancia_km) v FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas GROUP BY ym ORDER BY ym", $P, 'KM por mês', 'line');
            break;

        case 'rota_top':
            $ultima = (strpos($q, 'ultima') !== false || strpos($q, 'recente') !== false);
            $ordCol = $ultima ? 'COALESCE(r.data_rota, r.data_saida) DESC' : 'r.frete DESC';
            $st = $conn->prepare("SELECT r.id, r.frete, r.distancia_km, COALESCE(r.data_rota, r.data_saida) dt, m.nome motorista, v.placa
                FROM rotas r LEFT JOIN motoristas m ON m.id=r.motorista_id LEFT JOIN veiculos v ON v.id=r.veiculo_id
                WHERE r.empresa_id=:e AND $periodoRotas ORDER BY $ordCol LIMIT 5");
            $st->execute($P); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            if (!$rs) { $answer = "Nenhuma rota no $periodoLabel."; }
            elseif ($ultima) { $answer = "A última rota ({$rs[0]['dt']}) é a #{$rs[0]['id']}, frete {$moeda($rs[0]['frete'])}, motorista " . ($rs[0]['motorista'] ?: '—') . "."; }
            else { $answer = "A rota de maior frete ($periodoLabel) é a #{$rs[0]['id']} ({$moeda($rs[0]['frete'])}), motorista " . ($rs[0]['motorista'] ?: '—') . "."; }
            foreach ($rs as $r) $rows[] = ['Rota'=>'#'.$r['id'], 'Data'=>$r['dt'], 'Frete'=>$moeda($r['frete']), 'KM'=>$num($r['distancia_km']), 'Motorista'=>$r['motorista'] ?: '—', 'Placa'=>$r['placa'] ?: '—'];
            break;

        case 'cliente_top':
            $st = $conn->prepare("SELECT cliente_nome, ROUND(SUM(valor_frete),2) fat, COUNT(*) ots
                FROM ordens_transporte WHERE empresa_id=:e AND created_at BETWEEN :ini AND :fim AND cliente_nome IS NOT NULL AND cliente_nome<>''
                GROUP BY cliente_nome ORDER BY fat DESC LIMIT 5");
            $st->execute($P); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            $answer = $rs ? "No $periodoLabel, o cliente que mais gerou frete foi {$rs[0]['cliente_nome']} com {$moeda($rs[0]['fat'])} em " . (int)$rs[0]['ots'] . " ordem(ns)." : "Sem ordens de transporte com cliente no $periodoLabel.";
            foreach ($rs as $r) $rows[] = ['Cliente'=>$r['cliente_nome'], 'Frete'=>$moeda($r['fat']), 'OTs'=>(int)$r['ots']];
            break;

        case 'veiculo_top':
        case 'veiculo_parado':
            $ord = $intent === 'veiculo_top' ? 'DESC' : 'ASC';
            $st = $conn->prepare("SELECT v.placa, v.modelo, COALESCE(SUM(r.distancia_km),0) km, COUNT(r.id) rotas
                FROM veiculos v LEFT JOIN rotas r ON r.veiculo_id=v.id AND r.empresa_id=:e AND $periodoRotas
                WHERE v.empresa_id=:e GROUP BY v.id ORDER BY km $ord LIMIT 5");
            $st->execute($P); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            $rotulo = $intent === 'veiculo_parado' ? 'menos rodou' : 'mais rodou';
            $answer = $rs ? "O veículo que $rotulo no $periodoLabel foi {$rs[0]['placa']} ({$num($rs[0]['km'])} km em " . (int)$rs[0]['rotas'] . " rota(s))." : "Sem veículos cadastrados.";
            foreach ($rs as $r) $rows[] = ['Placa'=>$r['placa'], 'Modelo'=>$r['modelo'] ?: '—', 'KM'=>$num($r['km']), 'Rotas'=>(int)$r['rotas']];
            break;

        case 'rotas_ativas':
            $st = $conn->prepare("SELECT COUNT(*) qtd FROM rotas r WHERE r.empresa_id=:e AND r.status='aprovado'
                AND r.data_saida <= NOW() AND r.data_chegada >= NOW()");
            $st->execute([':e'=>$empresa_id]); $ativas = (int)$st->fetchColumn();
            $st2 = $conn->prepare("SELECT r.id, v.placa, m.nome, r.data_saida, r.data_chegada
                FROM rotas r LEFT JOIN veiculos v ON v.id=r.veiculo_id LEFT JOIN motoristas m ON m.id=r.motorista_id
                WHERE r.empresa_id=:e AND r.status='aprovado' AND r.data_saida <= NOW() AND r.data_chegada >= NOW()
                ORDER BY r.data_saida DESC LIMIT 10");
            $st2->execute([':e'=>$empresa_id]); $rs = $st2->fetchAll(PDO::FETCH_ASSOC);
            $answer = "Você tem $ativas rota(s) em andamento agora (aprovadas, já saíram e ainda não chegaram).";
            foreach ($rs as $r) $rows[] = ['Rota'=>'#'.$r['id'], 'Placa'=>$r['placa'] ?: '—', 'Motorista'=>$r['nome'] ?: '—', 'Saída'=>$r['data_saida'], 'Chegada prev.'=>$r['data_chegada']];
            break;

        case 'rotas_mes':
            $st = $conn->prepare("SELECT
                    SUM(r.status='aprovado') aprovadas, SUM(r.status='pendente') pendentes,
                    SUM(r.status='rejeitado') rejeitadas, COUNT(*) total
                FROM rotas r WHERE r.empresa_id=:e AND $periodoRotas");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            if ((int)$d['total'] === 0) {
                $answer = "Não há rotas registradas no $periodoLabel. Tente perguntar por 'mês passado' ou 'ano'.";
            } else {
                $answer = "No $periodoLabel você tem " . (int)$d['total'] . " rota(s): " . (int)$d['aprovadas'] . " aprovada(s), " . (int)$d['pendentes'] . " pendente(s), " . (int)$d['rejeitadas'] . " rejeitada(s).";
            }
            $rows = [['Total'=>(int)$d['total'], 'Aprovadas'=>(int)$d['aprovadas'], 'Pendentes'=>(int)$d['pendentes'], 'Rejeitadas'=>(int)$d['rejeitadas']]];
            break;

        case 'abastecimentos':
            $st = $conn->prepare("SELECT a.data_abastecimento, v.placa, a.litros, a.valor_total
                FROM abastecimentos a LEFT JOIN veiculos v ON v.id=a.veiculo_id
                WHERE a.empresa_id=:e ORDER BY a.data_abastecimento DESC LIMIT 8");
            $st->execute([':e'=>$empresa_id]); $rs = $st->fetchAll(PDO::FETCH_ASSOC);
            $answer = $rs ? "Últimos abastecimentos registrados (mostrando " . count($rs) . ")." : "Nenhum abastecimento registrado.";
            foreach ($rs as $r) $rows[] = ['Data'=>$r['data_abastecimento'], 'Placa'=>$r['placa'] ?: '—', 'Litros'=>$num($r['litros']), 'Valor'=>$moeda($r['valor_total'])];
            break;

        case 'pedagio':
            $st = $conn->prepare("SELECT COALESCE(SUM(pedagios),0) total, COUNT(*) qtd FROM despesas_viagem
                WHERE empresa_id=:e AND DATE(created_at) BETWEEN :ini AND :fim");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = (int)$d['qtd'] > 0 ? "No $periodoLabel, gasto com pedágio de {$moeda($d['total'])} em " . (int)$d['qtd'] . " lançamento(s) de despesa de viagem." : "Sem despesas de viagem lançadas no $periodoLabel.";
            $rows = [['Pedágio'=>$moeda($d['total']), 'Lançamentos'=>(int)$d['qtd']]];
            break;

        case 'despesas':
            $st = $conn->prepare("SELECT COALESCE(SUM(total_despviagem),0) total, COALESCE(SUM(pedagios),0) ped, COALESCE(SUM(adiantamento),0) adi, COUNT(*) qtd
                FROM despesas_viagem WHERE empresa_id=:e AND DATE(created_at) BETWEEN :ini AND :fim");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = "No $periodoLabel, despesas de viagem de {$moeda($d['total'])} em " . (int)$d['qtd'] . " lançamento(s) — inclui {$moeda($d['ped'])} de pedágio e {$moeda($d['adi'])} de adiantamento.";
            $rows = [['Total despesas'=>$moeda($d['total']), 'Pedágio'=>$moeda($d['ped']), 'Adiantamento'=>$moeda($d['adi']), 'Lançamentos'=>(int)$d['qtd']]];
            break;

        case 'multas':
            $st = $conn->prepare("SELECT COUNT(*) qtd, COALESCE(SUM(valor),0) total FROM multas WHERE empresa_id=:e AND data_infracao BETWEEN :ini AND :fim");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = (int)$d['qtd'] > 0 ? "No $periodoLabel houve " . (int)$d['qtd'] . " multa(s), somando {$moeda($d['total'])}." : "Nenhuma multa registrada no $periodoLabel. ✔";
            $rows = [['Multas'=>(int)$d['qtd'], 'Total'=>$moeda($d['total'])]];
            $chart = sf_ia_serie($conn, "SELECT DATE_FORMAT(data_infracao,'%Y-%m') ym, COUNT(*) v FROM multas WHERE empresa_id=:e AND data_infracao BETWEEN :ini AND :fim GROUP BY ym ORDER BY ym", $P, 'Multas por mês', 'bar');
            break;

        case 'frota':
            $st = $conn->prepare("SELECT COUNT(*) total FROM veiculos WHERE empresa_id=:e");
            $st->execute([':e'=>$empresa_id]); $tot = (int)$st->fetchColumn();
            $answer = "A frota tem $tot veículo(s) cadastrado(s).";
            $rows = [['Veículos'=>$tot]];
            break;

        case 'manutencao':
            $st = $conn->prepare("SELECT COUNT(*) qtd, COALESCE(SUM(COALESCE(custo_total, valor, 0)),0) total,
                    COALESCE(SUM(status_manutencao_id IN (1,2)),0) abertas
                FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :ini AND :fim");
            $st->execute($P); $d = $st->fetch(PDO::FETCH_ASSOC);
            $causasManut = sf_ia_copilot_manutencao_causas($conn, $empresa_id, $ini, $fim);
            if ((int)$d['qtd'] > 0) {
                $answer = "No $periodoLabel houve " . (int)$d['qtd'] . " manutenção(ões), custo total de {$moeda($d['total'])} (" . (int)$d['abertas'] . " agendada(s)/em andamento).";
                if ($causasManut !== []) {
                    $answer .= "\nPrincipais causas: " . implode(', ', array_map(static function ($c) use ($moeda) {
                        return ($c['tipo'] ?? 'Outros') . ' (' . $moeda((float) ($c['total'] ?? 0)) . ')';
                    }, array_slice($causasManut, 0, 3)));
                }
            } else {
                $answer = "Nenhuma manutenção registrada no $periodoLabel.";
            }
            $rows = [['Manutenções'=>(int)$d['qtd'], 'Custo total'=>$moeda($d['total']), 'Abertas/andamento'=>(int)$d['abertas']]];
            $chart = sf_ia_serie($conn, "SELECT DATE_FORMAT(data_manutencao,'%Y-%m') ym, SUM(COALESCE(custo_total,valor,0)) v FROM manutencoes WHERE empresa_id=:e AND data_manutencao BETWEEN :ini AND :fim GROUP BY ym ORDER BY ym", $P, 'Gasto com manutenção por mês (R$)', 'bar');
            break;

        case 'contas_pagar':
            $st = $conn->prepare("SELECT COUNT(*) qtd, COALESCE(SUM(valor),0) total,
                    COALESCE(SUM(data_vencimento < CURDATE()),0) vencidas,
                    COALESCE(SUM(data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)),0) prox7
                FROM contas_pagar WHERE empresa_id=:e AND data_pagamento IS NULL");
            $st->execute([':e'=>$empresa_id]); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = (int)$d['qtd'] > 0
                ? "Você tem " . (int)$d['qtd'] . " conta(s) a pagar em aberto, somando {$moeda($d['total'])} — " . (int)$d['vencidas'] . " vencida(s) e " . (int)$d['prox7'] . " vencendo em 7 dias."
                : "Nenhuma conta a pagar em aberto. ✔";
            $rows = [['Em aberto'=>(int)$d['qtd'], 'Total'=>$moeda($d['total']), 'Vencidas'=>(int)$d['vencidas'], 'Vence em 7d'=>(int)$d['prox7']]];
            break;

        case 'pneus':
            $st = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status_id=1),0) em_uso,
                    COALESCE(SUM(status_id=2),0) reserva, COALESCE(SUM(status_id=5),0) novos
                FROM pneus WHERE empresa_id=:e");
            $st->execute([':e'=>$empresa_id]); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = "Você tem " . (int)$d['total'] . " pneu(s): " . (int)$d['em_uso'] . " em uso, " . (int)$d['reserva'] . " em reserva, " . (int)$d['novos'] . " novo(s).";
            $rows = [['Total'=>(int)$d['total'], 'Em uso'=>(int)$d['em_uso'], 'Reserva'=>(int)$d['reserva'], 'Novos'=>(int)$d['novos']]];
            break;

        case 'implementos':
            $st = $conn->prepare("SELECT COUNT(*) total, COALESCE(SUM(status='ativo'),0) ativos FROM implementos WHERE empresa_id=:e");
            $st->execute([':e'=>$empresa_id]); $d = $st->fetch(PDO::FETCH_ASSOC);
            $answer = "Você tem " . (int)$d['total'] . " implemento(s) cadastrado(s)" . ((int)$d['total'] > 0 ? ", sendo " . (int)$d['ativos'] . " ativo(s)." : ".");
            $rows = [['Implementos'=>(int)$d['total'], 'Ativos'=>(int)$d['ativos']]];
            break;

        case 'analise_empresa':
            $ops = sf_ia_copilot_analise_empresa($conn, $empresa_id, $moeda);
            $answer = 'Encontrei ' . count($ops) . " ponto(s) importantes na operação:\n• " . implode("\n• ", $ops);
            foreach ($ops as $i => $op) {
                $rows[] = ['#'=> $i + 1, 'Oportunidade / alerta' => $op];
            }
            break;

        case 'executivo':
            $exec = sf_ia_copilot_executivo($conn, $empresa_id, $moeda, $num);
            $answer = (string) ($exec['resumo'] ?? '');
            $rows = [];
            foreach (($exec['cards'] ?? []) as $c) {
                $rows[] = [$c['title'] => $c['value']];
            }
            break;

        case 'glossario':
            $sk = $conn->prepare("SELECT COALESCE(SUM(r.distancia_km),0) km, COALESCE(SUM(r.frete),0) fat, COALESCE(SUM(r.comissao),0) com
                FROM rotas r WHERE r.empresa_id=:e AND COALESCE(r.data_rota,r.data_saida) BETWEEN :ini AND :fim");
            $sk->execute($P); $gd = $sk->fetch(PDO::FETCH_ASSOC);
            $kmG = (float) ($gd['km'] ?? 0);
            $custoG = (float) ($gd['com'] ?? 0);
            $sl = $conn->prepare("SELECT COALESCE(SUM(valor_total),0) FROM abastecimentos WHERE empresa_id=:e AND data_abastecimento BETWEEN :ini AND :fim");
            $sl->execute($P); $combG = (float) $sl->fetchColumn();
            $custoTotal = $custoG + $combG;
            $custoKm = $kmG > 0 ? $custoTotal / $kmG : 0;
            $answer = "Custo/km é o gasto operacional dividido pelos km rodados no período (comissões + combustível registrado, sobre as rotas).\n"
                . "No $periodoLabel, com {$num($kmG)} km, isso dá aproximadamente {$moeda($custoKm)}/km.\n"
                . "Consumo médio (km/L) usa km das rotas ÷ litros abastecidos — pergunte «qual o consumo médio da frota?» para ver o seu número real.";
            $rows = [['KM'=>$num($kmG), 'Combustível'=>$moeda($combG), 'Comissões'=>$moeda($custoG), 'Custo/km'=>$moeda($custoKm)]];
            break;

        case 'previsoes':
            $prev = sf_ia_previsoes($conn, $empresa_id, $moeda);
            $answer = $prev['resumo'] ?? 'Sem previsões disponíveis.';
            foreach ($prev['itens'] ?? [] as $i => $item) {
                $rows[] = ['#'=> $i + 1, 'Previsão' => ($item['titulo'] ?? ''), 'Detalhe' => ($item['detalhe'] ?? '')];
            }
            if ($rows === []) {
                $rows = [['Status' => 'Sem dados suficientes para previsão']];
            }
            break;

        case 'documentos':
            $chunks = sf_ia_rag_search($conn, $empresa_id, $pergunta, 5);
            if ($chunks === []) {
                $answer = 'Não encontrei trechos nos documentos enviados para esta pergunta. Envie NF/contratos na aba Documentos ou cole o texto no upload.';
            } else {
                $answer = 'Encontrei ' . count($chunks) . ' trecho(s) nos seus documentos:';
                foreach ($chunks as $c) {
                    $snippet = mb_substr((string) ($c['chunk_text'] ?? ''), 0, 220);
                    $rows[] = ['Arquivo' => $c['nome_arquivo'] ?? '—', 'Trecho' => $snippet . (mb_strlen((string) ($c['chunk_text'] ?? '')) > 220 ? '…' : '')];
                }
            }
            break;

        case 'mapa_frota':
            $ctx->timeline('consultando_gps');
            $mapData = sf_ia_tools_mapa_gps($conn, $empresa_id);
            $ctx->mapa = $mapData;
            $nMap = (int) ($mapData['total'] ?? 0);
            $answer = $nMap > 0
                ? "Encontrei {$nMap} veículo(s) com posição GPS recente. Veja o mapa abaixo ou abra o GPS completo."
                : 'Nenhuma posição GPS disponível no momento. Verifique se o app do motorista está enviando localização.';
            foreach (($mapData['markers'] ?? []) as $m) {
                $rows[] = [
                    'Placa' => $m['placa'] ?? '—',
                    'Última hora' => $m['hora'] ?? '—',
                    'Vel.' => isset($m['vel']) ? ($m['vel'] . ' km/h') : '—',
                ];
            }
            break;

        case 'ajuda_sistema':
            $ctx->timeline('rag_docs');
            if ($navAcao) {
                $answer = 'Abrindo **' . ($navAcao['titulo'] ?? 'tela') . '**. Use o botão abaixo para ir direto.';
                $rows = [['Ação' => $navAcao['titulo'] ?? '—', 'URL' => $navAcao['url'] ?? '—']];
            } else {
                $help = sf_ia_help_answer($pergunta);
                $answer = $help['answer'];
                $rows = $help['rows'] ?? [];
            }
            break;

        case 'analise_completa':
            $ctx->timeline('scorecard');
            $ac = sf_ia_copilot_analise_completa($conn, $empresa_id, $moeda, $num);
            $answer = (string) ($ac['resumo'] ?? 'Análise completa indisponível.');
            $rows = $ac['rows'] ?? [];
            $recomendacoes = $ac['recomendacoes'] ?? [];
            $cards = [['title' => 'Saúde geral', 'value' => ($ac['score_geral'] ?? 0) . '%']];
            foreach ($ac['dimensoes'] ?? [] as $dim) {
                $cards[] = ['title' => (string) ($dim['area'] ?? '—'), 'value' => (string) (($dim['score'] ?? 0) . '%')];
            }
            break;

        case 'exportar_relatorio':
            $tipoRel = strpos($q, 'manutenc') !== false ? 'manutencoes' : (strpos($q, 'multa') !== false ? 'multas' : 'faturamento');
            $baseUrl = function_exists('sf_app_url') ? rtrim(sf_app_url(''), '/') : '/sistema-frotas';
            $url = $baseUrl . '/pages/relatorios.php?tipo=' . rawurlencode($tipoRel);
            $idAcao = sf_ia_propor_acao(
                $conn,
                $empresa_id,
                $usuario_id ?: null,
                'exportar_relatorio',
                'Exportar relatório — ' . $tipoRel,
                'Abrir tela de relatórios para exportar ' . $tipoRel . ' (' . $periodoLabel . ').',
                ['url' => $url, 'tipo' => $tipoRel, 'periodo' => $periodoLabel]
            );
            $answer = $idAcao
                ? "Preparei a exportação de {$tipoRel} ({$periodoLabel}). Clique em Confirmar abaixo ou abra Relatórios."
                : 'Não foi possível criar a proposta de exportação.';
            $rows = [['Relatório' => $tipoRel, 'Período' => $periodoLabel]];
            $acaoPendenteId = $idAcao;
            $acaoPendenteLabel = 'Confirmar exportação';
            $ctx->acaoTela = ['tipo' => 'abrir_tela', 'titulo' => 'Relatórios', 'url' => $url, 'filtros' => ['tipo' => $tipoRel]];
            break;

        case 'notificar_whatsapp':
            $stM = $conn->prepare("SELECT id, nome, telefone FROM motoristas WHERE empresa_id = :e AND telefone IS NOT NULL AND telefone <> '' ORDER BY nome LIMIT 5");
            $stM->execute([':e' => $empresa_id]);
            $motos = $stM->fetchAll(PDO::FETCH_ASSOC);
            if ($motos === []) {
                $answer = 'Nenhum motorista com telefone cadastrado para enviar WhatsApp.';
            } else {
                $m0 = $motos[0];
                $tel = preg_replace('/\D/', '', (string) $m0['telefone']);
                $msg = 'Olá ' . ($m0['nome'] ?? '') . ', mensagem da transportadora via Centro de Inteligência.';
                $waUrl = 'https://wa.me/' . $tel . '?text=' . rawurlencode($msg);
                $idAcao = sf_ia_propor_acao(
                    $conn,
                    $empresa_id,
                    $usuario_id ?: null,
                    'abrir_whatsapp',
                    'WhatsApp — ' . ($m0['nome'] ?? 'motorista'),
                    'Abrir conversa no WhatsApp Web/App (você envia a mensagem).',
                    ['url' => $waUrl, 'motorista_id' => (int) $m0['id']]
                );
                $answer = $idAcao
                    ? 'Preparei o WhatsApp para ' . ($m0['nome'] ?? 'motorista') . '. Clique em Confirmar abaixo para abrir a conversa.'
                    : 'Não foi possível propor o WhatsApp.';
                foreach ($motos as $m) {
                    $rows[] = ['Motorista' => $m['nome'], 'Telefone' => $m['telefone']];
                }
                $acaoPendenteId = $idAcao;
                $acaoPendenteLabel = 'Confirmar e abrir WhatsApp';
                $ctx->acaoTela = ['tipo' => 'abrir_whatsapp', 'titulo' => 'WhatsApp', 'url' => $waUrl];
            }
            break;

        case 'criar_ot':
            $ot = sf_ia_parse_ot_params($conn, $empresa_id, $pergunta);
            $cliNome = $ot['cliente_nome'];
            $origem = $ot['origem'];
            $destino = $ot['destino'];
            $idAcao = sf_ia_propor_acao(
                $conn,
                $empresa_id,
                $usuario_id ?: null,
                'criar_ot',
                'Criar OT — ' . $origem . ' → ' . $destino,
                'Nova ordem de transporte em cotação para o cliente ' . $cliNome . '.',
                [
                    'cliente_nome' => $cliNome,
                    'origem' => $origem,
                    'destino' => $destino,
                    'valor_frete' => $ot['valor_frete'],
                    'distancia_km' => $ot['distancia_km'],
                ]
            );
            $freteTxt = $ot['valor_frete'] > 0 ? $moeda($ot['valor_frete']) : 'a definir';
            $kmTxt = $ot['distancia_km'] > 0 ? $num($ot['distancia_km']) . ' km' : 'a definir';
            $anttTxt = $ot['antt_piso'] ? ' (piso ANTT)' : '';
            $answer = $idAcao
                ? "Preparei a OT de {$origem} para {$destino} — cliente {$cliNome}, frete {$freteTxt}{$anttTxt}, {$kmTxt}. Clique em Confirmar abaixo."
                : 'Não foi possível propor a criação da OT.';
            $rows = [
                ['Cliente' => $cliNome, 'Origem' => $origem, 'Destino' => $destino, 'Frete' => $freteTxt, 'Distância' => $kmTxt, 'Status' => 'cotação (proposta)'],
            ];
            $acaoPendenteId = $idAcao;
            $acaoPendenteLabel = sf_ia_acao_label_confirm('criar_ot');
            $baseUrl = function_exists('sf_app_url') ? rtrim(sf_app_url(''), '/') : '/sistema-frotas';
            $ctx->acaoTela = ['tipo' => 'abrir_tela', 'titulo' => 'Ordens de Transporte', 'url' => $baseUrl . '/pages/ordens_transporte.php'];
            break;

        case 'agendar_manutencao':
            $veiculoId = 0;
            $placa = '';
            if (preg_match('/\b([A-Z]{3}[-\s]?\d[A-Z0-9]\d{2})\b/i', $pergunta, $mPl)) {
                $placa = strtoupper(preg_replace('/\s/', '', $mPl[1]));
                $sv = $conn->prepare('SELECT id, placa FROM veiculos WHERE empresa_id = :e AND REPLACE(UPPER(placa), "-", "") = REPLACE(:p, "-", "") LIMIT 1');
                $sv->execute([':e' => $empresa_id, ':p' => $placa]);
                $veic = $sv->fetch(PDO::FETCH_ASSOC);
                if ($veic) {
                    $veiculoId = (int) $veic['id'];
                    $placa = (string) $veic['placa'];
                }
            }
            if ($veiculoId < 1) {
                $sv = $conn->prepare('SELECT id, placa FROM veiculos WHERE empresa_id = :e ORDER BY placa LIMIT 1');
                $sv->execute([':e' => $empresa_id]);
                $veic = $sv->fetch(PDO::FETCH_ASSOC);
                if ($veic) {
                    $veiculoId = (int) $veic['id'];
                    $placa = (string) $veic['placa'];
                }
            }
            if ($veiculoId < 1) {
                $answer = 'Nenhum veículo cadastrado para agendar manutenção.';
            } else {
                $dataMan = date('Y-m-d', strtotime('+5 days'));
                if (preg_match('/\b(\d{1,2}\/\d{1,2}(?:\/\d{2,4})?)\b/', $pergunta, $mD)) {
                    $ts = strtotime(str_replace('/', '-', $mD[1]));
                    if ($ts) {
                        $dataMan = date('Y-m-d', $ts);
                    }
                }
                $desc = 'Revisão solicitada via Centro de Inteligência';
                $idAcao = sf_ia_propor_acao(
                    $conn,
                    $empresa_id,
                    $usuario_id ?: null,
                    'agendar_manutencao',
                    'Agendar revisão — ' . $placa,
                    'Manutenção preventiva para ' . $placa . ' em ' . date('d/m/Y', strtotime($dataMan)) . '.',
                    ['veiculo_id' => $veiculoId, 'data_manutencao' => $dataMan, 'descricao' => $desc]
                );
                $answer = $idAcao
                    ? "Preparei agendamento de revisão para {$placa} em " . date('d/m/Y', strtotime($dataMan)) . '. Clique em Confirmar abaixo.'
                    : 'Não foi possível propor o agendamento.';
                $rows = [['Veículo' => $placa, 'Data' => date('d/m/Y', strtotime($dataMan)), 'Tipo' => 'preventiva']];
                $acaoPendenteId = $idAcao;
                $acaoPendenteLabel = sf_ia_acao_label_confirm('agendar_manutencao');
            }
            break;

        case 'notificar_motorista':
            $stM = $conn->prepare('SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome LIMIT 5');
            $stM->execute([':e' => $empresa_id]);
            $motos = $stM->fetchAll(PDO::FETCH_ASSOC);
            if ($motos === []) {
                $answer = 'Nenhum motorista cadastrado para notificar.';
            } else {
                $m0 = $motos[0];
                $msg = 'Olá ' . ($m0['nome'] ?? '') . ', mensagem da transportadora via Centro de Inteligência.';
                $idAcao = sf_ia_propor_acao(
                    $conn,
                    $empresa_id,
                    $usuario_id ?: null,
                    'notificar_motorista',
                    'Notificar — ' . ($m0['nome'] ?? 'motorista'),
                    'Registrar aviso operacional para o motorista (Central de Alertas).',
                    ['motorista_id' => (int) $m0['id'], 'mensagem' => $msg]
                );
                $answer = $idAcao
                    ? 'Preparei a notificação para ' . ($m0['nome'] ?? 'motorista') . '. Clique em Confirmar abaixo para enviar o aviso.'
                    : 'Não foi possível propor a notificação.';
                foreach ($motos as $m) {
                    $rows[] = ['Motorista' => $m['nome']];
                }
                $acaoPendenteId = $idAcao;
                $acaoPendenteLabel = 'Confirmar e notificar';
            }
            break;

        default:
            if ($intent === 'ajuda') {
                $hybrid = sf_ia_hybrid_resolve($conn, $empresa_id, $pergunta);
                if (($hybrid['answer'] ?? '') !== '') {
                    $answer = $hybrid['answer'];
                    $rows = $hybrid['rows'] ?? [];
                    if (!empty($hybrid['camadas'])) {
                        $ctx->raciocinar('IA híbrida: ' . implode(' → ', $hybrid['camadas']) . '.');
                    }
                    if (!empty($hybrid['intent_sugerido'])) {
                        $intent = (string) $hybrid['intent_sugerido'];
                        $via = 'hibrido';
                    }
                    break;
                }
            }
            $answer = "Não entendi exatamente, mas registrei sua pergunta para eu aprender. 🙂\nPosso responder sobre, por exemplo:\n"
                . "• quantas rotas ativas tenho agora?\n• quantas rotas no mês?\n• qual o faturamento do mês passado?\n"
                . "• qual motorista mais faturou?\n• qual o consumo médio da frota?\n• quantos km rodei este mês?\n"
                . "• qual a rota mais rentável?\n• qual cliente gera mais frete?\n• qual veículo ficou mais parado?\n"
                . "• quem mais atrasou?\n• alguma CNH vencendo?\n• quantas multas este mês?\n• quanto gastei de pedágio?\n"
                . "• quanto gastei de manutenção?\n• quanto tenho a pagar?\n• quantos pneus tenho?\n• quantos implementos?\n"
                . "• analise minha empresa\n• analise completa da operação\n• resumo executivo de hoje\n"
                . "• mapa da frota agora\n• como cadastrar motorista?\n• o que significa custo/km?";
            $rows = [];
            break;
    }

    /* ---------- resposta conversacional (ECONÔMICA: só reescreve quando há chave E dados
       reais; pula estados vazios/zero, usa poucos tokens e timeout curto; se falhar, mantém
       o texto padrão). Custa 1 chamada curta ao provedor só nas perguntas que valem. ---------- */
    $semDados = (bool) preg_match('/^(n[ãa]o h[áa]|nenhum|nenhuma|sem )/iu', $answer);
    $usedNarrativa = false;
    $answerStreamed = false;
    $cfg = $cfg ?? sf_ia_router_resolve($conn, $empresa_id, 'narrate');
    $intentsAcao = ['criar_ot', 'exportar_relatorio', 'notificar_whatsapp', 'notificar_motorista', 'agendar_manutencao'];
    if ($cfg && !empty($rows) && !$semDados && $acaoPendenteId === null && !in_array($intent, $intentsAcao, true) && $intent !== 'ajuda' && $intent !== 'ajuda_sistema') {
        try {
            $rowsJson = json_encode(array_slice($rows, 0, 6), JSON_UNESCAPED_UNICODE);
            $sysN = 'Reescreva a resposta abaixo em UMA frase curta, cordial e direta, em português do Brasil, para um gestor de transportadora. Use EXATAMENTE os números fornecidos — não invente nem altere valores. Sem markdown, sem saudação, sem repetir o período se já estiver claro.';
            $usrN = "Resposta base: $answer\nDados (JSON): $rowsJson";
            $messagesN = [
                ['role' => 'system', 'content' => $sysN],
                ['role' => 'user', 'content' => $usrN],
            ];
            if (defined('SF_IA_SSE') && SF_IA_SSE) {
                sf_ia_sse_send('phase', ['step' => 'narrating', 'msg' => 'Reescrevendo resposta…']);
                $llmN = sf_ia_router_chat_stream($conn, $empresa_id, 'narrate', $messagesN, static function (string $delta): void {
                    sf_ia_sse_send('token', ['t' => $delta]);
                }, 90, 0.3);
                $answerStreamed = true;
            } else {
                $llmN = sf_ia_router_chat($conn, $empresa_id, 'narrate', $messagesN, 90, 0.3);
            }
            if ($llmN && ($llmN['content'] ?? '') !== '' && mb_strlen($llmN['content']) <= 400) {
                $answer = $llmN['content'];
                $usedNarrativa = true;
                $ctx->addTokens((int) ($llmN['tokens'] ?? 0));
                $ctx->modelo = $llmN['model'] ?? null;
            }
        } catch (Throwable $e) {
            error_log('ia_assistente narrativa: ' . $e->getMessage());
        }
    }

    $ctx->timeline('pos_processamento');
    $sqlMs = (int) round((microtime(true) - $t0) * 1000);
    sf_ia_tools_record_execution($ctx, $intent, count($rows), $sqlMs, ($via === 'ia' || $usedNarrativa), $ctx->cacheHit);

    $comparacao = sf_ia_copilot_comparacao($conn, $empresa_id, $intent, $ini, $fim, $moeda);
    if ($comparacao) {
        $analise = array_merge($analise, sf_ia_copilot_analise_bloco($comparacao, $causasManut, $moeda));
        if ($comparacao['pct'] > 0 && $intent === 'manutencao') {
            $analise[] = '↑ ' . abs($comparacao['pct']) . '% acima do período anterior equivalente.';
        }
    } elseif ($causasManut !== []) {
        $analise = sf_ia_copilot_analise_bloco(null, $causasManut, $moeda);
    }
    if (!empty($pedeComparacao) && !$comparacao) {
        $analise[] = 'Sem dados suficientes nos dois períodos para comparar — quando houver movimento, mostro a variação % automaticamente.';
    }
    $recomendacoes = $recomendacoes !== [] ? $recomendacoes : sf_ia_copilot_recomendacoes($conn, $empresa_id, $intent, $ini, $fim, $causasManut);
    $confianca = sf_ia_copilot_confianca($intent, $rows, $semDados);
    $cards = sf_ia_copilot_cards($intent, $rows);
    if ($intent === 'executivo') {
        $execCards = sf_ia_copilot_executivo($conn, $empresa_id, $moeda, $num);
        $cards = $execCards['cards'] ?? $cards;
    }
    $cards = sf_ia_tools_enrich_cards($cards, $intent, $rows);

    $agente = sf_ia_agente_for_intent($intent);
    $modoReq = trim((string) ($_GET['modo'] ?? $_POST['modo'] ?? 'conversar'));
    $skill = sf_ia_skill_for_intent($intent);
    $rowCount = count($rows);

    if ($acaoPendenteId) {
        $_SESSION['sf_ia_ultima_acao_id'] = $acaoPendenteId;
    }

    $payload = [
        'success' => true,
        'intent' => $intent,
        'periodo' => $periodoLabel,
        'via' => $via,
        'answer' => $answer,
        'rows' => $rows,
        'chart' => $chart,
        'cards' => $cards,
        'analise' => $analise,
        'recomendacoes' => $recomendacoes,
        'comparacao' => $comparacao,
        'confianca' => $confianca,
        'fontes' => sf_ia_copilot_fontes($intent),
        'fontes_clicaveis' => sf_ia_tools_fontes_clicaveis($intent, $rowCount, $periodoLabel),
        'sugestoes' => sf_ia_copilot_sugestoes($intent, $periodoLabel),
        'modo' => $modoReq,
        'agente' => $agente,
        'agente_label' => sf_ia_agente_label($agente),
        'skill' => $skill,
        'skill_label' => sf_ia_skill_label($skill),
        'tools' => $ctx->tools,
        'raciocinio' => $ctx->raciocinio,
        'timeline' => $ctx->timeline,
        'map' => $ctx->mapa,
        'acao_tela' => $ctx->acaoTela,
        'acao_pendente' => $acaoPendenteId ? ['id' => $acaoPendenteId, 'label' => $acaoPendenteLabel] : null,
        'tokens_estimados' => $ctx->tokensEstimados,
        'cache_hit' => $ctx->cacheHit,
    ];

    $devMode = ($_GET['dev'] ?? $_POST['dev'] ?? '') === '1' || !empty($_SESSION['adm_impersonating']);
    if ($devMode) {
        $payload['dev'] = true;
        sf_ia_support_trace($conn, $empresa_id, $usuario_id ?: null, $pergunta, $intent, $payload);
    }

    $tempoMs = (int) round((microtime(true) - $t0) * 1000);
    $auditMeta = [
        'tools' => $ctx->tools,
        'raciocinio' => $ctx->raciocinio,
        'timeline' => $ctx->timeline,
        'tokens_estimados' => $ctx->tokensEstimados,
        'cache_hit' => $ctx->cacheHit,
        'modelo' => $cfg['model'] ?? null,
    ];
    sf_ia_auditoria_log($conn, $empresa_id, $usuario_id ?: null, $intent, $agente, $pergunta, $via, (int) ($confianca['score'] ?? null), $payload['fontes'], $tempoMs, $auditMeta);
    sf_ia_conversa_save($conn, $empresa_id, $usuario_id ?: null, 'user', $pergunta, null, null);
    sf_ia_conversa_save($conn, $empresa_id, $usuario_id ?: null, 'assistant', $answer, $intent, ['cards' => $cards]);

    sf_ia_metrics_track($conn, $empresa_id, [
        'tokens_out' => $ctx->tokensEstimados,
        'cache_hit' => $ctx->cacheHit,
        'llm' => ($via === 'ia' || $usedNarrativa),
        'falha' => false,
    ]);
    sf_ia_metrics_track_intent($conn, $empresa_id, $intent, $tempoMs, false);

    if (!in_array($intent, $noCacheIntents, true)) {
        sf_ia_cache_set($conn, $empresa_id, $cacheKey, $payload, 15);
    }

    if (defined('SF_IA_SSE') && SF_IA_SSE) {
        if (!$answerStreamed) {
            sf_ia_stream_answer_chunks($answer);
        }
        sf_ia_sse_send('done', $payload);
        exit;
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ia_assistente.php: ' . $e->getMessage());
    if (isset($conn, $empresa_id) && $empresa_id > 0) {
        sf_ia_metrics_track($conn, $empresa_id, ['falha' => true]);
        if (isset($intent)) {
            sf_ia_metrics_track_intent($conn, $empresa_id, $intent, (int) round((microtime(true) - $t0) * 1000), true);
        }
    }
    if (defined('SF_IA_SSE') && SF_IA_SSE) {
        sf_ia_sse_send('error', ['error' => 'Não consegui consultar agora.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não consegui consultar agora. Tente novamente.'], JSON_UNESCAPED_UNICODE);
}
