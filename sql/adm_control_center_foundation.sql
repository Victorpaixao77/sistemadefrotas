-- Control Center ADM — fundação de dados
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_control_center_foundation.sql

-- Lifecycle SaaS em empresa_adm (idempotente via app em adm_schema.php)

CREATE TABLE IF NOT EXISTS adm_eventos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    empresa_cliente_id INT NULL,
    empresa_adm_id INT NULL,
    usuario_id INT NULL,
    admin_id INT NULL,
    modulo VARCHAR(64) NOT NULL DEFAULT 'sistema',
    tipo VARCHAR(64) NOT NULL DEFAULT 'info',
    severidade ENUM('info','aviso','erro','critico') NOT NULL DEFAULT 'info',
    mensagem VARCHAR(500) NOT NULL,
    meta JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_modulo (modulo),
    INDEX idx_tipo (tipo),
    INDEX idx_empresa_cliente (empresa_cliente_id),
    INDEX idx_severidade (severidade)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_sessoes_ativas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL,
    usuario_id INT NULL,
    empresa_cliente_id INT NULL,
    empresa_adm_id INT NULL,
    pagina VARCHAR(255) NULL,
    user_agent VARCHAR(500) NULL,
    ip_address VARCHAR(45) NULL,
    cidade VARCHAR(120) NULL,
    ultimo_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    iniciado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_session (session_id),
    INDEX idx_ping (ultimo_ping),
    INDEX idx_empresa (empresa_cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_alertas (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    regra VARCHAR(64) NOT NULL,
    prioridade ENUM('info','aviso','critico','emergencia') NOT NULL DEFAULT 'info',
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    empresa_adm_id INT NULL,
    status ENUM('aberto','reconhecido','resolvido') NOT NULL DEFAULT 'aberto',
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolvido_em TIMESTAMP NULL,
    INDEX idx_status (status),
    INDEX idx_prioridade (prioridade),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_health_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    servico VARCHAR(64) NOT NULL,
    status ENUM('ok','degradado','falha','desconhecido') NOT NULL DEFAULT 'desconhecido',
    latencia_ms INT NULL,
    detalhe VARCHAR(500) NULL,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_servico (servico),
    INDEX idx_checked (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_request_metrics (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rota VARCHAR(255) NOT NULL,
    modulo VARCHAR(64) NULL,
    duracao_ms INT NOT NULL,
    memoria_kb INT NULL,
    empresa_cliente_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rota (rota),
    INDEX idx_modulo (modulo),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_roadmap (
    id INT AUTO_INCREMENT PRIMARY KEY,
    versao VARCHAR(32) NOT NULL,
    titulo VARCHAR(200) NOT NULL,
    descricao TEXT NULL,
    status ENUM('concluido','desenvolvimento','planejado','beta') NOT NULL DEFAULT 'planejado',
    progresso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_ordem (ordem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_comunicacoes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    canal ENUM('email','whatsapp','push','banner','aviso') NOT NULL DEFAULT 'aviso',
    titulo VARCHAR(200) NOT NULL,
    mensagem TEXT NOT NULL,
    empresa_adm_id INT NULL COMMENT 'NULL = global',
    agendado_para DATETIME NULL,
    enviado_em DATETIME NULL,
    status ENUM('rascunho','agendado','enviado','cancelado') NOT NULL DEFAULT 'rascunho',
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_agendado (agendado_para)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_auditoria_suporte (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    usuario_id INT NOT NULL,
    empresa_cliente_id INT NOT NULL,
    acao VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    encerrado_em TIMESTAMP NULL,
    INDEX idx_admin (admin_id),
    INDEX idx_empresa (empresa_cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_ia_usage (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    empresa_cliente_id INT NULL,
    modelo VARCHAR(64) NULL,
    tokens_in INT NULL,
    tokens_out INT NULL,
    duracao_ms INT NULL,
    custo_estimado DECIMAL(10,4) NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_cliente_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
