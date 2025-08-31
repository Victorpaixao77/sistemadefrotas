-- Script para criar a tabela multas
USE sistema_frotas;

-- Criar tabela multas se não existir
CREATE TABLE IF NOT EXISTS `multas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `veiculo_id` int(11) DEFAULT NULL,
  `motorista_id` int(11) DEFAULT NULL,
  `rota_id` int(11) DEFAULT NULL,
  `data_infracao` date NOT NULL,
  `tipo_infracao` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `pontos` int(11) DEFAULT 0,
  `valor` decimal(10,2) NOT NULL,
  `status_pagamento` enum('pendente','pago','recurso') NOT NULL DEFAULT 'pendente',
  `vencimento` date DEFAULT NULL,
  `data_pagamento` date DEFAULT NULL,
  `comprovante` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_empresa_id` (`empresa_id`),
  KEY `idx_veiculo_id` (`veiculo_id`),
  KEY `idx_motorista_id` (`motorista_id`),
  KEY `idx_data_infracao` (`data_infracao`),
  KEY `idx_status_pagamento` (`status_pagamento`),
  KEY `idx_vencimento` (`vencimento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar chaves estrangeiras se as tabelas existirem
-- (Comentado para evitar erros se as tabelas não existirem)

/*
ALTER TABLE `multas` 
ADD CONSTRAINT `fk_multas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_multas_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_multas_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`id`) ON DELETE SET NULL,
ADD CONSTRAINT `fk_multas_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas` (`id`) ON DELETE SET NULL;
*/

-- Inserir dados de exemplo (opcional)
INSERT INTO `multas` (`empresa_id`, `veiculo_id`, `motorista_id`, `data_infracao`, `tipo_infracao`, `valor`, `status_pagamento`, `pontos`, `descricao`) VALUES
(1, 1, 1, '2025-08-23', 'Excesso de velocidade', 293.47, 'pendente', 4, 'Multa por excesso de velocidade na BR-101'),
(1, 1, 1, '2025-08-20', 'Estacionamento irregular', 88.38, 'pendente', 0, 'Veículo estacionado em local proibido');

SELECT 'Tabela multas criada com sucesso!' as resultado;
