-- ============================================
-- VERIFICAR POR QUE O LOGIN NÃO ESTÁ FUNCIONANDO
-- ============================================

-- 1. Ver quais empresas têm plano Premium/Enterprise
SELECT 
    ea.id as 'ID',
    ea.razao_social as 'Razão Social',
    ea.plano as 'Plano',
    ea.tem_acesso_seguro as 'Acesso Seguro',
    ea.status as 'Status Empresa'
FROM empresa_adm ea
WHERE ea.plano IN ('premium', 'enterprise')
ORDER BY ea.id;

-- 2. Ver se essas empresas foram criadas no Sistema Seguro
SELECT 
    ea.id as 'ID empresa_adm',
    ea.razao_social as 'Empresa',
    ea.plano as 'Plano',
    ea.tem_acesso_seguro as 'Acesso',
    sec.id as 'ID Seguro',
    sec.status as 'Status Seguro'
FROM empresa_adm ea
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE ea.plano IN ('premium', 'enterprise')
ORDER BY ea.id;

-- 3. Ver todos os usuários do sistema de frotas com empresas Premium
SELECT 
    u.id as 'ID Usuario',
    u.nome as 'Nome',
    u.email as 'Email',
    u.status as 'Status Usuario',
    ec.razao_social as 'Empresa',
    ea.plano as 'Plano',
    ea.tem_acesso_seguro as 'Acesso Seguro'
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
WHERE ea.plano IN ('premium', 'enterprise')
ORDER BY u.email;

-- 4. Ver usuários do Sistema Seguro
SELECT 
    su.id as 'ID',
    su.nome as 'Nome',
    su.email as 'Email',
    su.nivel_acesso as 'Nível',
    su.status as 'Status',
    sec.razao_social as 'Empresa',
    sec.status as 'Status Empresa'
FROM seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
ORDER BY su.email;

-- 5. TESTE ESPECÍFICO - Substitua 'SEU_EMAIL_AQUI' pelo email que você está tentando logar
SET @email_teste = 'SEU_EMAIL_AQUI@exemplo.com';

-- Buscar em seguro_usuarios
SELECT 
    'SEGURO_USUARIOS' as 'TABELA',
    su.id,
    su.email,
    su.nome,
    su.status as usuario_status,
    sec.razao_social,
    sec.status as empresa_status,
    ea.plano,
    ea.tem_acesso_seguro
FROM seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
INNER JOIN empresa_adm ea ON sec.empresa_adm_id = ea.id
WHERE su.email = @email_teste

UNION ALL

-- Buscar em usuarios (frotas)
SELECT 
    'USUARIOS (FROTAS)' as 'TABELA',
    u.id,
    u.email,
    u.nome,
    u.status as usuario_status,
    ec.razao_social,
    ec.status as empresa_status,
    ea.plano,
    ea.tem_acesso_seguro
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
WHERE u.email = @email_teste;

-- ============================================
-- INSTRUÇÕES
-- ============================================
-- 1. Execute as queries acima uma por uma
-- 2. Na query 5, substitua 'SEU_EMAIL_AQUI@exemplo.com' pelo seu email real
-- 3. Veja onde está o problema:
--    - Se não aparece nada = usuário não existe
--    - Se aparece mas tem_acesso_seguro = 'nao' = precisa atualizar empresa
--    - Se aparece mas plano != premium/enterprise = precisa mudar plano
--    - Se aparece mas status = 'inativo' = precisa ativar
-- ============================================

