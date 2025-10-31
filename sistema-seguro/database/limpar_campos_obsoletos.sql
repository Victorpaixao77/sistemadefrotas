-- =====================================================
-- Remover campos obsoletos da tabela seguro_clientes
-- Estes campos agora são gerenciados por seguro_contratos_clientes
-- =====================================================

-- Fazer backup dos dados antes de remover (opcional)
-- Descomente as linhas abaixo se quiser guardar os dados antigos:
-- CREATE TABLE IF NOT EXISTS backup_clientes_campos AS
-- SELECT id, codigo, placa, conjunto, matricula 
-- FROM seguro_clientes 
-- WHERE placa IS NOT NULL OR conjunto IS NOT NULL;

-- 1. Remover campo 'placa' (agora em seguro_contratos_clientes)
ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS placa;

-- 2. Remover campo 'conjunto' (agora em seguro_contratos_clientes)
ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS conjunto;

-- 3. (OPCIONAL) Você pode manter o campo 'matricula' pois ele pode ser útil
-- Descomente a linha abaixo se quiser remover também:
-- ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS matricula;

-- 4. Verificar estrutura final
SELECT 
    '✅ Campos obsoletos removidos com sucesso!' as status,
    'placa e conjunto agora são gerenciados por seguro_contratos_clientes' as informacao;

-- 5. Listar todos os campos restantes
SELECT 
    COLUMN_NAME as campo,
    COLUMN_TYPE as tipo,
    IS_NULLABLE as permite_nulo,
    COLUMN_DEFAULT as valor_padrao,
    COLUMN_COMMENT as comentario
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'seguro_clientes'
ORDER BY ORDINAL_POSITION;

