-- ============================================
-- SCRIPT DE DIAGNÓSTICO PARA INSERÇÃO DE ROTAS
-- ============================================
-- Este script verifica o banco de dados e identifica
-- possíveis problemas antes de inserir rotas
-- ============================================

-- 1. VERIFICAR SE A EMPRESA EXISTE
-- ============================================
SELECT '=== VERIFICAÇÃO DA EMPRESA ===' as secao;

-- Verificar se a tabela empresa_clientes existe
SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_clientes')
        THEN '✅ Tabela empresa_clientes existe'
        ELSE '❌ Tabela empresa_clientes NÃO existe - CRIE A TABELA PRIMEIRO!'
    END as status_tabela;

-- Verificar se a empresa ID 1 existe (só se a tabela existir)
SELECT 
    CASE 
        WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_clientes')
        THEN '❌ Tabela empresa_clientes não existe - não é possível verificar empresa ID 1'
        WHEN EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1)
        THEN '✅ Empresa ID 1 encontrada'
        ELSE '❌ Empresa ID 1 NÃO encontrada - CRIE A EMPRESA PRIMEIRO!'
    END as status_empresa;

-- Mostrar dados da empresa se a tabela existir
SELECT 
    id, 
    razao_social as nome,
    nome_fantasia,
    cnpj,
    'Empresa encontrada' as status
FROM empresa_clientes 
WHERE id = 1
AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_clientes');

-- 2. VERIFICAR VEÍCULOS
-- ============================================
SELECT '=== VERIFICAÇÃO DOS VEÍCULOS ===' as secao;
SELECT 
    id,
    placa,
    modelo,
    marca,
    empresa_id,
    status_id,
    CASE 
        WHEN empresa_id = 1 AND placa = 'ABC-1234' THEN '✅ Veículo 1 encontrado'
        WHEN empresa_id = 1 AND placa = 'XYZ-5678' THEN '✅ Veículo 2 encontrado'
        WHEN empresa_id = 1 AND placa = 'DEF-9012' THEN '✅ Veículo 3 encontrado'
        ELSE '⚠️ Veículo diferente'
    END as status
FROM veiculos 
WHERE empresa_id = 1 
AND placa IN ('ABC-1234', 'XYZ-5678', 'DEF-9012')
ORDER BY placa;

-- Verificar se algum veículo está faltando
SELECT 
    '❌ Veículo ABC-1234 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234')
UNION ALL
SELECT 
    '❌ Veículo XYZ-5678 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678')
UNION ALL
SELECT 
    '❌ Veículo DEF-9012 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012');

-- 3. VERIFICAR MOTORISTAS
-- ============================================
SELECT '=== VERIFICAÇÃO DOS MOTORISTAS ===' as secao;
SELECT 
    id,
    nome,
    cpf,
    empresa_id,
    CASE 
        WHEN empresa_id = 1 AND cpf = '12345678901' THEN '✅ Motorista 1 encontrado'
        WHEN empresa_id = 1 AND cpf = '98765432109' THEN '✅ Motorista 2 encontrado'
        WHEN empresa_id = 1 AND cpf = '11122233344' THEN '✅ Motorista 3 encontrado'
        ELSE '⚠️ Motorista diferente'
    END as status
FROM motoristas 
WHERE empresa_id = 1 
AND cpf IN ('12345678901', '98765432109', '11122233344')
ORDER BY cpf;

-- Verificar se algum motorista está faltando
SELECT 
    '❌ Motorista 12345678901 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901')
UNION ALL
SELECT 
    '❌ Motorista 98765432109 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109')
UNION ALL
SELECT 
    '❌ Motorista 11122233344 NÃO encontrado' as erro
WHERE NOT EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344');

-- 4. OBTER OS IDs (SIMULANDO O QUE O SCRIPT FAZ)
-- ============================================
SELECT '=== OBTENDO IDs DAS VARIÁVEIS ===' as secao;
SET @veiculo1_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234' LIMIT 1);
SET @veiculo2_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678' LIMIT 1);
SET @veiculo3_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012' LIMIT 1);

SET @motorista1_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901' LIMIT 1);
SET @motorista2_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109' LIMIT 1);
SET @motorista3_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344' LIMIT 1);

SELECT 
    @veiculo1_id as veiculo1_id,
    @veiculo2_id as veiculo2_id,
    @veiculo3_id as veiculo3_id,
    @motorista1_id as motorista1_id,
    @motorista2_id as motorista2_id,
    @motorista3_id as motorista3_id,
    CASE 
        WHEN @veiculo1_id IS NULL THEN '❌ Veículo 1 NULL'
        WHEN @veiculo2_id IS NULL THEN '❌ Veículo 2 NULL'
        WHEN @veiculo3_id IS NULL THEN '❌ Veículo 3 NULL'
        WHEN @motorista1_id IS NULL THEN '❌ Motorista 1 NULL'
        WHEN @motorista2_id IS NULL THEN '❌ Motorista 2 NULL'
        WHEN @motorista3_id IS NULL THEN '❌ Motorista 3 NULL'
        ELSE '✅ Todos os IDs foram obtidos com sucesso'
    END as status_validacao;

