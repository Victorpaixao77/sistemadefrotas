-- ============================================
-- SISTEMA SEGURO - ESTRUTURA DO BANCO DE DADOS
-- ============================================
-- Todas as tabelas relacionadas ao sistema de seguros
-- começam com o prefixo "seguro_"
-- ============================================

-- Tabela: seguro_empresa_clientes
-- Armazena as empresas que contrataram o sistema de seguros (Plano Premium)
CREATE TABLE IF NOT EXISTS `seguro_empresa_clientes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `empresa_adm_id` int(11) NOT NULL COMMENT 'ID da empresa na tabela empresa_adm (sistema principal)',
    `razao_social` varchar(255) NOT NULL,
    `nome_fantasia` varchar(255) DEFAULT NULL,
    `cnpj` varchar(18) NOT NULL,
    `inscricao_estadual` varchar(50) DEFAULT NULL,
    `inscricao_municipal` varchar(50) DEFAULT NULL,
    `telefone` varchar(20) DEFAULT NULL,
    `celular` varchar(20) DEFAULT NULL,
    `email` varchar(255) DEFAULT NULL,
    `site` varchar(255) DEFAULT NULL,
    `endereco` varchar(255) DEFAULT NULL,
    `numero` varchar(20) DEFAULT NULL,
    `complemento` varchar(100) DEFAULT NULL,
    `bairro` varchar(100) DEFAULT NULL,
    `cidade` varchar(100) DEFAULT NULL,
    `estado` varchar(2) DEFAULT NULL,
    `cep` varchar(10) DEFAULT NULL,
    `responsavel` varchar(100) DEFAULT NULL,
    `logo` varchar(255) DEFAULT NULL,
    `porcentagem_fixa` decimal(5,2) DEFAULT 0.00 COMMENT 'Porcentagem fixa da empresa',
    `unidade` varchar(100) DEFAULT NULL COMMENT 'Nome da unidade no sistema terceirizado',
    `observacoes` text DEFAULT NULL,
    `plano` enum('premium','enterprise') DEFAULT 'premium' COMMENT 'Tipo de plano contratado',
    `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
    PRIMARY KEY (`id`),
    KEY `empresa_adm_id` (`empresa_adm_id`),
    KEY `cnpj` (`cnpj`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Empresas com acesso ao Sistema Seguro';

-- Tabela: seguro_usuarios
-- Armazena os usuários que terão acesso ao sistema de seguros
CREATE TABLE IF NOT EXISTS `seguro_usuarios` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `usuario_adm_id` int(11) DEFAULT NULL COMMENT 'ID do usuário na tabela de usuários do sistema principal',
    `seguro_empresa_id` int(11) NOT NULL COMMENT 'ID da empresa no sistema de seguros',
    `nome` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `senha` varchar(255) NOT NULL,
    `nivel_acesso` enum('admin','gerente','operador','visualizador') DEFAULT 'operador',
    `telefone` varchar(20) DEFAULT NULL,
    `foto` varchar(255) DEFAULT NULL,
    `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `ultimo_acesso` datetime DEFAULT NULL,
    `status` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
    PRIMARY KEY (`id`),
    UNIQUE KEY `email` (`email`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `usuario_adm_id` (`usuario_adm_id`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Usuários do Sistema Seguro';

-- Tabela: seguro_clientes
-- Armazena os clientes comissionados (os clientes finais do sistema de seguros)
CREATE TABLE IF NOT EXISTS `seguro_clientes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL COMMENT 'Empresa proprietária do cliente',
    `codigo` varchar(50) DEFAULT NULL COMMENT 'Código do cliente',
    `tipo_pessoa` enum('fisica','juridica') NOT NULL DEFAULT 'fisica',
    `cpf_cnpj` varchar(18) NOT NULL,
    `nome_razao_social` varchar(255) NOT NULL,
    `sigla_fantasia` varchar(255) DEFAULT NULL,
    `cep` varchar(10) DEFAULT NULL,
    `logradouro` varchar(255) DEFAULT NULL,
    `numero` varchar(20) DEFAULT NULL,
    `complemento` varchar(100) DEFAULT NULL,
    `bairro` varchar(100) DEFAULT NULL,
    `cidade` varchar(100) DEFAULT NULL,
    `uf` varchar(2) DEFAULT NULL,
    `telefone` varchar(20) DEFAULT NULL,
    `celular` varchar(20) DEFAULT NULL,
    `email` varchar(255) DEFAULT NULL,
    `unidade` varchar(100) DEFAULT NULL COMMENT 'Unidade/empresa associada ao comissionado',
    `porcentagem_recorrencia` decimal(5,2) DEFAULT 0.00 COMMENT 'Porcentagem de recorrência do cliente',
    `observacoes` text DEFAULT NULL,
    `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
    `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `situacao` enum('ativo','inativo') NOT NULL DEFAULT 'ativo',
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `cpf_cnpj` (`cpf_cnpj`),
    KEY `situacao` (`situacao`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Clientes comissionados do Sistema Seguro';

-- Tabela: seguro_atendimentos
-- Armazena os atendimentos realizados para os clientes
CREATE TABLE IF NOT EXISTS `seguro_atendimentos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL,
    `seguro_cliente_id` int(11) NOT NULL,
    `usuario_id` int(11) DEFAULT NULL COMMENT 'Usuário que registrou o atendimento',
    `tipo` enum('suporte','reclamacao','duvida','venda','acompanhamento','outros') DEFAULT 'suporte',
    `prioridade` enum('baixa','media','alta','urgente') DEFAULT 'media',
    `status` enum('aberto','em_andamento','aguardando','resolvido','fechado','cancelado') DEFAULT 'aberto',
    `titulo` varchar(255) NOT NULL,
    `descricao` text NOT NULL,
    `solucao` text DEFAULT NULL,
    `protocolo` varchar(50) DEFAULT NULL,
    `data_abertura` datetime DEFAULT CURRENT_TIMESTAMP,
    `data_fechamento` datetime DEFAULT NULL,
    `tempo_resposta` int(11) DEFAULT NULL COMMENT 'Tempo de resposta em minutos',
    `avaliacao` tinyint(1) DEFAULT NULL COMMENT 'Avaliação do cliente (1-5)',
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `seguro_cliente_id` (`seguro_cliente_id`),
    KEY `usuario_id` (`usuario_id`),
    KEY `status` (`status`),
    KEY `protocolo` (`protocolo`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seguro_cliente_id`) REFERENCES `seguro_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Atendimentos do Sistema Seguro';

-- Tabela: seguro_financeiro
-- Armazena as movimentações financeiras relacionadas aos clientes
CREATE TABLE IF NOT EXISTS `seguro_financeiro` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL,
    `seguro_cliente_id` int(11) NOT NULL,
    `tipo` enum('receita','despesa','comissao','recorrencia') NOT NULL,
    `categoria` varchar(100) DEFAULT NULL,
    `descricao` varchar(255) NOT NULL,
    `valor` decimal(10,2) NOT NULL,
    `data_vencimento` date NOT NULL,
    `data_pagamento` date DEFAULT NULL,
    `status` enum('pendente','pago','vencido','cancelado') DEFAULT 'pendente',
    `forma_pagamento` varchar(50) DEFAULT NULL,
    `observacoes` text DEFAULT NULL,
    `numero_documento` varchar(50) DEFAULT NULL,
    `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `seguro_cliente_id` (`seguro_cliente_id`),
    KEY `tipo` (`tipo`),
    KEY `status` (`status`),
    KEY `data_vencimento` (`data_vencimento`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seguro_cliente_id`) REFERENCES `seguro_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Movimentações financeiras do Sistema Seguro';

-- Tabela: seguro_equipamentos
-- Armazena os equipamentos dos clientes (roteadores, antenas, etc.)
CREATE TABLE IF NOT EXISTS `seguro_equipamentos` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL,
    `seguro_cliente_id` int(11) NOT NULL,
    `tipo` varchar(100) DEFAULT NULL COMMENT 'Ex: Roteador, Antena, ONU, etc.',
    `modelo` varchar(100) DEFAULT NULL,
    `marca` varchar(100) DEFAULT NULL,
    `numero_serie` varchar(100) DEFAULT NULL,
    `mac_address` varchar(50) DEFAULT NULL,
    `ip` varchar(50) DEFAULT NULL,
    `status` enum('ativo','inativo','manutencao','substituido') DEFAULT 'ativo',
    `data_instalacao` date DEFAULT NULL,
    `data_ultima_manutencao` date DEFAULT NULL,
    `observacoes` text DEFAULT NULL,
    `data_cadastro` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `seguro_cliente_id` (`seguro_cliente_id`),
    KEY `numero_serie` (`numero_serie`),
    KEY `mac_address` (`mac_address`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`seguro_cliente_id`) REFERENCES `seguro_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Equipamentos dos clientes';

-- Tabela: seguro_logs
-- Armazena logs de atividades do sistema
CREATE TABLE IF NOT EXISTS `seguro_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL,
    `usuario_id` int(11) DEFAULT NULL,
    `acao` varchar(100) NOT NULL COMMENT 'Ex: criar, editar, deletar, login, etc.',
    `modulo` varchar(100) NOT NULL COMMENT 'Ex: clientes, financeiro, atendimentos, etc.',
    `descricao` text DEFAULT NULL,
    `ip` varchar(50) DEFAULT NULL,
    `user_agent` varchar(255) DEFAULT NULL,
    `data_hora` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    KEY `usuario_id` (`usuario_id`),
    KEY `acao` (`acao`),
    KEY `modulo` (`modulo`),
    KEY `data_hora` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Logs de atividades do sistema';

-- Tabela: seguro_configuracoes
-- Armazena configurações específicas de cada empresa
CREATE TABLE IF NOT EXISTS `seguro_configuracoes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `seguro_empresa_id` int(11) NOT NULL,
    `chave` varchar(100) NOT NULL,
    `valor` text DEFAULT NULL,
    `tipo` enum('texto','numero','booleano','json') DEFAULT 'texto',
    `descricao` varchar(255) DEFAULT NULL,
    `data_atualizacao` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `empresa_chave` (`seguro_empresa_id`, `chave`),
    KEY `seguro_empresa_id` (`seguro_empresa_id`),
    FOREIGN KEY (`seguro_empresa_id`) REFERENCES `seguro_empresa_clientes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configurações personalizadas por empresa';

-- ============================================
-- ALTERAÇÃO NA TABELA PRINCIPAL (empresa_adm)
-- ============================================
-- Adicionar campo para controlar acesso ao Sistema Seguro

-- Verificar se a coluna já existe antes de adicionar
-- Execute este comando manualmente no phpMyAdmin ou ajuste conforme necessário:

-- ALTER TABLE `empresa_adm` 
-- ADD COLUMN `tem_acesso_seguro` enum('sim','nao') DEFAULT 'nao' COMMENT 'Empresa tem acesso ao Sistema Seguro (Plano Premium)' AFTER `plano`;

-- ============================================
-- DADOS INICIAIS PARA TESTES
-- ============================================

-- Inserir uma empresa de exemplo (ajuste o empresa_adm_id conforme sua base)
-- INSERT INTO `seguro_empresa_clientes` (
--     `empresa_adm_id`, `razao_social`, `nome_fantasia`, `cnpj`, `email`, 
--     `telefone`, `cidade`, `estado`, `porcentagem_fixa`, `unidade`, `status`
-- ) VALUES (
--     1, 
--     'EMPRESA TESTE SEGURO LTDA', 
--     'Teste Seguro', 
--     '12.345.678/0001-90', 
--     'contato@testeseguro.com.br',
--     '(62) 3333-4444',
--     'Goiânia',
--     'GO',
--     5.00,
--     'Matriz',
--     'ativo'
-- );

-- ============================================
-- VIEWS ÚTEIS
-- ============================================

-- View para relatório de clientes ativos
CREATE OR REPLACE VIEW `view_seguro_clientes_ativos` AS
SELECT 
    sc.id,
    sc.codigo,
    sc.nome_razao_social,
    sc.cpf_cnpj,
    sc.cidade,
    sc.uf,
    sc.unidade,
    sc.porcentagem_recorrencia,
    sec.razao_social as empresa,
    sec.id as seguro_empresa_id
FROM seguro_clientes sc
INNER JOIN seguro_empresa_clientes sec ON sc.seguro_empresa_id = sec.id
WHERE sc.situacao = 'ativo' AND sec.status = 'ativo';

-- View para dashboard financeiro
CREATE OR REPLACE VIEW `view_seguro_financeiro_resumo` AS
SELECT 
    sf.seguro_empresa_id,
    sec.razao_social as empresa,
    COUNT(*) as total_movimentacoes,
    SUM(CASE WHEN sf.tipo = 'receita' THEN sf.valor ELSE 0 END) as total_receitas,
    SUM(CASE WHEN sf.tipo = 'despesa' THEN sf.valor ELSE 0 END) as total_despesas,
    SUM(CASE WHEN sf.tipo = 'comissao' THEN sf.valor ELSE 0 END) as total_comissoes,
    SUM(CASE WHEN sf.tipo = 'recorrencia' THEN sf.valor ELSE 0 END) as total_recorrencias,
    SUM(CASE WHEN sf.status = 'pendente' THEN sf.valor ELSE 0 END) as total_pendente,
    SUM(CASE WHEN sf.status = 'pago' THEN sf.valor ELSE 0 END) as total_pago
FROM seguro_financeiro sf
INNER JOIN seguro_empresa_clientes sec ON sf.seguro_empresa_id = sec.id
WHERE sec.status = 'ativo'
GROUP BY sf.seguro_empresa_id, sec.razao_social;

-- View para atendimentos em aberto
CREATE OR REPLACE VIEW `view_seguro_atendimentos_abertos` AS
SELECT 
    sa.id,
    sa.protocolo,
    sa.titulo,
    sa.tipo,
    sa.prioridade,
    sa.status,
    sa.data_abertura,
    sc.nome_razao_social as cliente,
    sec.razao_social as empresa,
    TIMESTAMPDIFF(HOUR, sa.data_abertura, NOW()) as horas_abertas
FROM seguro_atendimentos sa
INNER JOIN seguro_clientes sc ON sa.seguro_cliente_id = sc.id
INNER JOIN seguro_empresa_clientes sec ON sa.seguro_empresa_id = sec.id
WHERE sa.status IN ('aberto', 'em_andamento', 'aguardando')
ORDER BY sa.prioridade DESC, sa.data_abertura ASC;

-- ============================================
-- FIM DO SCRIPT
-- ============================================

