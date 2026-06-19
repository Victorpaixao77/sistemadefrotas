-- Comprovantes de entrega (POD) registrados pelo motorista no app Android.
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/comprovantes_entrega.sql

CREATE TABLE IF NOT EXISTS `comprovantes_entrega` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `empresa_id` INT(11) NOT NULL,
    `motorista_id` INT(11) NOT NULL,
    `rota_id` INT(11) DEFAULT NULL,
    `recebedor_nome` VARCHAR(120) DEFAULT NULL,
    `recebedor_doc` VARCHAR(40) DEFAULT NULL,
    `foto_nf_path` VARCHAR(255) DEFAULT NULL,
    `foto_carga_path` VARCHAR(255) DEFAULT NULL,
    `assinatura_path` VARCHAR(255) DEFAULT NULL,
    `latitude` DECIMAL(10,7) DEFAULT NULL,
    `longitude` DECIMAL(10,7) DEFAULT NULL,
    `data_hora` DATETIME NOT NULL,
    `observacoes` VARCHAR(255) DEFAULT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'entregue',
    `fonte` VARCHAR(20) NOT NULL DEFAULT 'motorista',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ce_empresa` (`empresa_id`),
    KEY `idx_ce_motorista` (`empresa_id`, `motorista_id`, `data_hora`),
    KEY `idx_ce_rota` (`rota_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
