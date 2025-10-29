-- ============================================
-- SCRIPT PARA CORRIGIR EMPRESAS EXISTENTES
-- ============================================
-- Este script atualiza empresas Premium/Enterprise
-- que ainda não têm acesso ao Sistema Seguro
-- ============================================

-- 1. ATUALIZAR campo tem_acesso_seguro para empresas Premium/Enterprise
UPDATE empresa_adm 
SET tem_acesso_seguro = 'sim' 
WHERE (plano = 'premium' OR plano = 'enterprise') 
AND (tem_acesso_seguro IS NULL OR tem_acesso_seguro = 'nao');

-- Verificar quantas foram atualizadas
SELECT 
    COUNT(*) as 'Empresas Atualizadas',
    'tem_acesso_seguro = sim' as 'Status'
FROM empresa_adm 
WHERE (plano = 'premium' OR plano = 'enterprise') 
AND tem_acesso_seguro = 'sim';

-- 2. CRIAR registros em seguro_empresa_clientes para empresas Premium/Enterprise
--    que ainda não têm registro no Sistema Seguro

INSERT INTO seguro_empresa_clientes 
(empresa_adm_id, razao_social, nome_fantasia, cnpj, email, telefone, porcentagem_fixa, unidade, plano, status)
SELECT 
    ea.id,
    ea.razao_social,
    ea.razao_social, -- usar razao_social como nome_fantasia
    ea.cnpj,
    ea.email,
    ea.telefone,
    5.00, -- porcentagem fixa padrão
    'Matriz', -- unidade padrão
    ea.plano,
    'ativo'
FROM empresa_adm ea
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE (ea.plano = 'premium' OR ea.plano = 'enterprise')
AND ea.tem_acesso_seguro = 'sim'
AND sec.id IS NULL; -- Não existe registro no Sistema Seguro

-- 3. CRIAR usuários admin para empresas que foram adicionadas ao Sistema Seguro

INSERT INTO seguro_usuarios 
(seguro_empresa_id, nome, email, senha, nivel_acesso, status)
SELECT 
    sec.id,
    SUBSTRING_INDEX(sec.email, '@', 1) as nome, -- usar parte do email como nome
    sec.email,
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- hash para '123456'
    'admin',
    'ativo'
FROM seguro_empresa_clientes sec
LEFT JOIN seguro_usuarios su ON sec.id = su.seguro_empresa_id
WHERE su.id IS NULL -- Não existe usuário para esta empresa
AND sec.status = 'ativo';

-- ============================================
-- RELATÓRIO FINAL
-- ============================================

-- Ver empresas com acesso ao Sistema Seguro
SELECT 
    ea.id,
    ea.razao_social,
    ea.plano,
    ea.tem_acesso_seguro as 'Acesso Seguro',
    sec.id as 'ID Seguro',
    sec.unidade,
    COUNT(DISTINCT su.id) as 'Usuários',
    COUNT(DISTINCT sc.id) as 'Clientes'
FROM empresa_adm ea
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
LEFT JOIN seguro_usuarios su ON sec.id = su.seguro_empresa_id
LEFT JOIN seguro_clientes sc ON sec.id = sc.seguro_empresa_id
WHERE ea.tem_acesso_seguro = 'sim'
GROUP BY ea.id, ea.razao_social, ea.plano, ea.tem_acesso_seguro, sec.id, sec.unidade
ORDER BY ea.razao_social;

-- Ver credenciais de acesso
SELECT 
    sec.razao_social as 'Empresa',
    su.email as 'Login (E-mail)',
    '123456' as 'Senha Padrão',
    su.nivel_acesso as 'Nível',
    su.status as 'Status'
FROM seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
WHERE su.nivel_acesso = 'admin'
ORDER BY sec.razao_social;

-- ============================================
-- INFORMAÇÕES
-- ============================================
-- Após executar este script:
-- 
-- 1. Todas as empresas Premium/Enterprise terão:
--    - tem_acesso_seguro = 'sim'
--    - Registro em seguro_empresa_clientes
--    - Usuário admin em seguro_usuarios
--
-- 2. Credenciais padrão:
--    - Login: E-mail da empresa
--    - Senha: 123456
--
-- 3. Os usuários devem alterar a senha no primeiro acesso!
-- ============================================

