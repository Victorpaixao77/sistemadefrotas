-- ============================================
-- SINCRONIZAR SENHAS DO SISTEMA FROTAS
-- ============================================
-- Este script atualiza as senhas dos usuários
-- do Sistema Seguro para usar as mesmas senhas
-- dos usuários do Sistema de Frotas
-- ============================================

-- OPÇÃO 1: Atualizar senhas em seguro_usuarios com as senhas de usuarios (frotas)
-- ─────────────────────────────────────────────────────────────────────────────

UPDATE seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
INNER JOIN usuarios u ON u.email = su.email
SET su.senha = u.senha
WHERE u.status = 'ativo';

SELECT 
    '✅ SENHAS SINCRONIZADAS' as 'RESULTADO',
    COUNT(*) as 'Total Atualizado'
FROM seguro_usuarios su
INNER JOIN usuarios u ON u.email = su.email;

-- ============================================
-- OU OPÇÃO 2: Deletar usuários de seguro_usuarios
-- ============================================
-- Se preferir que o sistema sempre busque em usuarios (frotas),
-- descomente as linhas abaixo:

-- DELETE FROM seguro_usuarios;
-- 
-- SELECT '✅ USUÁRIOS DELETADOS - Sistema usará apenas usuarios (frotas)' as 'RESULTADO';

-- ============================================
-- VERIFICAÇÃO FINAL
-- ============================================

-- Ver usuários e suas senhas (hash)
SELECT 
    'VERIFICAÇÃO' as 'TIPO',
    su.email as 'Email Sistema Seguro',
    SUBSTRING(su.senha, 1, 20) as 'Hash Seguro',
    u.email as 'Email Frotas',
    SUBSTRING(u.senha, 1, 20) as 'Hash Frotas',
    CASE 
        WHEN su.senha = u.senha THEN '✅ IGUAIS'
        ELSE '❌ DIFERENTES'
    END as 'Status'
FROM seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
LEFT JOIN usuarios u ON u.email = su.email;

-- ============================================
-- TESTE ESPECÍFICO PARA SEU USUÁRIO
-- ============================================
-- Substitua 'victor@rbxsoft.com' pelo seu email se for diferente

SELECT 
    'TESTE ESPECÍFICO' as 'TIPO',
    su.id as 'ID Seguro',
    su.email as 'Email',
    su.nome as 'Nome',
    SUBSTRING(su.senha, 1, 30) as 'Hash Seguro',
    u.id as 'ID Frotas',
    SUBSTRING(u.senha, 1, 30) as 'Hash Frotas',
    CASE 
        WHEN su.senha = u.senha THEN '✅ SENHAS IGUAIS - PODE LOGAR'
        WHEN u.id IS NULL THEN '⚠️ Não existe em usuarios (frotas)'
        ELSE '❌ SENHAS DIFERENTES - Execute OPÇÃO 1'
    END as 'STATUS'
FROM seguro_usuarios su
LEFT JOIN usuarios u ON u.email = su.email
WHERE su.email = 'victor@rbxsoft.com';

-- ============================================
-- INSTRUÇÕES
-- ============================================
-- 
-- APÓS EXECUTAR ESTE SCRIPT:
-- 
-- 1. Se escolheu OPÇÃO 1:
--    → As senhas foram sincronizadas
--    → Use a mesma senha do sistema frotas
--
-- 2. Se escolheu OPÇÃO 2:
--    → Deletou usuários de seguro_usuarios
--    → Sistema buscará direto em usuarios (frotas)
--    → Use email e senha do sistema frotas
--
-- 3. Teste novamente em:
--    http://localhost/sistema-frotas/SISTEMA-SEGURO/login.php
--
-- Email: victor@rbxsoft.com (ou seu email)
-- Senha: (sua senha do sistema frotas)
-- 
-- ============================================

