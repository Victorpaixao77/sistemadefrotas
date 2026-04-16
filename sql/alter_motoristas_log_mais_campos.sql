-- Incluir mais informações no histórico de alterações dos motoristas
-- Execute após create_motoristas_log.sql (apenas uma vez).
-- Se der erro de coluna já existente, ignore.

ALTER TABLE motoristas_log
    ADD COLUMN usuario_id INT NULL COMMENT 'ID do usuário que fez a alteração',
    ADD COLUMN nome_usuario VARCHAR(150) NULL COMMENT 'Nome do usuário no momento da alteração',
    ADD COLUMN ip_origem VARCHAR(45) NULL COMMENT 'IP de origem da requisição';
