-- ============================================
-- VERIFICAÇÃO DE TABELAS DO SISTEMA
-- ============================================
-- Este script verifica quais tabelas existem
-- e quais estão faltando no banco de dados
-- ============================================

-- Mostrar qual banco de dados está sendo usado
SELECT CONCAT('=== Banco de dados atual: ', DATABASE(), ' ===') as info;

-- Verificar se o banco de dados está selecionado
SELECT 
    CASE 
        WHEN DATABASE() IS NULL THEN '⚠️ NENHUM BANCO SELECIONADO - Use: USE nome_do_banco;'
        ELSE CONCAT('✅ Banco selecionado: ', DATABASE())
    END as status_banco;

SELECT '=== TABELAS NECESSÁRIAS PARA O SISTEMA ===' as secao;

-- Lista de tabelas necessárias (usando o banco atual ou sistema_frotas)
SELECT 
    'empresas' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'empresas')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'veiculos' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'veiculos')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'motoristas' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'motoristas')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'rotas' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'rotas')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'abastecimentos' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'abastecimentos')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'despesas_viagem' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'despesas_viagem')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
UNION ALL
SELECT 
    'despesas_fixas' as tabela,
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'despesas_fixas')
        THEN '✅ EXISTE'
        ELSE '❌ NÃO EXISTE'
    END as status
ORDER BY tabela;

-- Resumo
SELECT '=== RESUMO ===' as secao;

SELECT 
    CASE 
        WHEN EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'empresa_clientes')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'veiculos')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'motoristas')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'rotas')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'abastecimentos')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'despesas_viagem')
         AND EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                     WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas') 
                     AND TABLE_NAME = 'despesas_fixas')
        THEN '✅ TODAS AS TABELAS NECESSÁRIAS EXISTEM'
        ELSE '❌ ALGUMAS TABELAS ESTÃO FALTANDO - Execute o script de criação do banco de dados primeiro!'
    END as status;

-- Listar todas as tabelas que existem no banco atual
SELECT '=== TODAS AS TABELAS DO BANCO ATUAL ===' as secao;

SELECT 
    TABLE_NAME as tabela,
    TABLE_ROWS as registros,
    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) as tamanho_mb,
    TABLE_SCHEMA as banco
FROM INFORMATION_SCHEMA.TABLES
WHERE (TABLE_SCHEMA = DATABASE() OR TABLE_SCHEMA = 'sistema_frotas')
AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_SCHEMA, TABLE_NAME;

-- Verificar diretamente se as tabelas existem usando SHOW TABLES
SELECT '=== VERIFICAÇÃO DIRETA (SHOW TABLES) ===' as secao;

-- Esta query lista todas as tabelas do banco atual
-- Execute separadamente: SHOW TABLES;
