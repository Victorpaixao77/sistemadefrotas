-- Campos extras em gps_ultima_posicao (painel / menos cálculo em tempo real)
-- Execute após create_gps_tracking.sql

ALTER TABLE gps_ultima_posicao
    ADD COLUMN status ENUM('parado','movimento') NULL DEFAULT NULL COMMENT 'derivado da velocidade no servidor' AFTER velocidade,
    ADD COLUMN ignicao TINYINT(1) NULL DEFAULT NULL COMMENT '0/1 se enviado pelo app' AFTER status,
    ADD COLUMN ultima_atualizacao DATETIME NULL DEFAULT NULL COMMENT 'momento em que o servidor gravou' AFTER ignicao,
    ADD COLUMN endereco VARCHAR(255) NULL DEFAULT NULL COMMENT 'opcional; app ou job pode preencher' AFTER ultima_atualizacao;
