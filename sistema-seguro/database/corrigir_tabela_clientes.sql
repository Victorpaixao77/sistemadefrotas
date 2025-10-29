-- ===================================================================
-- CORRIGIR ESTRUTURA DA TABELA seguro_clientes
-- ===================================================================
-- Adiciona campos que estão faltando
-- ===================================================================

USE sistema_frotas;

-- 1. Adicionar campos que estão faltando na tabela seguro_clientes

-- Adicionar razao_social (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'razao_social'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN razao_social VARCHAR(255) NULL AFTER codigo',
    'SELECT "Campo razao_social já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar nome_fantasia (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'nome_fantasia'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN nome_fantasia VARCHAR(255) NULL AFTER razao_social',
    'SELECT "Campo nome_fantasia já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar identificador (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'identificador'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN identificador VARCHAR(100) NULL AFTER codigo',
    'SELECT "Campo identificador já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar placa (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'placa'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN placa VARCHAR(255) NULL',
    'SELECT "Campo placa já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar conjunto (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'conjunto'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN conjunto VARCHAR(100) NULL',
    'SELECT "Campo conjunto já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar matricula (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'matricula'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN matricula VARCHAR(100) NULL',
    'SELECT "Campo matricula já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar status (se não existir) - compatível com API
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN status ENUM(\'ativo\', \'inativo\') DEFAULT \'ativo\'',
    'SELECT "Campo status já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar data_cadastro (se não existir)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_clientes'
    AND COLUMN_NAME = 'data_cadastro'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_clientes ADD COLUMN data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP',
    'SELECT "Campo data_cadastro já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Adicionar campo em seguro_financeiro para quarentena

-- Adicionar cliente_nao_encontrado (flag)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'cliente_nao_encontrado'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN cliente_nao_encontrado ENUM(\'sim\', \'nao\') DEFAULT \'nao\' AFTER cliente_id',
    'SELECT "Campo cliente_nao_encontrado já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Adicionar identificador_original (para guardar o ID que veio do arquivo)
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'identificador_original'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN identificador_original VARCHAR(100) NULL AFTER cliente_nao_encontrado',
    'SELECT "Campo identificador_original já existe" AS resultado'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Modificar cliente_id para aceitar NULL (quarentena)
ALTER TABLE seguro_financeiro MODIFY COLUMN cliente_id INT(11) NULL COMMENT 'NULL = cliente não encontrado (quarentena)';

SELECT '✅ Tabelas corrigidas com sucesso!' AS resultado;

-- Mostrar estrutura final
SELECT '=================== ESTRUTURA seguro_clientes ===================' AS '';
DESCRIBE seguro_clientes;

SELECT '=================== ESTRUTURA seguro_financeiro ===================' AS '';
DESCRIBE seguro_financeiro;

