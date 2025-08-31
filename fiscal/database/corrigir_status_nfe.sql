-- =====================================================
-- 🔧 CORREÇÃO DE STATUS DAS NF-e EXISTENTES
-- 📋 Atualizar status NULL para 'rascunho'
-- =====================================================

-- Corrigir status das NF-e existentes
UPDATE fiscal_nfe_clientes 
SET status = 'rascunho' 
WHERE status IS NULL OR status = '';

-- Verificar resultado
SELECT 
    id,
    numero_nfe,
    serie_nfe,
    status,
    data_emissao,
    cliente_razao_social
FROM fiscal_nfe_clientes 
ORDER BY numero_nfe;
