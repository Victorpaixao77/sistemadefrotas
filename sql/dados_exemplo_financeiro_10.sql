-- ============================================
-- 10 registros de exemplo para cada tela do Financeiro
-- empresa_id = 1
-- ============================================
-- Execute após ter empresa_clientes id=1, veículos e motoristas para empresa 1.
-- Tabelas: contas_pagar, despesas_fixas, financiamentos (+ parcelas_financiamento), multas
-- ============================================

-- IDs auxiliares (use os primeiros disponíveis para empresa 1)
SET @empresa_id = 1;
SET @veiculo_id = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id ORDER BY id LIMIT 1);
SET @motorista_id = (SELECT id FROM motoristas WHERE empresa_id = @empresa_id ORDER BY id LIMIT 1);
SET @status_pendente_cp = (SELECT id FROM status_contas_pagar WHERE UPPER(nome) LIKE '%PENDENTE%' ORDER BY id LIMIT 1);
SET @status_pago_cp = (SELECT id FROM status_contas_pagar WHERE UPPER(nome) LIKE '%PAGA%' OR UPPER(nome) = 'PAGO' ORDER BY id LIMIT 1);
SET @forma_id = (SELECT id FROM formas_pagamento ORDER BY id LIMIT 1);
SET @banco_id = (SELECT id FROM bancos ORDER BY id LIMIT 1);
SET @status_pago = (SELECT id FROM status_pagamento WHERE UPPER(nome) LIKE '%PAGO%' ORDER BY id LIMIT 1);
SET @status_pendente = (SELECT id FROM status_pagamento WHERE UPPER(nome) LIKE '%PENDENTE%' ORDER BY id LIMIT 1);
SET @tipo_despesa_id = (SELECT id FROM tipos_despesa_fixa ORDER BY id LIMIT 1);

-- Garantir pelo menos um status para contas a pagar (se a tabela existir e estiver vazia)
INSERT IGNORE INTO status_contas_pagar (nome) VALUES ('Pendente'), ('Paga'), ('Cancelada'), ('Vencida');
INSERT IGNORE INTO formas_pagamento (nome) VALUES ('Transferência'), ('Boleto'), ('Dinheiro'), ('PIX');
INSERT IGNORE INTO status_pagamento (nome) VALUES ('Pendente'), ('Pago');

-- Re-atribuir IDs após possível inserção
SET @status_pendente_cp = COALESCE(@status_pendente_cp, (SELECT id FROM status_contas_pagar WHERE UPPER(nome) LIKE '%PENDENTE%' ORDER BY id LIMIT 1));
SET @status_pago_cp = COALESCE(@status_pago_cp, (SELECT id FROM status_contas_pagar WHERE UPPER(nome) LIKE '%PAGA%' OR UPPER(nome) = 'PAGO' ORDER BY id LIMIT 1));
SET @forma_id = (SELECT id FROM formas_pagamento ORDER BY id LIMIT 1);
SET @status_pago = (SELECT id FROM status_pagamento WHERE UPPER(nome) LIKE '%PAGO%' ORDER BY id LIMIT 1);
SET @status_pendente = (SELECT id FROM status_pagamento WHERE UPPER(nome) LIKE '%PENDENTE%' ORDER BY id LIMIT 1);

-- ============================================
-- 1. CONTAS A PAGAR (10 registros)
-- ============================================
INSERT INTO contas_pagar (empresa_id, fornecedor, descricao, valor, data_vencimento, data_pagamento, status_id, forma_pagamento_id, banco_id, observacoes) VALUES
(@empresa_id, 'Fornecedor Alpha', 'Material de escritório', 450.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, @status_pendente_cp, @forma_id, @banco_id, NULL),
(@empresa_id, 'Posto Beta', 'Combustível frota', 3200.00, DATE_ADD(CURDATE(), INTERVAL 10 DAY), NULL, @status_pendente_cp, @forma_id, NULL, 'Abastecimento mensal'),
(@empresa_id, 'Oficina Gama', 'Troca de óleo 3 veículos', 890.00, DATE_ADD(CURDATE(), INTERVAL 3 DAY), NULL, @status_pendente_cp, @forma_id, @banco_id, NULL),
(@empresa_id, 'Seguradora Delta', 'Apólice frota', 5400.00, DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), @status_pago_cp, @forma_id, @banco_id, 'Renovação anual'),
(@empresa_id, 'Pedágios BR', 'Vale-pedágio março', 1200.00, CURDATE(), NULL, @status_pendente_cp, @forma_id, NULL, NULL),
(@empresa_id, 'Fornecedor Epsilon', 'Peças sobressalentes', 1850.00, DATE_ADD(CURDATE(), INTERVAL 15 DAY), NULL, @status_pendente_cp, @forma_id, @banco_id, NULL),
(@empresa_id, 'Lavagem Zeta', 'Lavagem mensal frota', 380.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), @status_pago_cp, @forma_id, NULL, NULL),
(@empresa_id, 'DETRAN', 'Licenciamento veículos', 720.00, DATE_ADD(CURDATE(), INTERVAL 20 DAY), NULL, @status_pendente_cp, @forma_id, @banco_id, NULL),
(@empresa_id, 'Contabilidade Theta', 'Honorários mensais', 1500.00, DATE_ADD(CURDATE(), INTERVAL 7 DAY), NULL, @status_pendente_cp, @forma_id, @banco_id, NULL),
(@empresa_id, 'Manutenção Iota', 'Revisão preventiva', 2100.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), NULL, @status_pendente_cp, @forma_id, NULL, 'Vencida ontem');

