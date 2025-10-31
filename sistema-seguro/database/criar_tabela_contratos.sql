-- ====================================================================
-- TABELA: seguro_contratos_clientes
-- DESCRIÇÃO: Armazena os contratos de cada cliente (matrículas)
-- Cada cliente pode ter múltiplos contratos com diferentes placas e %
-- ====================================================================

CREATE TABLE IF NOT EXISTS `seguro_contratos_clientes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` INT(11) NOT NULL COMMENT 'FK para seguro_clientes',
  `empresa_id` INT(11) NOT NULL COMMENT 'FK para empresa_clientes',
  `matricula` VARCHAR(100) NOT NULL COMMENT 'Matrícula/Código do contrato (identificador único)',
  `placa` VARCHAR(255) DEFAULT NULL COMMENT 'Placa(s) vinculada(s) ao contrato',
  `porcentagem_recorrencia` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentual de recorrência para este contrato',
  `ativo` ENUM('sim', 'nao') NOT NULL DEFAULT 'sim' COMMENT 'Contrato ativo ou inativo',
  `observacoes` TEXT DEFAULT NULL COMMENT 'Observações sobre o contrato',
  `data_criacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_matricula_empresa` (`matricula`, `empresa_id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_empresa_id` (`empresa_id`),
  KEY `idx_matricula` (`matricula`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `fk_contratos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `seguro_clientes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contratos de clientes com matrículas e placas vinculadas';

-- Índice para busca rápida por placa
ALTER TABLE `seguro_contratos_clientes` ADD INDEX `idx_placa` (`placa`);

