-- Script para inserir dados de exemplo para empresa_id = 1
-- Dados dos últimos 6 meses (mês atual até 6 meses atrás)
-- Um mês ficará negativo, os demais positivos

-- ============================================
-- 0. VERIFICAR/CRIAR EMPRESA
-- ============================================

-- Verificar se existe empresa_clientes com id = 1
-- Se não existir, criar uma empresa de exemplo
INSERT INTO empresa_clientes (
    empresa_adm_id, razao_social, nome_fantasia, cnpj, 
    telefone, email, endereco, cidade, estado, cep, 
    responsavel, status
)
SELECT 
    1, 
    'Empresa Exemplo LTDA', 
    'Empresa Exemplo', 
    '12.345.678/0001-90',
    '(11) 3456-7890',
    'exemplo@empresa.com',
    'Rua Exemplo, 123',
    'São Paulo',
    'SP',
    '01234-567',
    'Responsável Exemplo',
    'ativo'
WHERE NOT EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

-- ============================================
-- 1. VERIFICAR/CRIAR VEÍCULOS E MOTORISTAS
-- ============================================

-- Verificar se existem veículos para empresa_id = 1
-- Se não existirem, criar alguns veículos de exemplo
-- NOTA: empresa_id referencia empresa_clientes.id
INSERT INTO veiculos (empresa_id, placa, modelo, marca, ano, status_id)
SELECT 1, 'ABC-1234', 'Mercedes-Benz Actros', 'Mercedes-Benz', 2020, 1
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

INSERT INTO veiculos (empresa_id, placa, modelo, marca, ano, status_id)
SELECT 1, 'XYZ-5678', 'Volvo FH', 'Volvo', 2021, 1
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

INSERT INTO veiculos (empresa_id, placa, modelo, marca, ano, status_id)
SELECT 1, 'DEF-9012', 'Scania R450', 'Scania', 2019, 1
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

-- Verificar se existem motoristas para empresa_id = 1
-- Se não existirem, criar alguns motoristas de exemplo
INSERT INTO motoristas (empresa_id, nome, cpf, telefone)
SELECT 1, 'João Silva', '12345678901', '(11) 98765-4321'
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

INSERT INTO motoristas (empresa_id, nome, cpf, telefone)
SELECT 1, 'Maria Santos', '98765432109', '(11) 91234-5678'
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

INSERT INTO motoristas (empresa_id, nome, cpf, telefone)
SELECT 1, 'Pedro Oliveira', '11122233344', '(11) 99876-5432'
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344')
AND EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1);

-- ============================================
-- 2. OBTER IDs DOS VEÍCULOS E MOTORISTAS
-- ============================================

-- Variáveis para armazenar IDs (serão usadas nas queries)
SET @veiculo1_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234' LIMIT 1);
SET @veiculo2_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678' LIMIT 1);
SET @veiculo3_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012' LIMIT 1);

SET @motorista1_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901' LIMIT 1);
SET @motorista2_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109' LIMIT 1);
SET @motorista3_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344' LIMIT 1);

-- ============================================
-- VALIDAÇÃO: Verificar se empresa existe e IDs foram encontrados
-- ============================================

-- Verificar se a empresa existe
SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1)
        THEN '✅ Empresa ID 1 encontrada em empresa_clientes'
        ELSE '❌ Empresa ID 1 NÃO encontrada em empresa_clientes'
    END as status_empresa;

