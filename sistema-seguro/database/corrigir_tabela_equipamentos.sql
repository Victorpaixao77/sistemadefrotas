-- ============================================
-- CORRIGIR TABELA DE EQUIPAMENTOS
-- Verifica e corrige a estrutura da tabela
-- ============================================

-- Primeiro, vamos verificar se a tabela existe
SET @exist = (SELECT COUNT(*) FROM information_schema.TABLES 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'seguro_equipamentos');

-- Se não existe, criar a tabela
SET @sql = IF(@exist = 0,
    'CREATE TABLE `seguro_equipamentos` (
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
        `situacao` ENUM("ativo", "inativo", "manutencao", "substituido") DEFAULT "ativo",
        `observacoes` TEXT DEFAULT NULL,
        `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `data_atualizacao` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `seguro_empresa_id` (`seguro_empresa_id`),
        KEY `seguro_cliente_id` (`seguro_cliente_id`),
        KEY `situacao` (`situacao`),
        KEY `tipo` (`tipo`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "Tabela já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar colunas se não existirem
-- Verificar e adicionar coluna 'descricao'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'descricao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `descricao` VARCHAR(255) NOT NULL AFTER `tipo`',
    'SELECT "Coluna descricao já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'marca'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'marca');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `marca` VARCHAR(100) DEFAULT NULL AFTER `descricao`',
    'SELECT "Coluna marca já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'modelo'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'modelo');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `modelo` VARCHAR(100) DEFAULT NULL AFTER `marca`',
    'SELECT "Coluna modelo já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'numero_serie'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'numero_serie');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `numero_serie` VARCHAR(100) DEFAULT NULL AFTER `modelo`',
    'SELECT "Coluna numero_serie já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'data_instalacao'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'data_instalacao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `data_instalacao` DATE DEFAULT NULL AFTER `numero_serie`',
    'SELECT "Coluna data_instalacao já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'localizacao'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'localizacao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `localizacao` VARCHAR(255) DEFAULT NULL AFTER `data_instalacao`',
    'SELECT "Coluna localizacao já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'situacao'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'situacao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `situacao` ENUM("ativo", "inativo", "manutencao", "substituido") DEFAULT "ativo" AFTER `localizacao`',
    'SELECT "Coluna situacao já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'observacoes'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'observacoes');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `observacoes` TEXT DEFAULT NULL AFTER `situacao`',
    'SELECT "Coluna observacoes já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'data_cadastro'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'data_cadastro');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `data_cadastro` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `observacoes`',
    'SELECT "Coluna data_cadastro já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'data_atualizacao'
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_equipamentos' 
                   AND COLUMN_NAME = 'data_atualizacao');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_equipamentos` ADD COLUMN `data_atualizacao` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `data_cadastro`',
    'SELECT "Coluna data_atualizacao já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mostrar estrutura final da tabela
DESCRIBE seguro_equipamentos;

-- Mostrar registros existentes
SELECT COUNT(*) as total_equipamentos FROM seguro_equipamentos;

SELECT 'Tabela corrigida com sucesso!' AS resultado;

