-- ============================================
-- ADICIONAR CAMPOS NA TABELA seguro_financeiro
-- ============================================
-- Campos: PLACA, CONJUNTO, MATRÍCULA
-- ============================================

-- Adicionar coluna PLACA
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS placa VARCHAR(100) DEFAULT NULL COMMENT 'Placa do veículo' 
AFTER valor;

-- Adicionar coluna CONJUNTO
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS conjunto VARCHAR(100) DEFAULT NULL COMMENT 'Conjunto/Grupo' 
AFTER placa;

-- Adicionar coluna MATRÍCULA
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS matricula VARCHAR(100) DEFAULT NULL COMMENT 'Matrícula' 
AFTER conjunto;

-- Adicionar coluna DATA_EMISSAO (separada do vencimento)
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS data_emissao DATE DEFAULT NULL COMMENT 'Data de emissão do documento' 
AFTER categoria;

-- Verificar se foram criados
SELECT 
    COLUMN_NAME as 'Campo',
    COLUMN_TYPE as 'Tipo',
    COLUMN_COMMENT as 'Comentário'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'seguro_financeiro' 
AND TABLE_SCHEMA = 'sistema_frotas'
AND COLUMN_NAME IN ('placa', 'conjunto', 'matricula', 'data_emissao')
ORDER BY ORDINAL_POSITION;

SELECT '✅ CAMPOS ADICIONADOS COM SUCESSO!' as 'RESULTADO';

