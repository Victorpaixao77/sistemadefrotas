-- =====================================================
-- 🚀 SCHEMA COMPLETO DO SISTEMA FISCAL
-- 📋 SISTEMA DE FROTAS - MÓDULO FISCAL
-- 
-- Este script cria todas as tabelas necessárias para
-- o funcionamento do sistema fiscal.
--
-- 📅 Data: Agosto 2025
-- 🔧 Versão: 2.0.0
-- 🏷️  Prefixo: fiscal_ (para organização do banco)
-- =====================================================

-- =====================================================
-- 🔐 1. CONFIGURAÇÃO FISCAL DA EMPRESA
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_config_empresa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cnpj VARCHAR(18) NOT NULL,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    inscricao_estadual VARCHAR(20),
    codigo_municipio VARCHAR(7),
    cep VARCHAR(9),
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    ambiente_sefaz ENUM('producao', 'homologacao') DEFAULT 'homologacao',
    certificado_digital VARCHAR(255),
    senha_certificado_criptografada VARCHAR(255),
    chave_criptografia VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_empresa (empresa_id),
    INDEX idx_cnpj (cnpj),
    INDEX idx_ambiente (ambiente_sefaz)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações fiscais específicas de cada empresa';

-- =====================================================
-- 📄 2. NOTAS FISCAIS DOS CLIENTES
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_nfe_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero_nfe VARCHAR(20) NOT NULL,
    serie_nfe VARCHAR(10),
    chave_acesso VARCHAR(44) UNIQUE,
    data_emissao DATE NOT NULL,
    data_entrada DATE,
    cliente_cnpj VARCHAR(18),
    cliente_razao_social VARCHAR(255),
    cliente_nome_fantasia VARCHAR(255),
    valor_total DECIMAL(15,2) NOT NULL,
    status ENUM('pendente', 'autorizada', 'cancelada', 'denegada', 'inutilizada') DEFAULT 'pendente',
    protocolo_autorizacao VARCHAR(50),
    xml_nfe LONGTEXT,
    pdf_nfe VARCHAR(255),
    observacoes TEXT,
    hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
    status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_numero (numero_nfe),
    INDEX idx_chave (chave_acesso),
    INDEX idx_status (status),
    INDEX idx_data_emissao (data_emissao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Notas fiscais recebidas dos clientes';

-- =====================================================
-- 📦 3. ITENS DAS NOTAS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_nfe_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nfe_id INT NOT NULL,
    codigo_produto VARCHAR(50),
    descricao_produto TEXT NOT NULL,
    ncm VARCHAR(10),
    cfop VARCHAR(4),
    unidade_comercial VARCHAR(10),
    quantidade_comercial DECIMAL(15,4) NOT NULL,
    valor_unitario DECIMAL(15,4) NOT NULL,
    valor_total_item DECIMAL(15,2) NOT NULL,
    peso_bruto DECIMAL(10,3),
    peso_liquido DECIMAL(10,3),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (nfe_id) REFERENCES fiscal_nfe_clientes(id) ON DELETE CASCADE,
    INDEX idx_nfe (nfe_id),
    INDEX idx_codigo (codigo_produto),
    INDEX idx_ncm (ncm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens das notas fiscais dos clientes';

-- =====================================================
-- 🚛 4. CONHECIMENTOS DE TRANSPORTE ELETRÔNICO
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_cte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero_cte VARCHAR(20) NOT NULL,
    serie_cte VARCHAR(10),
    chave_acesso VARCHAR(44) UNIQUE,
    data_emissao DATE NOT NULL,
    tipo_servico ENUM('normal', 'complemento', 'anulacao', 'substituicao') DEFAULT 'normal',
    natureza_operacao VARCHAR(100),
    protocolo_autorizacao VARCHAR(50),
    status ENUM('pendente', 'autorizado', 'cancelado', 'denegado', 'inutilizado') DEFAULT 'pendente',
    valor_total DECIMAL(15,2) NOT NULL,
    peso_total DECIMAL(10,3),
    origem_estado VARCHAR(2),
    origem_cidade VARCHAR(100),
    destino_estado VARCHAR(2),
    destino_cidade VARCHAR(100),
    xml_cte LONGTEXT,
    pdf_cte VARCHAR(255),
    observacoes TEXT,
    hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
    status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_numero (numero_cte),
    INDEX idx_chave (chave_acesso),
    INDEX idx_status (status),
    INDEX idx_data_emissao (data_emissao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conhecimentos de Transporte Eletrônico';

-- =====================================================
-- 📋 5. MANIFESTOS DE DOCUMENTOS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_mdfe (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    numero_mdfe VARCHAR(20) NOT NULL,
    serie_mdfe VARCHAR(10),
    chave_acesso VARCHAR(44) UNIQUE,
    data_emissao DATE NOT NULL,
    tipo_transporte ENUM('rodoviario', 'aereo', 'aquaviario', 'ferroviario') DEFAULT 'rodoviario',
    protocolo_autorizacao VARCHAR(50),
    status ENUM('pendente', 'autorizado', 'cancelado', 'encerrado', 'denegado') DEFAULT 'pendente',
    valor_total_carga DECIMAL(15,2),
    peso_total_carga DECIMAL(10,3),
    qtd_total_volumes INT,
    qtd_total_peso DECIMAL(10,3),
    xml_mdfe LONGTEXT,
    pdf_mdfe VARCHAR(255),
    observacoes TEXT,
    motorista_id INT NULL COMMENT 'ID do motorista responsável pela viagem',
    veiculo_id INT NULL COMMENT 'ID do veículo de tração principal',
    hash_assinatura VARCHAR(64) COMMENT 'Hash SHA-256 da assinatura digital',
    status_assinatura ENUM('valida', 'invalida', 'pendente') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE SET NULL,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
    INDEX idx_empresa (empresa_id),
    INDEX idx_numero (numero_mdfe),
    INDEX idx_chave (chave_acesso),
    INDEX idx_status (status),
    INDEX idx_data_emissao (data_emissao),
    INDEX idx_motorista (motorista_id),
    INDEX idx_veiculo (veiculo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Manifestos de Documentos Fiscais Eletrônicos';

-- =====================================================
-- 🔗 6. RELACIONAMENTO MDF-E COM CT-E
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_mdfe_cte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id INT NOT NULL,
    cte_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (mdfe_id) REFERENCES fiscal_mdfe(id) ON DELETE CASCADE,
    FOREIGN KEY (cte_id) REFERENCES fiscal_cte(id) ON DELETE CASCADE,
    UNIQUE KEY unique_mdfe_cte (mdfe_id, cte_id),
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_cte (cte_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relacionamento entre MDF-e e CT-e';

-- =====================================================
-- 📊 7. LOGS DE OPERAÇÕES FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    documento_tipo ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    documento_id INT NOT NULL,
    acao ENUM('criacao', 'edicao', 'exclusao', 'cancelamento', 'encerramento', 'cce', 'inutilizacao', 'assinatura', 'verificacao', 'envio', 'recebimento', 'erro', 'sincronizacao') NOT NULL,
    status ENUM('sucesso', 'erro', 'pendente') NOT NULL,
    mensagem TEXT,
    detalhes JSON,
    usuario_id INT NULL COMMENT 'ID do usuário que executou a ação',
    ip_usuario VARCHAR(45) COMMENT 'IP do usuário',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_tipo, documento_id),
    INDEX idx_acao (acao),
    INDEX idx_status (status),
    INDEX idx_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de todas as operações fiscais';

-- =====================================================
-- ⚙️ 8. CONFIGURAÇÃO DE ENVIO AUTOMÁTICO
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_config_envio_automatico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_documento ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    enviar_automatico BOOLEAN DEFAULT FALSE,
    enviar_cliente BOOLEAN DEFAULT TRUE,
    enviar_motorista BOOLEAN DEFAULT FALSE,
    template_email_cliente TEXT,
    template_email_motorista TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_empresa_tipo (empresa_id, tipo_documento),
    INDEX idx_empresa (empresa_id),
    INDEX idx_tipo (tipo_documento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações de envio automático de documentos';

-- =====================================================
-- 🎯 9. EVENTOS FISCAIS OFICIAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_eventos_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_evento ENUM('cancelamento', 'encerramento', 'cce', 'inutilizacao', 'carta_correcao') NOT NULL,
    documento_tipo ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    documento_id INT NOT NULL,
    protocolo_evento VARCHAR(50),
    justificativa TEXT,
    xml_evento LONGTEXT COMMENT 'XML completo do evento',
    xml_retorno LONGTEXT COMMENT 'XML de retorno da SEFAZ',
    status ENUM('pendente', 'aceito', 'rejeitado') DEFAULT 'pendente',
    data_evento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_processamento TIMESTAMP NULL,
    usuario_id INT NULL COMMENT 'Usuário que solicitou o evento',
    observacoes TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_tipo, documento_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_status (status),
    INDEX idx_data_evento (data_evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Eventos fiscais oficiais (cancelamento, CCE, encerramento)';

-- =====================================================
-- 📊 10. HISTÓRICO DE STATUS DOS DOCUMENTOS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_status_historico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    documento_tipo ENUM('nfe', 'cte', 'mdfe') NOT NULL,
    documento_id INT NOT NULL,
    status_anterior VARCHAR(50),
    status_novo VARCHAR(50),
    motivo_mudanca TEXT,
    usuario_id INT NULL COMMENT 'Usuário que alterou o status',
    data_alteracao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_usuario VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_tipo, documento_id),
    INDEX idx_status_anterior (status_anterior),
    INDEX idx_status_novo (status_novo),
    INDEX idx_data_alteracao (data_alteracao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de todas as mudanças de status dos documentos';

-- =====================================================
-- 🛡️ 11. CONFIGURAÇÃO DE SEGURANÇA FISCAL
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_config_seguranca (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    algoritmo_criptografia ENUM('AES-256', 'AES-128', '3DES') DEFAULT 'AES-256',
    chave_mestre VARCHAR(255) COMMENT 'Chave mestra para criptografia',
    salt_criptografia VARCHAR(64) COMMENT 'Salt para hash das senhas',
    tempo_expiracao_sessao INT DEFAULT 3600 COMMENT 'Tempo em segundos',
    max_tentativas_login INT DEFAULT 5,
    bloqueio_temporario INT DEFAULT 900 COMMENT 'Tempo de bloqueio em segundos',
    log_tentativas_acesso BOOLEAN DEFAULT TRUE,
    criptografar_arquivos BOOLEAN DEFAULT TRUE,
    backup_criptografado BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    UNIQUE KEY unique_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações de segurança e criptografia';

-- =====================================================
-- 🔑 12. CERTIFICADOS DIGITAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_certificados_digitais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome_certificado VARCHAR(255) NOT NULL,
    arquivo_certificado VARCHAR(255),
    senha_criptografada VARCHAR(255),
    tipo_certificado ENUM('A1', 'A3') DEFAULT 'A1',
    data_emissao DATE,
    data_vencimento DATE NOT NULL,
    cnpj_proprietario VARCHAR(18),
    razao_social_proprietario VARCHAR(255),
    emissor VARCHAR(255),
    serial_number VARCHAR(100),
    ativo BOOLEAN DEFAULT TRUE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_data_vencimento (data_vencimento),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Certificados digitais das empresas';

-- =====================================================
-- 📧 13. SISTEMA DE ALERTAS FISCAIS
-- =====================================================

CREATE TABLE IF NOT EXISTS fiscal_alertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_alerta ENUM('certificado_vencendo', 'mdfe_nao_encerrado', 'erro_sefaz', 'documento_pendente', 'backup_necessario') NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    mensagem TEXT NOT NULL,
    nivel ENUM('baixo', 'medio', 'alto', 'critico') DEFAULT 'medio',
    status ENUM('ativo', 'resolvido', 'ignorado') DEFAULT 'ativo',
    data_criacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_resolucao TIMESTAMP NULL,
    usuario_resolucao INT NULL,
    acao_requerida TEXT,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    INDEX idx_empresa (empresa_id),
    INDEX idx_tipo_alerta (tipo_alerta),
    INDEX idx_nivel (nivel),
    INDEX idx_status (status),
    INDEX idx_data_criacao (data_criacao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de alertas e notificações fiscais';

-- =====================================================
-- 🚀 14. INSERIR DADOS INICIAIS
-- =====================================================

-- Configuração fiscal para empresa ID 1
INSERT INTO fiscal_config_empresa (empresa_id, cnpj, razao_social, nome_fantasia, ambiente_sefaz) 
VALUES (1, '12.345.678/0001-90', 'EMPRESA EXEMPLO LTDA', 'EMPRESA EXEMPLO', 'homologacao')
ON DUPLICATE KEY UPDATE 
    cnpj = VALUES(cnpj),
    razao_social = VALUES(razao_social),
    nome_fantasia = VALUES(nome_fantasia),
    ambiente_sefaz = VALUES(ambiente_sefaz);

-- Configuração fiscal para empresa ID 2
INSERT INTO fiscal_config_empresa (empresa_id, cnpj, razao_social, nome_fantasia, ambiente_sefaz) 
VALUES (2, '98.765.432/0001-10', 'EMPRESA EXEMPLO 2 LTDA', 'EMPRESA EXEMPLO 2', 'homologacao')
ON DUPLICATE KEY UPDATE 
    cnpj = VALUES(cnpj),
    razao_social = VALUES(razao_social),
    nome_fantasia = VALUES(nome_fantasia),
    ambiente_sefaz = VALUES(ambiente_sefaz);

-- Configuração fiscal para empresa ID 3
INSERT INTO fiscal_config_empresa (empresa_id, cnpj, razao_social, nome_fantasia, ambiente_sefaz) 
VALUES (3, '11.222.333/0001-44', 'EMPRESA EXEMPLO 3 LTDA', 'EMPRESA EXEMPLO 3', 'homologacao')
ON DUPLICATE KEY UPDATE 
    cnpj = VALUES(cnpj),
    razao_social = VALUES(razao_social),
    nome_fantasia = VALUES(nome_fantasia),
    ambiente_sefaz = VALUES(ambiente_sefaz);

-- Configuração de segurança para empresa ID 1
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (1, 'AES-256', 'chave_mestra_empresa_1_' || UUID(), 'salt_empresa_1_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- Configuração de segurança para empresa ID 2
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (2, 'AES-256', 'chave_mestra_empresa_2_' || UUID(), 'salt_empresa_2_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- Configuração de segurança para empresa ID 3
INSERT INTO fiscal_config_seguranca (empresa_id, algoritmo_criptografia, chave_mestre, salt_criptografia) 
VALUES (3, 'AES-256', 'chave_mestra_empresa_3_' || UUID(), 'salt_empresa_3_' || UUID())
ON DUPLICATE KEY UPDATE 
    algoritmo_criptografia = VALUES(algoritmo_criptografia),
    chave_mestre = VALUES(chave_mestre),
    salt_criptografia = VALUES(salt_criptografia);

-- Configuração de envio automático para empresa ID 1
INSERT INTO fiscal_config_envio_automatico (empresa_id, tipo_documento, enviar_automatico, enviar_cliente, enviar_motorista) 
VALUES 
    (1, 'nfe', TRUE, TRUE, FALSE),
    (1, 'cte', TRUE, TRUE, TRUE),
    (1, 'mdfe', TRUE, TRUE, TRUE)
ON DUPLICATE KEY UPDATE 
    enviar_automatico = VALUES(enviar_automatico),
    enviar_cliente = VALUES(enviar_cliente),
    enviar_motorista = VALUES(enviar_motorista);

-- =====================================================
-- ✅ SCHEMA CONCLUÍDO COM SUCESSO!
-- =====================================================
-- 
-- 🏷️  Todas as tabelas agora têm o prefixo 'fiscal_'
-- 📊  Banco organizado e fácil de identificar
-- 🚀  Sistema pronto para uso
--
-- 📋 Tabelas criadas:
--    • fiscal_config_empresa
--    • fiscal_nfe_clientes
--    • fiscal_nfe_itens
--    • fiscal_cte
--    • fiscal_mdfe
--    • fiscal_mdfe_cte
--    • fiscal_logs
--    • fiscal_config_envio_automatico
--    • fiscal_eventos_fiscais
--    • fiscal_status_historico
--    • fiscal_config_seguranca
--    • fiscal_certificados_digitais
--    • fiscal_alertas
--
-- 🎯 Próximo passo: Executar o script de melhorias
-- =====================================================