-- ============================================
-- 2. DESPESAS FIXAS (10 registros)
-- ============================================
-- Garantir tipo de despesa
INSERT IGNORE INTO tipos_despesa_fixa (nome) VALUES ('Seguro'), ('IPVA'), ('Licenciamento'), ('Manutenção Preventiva');
SET @tipo_despesa_id = (SELECT id FROM tipos_despesa_fixa ORDER BY id LIMIT 1);
SET @tipo_seguro = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Seguro' LIMIT 1);
SET @tipo_ipva = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'IPVA' LIMIT 1);
SET @tipo_lic = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Licenciamento' LIMIT 1);
SET @tipo_manut = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Manutenção Preventiva' LIMIT 1);
SET @tipo_seguro = COALESCE(@tipo_seguro, @tipo_despesa_id);
SET @tipo_ipva = COALESCE(@tipo_ipva, @tipo_despesa_id);
SET @tipo_lic = COALESCE(@tipo_lic, @tipo_despesa_id);
SET @tipo_manut = COALESCE(@tipo_manut, @tipo_despesa_id);

INSERT INTO despesas_fixas (empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento, status_pagamento_id, forma_pagamento_id, ano_referencia, descricao) VALUES
(@empresa_id, @veiculo_id, @tipo_seguro, 850.00, DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Seguro mensal'),
(@empresa_id, @veiculo_id, @tipo_manut, 1200.00, DATE_ADD(CURDATE(), INTERVAL 12 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Revisão 10.000 km'),
(@empresa_id, @veiculo_id, @tipo_lic, 450.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), @status_pago, @forma_id, YEAR(CURDATE()), 'Licenciamento anual'),
(@empresa_id, @veiculo_id, @tipo_seguro, 850.00, DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 28 DAY), @status_pago, @forma_id, YEAR(CURDATE()), 'Seguro mês anterior'),
(@empresa_id, @veiculo_id, @tipo_ipva, 3500.00, DATE_ADD(CURDATE(), INTERVAL 45 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'IPVA 2025'),
(@empresa_id, @veiculo_id, @tipo_manut, 980.00, DATE_ADD(CURDATE(), INTERVAL 8 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Troca de filtros'),
(@empresa_id, @veiculo_id, @tipo_seguro, 850.00, DATE_ADD(CURDATE(), INTERVAL 35 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Seguro próximo mês'),
(@empresa_id, @veiculo_id, @tipo_lic, 450.00, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 58 DAY), @status_pago, @forma_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 60 DAY)), 'Licenciamento anterior'),
(@empresa_id, @veiculo_id, @tipo_manut, 1500.00, DATE_ADD(CURDATE(), INTERVAL 18 DAY), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Freios e pastilhas'),
(@empresa_id, @veiculo_id, @tipo_seguro, 920.00, CURDATE(), NULL, @status_pendente, @forma_id, YEAR(CURDATE()), 'Seguro vencimento hoje');

-- ============================================
-- 3. FINANCIAMENTOS (10 registros) + parcelas
-- ============================================
-- Bancos (criar se não existir para financiamento)
INSERT IGNORE INTO bancos (nome) VALUES ('Banco do Brasil'), ('Caixa Econômica'), ('Itaú');
SET @banco_id = (SELECT id FROM bancos ORDER BY id LIMIT 1);

INSERT INTO financiamentos (empresa_id, veiculo_id, banco_id, valor_total, numero_parcelas, valor_parcela, data_inicio, taxa_juros, status_pagamento_id, data_proxima_parcela, contrato, observacoes) VALUES
(@empresa_id, @veiculo_id, @banco_id, 180000.00, 48, 3750.00, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), 1.99, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'FIN-001', NULL),
(@empresa_id, @veiculo_id, @banco_id, 220000.00, 60, 3666.67, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), 2.19, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'FIN-002', NULL),
(@empresa_id, @veiculo_id, @banco_id, 195000.00, 48, 4062.50, DATE_SUB(CURDATE(), INTERVAL 12 MONTH), 1.79, @status_pago, CURDATE(), 'FIN-003', 'Quitado antecipado'),
(@empresa_id, @veiculo_id, @banco_id, 210000.00, 60, 3500.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 2.29, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 25 DAY), 'FIN-004', NULL),
(@empresa_id, @veiculo_id, @banco_id, 175000.00, 36, 4861.11, DATE_SUB(CURDATE(), INTERVAL 18 MONTH), 1.59, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'FIN-005', NULL),
(@empresa_id, @veiculo_id, @banco_id, 240000.00, 60, 4000.00, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 2.49, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 28 DAY), 'FIN-006', NULL),
(@empresa_id, @veiculo_id, @banco_id, 165000.00, 48, 3437.50, DATE_SUB(CURDATE(), INTERVAL 9 MONTH), 1.89, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'FIN-007', NULL),
(@empresa_id, @veiculo_id, @banco_id, 200000.00, 48, 4166.67, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), 2.09, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'FIN-008', NULL),
(@empresa_id, @veiculo_id, @banco_id, 188000.00, 60, 3133.33, DATE_SUB(CURDATE(), INTERVAL 7 MONTH), 1.99, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'FIN-009', NULL),
(@empresa_id, @veiculo_id, @banco_id, 230000.00, 60, 3833.33, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), 2.19, @status_pendente, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'FIN-010', NULL);

