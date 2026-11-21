-- =============================================================================
-- notificacoes_arquivo — histórico arquivado de notificações antigas.
-- Mesma estrutura de `notificacoes`. O cron `cron/arquivar_notificacoes.php`
-- move para cá as notificações além do período de retenção e as remove da
-- tabela viva (que fica enxuta e rápida).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/notificacoes_arquivo.sql
-- =============================================================================

CREATE TABLE IF NOT EXISTS notificacoes_arquivo LIKE notificacoes;
