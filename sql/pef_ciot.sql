-- CIOT / PEF ANTT — Pagamento Eletrônico de Frete (contrato de serviço v1.1)
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/pef_ciot.sql

CREATE TABLE IF NOT EXISTS pef_config_empresa (
    empresa_id INT NOT NULL PRIMARY KEY,
    ambiente ENUM('homologacao','producao') NOT NULL DEFAULT 'homologacao',
    cpf_cnpj_interessado VARCHAR(14) NULL COMMENT 'CNPJ/CPF do certificado ou IP',
    cert_pem_path VARCHAR(500) NULL COMMENT 'Caminho absoluto ou relativo ao cert PEM (client TLS)',
    cert_key_path VARCHAR(500) NULL COMMENT 'Chave privada PEM',
    cert_key_pass VARCHAR(255) NULL COMMENT 'Senha da chave (preferir env SF_PEF_CERT_PASS)',
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pef_ciot_operacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    rota_id INT NULL,
    id_operacao_transporte VARCHAR(12) NOT NULL COMMENT '12 dígitos — gerador ANTT',
    codigo_identificacao VARCHAR(16) NULL COMMENT 'CIOT + dígito verificador (16)',
    codigo_verificador CHAR(4) NULL,
    tipo_operacao TINYINT NOT NULL DEFAULT 1 COMMENT '1=Lotação 2=Fracionada 3=TAC-Agregado',
    status ENUM('rascunho','declarado','cancelado','encerrado','retificado','erro') NOT NULL DEFAULT 'rascunho',
    cpf_cnpj_contratado VARCHAR(14) NULL,
    rntrc_contratado VARCHAR(9) NULL,
    cpf_cnpj_contratante VARCHAR(14) NULL,
    cpf_cnpj_destinatario VARCHAR(14) NULL,
    valor_frete DECIMAL(12,2) NULL,
    data_declaracao DATETIME NULL,
    data_inicio_viagem DATE NULL,
    data_fim_viagem DATE NULL,
    ind_contingencia TINYINT(1) NOT NULL DEFAULT 0,
    payload_envio JSON NULL,
    payload_resposta JSON NULL,
    protocolo_antt VARCHAR(16) NULL,
    codigo_antt VARCHAR(20) NULL,
    mensagem_antt VARCHAR(500) NULL,
    aviso_transportador VARCHAR(300) NULL,
    motivo_cancelamento VARCHAR(500) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pef_id_op (empresa_id, id_operacao_transporte),
    KEY idx_pef_empresa_status (empresa_id, status),
    KEY idx_pef_rota (rota_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pef_ciot_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    operacao_id INT UNSIGNED NULL,
    servico VARCHAR(64) NOT NULL,
    request_json JSON NULL,
    response_json JSON NULL,
    http_code SMALLINT NULL,
    erro TEXT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pef_log_empresa (empresa_id, criado_em),
    KEY idx_pef_log_op (operacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
