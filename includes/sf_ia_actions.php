<?php
/**
 * Helpers de ações do Copiloto — confirmação contextual, parsing de OT, avisos motorista.
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_ia_platform.php';

/** Detecta confirmação ou cancelamento curto (ok, sim, confirmar, cancelar…). */
function sf_ia_detect_confirmacao(string $qNorm): ?string
{
    $q = trim($qNorm);
    if ($q === '') {
        return null;
    }
    $cancel = ['cancelar', 'cancela', 'nao', 'não', 'negar', 'recusar', 'desistir'];
    foreach ($cancel as $w) {
        if ($q === $w || preg_match('/^' . preg_quote($w, '/') . '\b/u', $q)) {
            return 'cancel';
        }
    }
    $confirm = ['ok', 'sim', 'confirmar', 'confirmo', 'pode criar', 'pode sim', 'aprovar', 'executar', 'manda ver', 'fechado', 'isso', 'bora'];
    foreach ($confirm as $w) {
        if ($q === $w || preg_match('/^' . preg_quote($w, '/') . '\b/u', $q)) {
            return 'confirm';
        }
    }
    if (preg_match('/^confirm(ar|o|a)?\s*(acao|ação|proposta|ot|ordem)?\s*#?\d*$/u', $q)) {
        return 'confirm';
    }
    return null;
}

/** Última ação pendente da empresa (opcionalmente do usuário). */
function sf_ia_ultima_acao_pendente(PDO $conn, int $empresaId, ?int $usuarioId = null, int $acaoId = 0): ?array
{
    if ($acaoId > 0) {
        return sf_ia_acao_get($conn, $empresaId, $acaoId);
    }
    $lista = sf_ia_acoes_list($conn, $empresaId, 'pendente', 5);
    if ($usuarioId !== null && $usuarioId > 0) {
        foreach ($lista as $a) {
            // Preferir ações do mesmo usuário quando houver
        }
    }
    return $lista[0] ?? null;
}

/** Rótulo do botão Confirmar conforme o tipo da ação. */
function sf_ia_acao_label_confirm(string $tipo): string
{
    static $map = [
        'criar_ot' => 'Confirmar e criar OT',
        'agendar_manutencao' => 'Confirmar manutenção',
        'exportar_relatorio' => 'Confirmar exportação',
        'abrir_whatsapp' => 'Confirmar e abrir WhatsApp',
        'enviar_email' => 'Confirmar envio de e-mail',
        'notificar_motorista' => 'Confirmar e notificar',
    ];
    return $map[$tipo] ?? 'Confirmar ação';
}

/** Extrai origem/destino da pergunta de OT. */
function sf_ia_parse_rota_ot(string $pergunta): array
{
    $txt = preg_replace('/.*\b(?:transporte|ordem|ot|cota[çc][aã]o)\b\s*/iu', '', $pergunta);
    if ($txt === $pergunta) {
        $txt = $pergunta;
    }
    $origem = '';
    $destino = '';
    if (preg_match('/\b(?:de\s+|entre\s+)?(.+?)\s+(?:para|até|ate|a|→|->)\s+(.+)/iu', $txt, $m)) {
        $origem = trim($m[1], " .,-\t");
        $destino = trim($m[2], " .,-\t");
        $destino = preg_replace('/\s+(frete|r\$|valor|km|cliente)\b.*$/iu', '', $destino);
        $destino = trim((string) $destino);
    }
    return ['origem' => $origem, 'destino' => $destino];
}

