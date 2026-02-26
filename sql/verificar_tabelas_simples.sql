-- ============================================
-- VERIFICAÇÃO SIMPLES DE TABELAS
-- ============================================
-- Este script verifica diretamente se as tabelas existem
-- ============================================

-- 1. Mostrar qual banco está sendo usado
SELECT DATABASE() as banco_atual;

-- 2. Listar TODAS as tabelas do banco atual
-- Execute: SHOW TABLES;

-- 3. Verificar cada tabela diretamente
SELECT 'empresas' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'empresas' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'veiculos' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'veiculos' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'motoristas' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'motoristas' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'rotas' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'rotas' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'abastecimentos' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'abastecimentos' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'despesas_viagem' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'despesas_viagem' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1

UNION ALL

SELECT 'despesas_fixas' as tabela, 
       CASE WHEN COUNT(*) > 0 THEN '✅ EXISTE' ELSE '❌ NÃO EXISTE' END as status
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_NAME = 'despesas_fixas' 
AND TABLE_SCHEMA IN (DATABASE(), 'sistema_frotas', SCHEMA())
LIMIT 1;

-- 4. Tentar acessar diretamente as tabelas (se existirem, mostrará estrutura)
-- Descomente as linhas abaixo para testar:

-- DESCRIBE empresas;
-- DESCRIBE veiculos;
-- DESCRIBE motoristas;
-- DESCRIBE rotas;
