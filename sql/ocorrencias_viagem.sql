-- Ocorrências de viagem registradas pelo motorista (app Android).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/ocorrencias_viagem.sql

CREATE TABLE IF NOT EXISTS `ocorrencias_viagem` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `motorista_id` INT(11) NOT NULL,
    `rota_id` INT(11) DEFAULT NULL,
    `veiculo_id` INT(11) DEFAULT NULL,
    `tipo` VARCHAR(40) NOT NULL,
    `descricao` TEXT DEFAULT NULL,
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `data_hora` DATETIME NOT NULL,
    `foto_path` VARCHAR(255) DEFAULT NULL,
    `video_path` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'aberta',
    `fonte` VARCHAR(20) NOT NULL DEFAULT 'motorista',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_oc_empresa` (`empresa_id`),
    KEY `idx_oc_motorista` (`empresa_id`, `motorista_id`),
    KEY `idx_oc_rota` (`rota_id`),
    KEY `idx_oc_data` (`empresa_id`, `data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
