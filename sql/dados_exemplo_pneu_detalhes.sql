-- =============================================================================
-- Dados de exemplo para testar "Detalhes do Pneu"
-- Use para o pneu TEMP-68444c5dcb216 (ou outro com numero_serie começando em TEMP)
-- Executar no MySQL: source sql/dados_exemplo_pneu_detalhes.sql
-- =============================================================================

-- 1) Atualizar o pneu temporário com dados completos (vida útil, recapagem, sulco, obs)
UPDATE pneus
SET
    vida_util_km       = 50000,
    numero_recapagens  = 1,
    data_ultima_recapagem = DATE_SUB(CURDATE(), INTERVAL 2 MONTH),
    sulco_inicial      = 8.5,
    observacoes        = 'Pneu de teste para tela de detalhes. Dados simulados.'
WHERE numero_serie = 'TEMP-68444c5dcb216'
LIMIT 1;

-- 2) Garantir que existe pelo menos um tipo de manutenção de pneu
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Recapagem');
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Calibragem');
INSERT IGNORE INTO tipo_manutencao_pneus (nome) VALUES ('Troca');

-- 3) Inserir manutenções de exemplo para esse pneu (usa empresa_id e id do pneu)
SET @empresa_id = (SELECT empresa_id FROM pneus WHERE numero_serie = 'TEMP-68444c5dcb216' LIMIT 1);
SET @pneu_id   = (SELECT id FROM pneus WHERE numero_serie = 'TEMP-68444c5dcb216' LIMIT 1);
SET @tipo_id   = (SELECT id FROM tipo_manutencao_pneus ORDER BY id LIMIT 1);
SET @veiculo_id = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id AND status_id = 1 LIMIT 1);

-- Primeira manutenção (recapagem)
INSERT INTO pneu_manutencao (empresa_id, pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, observacoes, tipo_manutencao_id)
SELECT @empresa_id, @pneu_id, @veiculo_id, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 12500, 350.00, 'Recapagem de teste', @tipo_id
FROM DUAL WHERE @pneu_id IS NOT NULL AND @tipo_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM pneu_manutencao WHERE pneu_id = @pneu_id LIMIT 1);

-- Segunda manutenção (calibragem) - usa outro tipo se existir
INSERT INTO pneu_manutencao (empresa_id, pneu_id, veiculo_id, data_manutencao, km_veiculo, custo, observacoes, tipo_manutencao_id)
SELECT @empresa_id, @pneu_id, @veiculo_id, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 8000, 120.00, 'Calibragem / pressão', COALESCE((SELECT id FROM tipo_manutencao_pneus ORDER BY id LIMIT 1 OFFSET 1), @tipo_id)
FROM DUAL WHERE @pneu_id IS NOT NULL AND @tipo_id IS NOT NULL
AND (SELECT COUNT(*) FROM pneu_manutencao WHERE pneu_id = @pneu_id) = 1;
