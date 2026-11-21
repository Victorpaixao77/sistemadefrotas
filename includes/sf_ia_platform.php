<?php
/**
 * Plataforma do Centro de Inteligência — schema, cache, memória, auditoria,
 * agentes, RAG, ações pendentes, permissões e previsões.
 */
declare(strict_types=1);

require_once __DIR__ . '/permissions.php';

/** Intenções que exigem permissão financeira. */
function sf_ia_intents_financeiros(): array
{
    return [
        'faturamento', 'contas_pagar', 'pedagio', 'despesas', 'multas',
        'executivo', 'glossario', 'cliente_top', 'rota_top', 'motorista_top',
    ];
}

/** Agente especializado por intenção. */
function sf_ia_agente_for_intent(string $intent): string
{
    static $map = [
        'faturamento' => 'financeiro', 'contas_pagar' => 'financeiro', 'pedagio' => 'financeiro',
        'despesas' => 'financeiro', 'multas' => 'financeiro', 'glossario' => 'financeiro',
        'frota' => 'frota', 'veiculo_top' => 'frota', 'veiculo_parado' => 'frota',
        'consumo' => 'frota', 'km' => 'frota', 'pneus' => 'frota', 'implementos' => 'frota',
        'manutencao' => 'oficina', 'cnh' => 'rh', 'motorista_top' => 'rh',
        'rotas_ativas' => 'logistica', 'rotas_mes' => 'logistica', 'rota_top' => 'logistica',
        'no_prazo' => 'logistica', 'atrasos' => 'logistica', 'cliente_top' => 'comercial',
        'abastecimentos' => 'frota', 'analise_empresa' => 'geral', 'executivo' => 'geral',
        'previsoes' => 'geral', 'documentos' => 'geral',
        'mapa_frota' => 'logistica', 'ajuda_sistema' => 'geral', 'analise_completa' => 'geral',
        'exportar_relatorio' => 'geral', 'notificar_whatsapp' => 'rh',
    ];
    return $map[$intent] ?? 'geral';
}

function sf_ia_agente_label(string $agente): string
{
    static $labels = [
        'financeiro' => 'Agente Financeiro',
        'frota' => 'Agente de Frota',
        'oficina' => 'Agente de Oficina',
        'logistica' => 'Agente de Logística',
        'rh' => 'Agente de RH',
        'comercial' => 'Agente Comercial',
        'geral' => 'Copiloto Geral',
    ];
    return $labels[$agente] ?? 'Copiloto';
}

/** Cria tabelas se não existirem (idempotente). */
function sf_ia_ensure_schema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $sqlFile = dirname(__DIR__) . '/sql/ia_copilot.sql';
    if (!is_file($sqlFile)) {
        return;
    }
    $sql = file_get_contents($sqlFile);
    if ($sql === false || trim($sql) === '') {
        return;
    }
    foreach (preg_split('/;\s*(?:\r?\n)/', $sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || stripos($stmt, 'CREATE TABLE') === false) {
            continue;
        }
        if (substr($stmt, -1) !== ';') {
            $stmt .= ';';
        }
        try {
            $conn->exec($stmt);
        } catch (PDOException $e) {
            // tabela já existe — ignorar
        }
    }
    $done = true;
    if (function_exists('sf_ia_metrics_ensure_schema')) {
        sf_ia_metrics_ensure_schema($conn);
    }
}

