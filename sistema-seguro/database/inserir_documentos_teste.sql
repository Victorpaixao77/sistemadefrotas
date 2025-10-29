-- ============================================
-- INSERIR DOCUMENTOS FINANCEIROS DE TESTE
-- ============================================

-- Pegar IDs da empresa e clientes
SET @empresa_id = (SELECT id FROM seguro_empresa_clientes ORDER BY id LIMIT 1);
SET @cliente1_id = (SELECT id FROM seguro_clientes WHERE codigo = '10001' LIMIT 1);
SET @cliente2_id = (SELECT id FROM seguro_clientes WHERE codigo = '10002' LIMIT 1);

-- Documentos para Cliente 1 (JOÃO DA SILVA SANTOS)
INSERT INTO seguro_financeiro 
(seguro_empresa_id, seguro_cliente_id, tipo, categoria, descricao, valor, data_emissao, data_vencimento, data_pagamento, status, numero_documento, forma_pagamento, placa, conjunto, matricula)
VALUES 
(@empresa_id, @cliente1_id, 'recorrencia', 'Mensalidades/Benefícios', 'Mensalidade Março/2025', 435.57, '2025-03-01', '2025-03-10', '2025-03-08', 'pago', '439', 'PIX', 'BDZ4E56', '382', '370'),
(@empresa_id, @cliente1_id, 'recorrencia', 'Mensalidades/Benefícios', 'Mensalidade Abril/2025', 435.57, '2025-04-01', '2025-04-10', NULL, 'pendente', '440', NULL, 'BDZ4E56', '382', '370'),
(@empresa_id, @cliente1_id, 'comissao', 'Comissões', 'Comissão Venda #1234', 75.50, '2025-03-15', '2025-03-15', '2025-03-15', 'pago', 'COM-001', 'Transferência', NULL, NULL, NULL);

-- Documentos para Cliente 2 (EMPRESA TESTE LTDA)
INSERT INTO seguro_financeiro 
(seguro_empresa_id, seguro_cliente_id, tipo, categoria, descricao, valor, data_emissao, data_vencimento, data_pagamento, status, numero_documento, forma_pagamento, placa, conjunto, matricula)
VALUES 
(@empresa_id, @cliente2_id, 'recorrencia', 'Mensalidades/Benefícios', 'Mensalidade Março/2025', 2172.42, '2025-03-01', '2025-03-05', '2025-03-04', 'pago', '713', 'Boleto', 'BAP2I35,IVK4I48,IVK4I57', '395', '382'),
(@empresa_id, @cliente2_id, 'recorrencia', 'Mensalidades/Benefícios', 'Mensalidade Abril/2025', 2172.42, '2025-04-01', '2025-04-05', NULL, 'pendente', '714', NULL, 'BAP2I35,IVK4I48,IVK4I57', '395', '382'),
(@empresa_id, @cliente2_id, 'comissao', 'Comissões', 'Comissão Venda #5678', 125.00, '2025-03-20', '2025-03-20', NULL, 'pendente', 'COM-002', NULL, NULL, NULL, NULL),
(@empresa_id, @cliente2_id, 'receita', 'Serviços Extras', 'Instalação Equipamento', 350.00, '2025-03-12', '2025-03-12', '2025-03-12', 'pago', 'SRV-001', 'Dinheiro', NULL, NULL, NULL);

-- Verificar documentos inseridos
SELECT 
    '✅ DOCUMENTOS INSERIDOS!' as 'RESULTADO',
    COUNT(*) as 'Total de Documentos'
FROM seguro_financeiro;

-- Ver documentos por cliente
SELECT 
    c.nome_razao_social as 'Cliente',
    f.numero_documento as 'N° Doc',
    f.tipo as 'Tipo',
    f.categoria as 'Classe',
    DATE_FORMAT(f.data_vencimento, '%d/%m/%Y') as 'Vencimento',
    f.valor as 'Valor',
    f.status as 'Situação'
FROM seguro_financeiro f
INNER JOIN seguro_clientes c ON f.seguro_cliente_id = c.id
ORDER BY c.nome_razao_social, f.data_vencimento;

-- Resumo financeiro por cliente
SELECT 
    c.codigo,
    c.nome_razao_social as 'Cliente',
    COUNT(f.id) as 'Total Docs',
    SUM(f.valor) as 'Total Valor',
    SUM(CASE WHEN f.status = 'pago' THEN f.valor ELSE 0 END) as 'Total Pago',
    SUM(CASE WHEN f.status = 'pendente' THEN f.valor ELSE 0 END) as 'Pendente'
FROM seguro_clientes c
LEFT JOIN seguro_financeiro f ON c.id = f.seguro_cliente_id
WHERE c.seguro_empresa_id = @empresa_id
GROUP BY c.id, c.codigo, c.nome_razao_social
ORDER BY c.codigo;

