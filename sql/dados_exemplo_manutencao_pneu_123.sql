-- =============================================================================
-- Inserir manutenções de exemplo para o pneu 123 (Pneumar / teste)
-- Para o histórico aparecer em "Detalhes do Pneu" > Histórico de manutenções
-- Executar no MySQL: source sql/dados_exemplo_manutencao_pneu_123.sql
-- =============================================================================

-- Pneu alvo: numero_serie = '123'
SET @empresa_id = (SELECT empresa_id FROM pneus WHERE numero_serie = '123' LIMIT 1);
SET @pneu_id   = (SELECT id FROM pneus WHERE numero_serie = '123' LIMIT 1);
SET @veiculo_id = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id AND status_id = 1 LIMIT 1);

-- Garantir tipos de manutenção
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Recapagem');
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Calibragem');
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Troca');
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Balanceamento');

SET @tipo_recap    = (SELECT id FROM tipo_manutencao_pneus WHERE nome = 'Recapagem' LIMIT 1);
SET @tipo_calib   = (SELECT id FROM tipo_manutencao_pneus WHERE nome = 'Calibragem' LIMIT 1);
SET @tipo_balance  = (SELECT id FROM tipo_manutencao_pneus WHERE nome = 'Balanceamento' LIMIT 1);
SET @tipo_troca   = (SELECT id FROM tipo_manutencao_pneus WHERE nome = 'Troca' LIMIT 1);

-- Só insere se encontrou o pneu (FROM DUAL para retornar 1 linha quando @pneu_id não é NULL)
INSERT INTO pneu_manutencao (empresa_id, pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, observacoes, tipo_manutencao_id)
SELECT @empresa_id, @pneu_id, @veiculo_id, '2025-05-15', 350, 280.00, 'Recapagem pneu 123 - simulação', COALESCE(@tipo_recap, (SELECT id FROM tipo_manutencao_pneus LIMIT 1))
FROM DUAL WHERE @pneu_id IS NOT NULL;

INSERT INTO pneu_manutencao (empresa_id, pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, observacoes, tipo_manutencao_id)
SELECT @empresa_id, @pneu_id, @veiculo_id, '2025-04-10', 180, 85.00, 'Calibragem e verificação de pressão - pneu 123', COALESCE(@tipo_calib, (SELECT id FROM tipo_manutencao_pneus LIMIT 1))
FROM DUAL WHERE @pneu_id IS NOT NULL;

INSERT INTO pneu_manutencao (empresa_id, pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, observacoes, tipo_manutencao_id)
SELECT @empresa_id, @pneu_id, @veiculo_id, '2025-06-01', 420, 120.00, 'Balanceamento após recapagem - pneu 123', COALESCE(@tipo_balance, @tipo_troca, (SELECT id FROM tipo_manutencao_pneus LIMIT 1))
FROM DUAL WHERE @pneu_id IS NOT NULL;

-- Fim
