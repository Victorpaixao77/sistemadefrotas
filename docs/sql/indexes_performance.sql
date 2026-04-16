-- =============================================================================
-- Índices de performance — referência verificada (schema MySQL local)
-- =============================================================================
-- Colunas conferidas em: rotas, abastecimentos, contas_pagar, despesas_fixas, notifications
-- No ambiente de desenvolvimento atual estes índices JÁ EXISTEM (ver seção “Verificação”).
-- Use este arquivo em instalações novas ou após comparar com SHOW INDEX.
--
-- Se ao rodar ALTER aparecer "Duplicate key name", o índice já existe — pode ignorar.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- rotas
-- Colunas relevantes: empresa_id, status, data_saida, data_rota, motorista_id
-- -----------------------------------------------------------------------------
-- Índice composto listagens / dashboard por empresa + status + datas
-- ALTER TABLE rotas ADD INDEX idx_rotas_empresa_status_data (empresa_id, status, data_saida, data_rota);

-- Por empresa + motorista
-- ALTER TABLE rotas ADD INDEX idx_rotas_empresa_motorista (empresa_id, motorista_id);

-- -----------------------------------------------------------------------------
-- abastecimentos
-- Colunas: empresa_id, data_abastecimento (DATETIME), status, veiculo_id, motorista_id
-- -----------------------------------------------------------------------------
-- ALTER TABLE abastecimentos ADD INDEX idx_abast_empresa_data (empresa_id, data_abastecimento);

-- -----------------------------------------------------------------------------
-- contas_pagar
-- Colunas: empresa_id, data_vencimento, status_id, fornecedor_id
-- -----------------------------------------------------------------------------
-- ALTER TABLE contas_pagar ADD INDEX idx_cp_empresa_venc (empresa_id, data_vencimento);
-- ALTER TABLE contas_pagar ADD INDEX idx_cp_empresa_status (empresa_id, status_id);

-- -----------------------------------------------------------------------------
-- despesas_fixas
-- Colunas: empresa_id, vencimento
-- -----------------------------------------------------------------------------
-- ALTER TABLE despesas_fixas ADD INDEX idx_df_empresa_venc (empresa_id, vencimento);

-- -----------------------------------------------------------------------------
-- notifications (tabela: notifications — campos user_id, read_at, created_at)
-- -----------------------------------------------------------------------------
-- ALTER TABLE notifications ADD INDEX idx_notif_user_read (user_id, read_at);
-- ALTER TABLE notifications ADD INDEX idx_notif_user_created (user_id, created_at);

-- =============================================================================
-- Verificação (execute no MySQL; lista índices existentes por tabela)
-- =============================================================================
-- SHOW INDEX FROM rotas;
-- SHOW INDEX FROM abastecimentos;
-- SHOW INDEX FROM contas_pagar;
-- SHOW INDEX FROM despesas_fixas;
-- SHOW INDEX FROM notifications;

-- =============================================================================
-- Nota: em uma instalação verificada (mar/2026), os nomes acima já estavam
-- criados e alinhados às colunas listadas. Descomente o ALTER apenas onde
-- SHOW INDEX indicar ausência.
-- =============================================================================
