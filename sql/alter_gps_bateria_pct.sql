-- Percentual de bateria do celular (app motorista), 0–100, opcional.
-- Execute no banco após create_gps_tracking.sql.

ALTER TABLE gps_logs
    ADD COLUMN bateria_pct TINYINT UNSIGNED NULL DEFAULT NULL
    COMMENT '0-100 bateria celular motorista'
    AFTER velocidade;

ALTER TABLE gps_ultima_posicao
    ADD COLUMN bateria_pct TINYINT UNSIGNED NULL DEFAULT NULL
    COMMENT '0-100 bateria celular motorista'
    AFTER endereco;
