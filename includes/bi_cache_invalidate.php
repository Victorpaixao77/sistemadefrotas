<?php
/**
 * Remove entradas de bi_cache para a empresa (usado após alterar rotas, abastecimentos, manutenções, etc.).
 * Falhas são ignoradas para não quebrar o fluxo principal da API.
 */
if (!function_exists('bi_cache_invalidate_empresa')) {
    function bi_cache_invalidate_empresa(?PDO $conn, int $empresa_id): void
    {
        if (!$conn || $empresa_id <= 0) {
            return;
        }
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS bi_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                empresa_id INT NOT NULL,
                cache_key VARCHAR(120) NOT NULL,
                payload LONGTEXT NOT NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_bi_cache_key (empresa_id, cache_key),
                INDEX idx_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $st = $conn->prepare('DELETE FROM bi_cache WHERE empresa_id = ?');
            $st->execute([$empresa_id]);
        } catch (Throwable $e) {
            // silencioso
        }
    }
}
