-- MDF-e Fase 3: percurso, condutores, reboques, vínculo documentos
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/mdfe_fase3_completo.sql

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS ufs_percurso JSON NULL COMMENT 'Lista de UFs do percurso' AFTER cod_mun_desc_ibge;

ALTER TABLE fiscal_mdfe_documentos
    ADD COLUMN IF NOT EXISTS cte_id INT NULL AFTER chave_cte;

ALTER TABLE fiscal_mdfe_documentos
    ADD COLUMN IF NOT EXISTS nfe_id INT NULL AFTER chave_nfe;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_condutores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mdfe_id INT NOT NULL,
    empresa_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    cpf CHAR(11) NOT NULL,
    ordem SMALLINT NOT NULL DEFAULT 1,
    payload_json LONGTEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe_cond_mdfe (mdfe_id),
    INDEX idx_mdfe_cond_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_reboques (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mdfe_id INT NOT NULL,
    empresa_id INT NOT NULL,
    placa VARCHAR(10) NOT NULL,
    renavam VARCHAR(20) NULL,
    tara INT NULL,
    cap_kg INT NULL,
    tp_rod VARCHAR(2) NULL,
    tp_car VARCHAR(2) NULL,
    uf CHAR(2) NULL,
    cod_interno VARCHAR(30) NULL,
    payload_json LONGTEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe_reb_mdfe (mdfe_id),
    INDEX idx_mdfe_reb_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