-- Mostrar status de cada ID (para verificação visual)
SELECT 
    CASE WHEN @veiculo1_id IS NULL THEN '❌ ERRO: Veículo ABC-1234 não encontrado!' ELSE CONCAT('✅ OK: Veículo 1 (ID: ', @veiculo1_id, ')') END as veiculo1,
    CASE WHEN @veiculo2_id IS NULL THEN '❌ ERRO: Veículo XYZ-5678 não encontrado!' ELSE CONCAT('✅ OK: Veículo 2 (ID: ', @veiculo2_id, ')') END as veiculo2,
    CASE WHEN @veiculo3_id IS NULL THEN '❌ ERRO: Veículo DEF-9012 não encontrado!' ELSE CONCAT('✅ OK: Veículo 3 (ID: ', @veiculo3_id, ')') END as veiculo3,
    CASE WHEN @motorista1_id IS NULL THEN '❌ ERRO: Motorista 1 não encontrado!' ELSE CONCAT('✅ OK: Motorista 1 (ID: ', @motorista1_id, ')') END as motorista1,
    CASE WHEN @motorista2_id IS NULL THEN '❌ ERRO: Motorista 2 não encontrado!' ELSE CONCAT('✅ OK: Motorista 2 (ID: ', @motorista2_id, ')') END as motorista2,
    CASE WHEN @motorista3_id IS NULL THEN '❌ ERRO: Motorista 3 não encontrado!' ELSE CONCAT('✅ OK: Motorista 3 (ID: ', @motorista3_id, ')') END as motorista3;

-- Validação: Verificar se empresa existe e IDs foram obtidos
SELECT 
    CASE 
        WHEN NOT EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1) THEN '❌ ERRO: Empresa ID 1 não existe em empresa_clientes'
        WHEN @veiculo1_id IS NULL THEN '❌ ERRO: Veículo 1 (ABC-1234) não encontrado'
        WHEN @veiculo2_id IS NULL THEN '❌ ERRO: Veículo 2 (XYZ-5678) não encontrado'
        WHEN @veiculo3_id IS NULL THEN '❌ ERRO: Veículo 3 (DEF-9012) não encontrado'
        WHEN @motorista1_id IS NULL THEN '❌ ERRO: Motorista 1 não encontrado'
        WHEN @motorista2_id IS NULL THEN '❌ ERRO: Motorista 2 não encontrado'
        WHEN @motorista3_id IS NULL THEN '❌ ERRO: Motorista 3 não encontrado'
        ELSE '✅ VALIDAÇÃO OK - Todos os IDs foram encontrados'
    END as validacao_final;

-- Validação que causa erro se algum ID estiver NULL
-- Isso garante que o script pare antes de inserir rotas com IDs inválidos
SELECT 
    1 / (
        CASE 
            WHEN NOT EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1) THEN 0
            WHEN @veiculo1_id IS NULL THEN 0
            WHEN @veiculo2_id IS NULL THEN 0
            WHEN @veiculo3_id IS NULL THEN 0
            WHEN @motorista1_id IS NULL THEN 0
            WHEN @motorista2_id IS NULL THEN 0
            WHEN @motorista3_id IS NULL THEN 0
            ELSE 1
        END
    ) as validacao_ids;

-- Se a validação passou, continuar com os INSERTs de rotas
-- Se algum ID estiver NULL ou empresa não existir, o erro acima interromperá a execução

-- ============================================
-- 3. INSERIR ROTAS (últimos 6 meses)
-- ============================================

-- Mês atual (Mês 0) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
-- Rota 1 - Mês atual
(1, @veiculo1_id, @motorista1_id,
 'SP', NULL, 'RJ', NULL,
 DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 4 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY),
 50000, 50350, 350, 50, 400,
 8500.00, 850.00, 15000, 'Carga geral',
 12.5, 87.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês atual - 1'),

-- Rota 2 - Mês atual
(1, @veiculo2_id, @motorista2_id,
 'RJ', NULL, 'MG', NULL,
 DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY),
 30000, 30420, 420, 80, 500,
 10200.00, 1020.00, 18000, 'Eletrônicos',
 16.0, 84.0, 1,
 'aprovado', 'gestor', 'Rota exemplo mês atual - 2'),

