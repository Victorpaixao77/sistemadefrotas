-- Control Center ADM — Fase 2 (Deploy, DB, Jobs, Licenciamento, Marketplace, etc.)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_control_center_phase2.sql

CREATE TABLE IF NOT EXISTS adm_deploys (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ambiente ENUM('producao','homologacao','desenvolvimento') NOT NULL DEFAULT 'producao',
    versao VARCHAR(32) NOT NULL,
    git_hash VARCHAR(64) NULL,
    git_autor VARCHAR(120) NULL,
    git_mensagem VARCHAR(500) NULL,
    admin_id INT NULL,
    admin_nome VARCHAR(120) NULL,
    duracao_seg INT NULL,
    arquivos_alterados INT NULL DEFAULT 0,
    sql_aplicado TINYINT(1) NOT NULL DEFAULT 0,
    rollback_disponivel TINYINT(1) NOT NULL DEFAULT 0,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ambiente (ambiente),
    INDEX idx_created (created_at),
    INDEX idx_hash (git_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_sql_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('SELECT','INSERT','UPDATE','DELETE','OTHER') NOT NULL DEFAULT 'OTHER',
    rota VARCHAR(255) NULL,
    modulo VARCHAR(64) NULL,
    duracao_ms INT NOT NULL,
    empresa_cliente_id INT NULL,
    detalhe VARCHAR(500) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created (created_at),
    INDEX idx_duracao (duracao_ms),
    INDEX idx_modulo (modulo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    script_path VARCHAR(255) NOT NULL,
    schedule_expr VARCHAR(64) NULL COMMENT 'cron expression ou manual',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_cron_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    status ENUM('executando','pendente','concluido','falhou') NOT NULL DEFAULT 'pendente',
    duracao_ms INT NULL,
    mensagem TEXT NULL,
    iniciado_em TIMESTAMP NULL,
    concluido_em TIMESTAMP NULL,
    proxima_execucao DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job (job_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    FOREIGN KEY (job_id) REFERENCES adm_cron_jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_integration_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    integracao VARCHAR(64) NOT NULL,
    status ENUM('ok','degradado','falha','desconhecido') NOT NULL DEFAULT 'desconhecido',
    latencia_ms INT NULL,
    erro VARCHAR(500) NULL,
    limite_diario INT NULL,
    uso_hoje INT NULL DEFAULT 0,
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_integracao (integracao),
    INDEX idx_checked (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_licenca_limites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    recurso ENUM('veiculos','motoristas','usuarios','ia','storage_mb','api_calls') NOT NULL,
    limite_valor INT NULL COMMENT 'NULL = ilimitado',
    uso_valor INT NOT NULL DEFAULT 0,
    overage_valor DECIMAL(10,2) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_recurso (empresa_adm_id, recurso),
    INDEX idx_empresa (empresa_adm_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_empresa_scores (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    nota_geral TINYINT UNSIGNED NOT NULL DEFAULT 0,
    seguranca TINYINT UNSIGNED NOT NULL DEFAULT 0,
    uso TINYINT UNSIGNED NOT NULL DEFAULT 0,
    financeiro TINYINT UNSIGNED NOT NULL DEFAULT 0,
    performance TINYINT UNSIGNED NOT NULL DEFAULT 0,
    treinamento TINYINT UNSIGNED NOT NULL DEFAULT 0,
    explicacao TEXT NULL,
    calculado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_empresa (empresa_adm_id),
    INDEX idx_nota (nota_geral)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_custos_mensais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(64) NOT NULL,
    valor DECIMAL(12,2) NOT NULL DEFAULT 0,
    referencia_mes CHAR(7) NOT NULL COMMENT 'YYYY-MM',
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cat_mes (categoria, referencia_mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    descricao TEXT NULL,
    icone VARCHAR(64) NULL DEFAULT 'fa-puzzle-piece',
    ativo_global TINYINT(1) NOT NULL DEFAULT 1,
    ordem INT NOT NULL DEFAULT 0,
    UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_empresa_modulos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    modulo_id INT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ativado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_modulo (empresa_adm_id, modulo_id),
    FOREIGN KEY (modulo_id) REFERENCES adm_modulos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_platform_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    chave VARCHAR(64) NOT NULL,
    valor TEXT NULL,
    categoria VARCHAR(32) NOT NULL DEFAULT 'sistema',
    sensivel TINYINT(1) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
