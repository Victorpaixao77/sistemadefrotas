-- Timeline do Modo Viagem (sync app Android → servidor)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/trip_timeline.sql

CREATE TABLE IF NOT EXISTS `trip_timeline_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `motorista_id` INT(11) NOT NULL,
    `rota_id` INT(11) NOT NULL,
    `event_uuid` VARCHAR(36) NOT NULL,
    `tipo` VARCHAR(40) NOT NULL,
    `titulo` VARCHAR(160) NOT NULL,
    `detalhe` TEXT NULL,
    `latitude` DECIMAL(10,7) NULL,
    `longitude` DECIMAL(10,7) NULL,
    `evento_em` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_trip_event_uuid` (`event_uuid`),
    KEY `idx_trip_rota` (`empresa_id`, `rota_id`),
    KEY `idx_trip_motorista` (`empresa_id`, `motorista_id`, `evento_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
