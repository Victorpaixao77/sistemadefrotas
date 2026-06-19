-- Metadados do wizard MDF-e + documentos por município de descarga
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/fiscal_mdfe_meta_v1.sql

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS tipo_emitente CHAR(1) NULL COMMENT '1 prestador 2 carga propria 3 globalizado' AFTER tipo_viagem;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS tipo_transportador CHAR(1) NULL COMMENT '1 ETC 2 TAC 3 CTC' AFTER tipo_emitente;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS rod_rntrc VARCHAR(20) NULL AFTER tipo_transportador;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS rod_placa VARCHAR(10) NULL AFTER rod_rntrc;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS rod_tipo_rodado VARCHAR(2) NULL AFTER rod_placa;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS rod_tipo_carroceria VARCHAR(2) NULL AFTER rod_tipo_rodado;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS cod_mun_carreg_ibge VARCHAR(7) NULL AFTER municipio_descarregamento;

ALTER TABLE fiscal_mdfe
    ADD COLUMN IF NOT EXISTS cod_mun_desc_ibge VARCHAR(7) NULL AFTER cod_mun_carreg_ibge;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_documentos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mdfe_id INT NOT NULL,
    empresa_id INT NOT NULL,
    municipio_descarga VARCHAR(120) NULL,
    cod_mun_descarga VARCHAR(7) NULL,
    chave_nfe CHAR(44) NULL,
    chave_cte CHAR(44) NULL,
    numero_nfe VARCHAR(30) NULL,
    payload_json LONGTEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe_doc_mdfe (mdfe_id),
    INDEX idx_mdfe_doc_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
