-- ============================================
-- DIAGNÓSTICO RÁPIDO - Verificação Essencial
-- ============================================
-- Execute este script para verificar rapidamente
-- se tudo está pronto para inserir rotas
-- ============================================

-- 1. EMPRESA
SELECT '1. EMPRESA_CLIENTES' as item,
       CASE 
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_clientes')
            THEN '❌ TABELA NÃO EXISTE - Crie a tabela empresa_clientes primeiro!'
            WHEN EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1) 
            THEN '✅ OK' 
            ELSE '❌ FALTANDO - Crie a empresa ID 1 primeiro!' 
       END as status;

-- 2. VEÍCULOS
SELECT '2. VEÍCULOS' as item,
       CASE 
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos')
            THEN '❌ TABELA NÃO EXISTE - Crie a tabela veiculos primeiro!'
            ELSE CONCAT(
                CASE WHEN EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234') THEN '✅' ELSE '❌' END, ' ABC-1234 | ',
                CASE WHEN EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678') THEN '✅' ELSE '❌' END, ' XYZ-5678 | ',
                CASE WHEN EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012') THEN '✅' ELSE '❌' END, ' DEF-9012'
            )
       END as status;

-- 3. MOTORISTAS
SELECT '3. MOTORISTAS' as item,
       CASE 
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'motoristas')
            THEN '❌ TABELA NÃO EXISTE - Crie a tabela motoristas primeiro!'
            ELSE CONCAT(
                CASE WHEN EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901') THEN '✅' ELSE '❌' END, ' João Silva | ',
                CASE WHEN EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109') THEN '✅' ELSE '❌' END, ' Maria Santos | ',
                CASE WHEN EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344') THEN '✅' ELSE '❌' END, ' Pedro Oliveira'
            )
       END as status;

-- 4. OBTER IDs (só se as tabelas existirem)
SET @veiculo1_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234' LIMIT 1);
SET @veiculo2_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678' LIMIT 1);
SET @veiculo3_id = (SELECT id FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012' LIMIT 1);
SET @motorista1_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901' LIMIT 1);
SET @motorista2_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109' LIMIT 1);
SET @motorista3_id = (SELECT id FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344' LIMIT 1);

-- 5. VALIDAÇÃO DOS IDs
SELECT '4. VALIDAÇÃO DOS IDs' as item,
       CASE 
           WHEN @veiculo1_id IS NOT NULL 
            AND @veiculo2_id IS NOT NULL 
            AND @veiculo3_id IS NOT NULL
            AND @motorista1_id IS NOT NULL 
            AND @motorista2_id IS NOT NULL 
            AND @motorista3_id IS NOT NULL
           THEN '✅ TODOS OS IDs FORAM OBTIDOS'
           ELSE CONCAT(
               '❌ IDs FALTANDO: ',
               IF(@veiculo1_id IS NULL, 'Veículo1 ', ''),
               IF(@veiculo2_id IS NULL, 'Veículo2 ', ''),
               IF(@veiculo3_id IS NULL, 'Veículo3 ', ''),
               IF(@motorista1_id IS NULL, 'Motorista1 ', ''),
               IF(@motorista2_id IS NULL, 'Motorista2 ', ''),
               IF(@motorista3_id IS NULL, 'Motorista3 ', '')
           )
       END as status;

-- 6. MOSTRAR VALORES DOS IDs
SELECT '5. VALORES DOS IDs' as item,
       CONCAT(
           'V1:', COALESCE(@veiculo1_id, 'NULL'), ' | ',
           'V2:', COALESCE(@veiculo2_id, 'NULL'), ' | ',
           'V3:', COALESCE(@veiculo3_id, 'NULL'), ' | ',
           'M1:', COALESCE(@motorista1_id, 'NULL'), ' | ',
           'M2:', COALESCE(@motorista2_id, 'NULL'), ' | ',
           'M3:', COALESCE(@motorista3_id, 'NULL')
       ) as valores;

-- 7. RESUMO FINAL
SELECT '=== RESUMO FINAL ===' as item,
       CASE 
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresa_clientes')
            THEN '❌ TABELA empresa_clientes NÃO EXISTE - Crie as tabelas do sistema primeiro!'
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'veiculos')
            THEN '❌ TABELA veiculos NÃO EXISTE - Crie as tabelas do sistema primeiro!'
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'motoristas')
            THEN '❌ TABELA motoristas NÃO EXISTE - Crie as tabelas do sistema primeiro!'
            WHEN NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rotas')
            THEN '❌ TABELA rotas NÃO EXISTE - Crie as tabelas do sistema primeiro!'
            WHEN EXISTS (SELECT 1 FROM empresa_clientes WHERE id = 1)
             AND EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'ABC-1234')
             AND EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'XYZ-5678')
             AND EXISTS (SELECT 1 FROM veiculos WHERE empresa_id = 1 AND placa = 'DEF-9012')
             AND EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '12345678901')
             AND EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '98765432109')
             AND EXISTS (SELECT 1 FROM motoristas WHERE empresa_id = 1 AND cpf = '11122233344')
            THEN '✅ TUDO OK - Pode executar o script dados_exemplo_empresa_1.sql'
            ELSE '❌ FALTAM DADOS - Execute primeiro a seção 1 do script dados_exemplo_empresa_1.sql'
       END as status;
