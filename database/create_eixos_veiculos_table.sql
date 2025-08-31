-- Tabela para armazenar eixos de veículos na gestão interativa
CREATE TABLE IF NOT EXISTS `eixos_veiculos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `veiculo_id` int(11) NOT NULL,
  `tipo_veiculo` enum('caminhao','carreta') NOT NULL COMMENT 'Tipo do veículo (caminhão ou carreta)',
  `numero_eixo` int(11) NOT NULL COMMENT 'Número sequencial do eixo',
  `quantidade_pneus` int(11) NOT NULL DEFAULT 2 COMMENT 'Quantidade de pneus por eixo (1=simples/2pneus, 2=duplo/4pneus)',
  `empresa_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_veiculo_tipo` (`veiculo_id`, `tipo_veiculo`),
  KEY `idx_empresa` (`empresa_id`),
  CONSTRAINT `fk_eixos_veiculos_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_eixos_veiculos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresa_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eixos de veículos criados na gestão interativa';

-- Tabela para armazenar alocações de pneus nos eixos flexíveis
CREATE TABLE IF NOT EXISTS `alocacoes_pneus_flexiveis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `eixo_veiculo_id` int(11) NOT NULL,
  `pneu_id` int(11) NOT NULL,
  `posicao_slot` int(11) NOT NULL COMMENT 'Posição do slot no eixo (0, 1, 2, 3)',
  `slot_id` varchar(50) NOT NULL COMMENT 'ID único do slot (ex: cavalo-0-0)',
  `empresa_id` int(11) NOT NULL,
  `data_alocacao` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_remocao` timestamp NULL DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Se a alocação está ativa',
  PRIMARY KEY (`id`),
  KEY `idx_eixo_veiculo` (`eixo_veiculo_id`),
  KEY `idx_pneu` (`pneu_id`),
  KEY `idx_slot_id` (`slot_id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_ativo` (`ativo`),
  CONSTRAINT `fk_alocacoes_eixo_veiculo` FOREIGN KEY (`eixo_veiculo_id`) REFERENCES `eixos_veiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alocacoes_pneu` FOREIGN KEY (`pneu_id`) REFERENCES `pneus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alocacoes_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresa_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alocações de pneus nos eixos flexíveis';