-- Rota 3 - Mês atual
(1, @veiculo3_id, @motorista3_id,
 'MG', NULL, 'SP', NULL,
 DATE_SUB(CURDATE(), INTERVAL 1 DAY), CURDATE(), DATE_SUB(CURDATE(), INTERVAL 1 DAY),
 25000, 25280, 280, 40, 320,
 7200.00, 720.00, 12000, 'Alimentos',
 12.5, 87.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês atual - 3');

-- Mês -1 (1 mês atrás) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo1_id, @motorista1_id,
 'SP', NULL, 'PR', NULL,
 DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
 48000, 48450, 450, 60, 510,
 11250.00, 1125.00, 20000, 'Móveis',
 11.8, 88.2, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -1 - 1'),

(1, @veiculo2_id, @motorista2_id,
 'PR', NULL, 'SC', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 2 DAY),
 35000, 35380, 380, 50, 430,
 9500.00, 950.00, 16000, 'Têxteis',
 11.6, 88.4, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -1 - 2'),

(1, @veiculo3_id, @motorista1_id,
 'SC', NULL, 'RS', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 4 DAY),
 40000, 40320, 320, 45, 365,
 8000.00, 800.00, 14000, 'Químicos',
 12.3, 87.7, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -1 - 3');

-- Mês -2 (2 meses atrás) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo1_id, @motorista2_id,
 'SP', NULL, 'BA', NULL,
 DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 2 DAY), DATE_SUB(CURDATE(), INTERVAL 2 MONTH),
 45000, 46120, 1120, 150, 1270,
 28000.00, 2800.00, 25000, 'Carga pesada',
 11.8, 88.2, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -2 - 1'),

(1, @veiculo2_id, @motorista3_id,
 'BA', NULL, 'GO', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 3 DAY),
 32000, 32850, 850, 100, 950,
 21250.00, 2125.00, 22000, 'Máquinas',
 10.5, 89.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -2 - 2');

-- Mês -3 (3 meses atrás) - NEGATIVO (custos superam receitas)
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo1_id, @motorista1_id,
 'SP', NULL, 'CE', NULL,
 DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 3 MONTH),
 42000, 43800, 1800, 200, 2000,
 15000.00, 1500.00, 18000, 'Carga de baixo valor',
 10.0, 90.0, 0,
 'aprovado', 'gestor', 'Rota exemplo mês -3 - NEGATIVA - 1'),

(1, @veiculo2_id, @motorista2_id,
 'CE', NULL, 'PE', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 4 DAY),
 38000, 38450, 450, 80, 530,
 8000.00, 800.00, 12000, 'Carga pequena',
 15.1, 84.9, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -3 - NEGATIVA - 2'),

(1, @veiculo3_id, @motorista3_id,
 'PE', NULL, 'AL', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 5 DAY),
 36000, 36220, 220, 50, 270,
 4500.00, 450.00, 8000, 'Carga leve',
 18.5, 81.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -3 - NEGATIVA - 3');

-- Mês -4 (4 meses atrás) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo1_id, @motorista3_id,
 'SP', NULL, 'MS', NULL,
 DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 4 MONTH),
 41000, 41650, 650, 80, 730,
 16250.00, 1625.00, 20000, 'Grãos',
 11.0, 89.0, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -4 - 1'),

(1, @veiculo2_id, @motorista1_id,
 'MS', NULL, 'MT', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 2 DAY),
 39000, 39580, 580, 70, 650,
 14500.00, 1450.00, 18000, 'Soja',
 10.8, 89.2, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -4 - 2');

-- Mês -5 (5 meses atrás) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo1_id, @motorista2_id,
 'SP', NULL, 'ES', NULL,
 DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 5 MONTH),
 40000, 40680, 680, 90, 770,
 17000.00, 1700.00, 22000, 'Papel',
 11.7, 88.3, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -5 - 1'),

(1, @veiculo3_id, @motorista3_id,
 'ES', NULL, 'RJ', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 1 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 2 DAY),
 35000, 35250, 250, 40, 290,
 6250.00, 625.00, 10000, 'Produtos químicos',
 13.8, 86.2, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -5 - 2');

