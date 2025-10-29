-- ===================================================================
-- ATUALIZAR ESTRUTURA DA TABELA seguro_financeiro
-- ===================================================================
-- Este script adiciona campos necessários para importação de documentos
-- Execute apenas se as colunas não existirem
-- ===================================================================

USE sistema_frotas;

-- Verificar e adicionar coluna 'unidade' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'unidade'
);

SET @sql_add_unidade = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN unidade VARCHAR(255) NULL AFTER seguro_empresa_id',
    'SELECT "Coluna unidade já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_unidade;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'identificador' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'identificador'
);

SET @sql_add_identificador = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN identificador VARCHAR(100) NULL AFTER unidade',
    'SELECT "Coluna identificador já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_identificador;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'associado' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'associado'
);

SET @sql_add_associado = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN associado VARCHAR(255) NULL AFTER numero_documento',
    'SELECT "Coluna associado já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_associado;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'classe' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'classe'
);

SET @sql_add_classe = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN classe VARCHAR(100) NULL AFTER associado',
    'SELECT "Coluna classe já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_classe;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'data_emissao' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'data_emissao'
);

SET @sql_add_data_emissao = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN data_emissao DATE NULL AFTER classe',
    'SELECT "Coluna data_emissao já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_data_emissao;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar e adicionar coluna 'data_vencimento' se não existir
SET @col_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'sistema_frotas'
    AND TABLE_NAME = 'seguro_financeiro'
    AND COLUMN_NAME = 'data_vencimento'
);

SET @sql_add_data_vencimento = IF(@col_exists = 0,
    'ALTER TABLE seguro_financeiro ADD COLUMN data_vencimento DATE NULL AFTER data_emissao',
    'SELECT "Coluna data_vencimento já existe" AS resultado'
);

PREPARE stmt FROM @sql_add_data_vencimento;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Mostrar estrutura final da tabela
DESCRIBE seguro_financeiro;

SELECT '✅ Estrutura da tabela seguro_financeiro atualizada com sucesso!' AS resultado;