/** Distâncias aproximadas entre capitais (km rodoviário). */
function sf_ia_distancia_heuristica(string $origem, string $destino): float
{
    $norm = static function (string $s): string {
        $s = mb_strtolower($s, 'UTF-8');
        $map = ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c'];
        $s = strtr($s, $map);
        if (preg_match('/\b([a-z]{2})\b/u', $s, $m)) {
            return $m[1];
        }
        if (strpos($s, 'sao paulo') !== false || preg_match('/\bsp\b/', $s)) {
            return 'sp';
        }
        if (strpos($s, 'curitiba') !== false || preg_match('/\bpr\b/', $s)) {
            return 'pr';
        }
        if (strpos($s, 'rio') !== false || preg_match('/\brj\b/', $s)) {
            return 'rj';
        }
        if (strpos($s, 'belo horizonte') !== false || preg_match('/\bmg\b/', $s)) {
            return 'mg';
        }
        if (strpos($s, 'brasilia') !== false || preg_match('/\bdf\b/', $s)) {
            return 'df';
        }
        return trim($s);
    };
    $o = $norm($origem);
    $d = $norm($destino);
    static $pares = [
        'sp-pr' => 408, 'sp-rj' => 430, 'sp-mg' => 586, 'sp-df' => 1015,
        'rj-mg' => 434, 'pr-sc' => 300, 'sp-sc' => 500, 'mg-df' => 740,
    ];
    $key1 = $o . '-' . $d;
    $key2 = $d . '-' . $o;
    return (float) ($pares[$key1] ?? $pares[$key2] ?? 0);
}

/** Monta parâmetros completos para criar OT a partir da pergunta. */
function sf_ia_parse_ot_params(PDO $conn, int $empresaId, string $pergunta): array
{
    $rota = sf_ia_parse_rota_ot($pergunta);
    $origem = $rota['origem'] !== '' ? $rota['origem'] : 'Origem a definir';
    $destino = $rota['destino'] !== '' ? $rota['destino'] : 'Destino a definir';

    $cliNome = 'Cliente via Copiloto';
    if (sf_ia_table_exists($conn, 'clientes')) {
        $st = $conn->prepare('SELECT id, COALESCE(NULLIF(nome_fantasia, ""), razao_social) AS nome FROM clientes WHERE empresa_id = :e AND status = "ativo" ORDER BY razao_social');
        $st->execute([':e' => $empresaId]);
        $perguntaLow = mb_strtolower($pergunta, 'UTF-8');
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $nome = (string) ($c['nome'] ?? '');
            if ($nome !== '' && mb_strlen($nome) >= 4 && mb_stripos($perguntaLow, mb_strtolower($nome, 'UTF-8')) !== false) {
                $cliNome = $nome;
                break;
            }
        }
        if ($cliNome === 'Cliente via Copiloto') {
            $st2 = $conn->prepare('SELECT COALESCE(NULLIF(nome_fantasia, ""), razao_social) AS nome FROM clientes WHERE empresa_id = :e AND status = "ativo" ORDER BY razao_social LIMIT 1');
            $st2->execute([':e' => $empresaId]);
            $cliNome = (string) ($st2->fetchColumn() ?: 'Cliente via Copiloto');
        }
    }

    $valorFrete = 0.0;
    if (preg_match('/r\$\s*([\d.,]+)/iu', $pergunta, $m)) {
        $valorFrete = (float) str_replace(['.', ','], ['', '.'], $m[1]);
    } elseif (preg_match('/frete\s*(?:de\s*)?([\d.,]+)/iu', $pergunta, $m)) {
        $valorFrete = (float) str_replace(['.', ','], ['', '.'], $m[1]);
    }

    $distKm = 0.0;
    if (preg_match('/(\d{2,5})\s*km/iu', $pergunta, $m)) {
        $distKm = (float) $m[1];
    } else {
        $distKm = sf_ia_distancia_heuristica($origem, $destino);
    }

    $anttPiso = null;
    if ($distKm > 0 && $valorFrete <= 0 && function_exists('sf_antt_calc_piso')) {
        require_once __DIR__ . '/sf_antt_frete.php';
        $antt = sf_antt_calc_piso($conn, 'geral', 5, $distKm);
        if ($antt && isset($antt['piso'])) {
            $anttPiso = (float) $antt['piso'];
            $valorFrete = $anttPiso;
        }
    }

    return [
        'cliente_nome' => $cliNome,
        'origem' => $origem,
        'destino' => $destino,
        'valor_frete' => $valorFrete,
        'distancia_km' => $distKm,
        'antt_piso' => $anttPiso,
    ];
}

