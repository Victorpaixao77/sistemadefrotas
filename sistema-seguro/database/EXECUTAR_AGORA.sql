-- ============================================
-- EXECUTE ESTE SQL NO PHPMYADMIN
-- ============================================
-- Copia e cola TUDO de uma vez
-- ============================================

-- PASSO 1: Atualizar todas as empresas Premium para ter acesso
UPDATE empresa_adm 
SET tem_acesso_seguro = 'sim' 
WHERE plano IN ('premium', 'enterprise')
AND (tem_acesso_seguro IS NULL OR tem_acesso_seguro = 'nao');

-- PASSO 2: Criar empresas no Sistema Seguro (se não existir)
INSERT INTO seguro_empresa_clientes 
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
WHERE (ea.plano = 'premium' OR ea.plano = 'enterprise')
AND ea.tem_acesso_seguro = 'sim'
AND sec.id IS NULL;

-- PASSO 3: Verificar o resultado
SELECT 
    '✅ EMPRESAS PREMIUM COM ACESSO' as 'STATUS',
    COUNT(*) as 'Total'
FROM empresa_adm ea
INNER JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE ea.tem_acesso_seguro = 'sim';

-- PASSO 4: Ver lista de usuários que podem logar
SELECT 
    '📧 USE ESTE EMAIL' as 'ATENÇÃO',
    u.email as 'EMAIL',
    '(mesma senha do frotas)' as 'SENHA',
    u.nome as 'Nome',
    ec.razao_social as 'Empresa',
    ea.plano as 'Plano'
FROM usuarios u
INNER JOIN empresa_clientes ec ON u.empresa_id = ec.id
INNER JOIN empresa_adm ea ON ec.empresa_adm_id = ea.id
INNER JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE u.status = 'ativo'
AND ea.tem_acesso_seguro = 'sim'
ORDER BY u.email
LIMIT 10;

-- ============================================
-- APÓS EXECUTAR, TENTE FAZER LOGIN COM:
-- Email: (veja na tabela acima)
-- Senha: (a mesma que você usa no sistema de frotas)
-- ============================================

-- PASSO 5: Ver logs de tentativas de login (após tentar logar)
SELECT 
    DATE_FORMAT(data_hora, '%d/%m/%Y %H:%i:%s') as 'Data/Hora',
    descricao as 'Descrição',
    ip as 'IP'
FROM seguro_logs 
WHERE modulo = 'autenticacao'
ORDER BY data_hora DESC 
LIMIT 5;

