-- ============================================
-- ADICIONAR CAMPOS NA TABELA seguro_clientes
-- ============================================
-- Campos: Identificador, PLACA, CONJUNTO, MATRÍCULA
-- ============================================

-- Adicionar coluna Identificador
ALTER TABLE seguro_clientes 
ADD COLUMN IF NOT EXISTS identificador VARCHAR(100) DEFAULT NULL COMMENT 'Identificador do cliente' 
AFTER codigo;

-- Adicionar coluna PLACA
ALTER TABLE seguro_clientes 
ADD COLUMN IF NOT EXISTS placa VARCHAR(100) DEFAULT NULL COMMENT 'Placa do veículo' 
AFTER uf;

-- Adicionar coluna CONJUNTO
ALTER TABLE seguro_clientes 
ADD COLUMN IF NOT EXISTS conjunto VARCHAR(100) DEFAULT NULL COMMENT 'Conjunto/Grupo' 
AFTER placa;

-- Adicionar coluna MATRÍCULA
ALTER TABLE seguro_clientes 
ADD COLUMN IF NOT EXISTS matricula VARCHAR(100) DEFAULT NULL COMMENT 'Matrícula' 
AFTER conjunto;

-- Verificar se foram criados
SELECT 
    COLUMN_NAME as 'Campo',
    COLUMN_TYPE as 'Tipo',
    COLUMN_COMMENT as 'Comentário'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'seguro_clientes' 
AND TABLE_SCHEMA = 'sistema_frotas'
AND COLUMN_NAME IN ('identificador', 'placa', 'conjunto', 'matricula')
ORDER BY ORDINAL_POSITION;

SELECT '✅ CAMPOS ADICIONADOS NA TABELA CLIENTES!' as 'RESULTADO';

