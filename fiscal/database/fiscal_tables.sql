-- =====================================================
-- 🏢 SISTEMA FISCAL - ESTRUTURA COMPLETA DO BANCO
-- =====================================================

-- Tabela de documentos fiscais (NF-e, CT-e, MDF-e)
CREATE TABLE IF NOT EXISTS documentos_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_documento ENUM('NFE', 'CTE', 'MDFE') NOT NULL,
    numero_documento VARCHAR(20) NOT NULL,
    serie VARCHAR(10) NOT NULL,
    chave_acesso VARCHAR(44),
    protocolo_autorizacao VARCHAR(50),
    status ENUM('rascunho', 'pendente', 'autorizado', 'cancelado', 'erro') DEFAULT 'rascunho',
    ambiente ENUM('homologacao', 'producao') DEFAULT 'homologacao',
    data_emissao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_autorizacao DATETIME NULL,
    data_cancelamento DATETIME NULL,
    valor_total DECIMAL(15,2) DEFAULT 0.00,
    natureza_operacao VARCHAR(100),
    tipo_operacao ENUM('entrada', 'saida') DEFAULT 'saida',
    destinatario_nome VARCHAR(255),
    destinatario_cnpj VARCHAR(18),
    destinatario_ie VARCHAR(20),
    destinatario_endereco TEXT,
    destinatario_cidade VARCHAR(100),
    destinatario_uf CHAR(2),
    destinatario_cep VARCHAR(10),
    observacoes TEXT,
    xml_documento LONGTEXT,
    xml_retorno LONGTEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_empresa_tipo (empresa_id, tipo_documento),
    INDEX idx_status (status),
    INDEX idx_data_emissao (data_emissao),
    INDEX idx_chave_acesso (chave_acesso),
    UNIQUE KEY uk_numero_serie (empresa_id, tipo_documento, numero_documento, serie)
);

-- Tabela de itens dos documentos (produtos/serviços)
CREATE TABLE IF NOT EXISTS itens_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    codigo_produto VARCHAR(50),
    descricao_produto VARCHAR(255) NOT NULL,
    ncm VARCHAR(10),
    cfop VARCHAR(10),
    unidade_comercial VARCHAR(10),
    quantidade_comercial DECIMAL(15,4) NOT NULL,
    valor_unitario DECIMAL(15,4) NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    valor_desconto DECIMAL(15,2) DEFAULT 0.00,
    valor_frete DECIMAL(15,2) DEFAULT 0.00,
    valor_seguro DECIMAL(15,2) DEFAULT 0.00,
    valor_outros DECIMAL(15,2) DEFAULT 0.00,
    icms_cst VARCHAR(10),
    icms_base_calculo DECIMAL(15,2) DEFAULT 0.00,
    icms_aliquota DECIMAL(5,2) DEFAULT 0.00,
    icms_valor DECIMAL(15,2) DEFAULT 0.00,
    pis_cst VARCHAR(10),
    pis_base_calculo DECIMAL(15,2) DEFAULT 0.00,
    pis_aliquota DECIMAL(5,2) DEFAULT 0.00,
    pis_valor DECIMAL(15,2) DEFAULT 0.00,
    cofins_cst VARCHAR(10),
    cofins_base_calculo DECIMAL(15,2) DEFAULT 0.00,
    cofins_aliquota DECIMAL(5,2) DEFAULT 0.00,
    cofins_valor DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (documento_id) REFERENCES documentos_fiscais(id) ON DELETE CASCADE,
    INDEX idx_documento (documento_id),
    INDEX idx_codigo_produto (codigo_produto)
);

-- Tabela de eventos fiscais (cancelamentos, correções, etc.)
CREATE TABLE IF NOT EXISTS eventos_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    documento_id INT NOT NULL,
    tipo_evento ENUM('cancelamento', 'correcao', 'carta_correcao', 'manifestacao', 'outros') NOT NULL,
    sequencia INT DEFAULT 1,
    protocolo VARCHAR(50),
    justificativa TEXT,
    data_evento DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pendente', 'processado', 'erro') DEFAULT 'pendente',
    xml_evento LONGTEXT,
    xml_retorno LONGTEXT,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (documento_id) REFERENCES documentos_fiscais(id) ON DELETE CASCADE,
    INDEX idx_documento (documento_id),
    INDEX idx_tipo_evento (tipo_evento),
    INDEX idx_status (status),
    UNIQUE KEY uk_documento_sequencia (documento_id, tipo_evento, sequencia)
);

-- Tabela de configurações fiscais da empresa
CREATE TABLE IF NOT EXISTS configuracoes_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    ambiente_padrao ENUM('homologacao', 'producao') DEFAULT 'homologacao',
    cnpj_empresa VARCHAR(18) NOT NULL,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    inscricao_estadual VARCHAR(20),
    codigo_municipio VARCHAR(7),
    cep VARCHAR(10),
    endereco TEXT,
    telefone VARCHAR(20),
    email VARCHAR(255),
    certificado_digital VARCHAR(255),
    senha_certificado VARCHAR(255),
    tipo_certificado ENUM('A1', 'A3') DEFAULT 'A1',
    data_validade_certificado DATE,
    regiao_tributaria VARCHAR(10),
    regime_tributario ENUM('simples', 'presumido', 'real') DEFAULT 'presumido',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_empresa (empresa_id),
    INDEX idx_cnpj (cnpj_empresa)
);

-- Tabela de sequências de documentos
CREATE TABLE IF NOT EXISTS sequencias_documentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    tipo_documento ENUM('NFE', 'CTE', 'MDFE') NOT NULL,
    serie VARCHAR(10) NOT NULL,
    ultimo_numero INT DEFAULT 0,
    proximo_numero INT DEFAULT 1,
    ano_exercicio INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY uk_empresa_tipo_serie_ano (empresa_id, tipo_documento, serie, ano_exercicio),
    INDEX idx_empresa_tipo (empresa_id, tipo_documento)
);

-- Tabela de logs de operações fiscais
CREATE TABLE IF NOT EXISTS logs_fiscais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    documento_id INT NULL,
    tipo_operacao VARCHAR(50) NOT NULL,
    descricao TEXT NOT NULL,
    status ENUM('sucesso', 'erro', 'aviso') DEFAULT 'sucesso',
    dados_entrada TEXT,
    dados_saida TEXT,
    tempo_execucao DECIMAL(10,3),
    ip_origem VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_empresa (empresa_id),
    INDEX idx_documento (documento_id),
    INDEX idx_tipo_operacao (tipo_operacao),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
);

-- Inserir dados iniciais para sequências
INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio) VALUES
(1, 'NFE', '1', 0, 1, YEAR(CURRENT_DATE)),
(1, 'CTE', '1', 0, 1, YEAR(CURRENT_DATE)),
(1, 'MDFE', '1', 0, 1, YEAR(CURRENT_DATE));

-- Inserir configuração fiscal padrão
INSERT INTO configuracoes_fiscais (empresa_id, cnpj_empresa, razao_social, nome_fantasia, ambiente_padrao) VALUES
(1, '00.000.000/0001-91', 'EMPRESA TESTE LTDA', 'EMPRESA TESTE', 'homologacao');
