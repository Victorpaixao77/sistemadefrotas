<?php
/**
 * Tools do Centro de Inteligência — registry, execução, raciocínio e timeline.
 */
declare(strict_types=1);

/** Contexto de uma execução (uma pergunta). */
final class SfIaRunContext
{
    public array $tools = [];
    public array $raciocinio = [];
    public array $timeline = [];
    public int $linhasLidas = 0;
    public bool $cacheHit = false;
    public ?string $modelo = null;
    public int $tokensEstimados = 0;
    public bool $falhou = false;
    public ?array $mapa = null;
    public ?array $acaoTela = null;

    public function timeline(string $fase, array $dados = []): void
    {
        $this->timeline[] = array_merge(['fase' => $fase, 'ts' => microtime(true)], $dados);
    }

    public function raciocinar(string $texto): void
    {
        if ($texto !== '') {
            $this->raciocinio[] = $texto;
        }
    }

    public function tool(string $id, string $label, int $ms, int $linhas = 0, ?string $detalhe = null): void
    {
        $this->tools[] = [
            'id' => $id,
            'label' => $label,
            'tempo_ms' => $ms,
            'linhas' => $linhas,
            'detalhe' => $detalhe,
        ];
        $this->linhasLidas += max(0, $linhas);
    }

    public function addTokens(int $n): void
    {
        $this->tokensEstimados += max(0, $n);
    }
}

/** Registry de ferramentas disponíveis. */
function sf_ia_tools_registry(): array
{
    return [
        'consultar_banco' => ['label' => 'Consultar Banco', 'permission' => null],
        'consultar_rotas' => ['label' => 'Consultar Rotas', 'permission' => null, 'tela' => 'pages/routes.php'],
        'consultar_financeiro' => ['label' => 'Consultar Financeiro', 'permission' => 'can_view_financial_data', 'tela' => 'pages/financial.php'],
        'consultar_veiculos' => ['label' => 'Consultar Veículos', 'permission' => null, 'tela' => 'pages/vehicles.php'],
        'consultar_gps' => ['label' => 'Consultar GPS', 'permission' => 'can_view_fleet_map', 'tela' => 'pages/mapa_frota.php'],
        'consultar_documentos' => ['label' => 'Consultar Documentos', 'permission' => null],
        'consultar_ocr' => ['label' => 'Consultar OCR', 'permission' => null],
        'consultar_apis' => ['label' => 'Consultar APIs', 'permission' => null],
        'executar_automacao' => ['label' => 'Executar Automação', 'permission' => null],
        'gerar_pdf' => ['label' => 'Gerar PDF', 'permission' => 'can_view_financial_data', 'tela' => 'api/ia_relatorio_pdf.php'],
        'exportar_excel' => ['label' => 'Exportar Excel', 'permission' => null, 'tela' => 'pages/relatorios.php'],
        'abrir_tela' => ['label' => 'Abrir Tela', 'permission' => null],
        'narrativa_llm' => ['label' => 'Narrativa LLM', 'permission' => null],
        'classificador_llm' => ['label' => 'Classificador LLM', 'permission' => null],
    ];
}

/** Tools usadas por intenção (skill → tools). */
function sf_ia_tools_for_intent(string $intent): array
{
    static $map = [
        'faturamento' => ['consultar_banco', 'consultar_rotas', 'consultar_financeiro'],
        'motorista_top' => ['consultar_banco', 'consultar_rotas'],
        'cliente_top' => ['consultar_banco', 'consultar_rotas'],
        'rota_top' => ['consultar_banco', 'consultar_rotas'],
        'veiculo_top' => ['consultar_banco', 'consultar_rotas', 'consultar_veiculos'],
        'veiculo_parado' => ['consultar_banco', 'consultar_rotas', 'consultar_veiculos'],
        'rotas_ativas' => ['consultar_banco', 'consultar_rotas'],
        'rotas_mes' => ['consultar_banco', 'consultar_rotas'],
        'no_prazo' => ['consultar_banco', 'consultar_rotas'],
        'atrasos' => ['consultar_banco', 'consultar_rotas'],
        'cnh' => ['consultar_banco', 'consultar_veiculos'],
        'consumo' => ['consultar_banco', 'consultar_rotas', 'consultar_veiculos'],
        'km' => ['consultar_banco', 'consultar_rotas'],
        'abastecimentos' => ['consultar_banco', 'consultar_veiculos'],
        'pedagio' => ['consultar_banco', 'consultar_financeiro'],
        'despesas' => ['consultar_banco', 'consultar_financeiro'],
        'multas' => ['consultar_banco', 'consultar_financeiro'],
        'frota' => ['consultar_banco', 'consultar_veiculos'],
        'manutencao' => ['consultar_banco', 'consultar_veiculos'],
        'contas_pagar' => ['consultar_banco', 'consultar_financeiro'],
        'pneus' => ['consultar_banco', 'consultar_veiculos'],
        'implementos' => ['consultar_banco', 'consultar_veiculos'],
        'analise_empresa' => ['consultar_banco', 'consultar_rotas', 'consultar_financeiro', 'consultar_veiculos'],
        'analise_completa' => ['consultar_banco', 'consultar_rotas', 'consultar_financeiro', 'consultar_veiculos', 'consultar_gps'],
        'executivo' => ['consultar_banco', 'consultar_rotas', 'consultar_financeiro', 'consultar_gps'],
        'previsoes' => ['consultar_banco', 'consultar_veiculos'],
        'documentos' => ['consultar_documentos'],
        'mapa_frota' => ['consultar_gps', 'consultar_veiculos'],
        'ajuda_sistema' => ['consultar_documentos'],
        'glossario' => ['consultar_banco', 'consultar_financeiro'],
    ];
    return $map[$intent] ?? ['consultar_banco'];
}

