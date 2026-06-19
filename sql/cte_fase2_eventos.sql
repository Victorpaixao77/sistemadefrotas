-- CT-e Fase 2: eventos (CC-e, comprovante entrega) e sequência
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/cte_fase2_eventos.sql

ALTER TABLE fiscal_cte
    ADD COLUMN IF NOT EXISTS cce_seq INT NOT NULL DEFAULT 0 COMMENT 'Último nSeq CC-e homologado' AFTER rntrc;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS ult_protocolo_ce VARCHAR(30) NULL COMMENT 'Protocolo comprovante entrega 110180' AFTER rntrc;

ALTER TABLE fiscal_cte_itens
    ADD COLUMN IF NOT EXISTS ult_protocolo_desacordo VARCHAR(30) NULL AFTER ult_protocolo_ce;

CREATE TABLE IF NOT EXISTS fiscal_cte_eventos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cte_id INT NOT NULL,
    tp_evento VARCHAR(10) NOT NULL COMMENT '110110,110111,110180,610110,...',
    n_seq_evento INT NOT NULL DEFAULT 1,
    protocolo_evento VARCHAR(30) NULL,
    status ENUM('pendente','aceito','rejeitado') NOT NULL DEFAULT 'pendente',
    payload_json LONGTEXT NULL,
    xml_retorno LONGTEXT NULL,
    motivo_rejeicao VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cte_ev_cte (cte_id),
    INDEX idx_cte_ev_empresa (empresa_id),
    INDEX idx_cte_ev_tipo (tp_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
