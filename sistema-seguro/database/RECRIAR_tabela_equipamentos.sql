-- ============================================
-- RECRIAR TABELA DE EQUIPAMENTOS
-- Script simplificado para recriar a tabela
-- ============================================

-- Remover a tabela se existir
DROP TABLE IF EXISTS `seguro_equipamentos`;

-- Criar a tabela com a estrutura correta
CREATE TABLE `seguro_equipamentos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` INT(11) NOT NULL,
    `seguro_cliente_id` INT(11) NOT NULL,
    `tipo` VARCHAR(100) NOT NULL,
    `descricao` VARCHAR(255) NOT NULL,
    `marca` VARCHAR(100) DEFAULT NULL,
    `modelo` VARCHAR(100) DEFAULT NULL,
    `numero_serie` VARCHAR(100) DEFAULT NULL,
    `data_instalacao` DATE DEFAULT NULL,
    `localizacao` VARCHAR(255) DEFAULT NULL,
    `situacao` ENUM('ativo', 'inativo', 'manutencao', 'substituido') DEFAULT 'ativo',
    `observacoes` TEXT DEFAULT NULL,
    `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_empresa` (`seguro_empresa_id`),
    KEY `idx_cliente` (`seguro_cliente_id`),
    KEY `idx_situacao` (`situacao`),
    KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verificar se foi criada
SELECT 'Tabela criada com sucesso!' AS resultado;

-- Mostrar estrutura
DESCRIBE seguro_equipamentos;

