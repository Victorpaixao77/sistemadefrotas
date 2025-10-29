-- ============================================
-- CORRIGIR SENHA DO USUÁRIO victor@rbxsoft.com
-- ============================================

-- Atualizar senha em seguro_usuarios para usar a mesma do sistema frotas
UPDATE seguro_usuarios su
INNER JOIN usuarios u ON u.email = su.email
SET su.senha = u.senha
WHERE su.email = 'victor@rbxsoft.com';

-- Verificar se atualizou
SELECT 
    '✅ SENHA CORRIGIDA!' as 'RESULTADO',
    su.email as 'Email',
    'Use a mesma senha do sistema frotas' as 'Senha',
    CASE 
        WHEN su.senha = u.senha THEN '✅ SENHAS IGUAIS'
        ELSE '❌ AINDA DIFERENTES'
    END as 'Status'
FROM seguro_usuarios su
LEFT JOIN usuarios u ON u.email = su.email
WHERE su.email = 'victor@rbxsoft.com';

-- ============================================
-- PRONTO! AGORA TESTE O LOGIN
-- ============================================
-- Acesse: http://localhost/sistema-frotas/SISTEMA-SEGURO/login.php
-- Email: victor@rbxsoft.com
-- Senha: (sua senha do sistema frotas)
-- ============================================

