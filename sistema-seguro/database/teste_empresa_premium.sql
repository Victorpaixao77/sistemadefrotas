-- ============================================
-- SCRIPT DE TESTE RÁPIDO
-- ============================================
-- Este script cria uma empresa Premium de teste
-- com acesso ao Sistema Seguro
-- ============================================

-- Inserir empresa na tabela principal
INSERT INTO `empresa_adm` 
(`razao_social`, `cnpj`, `telefone`, `email`, `valor_por_veiculo`, `plano`, `tem_acesso_seguro`, `status`, `data_cadastro`)
VALUES 
('EMPRESA TESTE SEGURO LTDA', '12.345.678/0001-90', '(62) 99999-9999', 'teste@seguro.com.br', 100.00, 'premium', 'sim', 'ativo', NOW());

-- Obter o ID da empresa recém criada
SET @empresa_adm_id = LAST_INSERT_ID();

-- Inserir na tabela empresa_clientes (sistema de frotas)
INSERT INTO `empresa_clientes`
(`empresa_adm_id`, `razao_social`, `cnpj`, `telefone`, `email`, `status`)
VALUES
(@empresa_adm_id, 'EMPRESA TESTE SEGURO LTDA', '12.345.678/0001-90', '(62) 99999-9999', 'teste@seguro.com.br', 'ativo');

-- Inserir na tabela do Sistema Seguro
INSERT INTO `seguro_empresa_clientes`
(`empresa_adm_id`, `razao_social`, `nome_fantasia`, `cnpj`, `email`, `telefone`, `cidade`, `estado`, `porcentagem_fixa`, `unidade`, `plano`, `status`)
VALUES
(@empresa_adm_id, 'EMPRESA TESTE SEGURO LTDA', 'Teste Seguro', '12.345.678/0001-90', 'teste@seguro.com.br', '(62) 99999-9999', 'Goiânia', 'GO', 5.00, 'Matriz', 'premium', 'ativo');

-- Obter o ID da empresa no Sistema Seguro
SET @seguro_empresa_id = LAST_INSERT_ID();

-- Criar usuário admin para o Sistema Seguro
-- Senha: 123456 (hash gerado)
INSERT INTO `seguro_usuarios`
(`seguro_empresa_id`, `nome`, `email`, `senha`, `nivel_acesso`, `status`)
VALUES
(@seguro_empresa_id, 'Admin Teste', 'teste@seguro.com.br', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'ativo');

-- Inserir alguns clientes de teste
INSERT INTO `seguro_clientes`
(`seguro_empresa_id`, `codigo`, `tipo_pessoa`, `cpf_cnpj`, `nome_razao_social`, `sigla_fantasia`, `cidade`, `uf`, `unidade`, `porcentagem_recorrencia`, `situacao`)
VALUES
(@seguro_empresa_id, '10001', 'fisica', '123.456.789-00', 'JOÃO DA SILVA SANTOS', 'João Silva', 'Goiânia', 'GO', 'Matriz', 10.00, 'ativo'),
(@seguro_empresa_id, '10002', 'fisica', '987.654.321-00', 'MARIA OLIVEIRA COSTA', 'Maria Oliveira', 'Anápolis', 'GO', 'Unidade 01', 15.00, 'ativo'),
(@seguro_empresa_id, '10003', 'juridica', '11.222.333/0001-44', 'TECH SOLUTIONS LTDA', 'Tech Solutions', 'Goiânia', 'GO', 'Filial São Paulo', 20.00, 'ativo'),
(@seguro_empresa_id, '10004', 'fisica', '555.666.777-88', 'CARLOS EDUARDO LIMA', 'Carlos Lima', 'Itaberaí', 'GO', 'Matriz', 12.00, 'inativo'),
(@seguro_empresa_id, '10005', 'fisica', '444.555.666-77', 'ANA PAULA FERREIRA', 'Ana Paula', 'Goiânia', 'GO', 'Unidade 02', 8.00, 'ativo');

-- ============================================
-- VERIFICAÇÃO
-- ============================================

-- Ver a empresa criada
SELECT 
    ea.id as 'ID Empresa',
    ea.razao_social as 'Razão Social',
    ea.plano as 'Plano',
    ea.tem_acesso_seguro as 'Acesso Seguro',
    sec.id as 'ID Seguro',
    sec.unidade as 'Unidade',
    sec.porcentagem_fixa as '% Fixa'
FROM empresa_adm ea
LEFT JOIN seguro_empresa_clientes sec ON ea.id = sec.empresa_adm_id
WHERE ea.razao_social = 'EMPRESA TESTE SEGURO LTDA';

-- Ver o usuário criado
SELECT 
    su.id as 'ID',
    su.nome as 'Nome',
    su.email as 'Email',
    su.nivel_acesso as 'Nível',
    su.status as 'Status',
    sec.razao_social as 'Empresa'
FROM seguro_usuarios su
INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
WHERE su.email = 'teste@seguro.com.br';

-- Ver os clientes cadastrados
SELECT 
    sc.codigo as 'Código',
    sc.nome_razao_social as 'Nome',
    sc.cidade as 'Cidade',
    sc.unidade as 'Unidade',
    sc.porcentagem_recorrencia as '% Recorrência',
    sc.situacao as 'Situação'
FROM seguro_clientes sc
WHERE sc.seguro_empresa_id = @seguro_empresa_id;

-- ============================================
-- CREDENCIAIS DE ACESSO
-- ============================================
-- E-mail: teste@seguro.com.br
-- Senha: 123456
-- ============================================

SELECT 
    '✅ EMPRESA PREMIUM CRIADA COM SUCESSO!' as 'STATUS',
    'teste@seguro.com.br' as 'LOGIN',
    '123456' as 'SENHA',
    'http://localhost/sistema-frotas/SISTEMA-SEGURO/clientes.php' as 'ACESSO';