/** Garante tabela de avisos push para o app motorista. */
function sf_ia_motorista_avisos_ensure(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    try {
        $conn->exec('CREATE TABLE IF NOT EXISTS motorista_avisos_ia (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT UNSIGNED NOT NULL,
            motorista_id INT UNSIGNED NOT NULL,
            titulo VARCHAR(160) NOT NULL,
            mensagem TEXT NOT NULL,
            origem VARCHAR(40) NOT NULL DEFAULT "ia_copilot",
            lido_em DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_mot (motorista_id, empresa_id, lido_em),
            KEY idx_emp (empresa_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    } catch (Throwable $e) {
        error_log('sf_ia_motorista_avisos_ensure: ' . $e->getMessage());
    }
}

/** Insere aviso para o motorista ver no app (polling avisos.php). */
function sf_ia_motorista_aviso_inserir(PDO $conn, int $empresaId, int $motoristaId, string $titulo, string $mensagem): bool
{
    sf_ia_motorista_avisos_ensure($conn);
    try {
        $st = $conn->prepare('INSERT INTO motorista_avisos_ia (empresa_id, motorista_id, titulo, mensagem, origem) VALUES (:e, :m, :t, :msg, "ia_copilot")');
        $st->execute([
            ':e' => $empresaId,
            ':m' => $motoristaId,
            ':t' => mb_substr($titulo, 0, 160),
            ':msg' => mb_substr($mensagem, 0, 2000),
        ]);
        return true;
    } catch (Throwable $e) {
        error_log('sf_ia_motorista_aviso_inserir: ' . $e->getMessage());
        return false;
    }
}

/** Reindexa embeddings de todos os chunks da empresa (ou uma). */
function sf_ia_reindex_embeddings(PDO $conn, int $empresaId, int $limit = 500): array
{
    require_once __DIR__ . '/sf_ia_embeddings.php';
    sf_ia_embeddings_ensure_schema($conn);
    if (!sf_ia_table_exists($conn, 'ia_documento_chunks')) {
        return ['ok' => false, 'indexados' => 0, 'msg' => 'Tabela de chunks não existe.'];
    }
    $limit = max(1, min(2000, $limit));
    try {
        $st = $conn->prepare("SELECT c.id, c.chunk_text FROM ia_documento_chunks c
            JOIN ia_documentos d ON d.id = c.documento_id
            WHERE c.empresa_id = :e AND d.status = 'pronto' AND (c.embedding IS NULL OR c.embedding = 'null')
            ORDER BY c.id DESC LIMIT {$limit}");
        $st->execute([':e' => $empresaId]);
        $n = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            sf_ia_embedding_index_chunk($conn, (int) $row['id'], $empresaId, (string) $row['chunk_text']);
            $n++;
        }
        return ['ok' => true, 'indexados' => $n, 'msg' => "{$n} chunk(s) reindexado(s)."];
    } catch (Throwable $e) {
        error_log('sf_ia_reindex_embeddings: ' . $e->getMessage());
        return ['ok' => false, 'indexados' => 0, 'msg' => 'Erro ao reindexar.'];
    }
}

/** Custos IA agregados multi-empresa (admin). */
function sf_ia_metrics_custos_global(PDO $conn, int $dias = 30): array
{
    sf_ia_metrics_ensure_schema($conn);
    $out = ['consultas' => 0, 'tokens' => 0, 'custo_usd' => 0, 'empresas' => 0, 'por_dia' => []];
    if (!sf_ia_table_exists($conn, 'ia_uso_empresa')) {
        return $out;
    }
    $dias = max(1, min(90, $dias));
  try {
        $st = $conn->prepare("SELECT SUM(consultas) c, SUM(tokens_in+tokens_out) t, SUM(custo_estimado_usd) u, COUNT(DISTINCT empresa_id) emp
            FROM ia_uso_empresa WHERE dia >= DATE_SUB(CURDATE(), INTERVAL :d DAY)");
        $st->execute([':d' => $dias]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $out['consultas'] = (int) ($r['c'] ?? 0);
        $out['tokens'] = (int) ($r['t'] ?? 0);
        $out['custo_usd'] = round((float) ($r['u'] ?? 0), 4);
        $out['empresas'] = (int) ($r['emp'] ?? 0);
        $st2 = $conn->prepare("SELECT dia, SUM(consultas) c, SUM(custo_estimado_usd) u FROM ia_uso_empresa
            WHERE dia >= DATE_SUB(CURDATE(), INTERVAL :d DAY) GROUP BY dia ORDER BY dia");
        $st2->execute([':d' => $dias]);
        $out['por_dia'] = $st2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // ignora
    }
    return $out;
}
