-- =====================================================
-- Adicionar campos 'ponteiro' e 'proposals' na tabela seguro_financeiro
-- Remover campo 'identificador' obsoleto
-- =====================================================

-- 1. Adicionar campo 'ponteiro' (se não existir)
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS ponteiro VARCHAR(50) NULL 
AFTER unidade;

-- 2. Adicionar campo 'proposals' (se não existir)
ALTER TABLE seguro_financeiro 
ADD COLUMN IF NOT EXISTS proposals VARCHAR(100) NULL 
AFTER data_baixa;

-- 3. Remover campo 'identificador' obsoleto (se existir)
ALTER TABLE seguro_financeiro 
DROP COLUMN IF EXISTS identificador;

-- 4. Remover campo 'identificador_original' obsoleto (se existir)
ALTER TABLE seguro_financeiro 
DROP COLUMN IF EXISTS identificador_original;

SELECT 
    '✅ Campos atualizados com sucesso!' as status,
    'Tabela seguro_financeiro preparada para nova lógica de importação' as informacao;

-- Listar estrutura final
SELECT 
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    IS_NULLABLE as permite_nulo
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'seguro_financeiro'
ORDER BY ORDINAL_POSITION;
