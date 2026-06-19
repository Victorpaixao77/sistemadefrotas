-- Alertas de pânico acionados pelo motorista (app Android).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/panico_alertas.sql

CREATE TABLE IF NOT EXISTS `panico_alertas` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `motorista_id` INT(11) NOT NULL,
    `veiculo_id` INT(11) DEFAULT NULL,
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `data_hora` DATETIME NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'aberto',   -- aberto, em_atendimento, resolvido
    `atendido_por` INT(11) DEFAULT NULL,
    `atendido_em` DATETIME DEFAULT NULL,
    `observacoes` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_pa_empresa_status` (`empresa_id`, `status`),
    KEY `idx_pa_motorista` (`empresa_id`, `motorista_id`, `data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