-- Mês -6 (6 meses atrás) - POSITIVO
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES
(1, @veiculo2_id, @motorista1_id,
 'SP', NULL, 'RJ', NULL,
 DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 6 MONTH),
 38000, 38350, 350, 50, 400,
 8750.00, 875.00, 15000, 'Carga geral',
 12.5, 87.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -6 - 1'),

(1, @veiculo3_id, @motorista2_id,
 'RJ', NULL, 'SP', NULL,
 DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 3 DAY),
 33000, 33280, 280, 40, 320,
 7000.00, 700.00, 12000, 'Retorno vazio',
 12.5, 87.5, 1,
 'aprovado', 'gestor', 'Rota exemplo mês -6 - 2');

-- ============================================
-- 4. INSERIR ABASTECIMENTOS (vinculados às rotas)
-- ============================================

-- Abastecimentos para rotas do mês atual
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 80.00
        WHEN distancia_km <= 800 THEN 150.00
        ELSE 250.00
    END,
    5.50,
    CASE 
        WHEN distancia_km <= 400 THEN 440.00
        WHEN distancia_km <= 800 THEN 825.00
        ELSE 1375.00
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY), INTERVAL 1 MONTH);

-- Abastecimentos para rotas do mês -1
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 85.00
        WHEN distancia_km <= 800 THEN 160.00
        ELSE 260.00
    END,
    5.45,
    CASE 
        WHEN distancia_km <= 400 THEN 463.25
        WHEN distancia_km <= 800 THEN 872.00
        ELSE 1417.00
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Abastecimentos para rotas do mês -2
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 90.00
        WHEN distancia_km <= 800 THEN 170.00
        WHEN distancia_km <= 1200 THEN 280.00
        ELSE 350.00
    END,
    5.40,
    CASE 
        WHEN distancia_km <= 400 THEN 486.00
        WHEN distancia_km <= 800 THEN 918.00
        WHEN distancia_km <= 1200 THEN 1512.00
        ELSE 1890.00
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Abastecimentos para rotas do mês -3 (NEGATIVO - valores mais altos)
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 100.00
        WHEN distancia_km <= 800 THEN 200.00
        WHEN distancia_km <= 1200 THEN 350.00
        ELSE 450.00
    END,
    6.20, -- Preço mais alto para tornar negativo
    CASE 
        WHEN distancia_km <= 400 THEN 620.00
        WHEN distancia_km <= 800 THEN 1240.00
        WHEN distancia_km <= 1200 THEN 2170.00
        ELSE 2790.00
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo - mês negativo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Abastecimentos para rotas do mês -4
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 88.00
        WHEN distancia_km <= 800 THEN 165.00
        ELSE 270.00
    END,
    5.35,
    CASE 
        WHEN distancia_km <= 400 THEN 470.80
        WHEN distancia_km <= 800 THEN 882.75
        ELSE 1444.50
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Abastecimentos para rotas do mês -5
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 87.00
        WHEN distancia_km <= 800 THEN 162.00
        ELSE 265.00
    END,
    5.30,
    CASE 
        WHEN distancia_km <= 400 THEN 461.10
        WHEN distancia_km <= 800 THEN 858.60
        ELSE 1404.50
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Abastecimentos para rotas do mês -6
INSERT INTO abastecimentos (
    empresa_id, veiculo_id, motorista_id, rota_id,
    data_abastecimento, litros, valor_litro, valor_total,
    km_atual, posto, status, fonte, observacoes
)
SELECT 
    1, veiculo_id, motorista_id, id,
    data_saida, 
    CASE 
        WHEN distancia_km <= 400 THEN 86.00
        WHEN distancia_km <= 800 THEN 160.00
        ELSE 260.00
    END,
    5.25,
    CASE 
        WHEN distancia_km <= 400 THEN 451.50
        WHEN distancia_km <= 800 THEN 840.00
        ELSE 1365.00
    END,
    km_saida, 'Posto Exemplo', 'aprovado', 'gestor', 'Abastecimento exemplo'
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- ============================================
-- 5. INSERIR DESPESAS DE VIAGEM (vinculadas às rotas)
-- ============================================

