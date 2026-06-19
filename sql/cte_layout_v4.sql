-- CT-e layout 4.00 — colunas wizard, vínculo NF-e, rem/dest em itens
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/cte_layout_v4.sql

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS veiculo_id INT NULL COMMENT 'Veículo de tração' AFTER destino_cidade;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS motorista_id INT NULL COMMENT 'Motorista condutor' AFTER veiculo_id;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS origem VARCHAR(255) NULL COMMENT 'Origem textual (wizard)' AFTER motorista_id;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS destino VARCHAR(255) NULL COMMENT 'Destino textual (wizard)' AFTER origem;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS peso_carga DECIMAL(12,3) NULL COMMENT 'Peso da carga (kg)' AFTER valor_total;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS volumes_carga INT NULL COMMENT 'Volumes da carga' AFTER peso_carga;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS cfop VARCHAR(4) NULL DEFAULT '5353' AFTER natureza_operacao;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS tp_cte TINYINT NULL DEFAULT 0 COMMENT '0=Normal' AFTER cfop;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS modal VARCHAR(2) NULL DEFAULT '01' COMMENT '01=Rodoviário' AFTER tp_cte;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS cod_mun_env VARCHAR(7) NULL AFTER modal;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS cod_mun_ini VARCHAR(7) NULL AFTER cod_mun_env;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS cod_mun_fim VARCHAR(7) NULL AFTER cod_mun_ini;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS rntrc VARCHAR(20) NULL AFTER cod_mun_fim;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS nfe_ids JSON NULL COMMENT 'IDs NF-e vinculadas (legado)' AFTER rntrc;

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS mdfe_id INT NULL AFTER nfe_ids;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS cte_id INT NULL COMMENT 'CT-e que transporta esta NF-e' AFTER observacoes;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS cliente_remetente VARCHAR(255) NULL AFTER cliente_nome_fantasia;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS cnpj_remetente VARCHAR(18) NULL AFTER cliente_remetente;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS cliente_destinatario VARCHAR(255) NULL AFTER cnpj_remetente;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS cnpj_destinatario VARCHAR(18) NULL AFTER cliente_destinatario;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS peso_carga DECIMAL(12,3) NULL AFTER valor_total;

ALTER TABLE fiscal_nfe_clientes
    ADD COLUMN IF NOT EXISTS volumes INT NULL AFTER peso_carga;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS tp_toma TINYINT NULL DEFAULT 0 COMMENT '0=Remetente (toma3)' AFTER tomador_nome;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_cnpj VARCHAR(18) NULL AFTER tp_toma;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_nome VARCHAR(255) NULL AFTER rem_cnpj;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_ie VARCHAR(20) NULL AFTER rem_nome;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_logradouro VARCHAR(255) NULL AFTER rem_ie;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_mun VARCHAR(100) NULL AFTER rem_logradouro;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_uf CHAR(2) NULL AFTER rem_mun;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rem_cmun VARCHAR(7) NULL AFTER rem_uf;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_cnpj VARCHAR(18) NULL AFTER rem_cmun;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_nome VARCHAR(255) NULL AFTER dest_cnpj;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_ie VARCHAR(20) NULL AFTER dest_nome;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_logradouro VARCHAR(255) NULL AFTER dest_ie;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_mun VARCHAR(100) NULL AFTER dest_logradouro;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_uf CHAR(2) NULL AFTER dest_mun;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS dest_cmun VARCHAR(7) NULL AFTER dest_uf;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS rntrc VARCHAR(20) NULL AFTER dest_cmun;

-- Status rascunho (wizard antes do envio SEFAZ)
ALTER TABLE fiscal_cte
    MODIFY COLUMN status ENUM('rascunho','pendente','autorizado','cancelado','denegado','inutilizado') DEFAULT 'pendente';

CREATE INDEX IF NOT EXISTS idx_fiscal_cte_veiculo ON fiscal_cte (veiculo_id);
CREATE INDEX IF NOT EXISTS idx_fiscal_cte_motorista ON fiscal_cte (motorista_id);
CREATE INDEX IF NOT EXISTS idx_nfe_cte_id ON fiscal_nfe_clientes (cte_id);
