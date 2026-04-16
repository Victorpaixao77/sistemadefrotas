-- Log de validação CT-e (criar_cte / emitir_cte_sefaz).
-- A API também cria a tabela via ensureCteValidationLogSchema() em documentos_fiscais_v2.php.

CREATE TABLE IF NOT EXISTS cte_validacao_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    cte_id BIGINT NULL,
    empresa_id INT NOT NULL,
    versao_regra VARCHAR(30) NULL,
    contexto VARCHAR(30) NOT NULL,
    payload_hash CHAR(64) NULL,
    payload_json LONGTEXT NULL,
    erros_json LONGTEXT NULL,
    warnings_json LONGTEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cte_log_cte (cte_id),
    INDEX idx_cte_log_empresa (empresa_id),
    INDEX idx_cte_log_empresa_data (empresa_id, criado_em),
    INDEX idx_cte_log_payload_hash (payload_hash),
    INDEX idx_cte_log_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