-- 5. VERIFICAR ESTRUTURA DA TABELA ROTAS
-- ============================================
SELECT '=== ESTRUTURA DA TABELA ROTAS ===' as secao;
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_KEY,
    EXTRA
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'rotas'
ORDER BY ORDINAL_POSITION;

-- 6. VERIFICAR CONSTRAINTS E FOREIGN KEYS
-- ============================================
SELECT '=== CONSTRAINTS E FOREIGN KEYS ===' as secao;
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'rotas';

-- 7. TESTAR INSERT COM DADOS REAIS (SEM COMMIT)
-- ============================================
SELECT '=== TESTE DE INSERT (SIMULAÇÃO) ===' as secao;

-- Verificar se os valores das variáveis são válidos
SELECT 
    'Teste de valores' as teste,
    CASE 
        WHEN @veiculo1_id IS NULL THEN 'ERRO: @veiculo1_id é NULL'
        WHEN @veiculo1_id NOT IN (SELECT id FROM veiculos WHERE empresa_id = 1) THEN 'ERRO: @veiculo1_id não existe na tabela veiculos'
        ELSE CONCAT('OK: @veiculo1_id = ', @veiculo1_id, ' existe')
    END as veiculo1,
    CASE 
        WHEN @motorista1_id IS NULL THEN 'ERRO: @motorista1_id é NULL'
        WHEN @motorista1_id NOT IN (SELECT id FROM motoristas WHERE empresa_id = 1) THEN 'ERRO: @motorista1_id não existe na tabela motoristas'
        ELSE CONCAT('OK: @motorista1_id = ', @motorista1_id, ' existe')
    END as motorista1;

-- 8. VERIFICAR CAMPOS OBRIGATÓRIOS
-- ============================================
SELECT '=== CAMPOS OBRIGATÓRIOS DA TABELA ROTAS ===' as secao;
SELECT 
    COLUMN_NAME,
    IS_NULLABLE,
    DATA_TYPE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'rotas'
AND IS_NULLABLE = 'NO'
AND COLUMN_DEFAULT IS NULL
ORDER BY COLUMN_NAME;

-- 9. TESTAR INSERT COM UM REGISTRO DE EXEMPLO (ROLLBACK)
-- ============================================
SELECT '=== TESTE DE INSERT REAL (COM ROLLBACK) ===' as secao;

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
    COALESCE(@veiculo1_id, 0), 
    COALESCE(@motorista1_id, 0),
    'SP', NULL, 'PR', NULL,
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH - INTERVAL 1 DAY), 
    DATE_SUB(CURDATE(), INTERVAL 1 MONTH),
    48000, 48450, 450, 60, 510,
    11250.00, 1125.00, 20000, 'Móveis',
    11.8, 88.2, 1,
    'aprovado', 'sistema', 'Rota exemplo mês -1 - 1 - TESTE'
);

-- Se chegou aqui, o INSERT funcionou
SELECT '✅ INSERT FUNCIONOU! Nenhum erro encontrado.' as resultado;

-- Desfazer o INSERT de teste
ROLLBACK;

-- 10. RESUMO FINAL
-- ============================================
SELECT '=== RESUMO FINAL ===' as secao;
SELECT 
    CASE 
        WHEN @veiculo1_id IS NOT NULL 
         AND @veiculo2_id IS NOT NULL 
         AND @veiculo3_id IS NOT NULL
         AND @motorista1_id IS NOT NULL 
         AND @motorista2_id IS NOT NULL 
         AND @motorista3_id IS NOT NULL
        THEN '✅ TUDO OK - Pode executar o script de inserção de rotas'
        ELSE '❌ ERRO ENCONTRADO - Verifique os erros acima antes de continuar'
    END as status_final,
    CONCAT(
        'Veículos: ', 
        CASE WHEN @veiculo1_id IS NOT NULL THEN '1✅' ELSE '1❌' END, ' ',
        CASE WHEN @veiculo2_id IS NOT NULL THEN '2✅' ELSE '2❌' END, ' ',
        CASE WHEN @veiculo3_id IS NOT NULL THEN '3✅' ELSE '3❌' END,
        ' | Motoristas: ',
        CASE WHEN @motorista1_id IS NOT NULL THEN '1✅' ELSE '1❌' END, ' ',
        CASE WHEN @motorista2_id IS NOT NULL THEN '2✅' ELSE '2❌' END, ' ',
        CASE WHEN @motorista3_id IS NOT NULL THEN '3✅' ELSE '3❌' END
    ) as detalhes;
