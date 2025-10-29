-- ============================================
-- DIAGNÓSTICO E CORREÇÃO COMPLETA
-- ============================================
-- Execute TODO este arquivo de uma vez no phpMyAdmin
-- ============================================

-- ========================================
-- PARTE 1: VERIFICAR TABELAS EXISTEM
-- ========================================
SELECT 
    '1. VERIFICANDO TABELAS...' as 'ETAPA',
    CASE 
        WHEN COUNT(*) = 8 THEN '✅ TODAS AS TABELAS EXISTEM'
        ELSE '❌ FALTAM TABELAS - Execute seguro_database.sql'
    END as 'RESULTADO'
FROM information_schema.tables 
WHERE table_schema = 'sistema_frotas' 
AND table_name IN (
    'seguro_empresa_clientes',
    'seguro_usuarios',
    'seguro_clientes',
    'seguro_atendimentos',
    'seguro_financeiro',
    'seguro_equipamentos',
    'seguro_logs',
    'seguro_configuracoes'
);

-- ========================================
-- PARTE 2: VERIFICAR CAMPO tem_acesso_seguro
-- ========================================
SELECT 
    '2. VERIFICANDO CAMPO tem_acesso_seguro...' as 'ETAPA',
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ CAMPO EXISTE'
        ELSE '❌ CAMPO NÃO EXISTE - Execute adicionar_campo_empresa.sql'
    END as 'RESULTADO'
FROM information_schema.columns 
WHERE table_schema = 'sistema_frotas' 
AND table_name = 'empresa_adm' 
AND column_name = 'tem_acesso_seguro';

-- ========================================
-- PARTE 3: VERIFICAR EMPRESAS PREMIUM
-- ========================================
SELECT 
    '3. EMPRESAS PREMIUM/ENTERPRISE' as 'ETAPA',
    COUNT(*) as 'Total',
    GROUP_CONCAT(razao_social SEPARATOR ', ') as 'Empresas'
FROM empresa_adm 
WHERE plano IN ('premium', 'enterprise');

-- ========================================
-- PARTE 4: CORRIGIR - Atualizar tem_acesso_seguro
-- ========================================
UPDATE empresa_adm 
SET tem_acesso_seguro = 'sim' 
WHERE plano IN ('premium', 'enterprise');

SELECT 
    '4. ACESSO ATUALIZADO' as 'ETAPA',
    COUNT(*) as 'Empresas com Acesso',
    GROUP_CONCAT(razao_social SEPARATOR ', ') as 'Empresas'
FROM empresa_adm 
WHERE tem_acesso_seguro = 'sim';

-- ========================================
-- PARTE 5: CORRIGIR - Criar empresas no Sistema Seguro
-- ========================================
INSERT IGNORE INTO seguro_empresa_clientes 
(empresa_adm_id, razao_social, nome_fantasia, cnpj, email, telefone, porcentagem_fixa, unidade, plano, status)
SELECT 
    ea.id,
    ea.razao_social,
    ea.razao_social,
    ea.cnpj,
    ea.email,
    ea.telefone,
    5.00,
    'Matriz',
    ea.plano,
    'ativo'
FROM empresa_adm ea
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE ea.plano IN ('premium', 'enterprise')
AND ea.tem_acesso_seguro = 'sim'
AND sec.id IS NULL;

SELECT 
    '5. EMPRESAS CRIADAS NO SISTEMA SEGURO' as 'ETAPA',
    COUNT(*) as 'Total',
    GROUP_CONCAT(razao_social SEPARATOR ', ') as 'Empresas'
FROM seguro_empresa_clientes;

-- ========================================
-- PARTE 6: VER USUÁRIOS DO SISTEMA FROTAS
-- ========================================
SELECT 
    '6. USUÁRIOS QUE PODEM LOGAR' as 'ETAPA';

SELECT 
    u.id as 'ID',
    u.email as '📧 EMAIL (use este)',
    u.nome as 'Nome',
    u.status as 'Status',
    ec.razao_social as 'Empresa',
    ea.plano as 'Plano',
    ea.tem_acesso_seguro as 'Acesso',
    CASE 
        WHEN sec.id IS NOT NULL THEN '✅ PODE LOGAR'
        ELSE '❌ NÃO PODE'
    END as 'Sistema Seguro'
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE ea.plano IN ('premium', 'enterprise')
AND u.status = 'ativo'
ORDER BY 
    CASE WHEN sec.id IS NOT NULL THEN 0 ELSE 1 END,
    u.email;

-- ========================================
-- PARTE 7: TESTE DE LOGIN ESPECÍFICO
-- ========================================
-- Pegue um email da tabela acima e teste aqui

SET @email_teste = (
    SELECT u.email 
    FROM usuarios u
    INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
    INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
    INNER JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
    WHERE u.status = 'ativo'
    AND ea.tem_acesso_seguro = 'sim'
    ORDER BY u.id
    LIMIT 1
);

SELECT 
    '7. TESTE DE LOGIN' as 'ETAPA',
    @email_teste as '📧 USE ESTE EMAIL',
    '(sua senha do sistema frotas)' as '🔑 SENHA',
    'http://localhost/sistema-frotas/SISTEMA-SEGURO/login.php' as '🌐 ACESSE AQUI';

-- Verificar dados completos deste usuário
SELECT 
    'VERIFICAÇÃO COMPLETA DO USUÁRIO' as 'INFO';
    
SELECT 
    u.id,
    u.email,
    u.nome,
    u.status as 'user_status',
    ec.razao_social as 'empresa',
    ea.plano,
    ea.tem_acesso_seguro,
    sec.id as 'seguro_empresa_id',
    sec.status as 'empresa_seguro_status'
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE u.email = @email_teste;

-- ========================================
-- PARTE 8: RESUMO FINAL
-- ========================================
SELECT 
    '8. RESUMO FINAL' as 'ETAPA';

SELECT 
    'Total de Empresas Premium' as 'Item',
    COUNT(*) as 'Quantidade'
FROM empresa_adm 
WHERE plano IN ('premium', 'enterprise')

UNION ALL

SELECT 
    'Empresas com Acesso Seguro',
    COUNT(*)
FROM empresa_adm 
WHERE tem_acesso_seguro = 'sim'

UNION ALL

SELECT 
    'Empresas no Sistema Seguro',
    COUNT(*)
FROM seguro_empresa_clientes
WHERE status = 'ativo'

UNION ALL

SELECT 
    'Usuários do Frotas (Premium)',
    COUNT(*)
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
WHERE ea.plano IN ('premium', 'enterprise')
AND u.status = 'ativo'

UNION ALL

SELECT 
    'Usuários que PODEM logar',
    COUNT(*)
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
INNER JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE u.status = 'ativo'
AND ea.tem_acesso_seguro = 'sim';

-- ============================================
-- PRONTO! AGORA TESTE O LOGIN:
-- ============================================
-- 1. Use o email mostrado na PARTE 7
-- 2. Use a MESMA SENHA do sistema de frotas
-- 3. Acesse: http://localhost/sistema-frotas/SISTEMA-SEGURO/login.php
-- ============================================

