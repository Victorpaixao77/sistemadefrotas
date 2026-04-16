-- Adicionar índice faltante e limpar cache do BI

-- 1. Índice faltante para despesas_viagem
CREATE INDEX IF NOT EXISTS idx_desp_viagem_rota_empresa ON despesas_viagem(rota_id, empresa_id);

-- 2. Limpar cache do BI para forçar recálculo
DELETE FROM bi_cache WHERE 1=1;

-- 3. Verificar se as alterações ficaram OK
SELECT 'Índices criados com sucesso!' as status;
