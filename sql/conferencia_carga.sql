-- Conferência de carga (coleta/entrega) — App Android Motoristas.
-- Motorista confere NF, volumes e lacre, com foto. OCR opcional preenche os campos.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/conferencia_carga.sql

CREATE TABLE IF NOT EXISTS `conferencia_carga` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id`   INT NOT NULL,
    `motorista_id` INT NOT NULL,
    `rota_id`      INT NULL,
    `veiculo_id`   INT NULL,
    `tipo`         VARCHAR(12) NOT NULL DEFAULT 'coleta',  -- coleta | entrega
    `nf_numero`    VARCHAR(40) NULL,
    `nf_valor`     DECIMAL(12,2) NULL,
    `volumes`      INT NULL,
    `lacre`        VARCHAR(60) NULL,
    `conforme`     TINYINT(1) NOT NULL DEFAULT 1,
    `observacoes`  TEXT NULL,
    `foto_path`    VARCHAR(255) NULL,
    `latitude`     DECIMAL(10,7) NULL,
    `longitude`    DECIMAL(10,7) NULL,
    `data_hora`    DATETIME NOT NULL,
    `fonte`        VARCHAR(20) NOT NULL DEFAULT 'motorista',
    `criado_em`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_conf_empresa_motorista` (`empresa_id`, `motorista_id`),
    KEY `idx_conf_rota` (`rota_id`),
    KEY `idx_conf_data` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