-- Parcelas para os 10 financiamentos (1 parcela cada para simplificar; a aplicação cria as demais)
-- Inserir 1 parcela por financiamento (as 10 últimas inseridas)
INSERT INTO parcelas_financiamento (financiamento_id, numero_parcela, valor, data_vencimento, status_id, empresa_id, data_pagamento)
SELECT f.id, 1, f.valor_parcela, f.data_proxima_parcela, f.status_pagamento_id, @empresa_id,
       CASE WHEN f.status_pagamento_id = @status_pago THEN f.data_inicio ELSE NULL END
FROM financiamentos f
WHERE f.empresa_id = @empresa_id
ORDER BY f.id DESC
LIMIT 10;

-- ============================================
-- 4. MULTAS (10 registros)
-- ============================================
INSERT INTO multas (empresa_id, veiculo_id, motorista_id, rota_id, data_infracao, tipo_infracao, descricao, pontos, valor, status_pagamento, vencimento, data_pagamento, comprovante) VALUES
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'Excesso de velocidade', 'Art. 218 CTB - Via urbana', 4, 195.23, 'pendente', DATE_ADD(CURDATE(), INTERVAL 20 DAY), NULL, NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 25 DAY), 'Ultrapassagem indevida', 'Art. 203 CTB', 5, 293.47, 'pendente', DATE_ADD(CURDATE(), INTERVAL 5 DAY), NULL, NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 45 DAY), 'Excesso de velocidade', 'Art. 218 - Rodovia', 5, 391.62, 'pago', DATE_SUB(CURDATE(), INTERVAL 15 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 5 DAY), 'Estacionamento em local proibido', 'Art. 181 CTB', 3, 130.16, 'pendente', DATE_ADD(CURDATE(), INTERVAL 25 DAY), NULL, NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 'Não usar cinto de segurança', 'Art. 167 CTB', 5, 195.23, 'pago', DATE_SUB(CURDATE(), INTERVAL 30 DAY), DATE_SUB(CURDATE(), INTERVAL 28 DAY), NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'Excesso de velocidade', 'Radar fixo - 20% acima', 4, 195.23, 'pendente', DATE_ADD(CURDATE(), INTERVAL 15 DAY), NULL, NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 35 DAY), 'Transitar em faixa de ônibus', 'Art. 182 CTB', 4, 195.23, 'pago', DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'Avancar sinal vermelho', 'Art. 208 CTB', 7, 293.47, 'pendente', DATE_ADD(CURDATE(), INTERVAL 27 DAY), NULL, NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 50 DAY), 'Excesso de velocidade', 'Art. 218', 5, 391.62, 'pago', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_SUB(CURDATE(), INTERVAL 18 DAY), NULL),
(@empresa_id, @veiculo_id, @motorista_id, NULL, DATE_SUB(CURDATE(), INTERVAL 20 DAY), 'Documentação - CNH vencida', 'Art. 162 CTB', 7, 293.47, 'pendente', DATE_ADD(CURDATE(), INTERVAL 10 DAY), NULL, NULL);

-- ============================================
-- FIM
-- ============================================
SELECT 'Inseridos: 10 contas_pagar, 10 despesas_fixas, 10 financiamentos (+ parcelas), 10 multas para empresa_id = 1' AS resultado;
