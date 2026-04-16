-- Índices sugeridos para API BI (api/performance_indicators.php)
-- Executar manualmente no MySQL/MariaDB. Se der erro "Duplicate key name", o índice já existe — pode ignorar.

-- Rotas: filtros por empresa + período + veículo (COUNT DISTINCT veículos ativos, joins BI)
CREATE INDEX idx_rotas_bi_empresa_saida_veiculo ON rotas (empresa_id, data_saida, veiculo_id);

-- Abastecimentos: agregações mensais e por veículo (status aprovado + datas)
CREATE INDEX idx_abast_bi_empresa_veiculo_data ON abastecimentos (empresa_id, veiculo_id, data_abastecimento);

-- Manutenções: totais por mês e joins com veículos
CREATE INDEX idx_manut_bi_empresa_data ON manutencoes (empresa_id, data_manutencao);

-- Despesas fixas: COALESCE(data_pagamento, vencimento) no BI — índices separados ajudam filtros por empresa
CREATE INDEX idx_desp_fixas_bi_empresa_venc ON despesas_fixas (empresa_id, vencimento);
CREATE INDEX idx_desp_fixas_bi_empresa_pag ON despesas_fixas (empresa_id, data_pagamento);

-- Despesas de viagem: join frequente por rota_id
CREATE INDEX idx_desp_viagem_rota ON despesas_viagem (rota_id);
