-- Melhorias de auditoria MDF-e (validacao centralizada)
-- Executar uma vez em producao.

CREATE TABLE IF NOT EXISTS mdfe_validacao_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NULL,
    empresa_id INT NOT NULL,
    versao_regra VARCHAR(30) NULL,
    contexto VARCHAR(30) NOT NULL,
    payload_hash CHAR(64) NULL,
    payload_json LONGTEXT NULL,
    erros_json LONGTEXT NULL,
    warnings_json LONGTEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE mdfe_validacao_log
    ADD COLUMN IF NOT EXISTS payload_hash CHAR(64) NULL,
    ADD INDEX IF NOT EXISTS idx_mdfe_log_mdfe_id (mdfe_id),
    ADD INDEX IF NOT EXISTS idx_mdfe_log_empresa (empresa_id),
    ADD INDEX IF NOT EXISTS idx_mdfe_log_data (criado_em),
    ADD INDEX IF NOT EXISTS idx_mdfe_log_payload_hash (payload_hash);

-- Retencao sugerida: 180 dias.
-- DELETE FROM mdfe_validacao_log WHERE criado_em < NOW() - INTERVAL 180 DAY;
