-- ============================================
-- ADICIONAR CAMPO DIA_FECHAMENTO
-- Para controle de fechamento mensal de comissões
-- ============================================

-- Verificar se a coluna já existe
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'seguro_empresa_clientes' 
                   AND COLUMN_NAME = 'dia_fechamento');

-- Adicionar coluna se não existir
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `seguro_empresa_clientes` 
     ADD COLUMN `dia_fechamento` INT(2) DEFAULT 25 
     COMMENT "Dia de fechamento mensal para cálculo de comissões" 
     AFTER `porcentagem_fixa`',
    'SELECT "Coluna dia_fechamento já existe" AS status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se foi adicionada
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'seguro_empresa_clientes'
AND COLUMN_NAME = 'dia_fechamento';

-- Mostrar resultado
SELECT 'Campo dia_fechamento adicionado/verificado com sucesso!' AS resultado;

-- Atualizar empresas existentes que não têm valor (definir padrão 25)
UPDATE seguro_empresa_clientes 
SET dia_fechamento = 25 
WHERE dia_fechamento IS NULL OR dia_fechamento = 0;

-- Mostrar total de empresas atualizadas
SELECT 
    COUNT(*) as total_empresas,
    dia_fechamento
FROM seguro_empresa_clientes
GROUP BY dia_fechamento;

