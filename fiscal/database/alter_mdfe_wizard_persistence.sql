-- Persistência estruturada dos grupos do wizard MDF-e

CREATE TABLE IF NOT EXISTS fiscal_mdfe_ciot (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    numero_ciot VARCHAR(60) NULL,
    valor_frete DECIMAL(15,2) NULL,
    cpf_cnpj_tac VARCHAR(20) NULL,
    ipef VARCHAR(120) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_vale_pedagio (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    eixos INT NULL,
    valor DECIMAL(15,2) NULL,
    tipo VARCHAR(80) NULL,
    cnpj_fornecedor VARCHAR(20) NULL,
    numero_comprovante VARCHAR(100) NULL,
    responsavel_pagamento VARCHAR(20) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_contratantes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    tipo_pessoa VARCHAR(20) NULL,
    documento VARCHAR(30) NULL,
    razao_social VARCHAR(255) NULL,
    numero_contrato VARCHAR(80) NULL,
    valor DECIMAL(15,2) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_pagamentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    tipo_pessoa VARCHAR(20) NULL,
    documento VARCHAR(30) NULL,
    razao_social VARCHAR(255) NULL,
    considerar_componentes TINYINT(1) NOT NULL DEFAULT 0,
    valor_total_contrato DECIMAL(15,2) NULL,
    indicador_forma_pagamento VARCHAR(30) NULL,
    forma_financiamento VARCHAR(100) NULL,
    alto_desempenho VARCHAR(30) NULL,
    tipo_pagamento VARCHAR(60) NULL,
    indicador_status_pagamento VARCHAR(30) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_pagamento_componentes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    pagamento_id BIGINT NULL,
    empresa_id INT NOT NULL,
    codigo VARCHAR(10) NULL,
    tipo VARCHAR(120) NULL,
    valor DECIMAL(15,2) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_pagamento (pagamento_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_seguros (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    responsavel VARCHAR(255) NULL,
    cpf_cnpj_responsavel VARCHAR(20) NULL,
    emitente VARCHAR(255) NULL,
    cnpj_seguradora VARCHAR(20) NULL,
    nome_seguradora VARCHAR(255) NULL,
    tomador_contratante VARCHAR(255) NULL,
    numero_apolice VARCHAR(80) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_seguros_averbacoes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    seguro_id BIGINT NULL,
    empresa_id INT NOT NULL,
    numero_averbacao VARCHAR(80) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_seguro (seguro_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_produtos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    tipo_carga VARCHAR(120) NULL,
    descricao_produto VARCHAR(255) NULL,
    gtin VARCHAR(20) NULL,
    ncm VARCHAR(12) NULL,
    carga_lotacao VARCHAR(5) NULL,
    local_carregamento_cep VARCHAR(12) NULL,
    local_descarregamento_cep VARCHAR(12) NULL,
    cep_descarregamento VARCHAR(12) NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_lacres (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    numero_lacre VARCHAR(80) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fiscal_mdfe_autorizados_download (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mdfe_id BIGINT NOT NULL,
    empresa_id INT NOT NULL,
    documento VARCHAR(20) NOT NULL,
    motorista VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mdfe (mdfe_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