/** Skill (domínio) por intenção — alinhado aos agentes. */
function sf_ia_skill_for_intent(string $intent): string
{
    return sf_ia_agente_for_intent($intent);
}

function sf_ia_skill_label(string $skill): string
{
    static $labels = [
        'financeiro' => 'Skill Financeiro',
        'frota' => 'Skill Frota',
        'oficina' => 'Skill Manutenção',
        'logistica' => 'Skill Logística',
        'rh' => 'Skill RH',
        'comercial' => 'Skill Comercial',
        'geral' => 'Skill Copiloto',
    ];
    return $labels[$skill] ?? 'Skill';
}

/** Registra tools simulando execução pós-query (tempo agregado). */
function sf_ia_tools_record_execution(SfIaRunContext $ctx, string $intent, int $rowCount, int $sqlMs, bool $usedLlm = false, bool $usedCache = false): void
{
    $registry = sf_ia_tools_registry();
    $toolIds = sf_ia_tools_for_intent($intent);
    $n = max(1, count($toolIds));
    $msEach = (int) max(1, round($sqlMs / $n));

    foreach ($toolIds as $tid) {
        $label = $registry[$tid]['label'] ?? $tid;
        $linhas = ($tid === 'consultar_banco' || str_starts_with($tid, 'consultar_')) ? $rowCount : 0;
        $ctx->tool($tid, $label, $msEach, $linhas);
    }

    if ($usedCache) {
        $ctx->tool('cache', 'Cache de consulta', 1, 0, 'Hit 15 min');
        $ctx->raciocinar('Resposta servida do cache (consulta idêntica nos últimos 15 min).');
    } else {
        $ctx->raciocinar('Consultei ' . max($rowCount, 1) . ' registro(s) no banco (empresa isolada).');
    }

    if ($usedLlm) {
        $ctx->tool('narrativa_llm', 'Narrativa LLM', 50, 0);
        $ctx->raciocinar('Reescrevi a resposta em linguagem natural (sem alterar números).');
    }

    $ctx->raciocinar('Skill ativa: ' . sf_ia_skill_label(sf_ia_skill_for_intent($intent)) . '.');
}

