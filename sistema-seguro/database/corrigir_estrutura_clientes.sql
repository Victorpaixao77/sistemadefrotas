-- =====================================================
-- Corrigir estrutura da tabela seguro_clientes
-- =====================================================

-- 1. Alterar o ENUM do campo 'situacao' para incluir 'aguardando_ativacao'
ALTER TABLE seguro_clientes 
MODIFY COLUMN situacao ENUM('ativo', 'inativo', 'aguardando_ativacao') 
NOT NULL DEFAULT 'aguardando_ativacao';

-- 2. Remover o campo 'status' duplicado (se existir)
ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS status;

-- 3. (OPCIONAL) Remover campos que agora são gerenciados por contratos
-- Descomente as linhas abaixo se quiser remover 'placa' e 'conjunto'
-- ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS placa;
-- ALTER TABLE seguro_clientes DROP COLUMN IF EXISTS conjunto;

-- 4. Atualizar clientes existentes que estão como NULL para 'ativo'
UPDATE seguro_clientes 
SET situacao = 'ativo' 
WHERE situacao IS NULL OR situacao = '';

-- 5. Remover índice duplicado de cpf_cnpj (se existir)
-- DROP INDEX IF EXISTS idx_cpf_cnpj ON seguro_clientes;

SELECT 
    '✅ Estrutura da tabela corrigida com sucesso!' as status,
    COUNT(*) as total_clientes,
    SUM(CASE WHEN situacao = 'ativo' THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN situacao = 'inativo' THEN 1 ELSE 0 END) as inativos,
    SUM(CASE WHEN situacao = 'aguardando_ativacao' THEN 1 ELSE 0 END) as aguardando
FROM seguro_clientes;