-- Despesas de viagem para rotas do mês atual
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.5, -- descarga baseada na distância
    distancia_km * 2.0, -- pedagios
    frete * 0.02, -- caixinha 2% do frete
    distancia_km * 0.3, -- estacionamento
    distancia_km * 0.2, -- lavagem
    0, -- borracharia
    0, -- elétrica/mecânica
    frete * 0.1, -- adiantamento 10% do frete
    (distancia_km * 0.5) + (distancia_km * 2.0) + (frete * 0.02) + (distancia_km * 0.3) + (distancia_km * 0.2) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY), INTERVAL 1 MONTH);

-- Despesas de viagem para rotas do mês -1
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.52,
    distancia_km * 2.1,
    frete * 0.02,
    distancia_km * 0.32,
    distancia_km * 0.22,
    0, 0,
    frete * 0.1,
    (distancia_km * 0.52) + (distancia_km * 2.1) + (frete * 0.02) + (distancia_km * 0.32) + (distancia_km * 0.22) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(CURDATE(), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Despesas de viagem para rotas do mês -2
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.55,
    distancia_km * 2.2,
    frete * 0.02,
    distancia_km * 0.35,
    distancia_km * 0.25,
    0, 0,
    frete * 0.1,
    (distancia_km * 0.55) + (distancia_km * 2.2) + (frete * 0.02) + (distancia_km * 0.35) + (distancia_km * 0.25) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Despesas de viagem para rotas do mês -3 (NEGATIVO - valores mais altos)
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 1.2, -- Valores muito mais altos
    distancia_km * 4.5,
    frete * 0.05, -- 5% ao invés de 2%
    distancia_km * 0.8,
    distancia_km * 0.6,
    500.00, -- borracharia
    800.00, -- elétrica/mecânica
    frete * 0.2, -- 20% ao invés de 10%
    (distancia_km * 1.2) + (distancia_km * 4.5) + (frete * 0.05) + (distancia_km * 0.8) + (distancia_km * 0.6) + 500.00 + 800.00 + (frete * 0.2),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Despesas de viagem para rotas do mês -4
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.48,
    distancia_km * 1.95,
    frete * 0.02,
    distancia_km * 0.28,
    distancia_km * 0.18,
    0, 0,
    frete * 0.1,
    (distancia_km * 0.48) + (distancia_km * 1.95) + (frete * 0.02) + (distancia_km * 0.28) + (distancia_km * 0.18) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Despesas de viagem para rotas do mês -5
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.47,
    distancia_km * 1.9,
    frete * 0.02,
    distancia_km * 0.27,
    distancia_km * 0.17,
    0, 0,
    frete * 0.1,
    (distancia_km * 0.47) + (distancia_km * 1.9) + (frete * 0.02) + (distancia_km * 0.27) + (distancia_km * 0.17) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- Despesas de viagem para rotas do mês -6
INSERT INTO despesas_viagem (
    empresa_id, rota_id, descarga, pedagios, caixinha, estacionamento,
    lavagem, borracharia, eletrica_mecanica, adiantamento, total_despviagem,
    status, fonte, created_at, updated_at
)
SELECT 
    1, id,
    distancia_km * 0.45,
    distancia_km * 1.85,
    frete * 0.02,
    distancia_km * 0.25,
    distancia_km * 0.15,
    0, 0,
    frete * 0.1,
    (distancia_km * 0.45) + (distancia_km * 1.85) + (frete * 0.02) + (distancia_km * 0.25) + (distancia_km * 0.15) + (frete * 0.1),
    'aprovado', 'gestor', NOW(), NOW()
FROM rotas
WHERE empresa_id = 1 
AND data_rota >= DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY)
AND data_rota < DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL DAY(CURDATE()) - 1 DAY);

