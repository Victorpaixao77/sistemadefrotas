-- ============================================
-- INSERIR 2 CLIENTES DE TESTE
-- ============================================

-- Pegar ID da primeira empresa no Sistema Seguro
SET @empresa_id = (SELECT id FROM seguro_empresa_clientes ORDER BY id LIMIT 1);

-- Inserir Cliente 1
INSERT INTO seguro_clientes 
(seguro_empresa_id, codigo, tipo_pessoa, cpf_cnpj, nome_razao_social, sigla_fantasia, 
 cep, logradouro, numero, complemento, bairro, cidade, uf, 
 identificador, placa, conjunto, matricula,
 telefone, celular, email, unidade, porcentagem_recorrencia, situacao)
VALUES 
(@empresa_id, '10001', 'fisica', '123.456.789-00', 'JOÃO DA SILVA SANTOS', 'João Silva',
 '74000-000', 'Rua das Flores', '123', 'Casa', 'Centro', 'Goiânia', 'GO',
 '2535749', 'BDZ4E56', '382', '370',
 '(62) 3333-4444', '(62) 99999-8888', 'joao.silva@email.com', 'Matriz', 15.00, 'ativo');

-- Inserir Cliente 2
INSERT INTO seguro_clientes 
(seguro_empresa_id, codigo, tipo_pessoa, cpf_cnpj, nome_razao_social, sigla_fantasia,
 cep, logradouro, numero, bairro, cidade, uf,
 identificador, placa, conjunto, matricula,
 telefone, celular, email, unidade, porcentagem_recorrencia, situacao)
VALUES 
(@empresa_id, '10002', 'juridica', '12.345.678/0001-90', 'EMPRESA TESTE LTDA', 'Empresa Teste',
 '74100-000', 'Avenida Principal', '456', 'Setor Bueno', 'Goiânia', 'GO',
 '2538796', 'BAP2I35,IVK4I48,IVK4I57', '395', '382',
 '(62) 3333-5555', '(62) 99999-7777', 'contato@empresateste.com', 'Unidade 01', 20.00, 'ativo');

-- Verificar se foram inseridos
SELECT 
    '✅ CLIENTES CADASTRADOS!' as 'RESULTADO',
    COUNT(*) as 'Total'
FROM seguro_clientes 
WHERE seguro_empresa_id = @empresa_id;

-- Ver os clientes
SELECT 
    codigo as 'Código',
    nome_razao_social as 'Nome',
    cpf_cnpj as 'CPF/CNPJ',
    cidade as 'Cidade',
    unidade as 'Unidade',
    porcentagem_recorrencia as '% Recorrência',
    situacao as 'Situação'
FROM seguro_clientes 
WHERE seguro_empresa_id = @empresa_id
ORDER BY codigo;