/** Fontes clicáveis com URL e contagem. */
function sf_ia_tools_fontes_clicaveis(string $intent, int $rowCount, string $periodoLabel = ''): array
{
    $registry = sf_ia_tools_registry();
    $base = function_exists('sf_app_url') ? rtrim(sf_app_url(''), '/') : '/sistema-frotas';
    $q = $periodoLabel !== '' ? ('?periodo=' . rawurlencode($periodoLabel)) : '';

    static $intentTelas = [
        'faturamento' => ['Rotas' => 'pages/routes.php', 'Financeiro' => 'pages/financial.php'],
        'multas' => ['Multas' => 'pages/multas.php'],
        'manutencao' => ['Manutenções' => 'pages/manutencoes.php', 'Veículos' => 'pages/vehicles.php'],
        'cnh' => ['Motoristas' => 'pages/motorists.php'],
        'rotas_ativas' => ['Rotas' => 'pages/routes.php', 'GPS' => 'pages/mapa_frota.php'],
        'mapa_frota' => ['GPS' => 'pages/mapa_frota.php', 'Veículos' => 'pages/vehicles.php'],
        'contas_pagar' => ['Contas a pagar' => 'pages/financial.php'],
        'frota' => ['Veículos' => 'pages/vehicles.php'],
        'pneus' => ['Pneus' => 'pages/pneus.php'],
        'implementos' => ['Implementos' => 'pages/implementos.php'],
        'abastecimentos' => ['Abastecimentos' => 'pages/refuels.php'],
        'executivo' => ['Rotas' => 'pages/routes.php', 'Financeiro' => 'pages/financial.php', 'GPS' => 'pages/mapa_frota.php'],
        'analise_completa' => ['Rotas' => 'pages/routes.php', 'Financeiro' => 'pages/financial.php', 'Manutenções' => 'pages/manutencoes.php'],
    ];

    $fontes = [];
    $map = $intentTelas[$intent] ?? [];
    if ($map === []) {
        $nomes = sf_ia_copilot_fontes($intent);
        foreach ($nomes as $nome) {
            $fontes[] = ['nome' => $nome, 'count' => $rowCount > 0 ? $rowCount : null, 'url' => null];
        }
        return $fontes;
    }

    foreach ($map as $nome => $path) {
        $url = $base . '/' . ltrim($path, '/') . $q;
        $fontes[] = [
            'nome' => $nome,
            'count' => $rowCount > 0 ? $rowCount : null,
            'url' => $url,
            'acao' => 'abrir',
        ];
    }
    return $fontes;
}

/** Detecta pedido de abrir tela / navegação. */
function sf_ia_tools_detectar_navegacao(string $q): ?array
{
    static $map = [
        'cadastrar veiculo' => ['titulo' => 'Cadastro de Veículos', 'url' => 'pages/vehicles.php', 'filtros' => []],
        'cadastrar motorista' => ['titulo' => 'Cadastro de Motoristas', 'url' => 'pages/motorists.php', 'filtros' => []],
        'mostre multas' => ['titulo' => 'Multas', 'url' => 'pages/multas.php', 'filtros' => ['periodo' => 'mes_atual']],
        'ver multas' => ['titulo' => 'Multas', 'url' => 'pages/multas.php', 'filtros' => ['periodo' => 'mes_atual']],
        'abrir rotas' => ['titulo' => 'Rotas', 'url' => 'pages/routes.php', 'filtros' => []],
        'mapa da frota' => ['titulo' => 'Mapa da Frota', 'url' => 'pages/mapa_frota.php', 'filtros' => []],
        'relatorios' => ['titulo' => 'Relatórios', 'url' => 'pages/relatorios.php', 'filtros' => []],
        'manutencoes' => ['titulo' => 'Manutenções', 'url' => 'pages/manutencoes.php', 'filtros' => []],
    ];
    foreach ($map as $needle => $tela) {
        if (strpos($q, $needle) !== false) {
            $base = function_exists('sf_app_url') ? rtrim(sf_app_url(''), '/') : '/sistema-frotas';
            $qs = http_build_query($tela['filtros']);
            return [
                'tipo' => 'abrir_tela',
                'titulo' => $tela['titulo'],
                'url' => $base . '/' . ltrim($tela['url'], '/') . ($qs ? '?' . $qs : ''),
                'filtros' => $tela['filtros'],
            ];
        }
    }
    return null;
}

/** Fórmulas explicáveis nos cards por intenção. */
function sf_ia_tools_card_formulas(string $intent): array
{
    static $formulas = [
        'faturamento' => [
            'Faturamento' => 'Soma do campo frete das rotas no período.',
            'Comissões' => 'Soma das comissões das rotas no período.',
            'Lucro aprox.' => 'Faturamento − comissões (não inclui combustível/oficina).',
        ],
        'consumo' => [
            'km/L' => 'KM das rotas ÷ litros abastecidos no período.',
        ],
        'glossario' => [
            'Custo/km' => '(Comissões + combustível registrado) ÷ KM das rotas.',
        ],
        'executivo' => [
            'Margem' => '(Faturamento − comissões) ÷ faturamento × 100.',
        ],
    ];
    return $formulas[$intent] ?? [];
}

/** Inputs explicáveis (valores usados no cálculo). */
function sf_ia_tools_card_inputs(string $intent, array $rows): array
{
    if ($rows === []) {
        return [];
    }
    $r0 = $rows[0];
    $out = [];
    if ($intent === 'faturamento') {
        if (isset($r0['Faturamento'])) {
            $out['Faturamento'] = ['frete' => $r0['Faturamento']];
        }
        if (isset($r0['Comissões'])) {
            $out['Comissões'] = ['comissões' => $r0['Comissões']];
        }
        if (isset($r0['Rotas'])) {
            $out['Lucro aprox.'] = ['rotas' => $r0['Rotas'], 'fórmula' => 'frete − comissões'];
        }
    }
    if ($intent === 'executivo' && isset($r0['Margem'])) {
        $out['Margem'] = ['valor' => $r0['Margem'] ?? '—'];
    }
    if ($intent === 'consumo' && isset($r0['km/L'])) {
        $out['km/L'] = ['consumo' => $r0['km/L']];
    }
    return $out;
}