-- ============================================
-- 6. INSERIR DESPESAS FIXAS (últimos 6 meses)
-- ============================================

-- Verificar se existe tipo de despesa fixa, se não, criar
INSERT INTO tipos_despesa_fixa (nome, descricao)
SELECT 'IPVA', 'Imposto sobre Propriedade de Veículos Automotores'
WHERE NOT EXISTS (SELECT 1 FROM tipos_despesa_fixa WHERE nome = 'IPVA');

INSERT INTO tipos_despesa_fixa (nome, descricao)
SELECT 'Seguro', 'Seguro do veículo'
WHERE NOT EXISTS (SELECT 1 FROM tipos_despesa_fixa WHERE nome = 'Seguro');

INSERT INTO tipos_despesa_fixa (nome, descricao)
SELECT 'Licenciamento', 'Taxa de licenciamento anual'
WHERE NOT EXISTS (SELECT 1 FROM tipos_despesa_fixa WHERE nome = 'Licenciamento');

INSERT INTO tipos_despesa_fixa (nome, descricao)
SELECT 'Manutenção Preventiva', 'Manutenção programada do veículo'
WHERE NOT EXISTS (SELECT 1 FROM tipos_despesa_fixa WHERE nome = 'Manutenção Preventiva');

-- Verificar se existe status de pagamento, se não, criar
INSERT INTO status_pagamento (nome)
SELECT 'Pago'
WHERE NOT EXISTS (SELECT 1 FROM status_pagamento WHERE nome = 'Pago');

INSERT INTO status_pagamento (nome)
SELECT 'Pendente'
WHERE NOT EXISTS (SELECT 1 FROM status_pagamento WHERE nome = 'Pendente');

-- Verificar se existe forma de pagamento, se não, criar
INSERT INTO formas_pagamento (nome)
SELECT 'Dinheiro'
WHERE NOT EXISTS (SELECT 1 FROM formas_pagamento WHERE nome = 'Dinheiro');

INSERT INTO formas_pagamento (nome)
SELECT 'Transferência'
WHERE NOT EXISTS (SELECT 1 FROM formas_pagamento WHERE nome = 'Transferência');

INSERT INTO formas_pagamento (nome)
SELECT 'Boleto'
WHERE NOT EXISTS (SELECT 1 FROM formas_pagamento WHERE nome = 'Boleto');

-- Obter IDs
SET @tipo_ipva_id = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'IPVA' LIMIT 1);
SET @tipo_seguro_id = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Seguro' LIMIT 1);
SET @tipo_licenciamento_id = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Licenciamento' LIMIT 1);
SET @tipo_manutencao_id = (SELECT id FROM tipos_despesa_fixa WHERE nome = 'Manutenção Preventiva' LIMIT 1);

SET @status_pago_id = (SELECT id FROM status_pagamento WHERE nome = 'Pago' LIMIT 1);
SET @status_pendente_id = (SELECT id FROM status_pagamento WHERE nome = 'Pendente' LIMIT 1);

SET @forma_transferencia_id = (SELECT id FROM formas_pagamento WHERE nome = 'Transferência' LIMIT 1);
SET @forma_boleto_id = (SELECT id FROM formas_pagamento WHERE nome = 'Boleto' LIMIT 1);

-- Despesas fixas - Mês atual
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 5 DAY), DATE_SUB(CURDATE(), INTERVAL 5 DAY), @status_pago_id, @forma_transferencia_id, YEAR(CURDATE()), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(CURDATE(), INTERVAL 3 DAY), DATE_SUB(CURDATE(), INTERVAL 3 DAY), @status_pago_id, @forma_transferencia_id, YEAR(CURDATE()), 'Seguro mensal veículo 2'),
(1, @veiculo3_id, @tipo_manutencao_id, 1200.00, DATE_SUB(CURDATE(), INTERVAL 1 DAY), DATE_SUB(CURDATE(), INTERVAL 1 DAY), @status_pago_id, @forma_transferencia_id, YEAR(CURDATE()), 'Manutenção preventiva veículo 3');

