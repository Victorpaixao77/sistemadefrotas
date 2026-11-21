<?php
/**
 * RAG vetorial — embeddings via API OpenAI-compatível + busca por similaridade.
 */
declare(strict_types=1);

require_once __DIR__ . '/sf_ia_router.php';
require_once __DIR__ . '/sf_ia_platform.php';

function sf_ia_embeddings_ensure_schema(PDO $conn): void
{
    $file = dirname(__DIR__) . '/sql/ia_copilot_phase4.sql';
    if (!is_file($file)) {
        return;
    }
    try {
        $conn->exec('ALTER TABLE ia_documento_chunks ADD COLUMN embedding JSON NULL COMMENT "Vetor embedding" AFTER chunk_text');
    } catch (PDOException $e) {
        // coluna já existe
    }
}

/** Gera embedding para um texto (retorna array de floats ou null). */
function sf_ia_embedding_vector(PDO $conn, int $empresaId, string $text): ?array
{
    $text = trim(mb_substr($text, 0, 6000));
    if ($text === '') {
        return null;
    }
    $cfg = sf_ia_router_resolve($conn, $empresaId, 'narrate');
    if (!$cfg) {
        $cfg = sf_ia_router_resolve($conn, $empresaId, 'classify');
    }
    if (!$cfg) {
        return null;
    }
    $base = preg_replace('#/chat/completions$#', '', $cfg['url']);
    $url = rtrim($base, '/') . '/embeddings';
    $model = getenv('SF_IA_EMBEDDING_MODEL') ?: 'text-embedding-3-small';
    $payload = ['model' => $model, 'input' => $text];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $cfg['key']],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
    if (!$raw) {
        return null;
    }
    $j = json_decode($raw, true);
    $vec = $j['data'][0]['embedding'] ?? null;
    return is_array($vec) ? array_map('floatval', $vec) : null;
}

function sf_ia_embedding_cosine(array $a, array $b): float
{
    $n = min(count($a), count($b));
    if ($n < 1) {
        return 0.0;
    }
    $dot = 0.0;
    $na = 0.0;
    $nb = 0.0;
    for ($i = 0; $i < $n; $i++) {
        $dot += $a[$i] * $b[$i];
        $na += $a[$i] * $a[$i];
        $nb += $b[$i] * $b[$i];
    }
    if ($na <= 0 || $nb <= 0) {
        return 0.0;
    }
    return $dot / (sqrt($na) * sqrt($nb));
}

/** Indexa embedding de um chunk após inserção. */
function sf_ia_embedding_index_chunk(PDO $conn, int $chunkId, int $empresaId, string $chunkText): void
{
    sf_ia_embeddings_ensure_schema($conn);
    $vec = sf_ia_embedding_vector($conn, $empresaId, $chunkText);
    if ($vec === null) {
        return;
    }
    try {
        $st = $conn->prepare('UPDATE ia_documento_chunks SET embedding = :emb WHERE id = :id AND empresa_id = :e');
        $st->execute([':emb' => json_encode($vec), ':id' => $chunkId, ':e' => $empresaId]);
    } catch (Throwable $e) {
        error_log('sf_ia_embedding_index_chunk: ' . $e->getMessage());
    }
}

/** Busca vetorial nos chunks da empresa. */
function sf_ia_rag_search_vector(PDO $conn, int $empresaId, string $query, int $limit = 5): array
{
    sf_ia_embeddings_ensure_schema($conn);
    if (!sf_ia_table_exists($conn, 'ia_documento_chunks')) {
        return [];
    }
    $qVec = sf_ia_embedding_vector($conn, $empresaId, $query);
    if ($qVec === null) {
        return [];
    }
    try {
        $st = $conn->prepare("SELECT c.id, c.chunk_text, c.embedding, d.nome_arquivo, d.id AS documento_id
            FROM ia_documento_chunks c
            JOIN ia_documentos d ON d.id = c.documento_id
            WHERE c.empresa_id = :e AND d.status = 'pronto' AND c.embedding IS NOT NULL
            ORDER BY c.id DESC LIMIT 200");
        $st->execute([':e' => $empresaId]);
        $hits = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $emb = json_decode((string) ($row['embedding'] ?? ''), true);
            if (!is_array($emb)) {
                continue;
            }
            $score = sf_ia_embedding_cosine($qVec, $emb);
            if ($score < 0.72) {
                continue;
            }
            $hits[] = [
                'chunk_text' => $row['chunk_text'],
                'nome_arquivo' => $row['nome_arquivo'],
                'documento_id' => $row['documento_id'],
                'score' => round($score, 4),
                'via' => 'vetorial',
            ];
        }
        usort($hits, static fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($hits, 0, max(1, min(10, $limit)));
    } catch (Throwable $e) {
        error_log('sf_ia_rag_search_vector: ' . $e->getMessage());
        return [];
    }
}
