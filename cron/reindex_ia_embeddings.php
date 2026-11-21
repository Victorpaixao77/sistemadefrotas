<?php
/**
 * Reindexa embeddings vetoriais dos documentos IA (todas as empresas ou uma).
 * CLI: php cron/reindex_ia_embeddings.php [empresa_id] [limit]
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/sf_ia_actions.php';

$empresaArg = isset($argv[1]) ? (int) $argv[1] : 0;
$limit = isset($argv[2]) ? (int) $argv[2] : 500;

try {
    $conn = getConnection();
    sf_ia_ensure_schema($conn);
    $empresas = [];
    if ($empresaArg > 0) {
        $empresas = [$empresaArg];
    } else {
        $st = $conn->query('SELECT DISTINCT empresa_id FROM ia_documento_chunks ORDER BY empresa_id');
        $empresas = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
    $total = 0;
    foreach ($empresas as $eid) {
        $res = sf_ia_reindex_embeddings($conn, $eid, $limit);
        $n = (int) ($res['indexados'] ?? 0);
        $total += $n;
        echo date('c') . " empresa={$eid} indexados={$n}\n";
    }
    echo "Total reindexados: {$total}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'reindex_ia_embeddings: ' . $e->getMessage() . "\n");
    exit(1);
}