-- Despesas fixas - Mês -1
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 2 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 'Seguro mensal veículo 2'),
(1, @veiculo3_id, @tipo_manutencao_id, 1100.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 1 MONTH), INTERVAL 4 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)), 'Manutenção preventiva veículo 3');

-- Despesas fixas - Mês -2
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 2 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 2 MONTH), INTERVAL 3 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 2 MONTH)), 'Seguro mensal veículo 2');

-- Despesas fixas - Mês -3 (NEGATIVO - valores mais altos)
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_ipva_id, 3500.00, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_SUB(CURDATE(), INTERVAL 3 MONTH), @status_pago_id, @forma_boleto_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 'IPVA veículo 1 - mês negativo'),
(1, @veiculo2_id, @tipo_ipva_id, 3800.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 2 DAY), @status_pago_id, @forma_boleto_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 'IPVA veículo 2 - mês negativo'),
(1, @veiculo3_id, @tipo_ipva_id, 3200.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 4 DAY), @status_pago_id, @forma_boleto_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 'IPVA veículo 3 - mês negativo'),
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 5 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_manutencao_id, 2500.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 6 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 3 MONTH), INTERVAL 6 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 3 MONTH)), 'Manutenção corretiva veículo 2 - mês negativo');

-- Despesas fixas - Mês -4
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(CURDATE(), INTERVAL 4 MONTH), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 4 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 2 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 4 MONTH)), 'Seguro mensal veículo 2'),
(1, @veiculo3_id, @tipo_licenciamento_id, 450.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 4 MONTH), INTERVAL 4 DAY), @status_pago_id, @forma_boleto_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 4 MONTH)), 'Licenciamento veículo 3');

-- Despesas fixas - Mês -5
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_SUB(CURDATE(), INTERVAL 5 MONTH), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 5 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 3 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 3 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 5 MONTH)), 'Seguro mensal veículo 2'),
(1, @veiculo3_id, @tipo_manutencao_id, 1050.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 5 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), INTERVAL 5 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 5 MONTH)), 'Manutenção preventiva veículo 3');

-- Despesas fixas - Mês -6
INSERT INTO despesas_fixas (
    empresa_id, veiculo_id, tipo_despesa_id, valor, vencimento, data_pagamento,
    status_pagamento_id, forma_pagamento_id, ano_referencia, descricao
) VALUES
(1, @veiculo1_id, @tipo_seguro_id, 850.00, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_SUB(CURDATE(), INTERVAL 6 MONTH), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 6 MONTH)), 'Seguro mensal veículo 1'),
(1, @veiculo2_id, @tipo_seguro_id, 920.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 2 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 2 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 6 MONTH)), 'Seguro mensal veículo 2'),
(1, @veiculo3_id, @tipo_seguro_id, 880.00, DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 4 DAY), DATE_SUB(DATE_SUB(CURDATE(), INTERVAL 6 MONTH), INTERVAL 4 DAY), @status_pago_id, @forma_transferencia_id, YEAR(DATE_SUB(CURDATE(), INTERVAL 6 MONTH)), 'Seguro mensal veículo 3');

-- ============================================
-- FIM DO SCRIPT
-- ============================================

-- Resumo:
-- - Rotas: Distribuídas pelos últimos 6 meses
-- - Abastecimentos: Vinculados às rotas
-- - Despesas de Viagem: Vinculadas às rotas
-- - Despesas Fixas: Mensais para cada veículo
-- - Mês -3 (3 meses atrás) foi configurado para ficar NEGATIVO
--   com valores mais altos de abastecimento, despesas de viagem e despesas fixas
