-- Trilha de aprovações (workflow) — registra decisões (quem aprovou/rejeitou, quando, comentário).
-- Genérica por entity_type; começa aplicada às Ordens de Transporte.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/aprovacoes.sql
CREATE TABLE IF NOT EXISTS aprovacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT NOT NULL,
    entity_ref VARCHAR(40) NULL,
    descricao VARCHAR(255) NULL,
    decisao ENUM('aprovado','rejeitado') NOT NULL,
    aprovador_id INT NULL,
    aprovador_nome VARCHAR(120) NULL,
    comentario TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_emp (empresa_id, created_at),
    KEY idx_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
