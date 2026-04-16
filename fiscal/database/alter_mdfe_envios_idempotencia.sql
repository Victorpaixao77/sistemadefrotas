-- Tabela para idempotencia e historico de comunicacao com SEFAZ (MDF-e).
CREATE TABLE IF NOT EXISTS mdfe_envios (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    mdfe_id BIGINT NULL,
    xml_hash CHAR(64) NOT NULL,
    status_envio VARCHAR(30) NOT NULL,
    protocolo VARCHAR(60) NULL,
    metodo_envio VARCHAR(40) NULL,
    resposta_sefaz LONGTEXT NULL,
    erro TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mdfe_envio_empresa_hash (empresa_id, xml_hash),
    INDEX idx_mdfe_envio_mdfe_id (mdfe_id),
    INDEX idx_mdfe_envio_status (status_envio),
    INDEX idx_mdfe_envio_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