/** Enriquece cards com formula e inputs quando existir. */
function sf_ia_tools_enrich_cards(array $cards, string $intent, array $rows = []): array
{
    $formulas = sf_ia_tools_card_formulas($intent);
    $inputs = sf_ia_tools_card_inputs($intent, $rows);
    foreach ($cards as &$c) {
        $title = (string) ($c['title'] ?? '');
        if (isset($formulas[$title])) {
            $c['formula'] = $formulas[$title];
        }
        if (isset($inputs[$title])) {
            $c['inputs'] = $inputs[$title];
        }
    }
    unset($c);
    return $cards;
}

/** GPS — posições para mapa no chat. */
function sf_ia_tools_mapa_gps(PDO $conn, int $empresaId): array
{
    $markers = [];
    try {
        $sql = "SELECT v.placa, g.latitude, g.longitude, g.data_hora, g.velocidade
            FROM gps_posicoes g
            INNER JOIN (
                SELECT veiculo_id, MAX(id) mid FROM gps_posicoes WHERE empresa_id = :e GROUP BY veiculo_id
            ) last ON last.mid = g.id
            JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = :e
            WHERE g.empresa_id = :e AND g.latitude IS NOT NULL AND g.longitude IS NOT NULL
            LIMIT 50";
        $st = $conn->prepare($sql);
        $st->execute([':e' => $empresaId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $markers[] = [
                'placa' => $r['placa'],
                'lat' => (float) $r['latitude'],
                'lng' => (float) $r['longitude'],
                'hora' => $r['data_hora'],
                'vel' => $r['velocidade'],
            ];
        }
    } catch (Throwable $e) {
        error_log('sf_ia_tools_mapa_gps: ' . $e->getMessage());
    }
    return ['markers' => $markers, 'total' => count($markers)];
}

/** Intenções custom da empresa (admin mapeou). */
function sf_ia_tools_match_custom(PDO $conn, int $empresaId, string $qNorm): ?string
{
    if (!function_exists('sf_ia_table_exists') || !sf_ia_table_exists($conn, 'ia_intencoes_custom')) {
        return null;
    }
    try {
        $st = $conn->prepare("SELECT padrao, intent FROM ia_intencoes_custom
            WHERE ativo = 1 AND (empresa_id IS NULL OR empresa_id = :e)");
        $st->execute([':e' => $empresaId]);
        // normaliza o padrão IGUAL a $qNorm (minúsculo + sem acento) para casar de fato
        $acc = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pat = strtr(mb_strtolower(trim((string) $row['padrao']), 'UTF-8'), $acc);
            if ($pat !== '' && strpos($qNorm, $pat) !== false) {
                return preg_replace('/[^a-z_]/', '', (string) $row['intent']);
            }
        }
    } catch (Throwable $e) {
        // ignora
    }
    return null;
}

/** Registra pergunta não entendida na fila admin. */
function sf_ia_tools_queue_unanswered(PDO $conn, int $empresaId, string $pergunta): void
{
    if (!sf_ia_table_exists($conn, 'ia_perguntas_pendentes') || $pergunta === '') {
        return;
    }
    try {
        $st = $conn->prepare('SELECT id, ocorrencias FROM ia_perguntas_pendentes
            WHERE empresa_id = :e AND pergunta = :p AND status = \'pendente\' LIMIT 1');
        $st->execute([':e' => $empresaId, ':p' => mb_substr($pergunta, 0, 500)]);
        $ex = $st->fetch(PDO::FETCH_ASSOC);
        if ($ex) {
            $conn->prepare('UPDATE ia_perguntas_pendentes SET ocorrencias = ocorrencias + 1, updated_at = NOW() WHERE id = :id')
                ->execute([':id' => $ex['id']]);
        } else {
            $conn->prepare('INSERT INTO ia_perguntas_pendentes (empresa_id, pergunta) VALUES (:e, :p)')
                ->execute([':e' => $empresaId, ':p' => mb_substr($pergunta, 0, 500)]);
        }
    } catch (Throwable $e) {
        error_log('sf_ia_tools_queue_unanswered: ' . $e->getMessage());
    }
}
