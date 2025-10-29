-- ============================================
-- ADICIONAR CAMPOS DE TEMA E CORES PERSONALIZÁVEIS
-- ============================================
-- Este script adiciona campos para personalização
-- visual do sistema (modo claro/escuro e cores)
-- ============================================

-- Verificar se as colunas já existem antes de adicionar
-- Execute este SQL no phpMyAdmin ou via script PHP

-- ============================================
-- 1. ADICIONAR CAMPOS NA TABELA seguro_usuarios
-- Para salvar a preferência de tema de cada usuário
-- ============================================

-- Verific se a coluna tema existe
SET @col_exists_tema = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_usuarios' 
    AND COLUMN_NAME = 'tema'
);

-- Adicionar coluna tema se não existir
SET @query_tema = IF(
    @col_exists_tema = 0,
    'ALTER TABLE `seguro_usuarios` 
     ADD COLUMN `tema` ENUM(''claro'', ''escuro'', ''auto'') DEFAULT ''claro'' 
     COMMENT ''Tema visual preferido pelo usuário'' 
     AFTER `ultimo_acesso`',
    'SELECT ''Coluna tema já existe'' as mensagem'
);

PREPARE stmt FROM @query_tema;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 2. ADICIONAR CAMPOS NA TABELA seguro_empresa_clientes
-- Para personalização de cores por empresa
-- ============================================

-- Verificar se a coluna tema existe na empresa
SET @col_exists_tema_empresa = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_empresa_clientes' 
    AND COLUMN_NAME = 'tema'
);

-- Adicionar coluna tema na empresa se não existir
SET @query_tema_empresa = IF(
    @col_exists_tema_empresa = 0,
    'ALTER TABLE `seguro_empresa_clientes` 
     ADD COLUMN `tema` ENUM(''claro'', ''escuro'') DEFAULT ''claro'' 
     COMMENT ''Tema padrão da empresa'' 
     AFTER `unidade`',
    'SELECT ''Coluna tema já existe na empresa'' as mensagem'
);

PREPARE stmt FROM @query_tema_empresa;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a coluna cor_primaria existe
SET @col_exists_cor_primaria = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_empresa_clientes' 
    AND COLUMN_NAME = 'cor_primaria'
);

-- Adicionar coluna cor_primaria se não existir
SET @query_cor_primaria = IF(
    @col_exists_cor_primaria = 0,
    'ALTER TABLE `seguro_empresa_clientes` 
     ADD COLUMN `cor_primaria` VARCHAR(7) DEFAULT ''#667eea'' 
     COMMENT ''Cor primária do tema (formato HEX)'' 
     AFTER `tema`',
    'SELECT ''Coluna cor_primaria já existe'' as mensagem'
);

PREPARE stmt FROM @query_cor_primaria;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a coluna cor_secundaria existe
SET @col_exists_cor_secundaria = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_empresa_clientes' 
    AND COLUMN_NAME = 'cor_secundaria'
);

-- Adicionar coluna cor_secundaria se não existir
SET @query_cor_secundaria = IF(
    @col_exists_cor_secundaria = 0,
    'ALTER TABLE `seguro_empresa_clientes` 
     ADD COLUMN `cor_secundaria` VARCHAR(7) DEFAULT ''#764ba2'' 
     COMMENT ''Cor secundária do tema (formato HEX)'' 
     AFTER `cor_primaria`',
    'SELECT ''Coluna cor_secundaria já existe'' as mensagem'
);

PREPARE stmt FROM @query_cor_secundaria;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a coluna cor_destaque existe
SET @col_exists_cor_destaque = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'seguro_empresa_clientes' 
    AND COLUMN_NAME = 'cor_destaque'
);

-- Adicionar coluna cor_destaque se não existir
SET @query_cor_destaque = IF(
    @col_exists_cor_destaque = 0,
    'ALTER TABLE `seguro_empresa_clientes` 
     ADD COLUMN `cor_destaque` VARCHAR(7) DEFAULT ''#28a745'' 
     COMMENT ''Cor de destaque do tema (formato HEX)'' 
     AFTER `cor_secundaria`',
    'SELECT ''Coluna cor_destaque já existe'' as mensagem'
);

PREPARE stmt FROM @query_cor_destaque;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 3. RESUMO DAS ALTERAÇÕES
-- ============================================

SELECT 
    'Campos de tema e cores adicionados com sucesso!' as STATUS,
    '✅ seguro_usuarios: tema' as USUARIO,
    '✅ seguro_empresa_clientes: tema, cor_primaria, cor_secundaria, cor_destaque' as EMPRESA;

-- ============================================
-- 4. VERIFICAR ESTRUTURA FINAL
-- ============================================

-- Verificar colunas adicionadas em seguro_usuarios
SELECT 
    COLUMN_NAME as 'Coluna Usuário',
    COLUMN_TYPE as 'Tipo',
    COLUMN_DEFAULT as 'Padrão',
    COLUMN_COMMENT as 'Comentário'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'seguro_usuarios'
  AND COLUMN_NAME IN ('tema');

-- Verificar colunas adicionadas em seguro_empresa_clientes
SELECT 
    COLUMN_NAME as 'Coluna Empresa',
    COLUMN_TYPE as 'Tipo',
    COLUMN_DEFAULT as 'Padrão',
    COLUMN_COMMENT as 'Comentário'
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'seguro_empresa_clientes'
  AND COLUMN_NAME IN ('tema', 'cor_primaria', 'cor_secundaria', 'cor_destaque');

-- ============================================
-- FIM DO SCRIPT
-- ============================================

