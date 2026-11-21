-- Índices adicionais para escala enterprise (aplicar manualmente).
-- Verificar duplicatas: SHOW INDEX FROM <tabela>;
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/enterprise_performance_indexes.sql

-- Rotas: dashboards usam data_rota; BI usa data_saida
CREATE INDEX IF NOT EXISTS idx_rotas_empresa_data_rota ON rotas (empresa_id, data_rota);
CREATE INDEX IF NOT EXISTS idx_rotas_empresa_motorista_data ON rotas (empresa_id, motorista_id, data_rota);
CREATE INDEX IF NOT EXISTS idx_rotas_empresa_status_saida ON rotas (empresa_id, status, data_saida);

-- Abastecimentos: filtro status + data (lucratividade + BI)
CREATE INDEX IF NOT EXISTS idx_abast_empresa_status_data ON abastecimentos (empresa_id, status, data_abastecimento);
CREATE INDEX IF NOT EXISTS idx_abast_rota_empresa ON abastecimentos (rota_id, empresa_id);

-- Contas a pagar
CREATE INDEX IF NOT EXISTS idx_cp_empresa_vencimento ON contas_pagar (empresa_id, data_vencimento);
CREATE INDEX IF NOT EXISTS idx_cp_empresa_status_venc ON contas_pagar (empresa_id, status_id, data_vencimento);

-- Multas
CREATE INDEX IF NOT EXISTS idx_multas_empresa_infracao ON multas (empresa_id, data_infracao);

-- Despesas viagem (complementa bi_performance_indexes.sql)
CREATE INDEX IF NOT EXISTS idx_desp_viagem_rota_empresa ON despesas_viagem (rota_id, empresa_id);

-- GPS purge por data
CREATE INDEX IF NOT EXISTS idx_gps_logs_data_hora ON gps_logs (data_hora);

-- Lookups de importação
CREATE INDEX IF NOT EXISTS idx_cidades_uf_nome ON cidades (uf, nome(100));
CREATE INDEX IF NOT EXISTS idx_motoristas_empresa_nome ON motoristas (empresa_id, nome(80));

-- Pneus: medições recentes (gestão / KPIs)
CREATE INDEX IF NOT EXISTS idx_pneu_sulco_pneu_data ON pneu_medicoes_sulco (pneu_id, data_medicao);
CREATE INDEX IF NOT EXISTS idx_pneu_calib_pneu_data ON pneu_calibragens (pneu_id, data_calibragem);