function sf_ia_table_exists(PDO $conn, string $table): bool
{
    $table = preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    if ($table === '') {
        return false;
    }
    try {
        $st = $conn->query("SHOW TABLES LIKE " . $conn->quote($table));
        return $st && (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function sf_ia_sse_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('Content-Type: text/event-stream; charset=utf-8');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');
}

function sf_ia_sse_send(string $event, array $data): void
{
    if (!defined('SF_IA_SSE') || !SF_IA_SSE) {
        return;
    }
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
}

function sf_ia_intent_permitido(string $intent): bool
{
    if ($intent === 'mapa_frota' && function_exists('can_view_fleet_map') && !can_view_fleet_map()) {
        return false;
    }
    if (!in_array($intent, sf_ia_intents_financeiros(), true)) {
        return true;
    }
    return can_view_financial_data();
}

function sf_ia_bloqueio_mapa(): array
{
    return [
        'success' => true,
        'intent' => 'ajuda',
        'answer' => 'Você não tem permissão para ver o mapa da frota. Peça ao administrador a permissão de visualização do GPS.',
        'rows' => [],
        'cards' => [],
        'fontes' => ['Permissões'],
        'confianca' => ['score' => 100, 'stars' => 5, 'label' => 'Alta', 'motivos' => ['Política de acesso da empresa']],
        'sugestoes' => ['Quantas rotas ativas tenho?', 'Quantas rotas no mês?'],
        'bloqueado_mapa' => true,
    ];
}

function sf_ia_bloqueio_financeiro(): array
{
    return [
        'success' => true,
        'intent' => 'ajuda',
        'answer' => 'Você não tem permissão para ver dados financeiros detalhados. Peça ao administrador a permissão «Ver dados financeiros» ou consulte indicadores operacionais (rotas, frota, manutenção).',
        'rows' => [],
        'cards' => [],
        'fontes' => ['Permissões'],
        'confianca' => ['score' => 100, 'stars' => 5, 'label' => 'Alta', 'motivos' => ['Política de acesso da empresa']],
        'sugestoes' => ['Quantas rotas ativas tenho?', 'Quantas manutenções abertas?', 'Alguma CNH vencendo?'],
        'bloqueado_financeiro' => true,
    ];
}

function sf_ia_cache_key(string $intent, string $ini, string $fim, int $empresaId): string
{
    return hash('sha256', $empresaId . '|' . $intent . '|' . $ini . '|' . $fim);
}

function sf_ia_cache_get(PDO $conn, int $empresaId, string $key): ?array
{
    if (!sf_ia_table_exists($conn, 'ia_cache_consulta')) {
        return null;
    }
    try {
        $st = $conn->prepare('SELECT payload FROM ia_cache_consulta WHERE empresa_id = :e AND cache_key = :k AND expires_at > NOW() LIMIT 1');
        $st->execute([':e' => $empresaId, ':k' => $key]);
        $raw = $st->fetchColumn();
        if (!$raw) {
            return null;
        }
        $j = json_decode((string) $raw, true);
        return is_array($j) ? $j : null;
    } catch (Throwable $e) {
        return null;
    }
}

function sf_ia_cache_set(PDO $conn, int $empresaId, string $key, array $payload, int $ttlMinutes = 15): void
{
    if (!sf_ia_table_exists($conn, 'ia_cache_consulta')) {
        return;
    }
    try {
        $exp = date('Y-m-d H:i:s', time() + max(60, $ttlMinutes * 60));
        $st = $conn->prepare('INSERT INTO ia_cache_consulta (empresa_id, cache_key, payload, expires_at)
            VALUES (:e, :k, :p, :x)
            ON DUPLICATE KEY UPDATE payload = VALUES(payload), expires_at = VALUES(expires_at)');
        $st->execute([
            ':e' => $empresaId,
            ':k' => $key,
            ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':x' => $exp,
        ]);
    } catch (Throwable $e) {
        error_log('sf_ia_cache_set: ' . $e->getMessage());
    }
}

function sf_ia_memoria_get(PDO $conn, int $empresaId): array
{
    if (!sf_ia_table_exists($conn, 'ia_empresa_memoria')) {
        return [];
    }
    try {
        $st = $conn->prepare('SELECT preferencias FROM ia_empresa_memoria WHERE empresa_id = :e');
        $st->execute([':e' => $empresaId]);
        $raw = $st->fetchColumn();
        if (!$raw) {
            return [];
        }
        $j = json_decode((string) $raw, true);
        return is_array($j) ? $j : [];
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_memoria_save(PDO $conn, int $empresaId, array $prefs): bool
{
    if (!sf_ia_table_exists($conn, 'ia_empresa_memoria')) {
        return false;
    }
    try {
        $st = $conn->prepare('INSERT INTO ia_empresa_memoria (empresa_id, preferencias) VALUES (:e, :p)
            ON DUPLICATE KEY UPDATE preferencias = VALUES(preferencias)');
        $st->execute([':e' => $empresaId, ':p' => json_encode($prefs, JSON_UNESCAPED_UNICODE)]);
        return true;
    } catch (Throwable $e) {
        error_log('sf_ia_memoria_save: ' . $e->getMessage());
        return false;
    }
}

function sf_ia_auditoria_log(PDO $conn, int $empresaId, ?int $usuarioId, string $intent, string $agente, ?string $pergunta, ?string $via, ?int $confianca, ?array $fontes, ?int $tempoMs, ?array $meta = null): void
{
    if (!sf_ia_table_exists($conn, 'ia_auditoria')) {
        return;
    }
    $fontesJson = $fontes;
    if ($meta !== null && $meta !== []) {
        $fontesJson = array_merge(['_lista' => $fontes ?? []], $meta);
    }
    try {
        $st = $conn->prepare('INSERT INTO ia_auditoria (empresa_id, usuario_id, intent, agente, pergunta, via, confianca_pct, fontes, tempo_ms, tokens_estimados)
            VALUES (:e, :u, :i, :a, :p, :v, :c, :f, :t, :tok)');
        $st->execute([
            ':e' => $empresaId,
            ':u' => $usuarioId,
            ':i' => substr($intent, 0, 64),
            ':a' => substr($agente, 0, 32),
            ':p' => $pergunta !== null ? mb_substr($pergunta, 0, 2000) : null,
            ':v' => $via,
            ':c' => $confianca,
            ':f' => $fontesJson ? json_encode($fontesJson, JSON_UNESCAPED_UNICODE) : null,
            ':t' => $tempoMs,
            ':tok' => (int) ($meta['tokens_estimados'] ?? 0),
        ]);
    } catch (Throwable $e) {
        error_log('sf_ia_auditoria_log: ' . $e->getMessage());
    }
}

function sf_ia_auditoria_list(PDO $conn, int $empresaId, int $limit = 50): array
{
    if (!sf_ia_table_exists($conn, 'ia_auditoria')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    try {
        $st = $conn->prepare("SELECT id, intent, agente, LEFT(pergunta, 120) AS pergunta, via, confianca_pct, tempo_ms, tokens_estimados, fontes, created_at
            FROM ia_auditoria WHERE empresa_id = :e ORDER BY id DESC LIMIT {$limit}");
        $st->execute([':e' => $empresaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_conversa_save(PDO $conn, int $empresaId, ?int $usuarioId, string $role, string $conteudo, ?string $intent = null, ?array $payload = null): void
{
    if (!sf_ia_table_exists($conn, 'ia_conversa_mensagens') || $conteudo === '') {
        return;
    }
    try {
        $st = $conn->prepare('INSERT INTO ia_conversa_mensagens (empresa_id, usuario_id, role, conteudo, intent, payload)
            VALUES (:e, :u, :r, :c, :i, :p)');
        $st->execute([
            ':e' => $empresaId,
            ':u' => $usuarioId,
            ':r' => $role === 'assistant' ? 'assistant' : 'user',
            ':c' => mb_substr($conteudo, 0, 8000),
            ':i' => $intent,
            ':p' => $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Throwable $e) {
        error_log('sf_ia_conversa_save: ' . $e->getMessage());
    }
}

function sf_ia_historico_list(PDO $conn, int $empresaId, int $limit = 40): array
{
    if (!sf_ia_table_exists($conn, 'ia_conversa_mensagens')) {
        return [];
    }
    $limit = max(1, min(200, $limit));
    try {
        $st = $conn->prepare("SELECT role, conteudo, intent, created_at FROM ia_conversa_mensagens
            WHERE empresa_id = :e ORDER BY id DESC LIMIT {$limit}");
        $st->execute([':e' => $empresaId]);
        return array_reverse($st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_chunk_text(string $text, int $size = 900): array
{
    $text = preg_replace('/\s+/u', ' ', trim($text)) ?? '';
    if ($text === '') {
        return [];
    }
    $chunks = [];
    $len = mb_strlen($text);
    for ($i = 0; $i < $len; $i += $size) {
        $chunks[] = mb_substr($text, $i, $size);
    }
    return $chunks;
}

function sf_ia_documento_dir(int $empresaId): string
{
    $base = dirname(__DIR__) . '/uploads/ia_docs/' . $empresaId;
    if (!is_dir($base)) {
        @mkdir($base, 0755, true);
    }
    return $base;
}

function sf_ia_documento_upload(PDO $conn, int $empresaId, ?int $usuarioId, array $file, ?string $textoManual = null): array
{
    sf_ia_ensure_schema($conn);
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Arquivo inválido.'];
    }
    $max = 8 * 1024 * 1024;
    if (($file['size'] ?? 0) > $max) {
        return ['success' => false, 'error' => 'Arquivo muito grande (máx. 8 MB).'];
    }
    $nome = basename((string) ($file['name'] ?? 'doc.txt'));
    $nome = preg_replace('/[^a-zA-Z0-9._-]/', '_', $nome) ?: 'doc.txt';
    $mime = (string) ($file['type'] ?? 'application/octet-stream');
    $ext = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    $allowed = ['txt', 'pdf', 'png', 'jpg', 'jpeg', 'webp'];
    if (!in_array($ext, $allowed, true)) {
        return ['success' => false, 'error' => 'Formato não suportado. Use TXT, PDF ou imagem.'];
    }

    $dir = sf_ia_documento_dir($empresaId);
    $dest = $dir . '/' . date('Ymd_His') . '_' . $nome;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'error' => 'Falha ao salvar arquivo.'];
    }

    $texto = trim((string) $textoManual);
    if ($texto === '' && $ext === 'txt') {
        $texto = trim((string) @file_get_contents($dest));
    }
    if ($texto === '' && in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
        $texto = sf_ia_ocr_arquivo($conn, $empresaId, $dest, $mime);
    }
    if ($texto === '' && $ext === 'pdf') {
        $texto = 'Documento PDF armazenado. Conteúdo não extraído automaticamente — cole o texto na descrição ou envie imagem/TXT.';
    }

    try {
        $st = $conn->prepare('INSERT INTO ia_documentos (empresa_id, usuario_id, nome_arquivo, caminho, mime, texto_extraido, status)
            VALUES (:e, :u, :n, :c, :m, :t, :s)');
        $status = $texto !== '' ? 'pronto' : 'erro';
        $st->execute([
            ':e' => $empresaId,
            ':u' => $usuarioId,
            ':n' => $nome,
            ':c' => $dest,
            ':m' => $mime,
            ':t' => $texto !== '' ? $texto : null,
            ':s' => $status,
        ]);
        $docId = (int) $conn->lastInsertId();
        if ($texto !== '') {
            sf_ia_documento_indexar($conn, $docId, $empresaId, $texto);
        }
        return ['success' => true, 'id' => $docId, 'nome' => $nome, 'status' => $status, 'chars' => mb_strlen($texto)];
    } catch (Throwable $e) {
        error_log('sf_ia_documento_upload: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Erro ao registrar documento.'];
    }
}

function sf_ia_ocr_arquivo(PDO $conn, int $empresaId, string $path, string $mime): string
{
    if (!is_file(__DIR__ . '/sf_vision_ocr.php')) {
        return '';
    }
    require_once __DIR__ . '/sf_vision_ocr.php';
    $cfg = sf_vision_ocr_resolve($conn, $empresaId);
    if (!$cfg) {
        return '';
    }
    $binary = file_get_contents($path);
    if ($binary === false) {
        return '';
    }
    [$binary, $mime] = sf_vision_ocr_prepare_image($binary, $mime);
    $b64 = base64_encode($binary);
    $dataUrl = 'data:' . $mime . ';base64,' . $b64;
    try {
        $res = sf_vision_ocr_call($cfg, $dataUrl, 'Extraia TODO o texto visível do documento, em português, sem inventar.', 'Transcreva o texto.');
        return trim((string) ($res['content'] ?? ''));
    } catch (Throwable $e) {
        error_log('sf_ia_ocr_arquivo: ' . $e->getMessage());
        return '';
    }
}

function sf_ia_documento_indexar(PDO $conn, int $docId, int $empresaId, string $texto): void
{
    if (!sf_ia_table_exists($conn, 'ia_documento_chunks')) {
        return;
    }
    require_once __DIR__ . '/sf_ia_embeddings.php';
    $conn->prepare('DELETE FROM ia_documento_chunks WHERE documento_id = :d')->execute([':d' => $docId]);
    $chunks = sf_ia_chunk_text($texto);
    $st = $conn->prepare('INSERT INTO ia_documento_chunks (documento_id, empresa_id, chunk_index, chunk_text) VALUES (:d, :e, :i, :t)');
    foreach ($chunks as $i => $chunk) {
        $st->execute([':d' => $docId, ':e' => $empresaId, ':i' => $i, ':t' => $chunk]);
        $chunkId = (int) $conn->lastInsertId();
        if ($chunkId > 0) {
            sf_ia_embedding_index_chunk($conn, $chunkId, $empresaId, $chunk);
        }
    }
}

function sf_ia_documentos_list(PDO $conn, int $empresaId, int $limit = 30): array
{
    if (!sf_ia_table_exists($conn, 'ia_documentos')) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    try {
        $st = $conn->prepare("SELECT id, nome_arquivo, status, CHAR_LENGTH(texto_extraido) AS chars, created_at
            FROM ia_documentos WHERE empresa_id = :e ORDER BY id DESC LIMIT {$limit}");
        $st->execute([':e' => $empresaId]);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_rag_search(PDO $conn, int $empresaId, string $query, int $limit = 5): array
{
    if (!sf_ia_table_exists($conn, 'ia_documento_chunks') || trim($query) === '') {
        return [];
    }
    $limit = max(1, min(10, $limit));
    require_once __DIR__ . '/sf_ia_embeddings.php';
    $vectorHits = sf_ia_rag_search_vector($conn, $empresaId, $query, $limit);
    if ($vectorHits !== []) {
        return array_map(static function (array $hit): array {
            return [
                'chunk_text' => $hit['chunk_text'],
                'nome_arquivo' => $hit['nome_arquivo'],
                'documento_id' => $hit['documento_id'],
                'score' => $hit['score'] ?? null,
                'via' => $hit['via'] ?? 'vetorial',
            ];
        }, $vectorHits);
    }
    $terms = preg_split('/\s+/u', mb_strtolower(trim($query), 'UTF-8')) ?: [];
    $terms = array_values(array_filter($terms, static fn($t) => mb_strlen($t) >= 3));
    if ($terms === []) {
        return [];
    }
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $terms[0]) . '%';
    try {
        $st = $conn->prepare("SELECT c.chunk_text, d.nome_arquivo, d.id AS documento_id
            FROM ia_documento_chunks c
            JOIN ia_documentos d ON d.id = c.documento_id
            WHERE c.empresa_id = :e AND c.chunk_text LIKE :q AND d.status = 'pronto'
            ORDER BY c.id DESC LIMIT {$limit}");
        $st->execute([':e' => $empresaId, ':q' => $like]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['via'] = 'texto';
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_propor_acao(PDO $conn, int $empresaId, ?int $usuarioId, string $tipo, string $titulo, string $resumo, array $params): ?int
{
    if (!sf_ia_table_exists($conn, 'ia_acoes_pendentes')) {
        return null;
    }
    try {
        $st = $conn->prepare('INSERT INTO ia_acoes_pendentes (empresa_id, usuario_id, tipo, titulo, resumo, params)
            VALUES (:e, :u, :t, :ti, :r, :p)');
        $st->execute([
            ':e' => $empresaId,
            ':u' => $usuarioId,
            ':t' => substr($tipo, 0, 64),
            ':ti' => mb_substr($titulo, 0, 255),
            ':r' => mb_substr($resumo, 0, 2000),
            ':p' => json_encode($params, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $conn->lastInsertId();
    } catch (Throwable $e) {
        error_log('sf_ia_propor_acao: ' . $e->getMessage());
        return null;
    }
}

function sf_ia_acoes_list(PDO $conn, int $empresaId, ?string $status = 'pendente', int $limit = 30): array
{
    if (!sf_ia_table_exists($conn, 'ia_acoes_pendentes')) {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $sql = "SELECT id, tipo, titulo, resumo, params, status, created_at FROM ia_acoes_pendentes WHERE empresa_id = :e";
    $bind = [':e' => $empresaId];
    if ($status !== null && $status !== '') {
        $sql .= ' AND status = :s';
        $bind[':s'] = $status;
    }
    $sql .= " ORDER BY id DESC LIMIT {$limit}";
    try {
        $st = $conn->prepare($sql);
        $st->execute($bind);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['params'] = json_decode((string) ($r['params'] ?? '{}'), true) ?: [];
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

function sf_ia_acao_get(PDO $conn, int $empresaId, int $acaoId): ?array
{
    try {
        $st = $conn->prepare('SELECT * FROM ia_acoes_pendentes WHERE id = :id AND empresa_id = :e LIMIT 1');
        $st->execute([':id' => $acaoId, ':e' => $empresaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['params'] = json_decode((string) ($row['params'] ?? '{}'), true) ?: [];
        return $row;
    } catch (Throwable $e) {
        return null;
    }
}

function sf_ia_lookup_status_agendada(PDO $conn): int
{
    try {
        $st = $conn->query("SELECT id FROM status_manutencao WHERE LOWER(nome) LIKE '%agend%' OR LOWER(nome) LIKE '%abert%' ORDER BY id LIMIT 1");
        $id = $st ? $st->fetchColumn() : false;
        return $id ? (int) $id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

function sf_ia_lookup_tipo_preventiva(PDO $conn): int
{
    try {
        $st = $conn->query("SELECT id FROM tipos_manutencao WHERE LOWER(nome) LIKE '%prevent%' OR LOWER(nome) LIKE '%revis%' ORDER BY id LIMIT 1");
        $id = $st ? $st->fetchColumn() : false;
        if ($id) {
            return (int) $id;
        }
        $st = $conn->query('SELECT id FROM tipos_manutencao ORDER BY id LIMIT 1');
        $id = $st ? $st->fetchColumn() : false;
        return $id ? (int) $id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

function sf_ia_lookup_componente_padrao(PDO $conn): int
{
    try {
        $st = $conn->query('SELECT id FROM componentes_manutencao ORDER BY id LIMIT 1');
        $id = $st ? $st->fetchColumn() : false;
        return $id ? (int) $id : 1;
    } catch (Throwable $e) {
        return 1;
    }
}

function sf_ia_executar_acao(PDO $conn, int $empresaId, int $usuarioId, array $acao): array
{
    $tipo = (string) ($acao['tipo'] ?? '');
    $params = is_array($acao['params'] ?? null) ? $acao['params'] : [];

    if ($tipo === 'agendar_manutencao') {
        $veiculoId = (int) ($params['veiculo_id'] ?? 0);
        if ($veiculoId < 1) {
            return ['ok' => false, 'msg' => 'Veículo não informado.'];
        }
        $sv = $conn->prepare('SELECT id, placa, km_atual FROM veiculos WHERE id = :id AND empresa_id = :e LIMIT 1');
        $sv->execute([':id' => $veiculoId, ':e' => $empresaId]);
        $veic = $sv->fetch(PDO::FETCH_ASSOC);
        if (!$veic) {
            return ['ok' => false, 'msg' => 'Veículo não encontrado nesta empresa.'];
        }
        $data = (string) ($params['data_manutencao'] ?? date('Y-m-d', strtotime('+3 days')));
        $desc = trim((string) ($params['descricao'] ?? 'Revisão sugerida pelo Centro de Inteligência'));
        $tipoId = sf_ia_lookup_tipo_preventiva($conn);
        $statusId = sf_ia_lookup_status_agendada($conn);
        $compId = sf_ia_lookup_componente_padrao($conn);
        $km = (int) ($veic['km_atual'] ?? 0);
        $sql = 'INSERT INTO manutencoes (empresa_id, data_manutencao, veiculo_id, tipo_manutencao_id, componente_id, status_manutencao_id, km_atual, descricao, descricao_servico, valor, custo_total, fornecedor, observacoes, responsavel_aprovacao)
            VALUES (:e, :d, :v, :tm, :c, :st, :km, :desc, :ds, 0, 0, :forn, :obs, :resp)';
        $st = $conn->prepare($sql);
        $forn = 'Sugerido pelo Copiloto';
        $obs = 'Criado via Centro de Inteligência (ação confirmada pelo gestor).';
        $resp = (string) ($_SESSION['nome'] ?? 'Gestor');
        $st->execute([
            ':e' => $empresaId, ':d' => $data, ':v' => $veiculoId, ':tm' => $tipoId,
            ':c' => $compId, ':st' => $statusId, ':km' => $km, ':desc' => $desc, ':ds' => $desc,
            ':forn' => $forn, ':obs' => $obs, ':resp' => $resp,
        ]);
        $mid = (int) $conn->lastInsertId();
        return ['ok' => true, 'msg' => "Manutenção #{$mid} agendada para {$veic['placa']} em " . date('d/m/Y', strtotime($data)) . '.'];
    }

    if ($tipo === 'exportar_relatorio') {
        $url = (string) ($params['url'] ?? '');
        if ($url === '') {
            return ['ok' => false, 'msg' => 'URL do relatório não informada.'];
        }
        return ['ok' => true, 'msg' => 'Exportação liberada. Abra Relatórios para concluir o download.', 'redirect' => $url];
    }

    if ($tipo === 'abrir_whatsapp') {
        $url = (string) ($params['url'] ?? '');
        if ($url === '' || !preg_match('#^https://wa\.me/#', $url)) {
            return ['ok' => false, 'msg' => 'Link WhatsApp inválido.'];
        }
        return ['ok' => true, 'msg' => 'WhatsApp pronto. O gestor deve enviar a mensagem manualmente.', 'redirect' => $url];
    }

    if ($tipo === 'enviar_email') {
        $email = trim((string) ($params['email'] ?? ''));
        $assunto = trim((string) ($params['assunto'] ?? 'Mensagem da operação'));
        $corpo = trim((string) ($params['corpo'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => 'E-mail inválido.'];
        }
        require_once __DIR__ . '/security.php';
        $smtpCfg = sf_security_config($empresaId);
        if (!empty($smtpCfg['smtp_host']) && (int) ($smtpCfg['smtp_port'] ?? 0) > 0) {
            $html = nl2br(htmlspecialchars($corpo, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            if (sf_smtp_send($smtpCfg, $email, $assunto, $html)) {
                return ['ok' => true, 'msg' => 'E-mail enviado via SMTP para ' . $email . '.'];
            }
            return ['ok' => false, 'msg' => 'Falha ao enviar via SMTP. Verifique Configurações → Segurança.'];
        }
        $mailto = 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($assunto) . '&body=' . rawurlencode($corpo);
        return ['ok' => true, 'msg' => 'SMTP não configurado — abrindo cliente de e-mail para revisão.', 'redirect' => $mailto];
    }

    if ($tipo === 'criar_ot') {
        $origem = trim((string) ($params['origem'] ?? ''));
        $destino = trim((string) ($params['destino'] ?? ''));
        $cliente = trim((string) ($params['cliente_nome'] ?? 'Cliente via Copiloto'));
        if ($origem === '' || $destino === '') {
            return ['ok' => false, 'msg' => 'Origem e destino são obrigatórios para criar a OT.'];
        }
        try {
            $mx = $conn->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 4) AS UNSIGNED)), 0) + 1 FROM ordens_transporte WHERE empresa_id = :e');
            $mx->execute([':e' => $empresaId]);
            $numero = 'OT-' . str_pad((string) $mx->fetchColumn(), 6, '0', STR_PAD_LEFT);
            $token = bin2hex(random_bytes(16));
            $valorFrete = (float) ($params['valor_frete'] ?? 0);
            $distKm = (float) ($params['distancia_km'] ?? 0);
            $st = $conn->prepare('INSERT INTO ordens_transporte (empresa_id, numero, status, token_rastreio, criado_por, cliente_nome, origem, destino, valor_frete, distancia_km, observacoes)
                VALUES (:e, :num, "cotacao", :tok, :cp, :cli, :ori, :des, :vf, :km, :obs)');
            $st->execute([
                ':e' => $empresaId,
                ':num' => $numero,
                ':tok' => $token,
                ':cp' => $usuarioId > 0 ? $usuarioId : null,
                ':cli' => mb_substr($cliente, 0, 255),
                ':ori' => mb_substr($origem, 0, 255),
                ':des' => mb_substr($destino, 0, 255),
                ':vf' => $valorFrete,
                ':km' => $distKm,
                ':obs' => 'Criada via Centro de Inteligência (ação confirmada pelo gestor).',
            ]);
            $otId = (int) $conn->lastInsertId();
            $baseUrl = function_exists('sf_app_url') ? rtrim(sf_app_url(''), '/') : '/sistema-frotas';
            return [
                'ok' => true,
                'msg' => "{$numero} criada com sucesso (cotação).",
                'redirect' => $baseUrl . '/pages/ordens_transporte.php',
                'ot_id' => $otId,
                'numero' => $numero,
            ];
        } catch (Throwable $e) {
            error_log('sf_ia_executar_acao criar_ot: ' . $e->getMessage());
            return ['ok' => false, 'msg' => 'Não foi possível criar a ordem de transporte.'];
        }
    }

    if ($tipo === 'notificar_motorista') {
        require_once __DIR__ . '/notificacoes_helpers.php';
        $motoristaId = (int) ($params['motorista_id'] ?? 0);
        $mensagem = trim((string) ($params['mensagem'] ?? 'Mensagem da transportadora via Centro de Inteligência.'));
        if ($motoristaId < 1) {
            return ['ok' => false, 'msg' => 'Motorista não informado.'];
        }
        $sm = $conn->prepare('SELECT id, nome FROM motoristas WHERE id = :id AND empresa_id = :e LIMIT 1');
        $sm->execute([':id' => $motoristaId, ':e' => $empresaId]);
        $mot = $sm->fetch(PDO::FETCH_ASSOC);
        if (!$mot) {
            return ['ok' => false, 'msg' => 'Motorista não encontrado nesta empresa.'];
        }
        $titulo = 'Aviso — ' . ($mot['nome'] ?? 'motorista');
        $ok = sf_notif_inserir($conn, $empresaId, 'motorista', $titulo, $mensagem, [
            'referencia_id' => $motoristaId,
            'severidade' => 'info',
            'meta_json' => ['motorista_id' => $motoristaId, 'origem' => 'ia_copilot'],
        ]);
        if (!$ok) {
            return ['ok' => true, 'msg' => 'Aviso já registrado recentemente para ' . ($mot['nome'] ?? 'motorista') . '.'];
        }
        require_once __DIR__ . '/sf_ia_actions.php';
        sf_ia_motorista_aviso_inserir($conn, $empresaId, $motoristaId, $titulo, $mensagem);
        return ['ok' => true, 'msg' => 'Notificação enviada para ' . ($mot['nome'] ?? 'motorista') . ' (app + Central de Alertas).'];
    }

    return ['ok' => false, 'msg' => 'Tipo de ação não suportado.'];
}

function sf_ia_confirmar_acao(PDO $conn, int $empresaId, int $usuarioId, int $acaoId): array
{
    $acao = sf_ia_acao_get($conn, $empresaId, $acaoId);
    if (!$acao) {
        return ['success' => false, 'error' => 'Ação não encontrada.'];
    }
    if (($acao['status'] ?? '') !== 'pendente') {
        return ['success' => false, 'error' => 'Esta ação já foi processada.'];
    }
    $res = sf_ia_executar_acao($conn, $empresaId, $usuarioId, $acao);
    $novoStatus = $res['ok'] ? 'executada' : 'erro';
    try {
        $st = $conn->prepare('UPDATE ia_acoes_pendentes SET status = :s, resultado = :r, executed_at = NOW() WHERE id = :id AND empresa_id = :e');
        $st->execute([':s' => $novoStatus, ':r' => $res['msg'], ':id' => $acaoId, ':e' => $empresaId]);
    } catch (Throwable $e) {
        error_log('sf_ia_confirmar_acao: ' . $e->getMessage());
    }
    if (!$res['ok']) {
        return ['success' => false, 'error' => $res['msg']];
    }
    return ['success' => true, 'message' => $res['msg'], 'redirect' => $res['redirect'] ?? null];
}

function sf_ia_cancelar_acao(PDO $conn, int $empresaId, int $acaoId): bool
{
    try {
        $st = $conn->prepare("UPDATE ia_acoes_pendentes SET status = 'cancelada' WHERE id = :id AND empresa_id = :e AND status = 'pendente'");
        $st->execute([':id' => $acaoId, ':e' => $empresaId]);
        return $st->rowCount() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/** Previsões: manutenção preditiva + estatísticas de gasto. */
function sf_ia_previsoes(PDO $conn, int $empresaId, callable $moeda): array
{
    $out = ['itens' => [], 'resumo' => ''];

    // Manutenção preditiva (regra km)
    $urgentes = [];
    try {
        $sv = $conn->prepare("SELECT id, placa, km_atual, km_ultima_manutencao FROM veiculos
            WHERE empresa_id = :e AND (status_id = 1 OR status_id IS NULL) ORDER BY placa LIMIT 80");
        $sv->execute([':e' => $empresaId]);
        foreach ($sv->fetchAll(PDO::FETCH_ASSOC) as $v) {
            $kmAtual = $v['km_atual'] !== null ? (float) $v['km_atual'] : null;
            $kmUlt = $v['km_ultima_manutencao'] !== null ? (float) $v['km_ultima_manutencao'] : null;
            if ($kmAtual === null) {
                continue;
            }
            $desde = $kmUlt !== null ? $kmAtual - $kmUlt : $kmAtual;
            $intervalo = 10000;
            if ($desde >= $intervalo * 0.9) {
                $urgentes[] = [
                    'placa' => $v['placa'],
                    'veiculo_id' => (int) $v['id'],
                    'km_desde' => (int) round($desde),
                    'prioridade' => $desde >= $intervalo ? 'alta' : 'media',
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('sf_ia_previsoes manut: ' . $e->getMessage());
    }
    if ($urgentes !== []) {
        $out['itens'][] = [
            'tipo' => 'manutencao',
            'titulo' => count($urgentes) . ' veículo(s) próximo(s) da revisão',
            'detalhe' => 'Com base em km desde a última manutenção (intervalo padrão 10.000 km).',
            'veiculos' => array_slice($urgentes, 0, 8),
        ];
    }

    // Combustível 30d (média histórica)
    try {
        $st = $conn->prepare("SELECT COALESCE(AVG(daily_total),0) media FROM (
            SELECT DATE(data_abastecimento) d, SUM(valor_total) daily_total FROM abastecimentos
            WHERE empresa_id = :e AND data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY d) t");
        $st->execute([':e' => $empresaId]);
        $media = (float) $st->fetchColumn();
        if ($media > 0) {
            $prev = $media * 30;
            $out['itens'][] = [
                'tipo' => 'combustivel',
                'titulo' => 'Previsão combustível (30 dias)',
                'detalhe' => $moeda($prev) . ' com base na média diária dos últimos 30 dias.',
            ];
        }
    } catch (Throwable $e) {
        // ignora
    }

    // Manutenção custo 30d
    try {
        $st = $conn->prepare("SELECT COALESCE(AVG(daily_total),0) media FROM (
            SELECT DATE(data_manutencao) d, SUM(COALESCE(custo_total,valor,0)) daily_total FROM manutencoes
            WHERE empresa_id = :e AND data_manutencao >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) GROUP BY d) t");
        $st->execute([':e' => $empresaId]);
        $media = (float) $st->fetchColumn();
        if ($media > 0) {
            $out['itens'][] = [
                'tipo' => 'custo_manutencao',
                'titulo' => 'Previsão gasto manutenção (30 dias)',
                'detalhe' => $moeda($media * 30) . ' (média diária dos últimos 90 dias).',
            ];
        }
    } catch (Throwable $e) {
        // ignora
    }

    $out['resumo'] = $out['itens'] === []
        ? 'Sem previsões com dados suficientes no momento.'
        : count($out['itens']) . ' previsão(ões) calculada(s) com dados reais da operação.';
    return $out;
}

function sf_ia_sugerir_acoes_manutencao(PDO $conn, int $empresaId, array $previsoes): array
{
    $acoes = [];
    foreach ($previsoes['itens'] ?? [] as $item) {
        if (($item['tipo'] ?? '') !== 'manutencao' || empty($item['veiculos'])) {
            continue;
        }
        foreach ($item['veiculos'] as $v) {
            if (($v['prioridade'] ?? '') !== 'alta') {
                continue;
            }
            $id = sf_ia_propor_acao(
                $conn,
                $empresaId,
                isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null,
                'agendar_manutencao',
                'Agendar revisão — ' . ($v['placa'] ?? ''),
                'Veículo com ' . ($v['km_desde'] ?? 0) . ' km desde a última manutenção.',
                [
                    'veiculo_id' => (int) ($v['veiculo_id'] ?? 0),
                    'data_manutencao' => date('Y-m-d', strtotime('+5 days')),
                    'descricao' => 'Revisão preventiva sugerida pelo Copiloto (km elevado desde última manutenção).',
                ]
            );
            if ($id) {
                $acoes[] = $id;
            }
            if (count($acoes) >= 3) {
                break 2;
            }
        }
    }
    return $acoes;
}

function sf_ia_relatorio_pdf_html(int $empresaId, string $empresaNome, array $exec, array $previsoes, array $auditoria): string
{
    $cards = $exec['cards'] ?? [];
    $cardsHtml = '';
    foreach ($cards as $c) {
        $cardsHtml .= '<tr><td>' . htmlspecialchars((string) ($c['title'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td><strong>' . htmlspecialchars((string) ($c['value'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></td></tr>';
    }
    $prevHtml = '';
    foreach ($previsoes['itens'] ?? [] as $p) {
        $prevHtml .= '<li><strong>' . htmlspecialchars((string) ($p['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong> — ' . htmlspecialchars((string) ($p['detalhe'] ?? ''), ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $audHtml = '';
    foreach (array_slice($auditoria, 0, 15) as $a) {
        $audHtml .= '<tr><td>' . htmlspecialchars((string) ($a['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($a['intent'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars((string) ($a['pergunta'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    $resumo = htmlspecialchars((string) ($exec['resumo'] ?? ''), ENT_QUOTES, 'UTF-8');
    $empresaNome = htmlspecialchars($empresaNome, ENT_QUOTES, 'UTF-8');
    $data = date('d/m/Y H:i');

    return <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:11pt;color:#222}
h1{font-size:16pt;color:#1e40af}h2{font-size:12pt;margin-top:18px;color:#334155}
table{border-collapse:collapse;width:100%;margin-top:8px}td,th{border:1px solid #ccc;padding:6px;text-align:left}
th{background:#f1f5f9;font-size:9pt;text-transform:uppercase}
.meta{color:#64748b;font-size:9pt}
</style></head><body>
<h1>Relatório Executivo — Centro de Inteligência</h1>
<p class="meta">{$empresaNome} · Gerado em {$data}</p>
<h2>Resumo</h2><p>{$resumo}</p>
<h2>Indicadores</h2>
<table><thead><tr><th>Indicador</th><th>Valor</th></tr></thead><tbody>{$cardsHtml}</tbody></table>
<h2>Previsões</h2><ul>{$prevHtml}</ul>
<h2>Últimas consultas (auditoria)</h2>
<table><thead><tr><th>Data</th><th>Intenção</th><th>Pergunta</th></tr></thead><tbody>{$audHtml}</tbody></table>
<p class="meta">Documento gerado automaticamente com dados reais. Não substitui relatórios contábeis oficiais.</p>
</body></html>
HTML;
}

function sf_ia_gerar_pdf(string $html, string $filename): void
{
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('Execute composer install para gerar PDF.');
    }
    require_once $autoload;
    $tempDir = dirname(__DIR__) . '/tmp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => $tempDir,
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 16,
        'margin_bottom' => 16,
        'default_font' => 'dejavusans',
    ]);
    $mpdf->SetTitle($filename);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename . '.pdf', 'D');
}

function sf_ia_stream_answer_chunks(string $answer): void
{
    if (!defined('SF_IA_SSE') || !SF_IA_SSE || $answer === '') {
        return;
    }
    $parts = preg_split('/(?<=[.!?])\s+/u', $answer) ?: [$answer];
    foreach ($parts as $part) {
        if (trim($part) === '') {
            continue;
        }
        sf_ia_sse_send('token', ['t' => $part . ' ']);
        usleep(40000);
    }
}
