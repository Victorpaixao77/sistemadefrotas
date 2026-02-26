-- ============================================
-- TESTE DE INSERT DE ROTA
-- ============================================
-- Este script testa se o INSERT de rotas funciona
-- ============================================

-- 1. Verificar se o banco está selecionado
SELECT DATABASE() as banco_atual;

-- 2. Obter IDs (se as tabelas existirem)
SET @veiculo1_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234' LIMIT 1);
SET @veiculo2_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678' LIMIT 1);
SET @veiculo3_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012' LIMIT 1);

SET @motorista1_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901' LIMIT 1);
SET @motorista2_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109' LIMIT 1);
SET @motorista3_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344' LIMIT 1);

-- 3. Mostrar os IDs obtidos
SELECT 
    @veiculo1_id as veiculo1_id,
    @veiculo2_id as veiculo2_id,
    @veiculo3_id as veiculo3_id,
    @motorista1_id as motorista1_id,
    @motorista2_id as motorista2_id,
    @motorista3_id as motorista3_id;

-- 4. Testar INSERT com ROLLBACK
START TRANSACTION;

-- Tentar inserir uma rota de teste
INSERT INTO rotas (
    empresa_id, veiculo_id, motorista_id,
    estado_origem, cidade_origem_id, estado_destino, cidade_destino_id,
    data_saida, data_chegada, data_rota,
    km_saida, km_chegada, distancia_km, km_vazio, total_km,
    frete, comissao, peso_carga, descricao_carga,
    percentual_vazio, eficiencia_viagem, no_prazo,
    status, fonte, observacoes
) VALUES (
    1, 
    COALESCE(@veiculo1_id, 1), 
    COALESCE(@motorista1_id, 1),
    'SP', NULL, 'PR', NULL,
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH - INTERVAL 1 DAY), 
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
    48000, 48450, 450, 60, 510,
    11250.00, 1125.00, 20000, 'Móveis',
    11.8, 88.2, 1,
    'aprovado', 'gestor', 'Rota exemplo mês -1 - 1 - TESTE'
);

-- Se chegou aqui, o INSERT funcionou!
SELECT '✅ INSERT FUNCIONOU! A rota foi inserida com sucesso.' as resultado;

-- Desfazer o INSERT de teste
ROLLBACK;

SELECT '✅ ROLLBACK executado - Nenhum dado foi inserido permanentemente.' as resultado;
