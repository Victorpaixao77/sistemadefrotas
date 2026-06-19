-- Controle de Pedágios (log granular por praça) — App Android Motoristas.
-- Cada registro é um pedágio pago, com valor, praça e foto opcional do comprovante.
-- O web já tem o agregado em despesas_viagem.pedagios; esta tabela é o detalhe.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/pedagios_viagem.sql

CREATE TABLE IF NOT EXISTS `pedagios_viagem` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `empresa_id`   INT NOT NULL,
    `motorista_id` INT NOT NULL,
    `rota_id`      INT NULL,
    `veiculo_id`   INT NULL,
    `praca`        VARCHAR(150) NULL,
    `valor`        DECIMAL(10,2) NOT NULL DEFAULT 0,
    `latitude`     DECIMAL(10,7) NULL,
    `longitude`    DECIMAL(10,7) NULL,
    `data_hora`    DATETIME NOT NULL,
    `foto_path`    VARCHAR(255) NULL,
    `fonte`        VARCHAR(20) NOT NULL DEFAULT 'motorista',
    `criado_em`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_pedagios_empresa_motorista` (`empresa_id`, `motorista_id`),
    KEY `idx_pedagios_rota` (`rota_id`),
    KEY `idx_pedagios_data` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
