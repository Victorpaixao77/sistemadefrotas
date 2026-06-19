-- Controle de jornada do motorista (Lei do Motorista) — registrado pelo app Android.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/jornada_motorista.sql

CREATE TABLE IF NOT EXISTS `jornada_motorista` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `motorista_id` INT(11) NOT NULL,
    `rota_id` INT(11) DEFAULT NULL,
    `tipo` VARCHAR(20) NOT NULL,            -- inicio, parada, descanso, refeicao, fim
    `data_hora` DATETIME NOT NULL,
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `observacoes` VARCHAR(255) DEFAULT NULL,
    `fonte` VARCHAR(20) NOT NULL DEFAULT 'motorista',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jm_empresa` (`empresa_id`),
    KEY `idx_jm_motorista` (`empresa_id`, `motorista_id`, `data_hora`),
    KEY `idx_jm_rota` (`rota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
