-- Central de Deploy Inteligente (Produção) — Control Center ADM
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_deploy_producao.sql

CREATE TABLE IF NOT EXISTS adm_deploy_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    ambiente ENUM('desenvolvimento','homologacao','staging','producao') NOT NULL DEFAULT 'producao',
    host VARCHAR(255) NOT NULL,
    porta_ssh INT NOT NULL DEFAULT 22,
    usuario VARCHAR(64) NOT NULL,
    project_path VARCHAR(500) NOT NULL,
    git_path VARCHAR(500) NULL,
    git_branch VARCHAR(120) NOT NULL DEFAULT 'main',
    auth_method ENUM('password','ssh_key') NOT NULL DEFAULT 'ssh_key',
    ssh_key_enc TEXT NULL,
    password_enc TEXT NULL,
    homolog_path VARCHAR(500) NULL COMMENT 'Caminho local homologação (origem)',
    db_host VARCHAR(255) NULL,
    db_port INT NULL DEFAULT 3306,
    db_name VARCHAR(120) NULL,
    db_user VARCHAR(120) NULL,
    db_pass_enc TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    ultima_sync DATETIME NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ambiente (ambiente),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_deploy_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    tipo ENUM('deploy','rollback','backup','compare') NOT NULL DEFAULT 'deploy',
    status ENUM('pending','running','success','failed','cancelled','rolled_back') NOT NULL DEFAULT 'pending',
    progress_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    step_atual VARCHAR(64) NULL,
    git_hash VARCHAR(64) NULL,
    git_tag VARCHAR(64) NULL,
    versao VARCHAR(32) NULL,
    admin_id INT NULL,
    admin_nome VARCHAR(120) NULL,
    arquivos_enviados INT NULL DEFAULT 0,
    arquivos_removidos INT NULL DEFAULT 0,
    sql_aplicado TINYINT(1) NOT NULL DEFAULT 0,
    backup_id BIGINT NULL,
    erro_msg TEXT NULL,
    report_json JSON NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    duracao_seg INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_server (server_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    CONSTRAINT fk_deploy_run_server FOREIGN KEY (server_id) REFERENCES adm_deploy_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_deploy_run_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT NOT NULL,
    step_key VARCHAR(64) NOT NULL,
    step_label VARCHAR(200) NOT NULL,
    status ENUM('pending','running','ok','warn','error','skipped') NOT NULL DEFAULT 'pending',
    mensagem TEXT NULL,
    duracao_ms INT NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_run (run_id),
    CONSTRAINT fk_deploy_log_run FOREIGN KEY (run_id) REFERENCES adm_deploy_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_deploy_backups (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    run_id BIGINT NULL,
    tipo ENUM('full','database','files','config') NOT NULL DEFAULT 'full',
    storage_path VARCHAR(500) NOT NULL,
    size_bytes BIGINT NULL DEFAULT 0,
    status ENUM('pending','ok','failed') NOT NULL DEFAULT 'pending',
    admin_id INT NULL,
    versao VARCHAR(32) NULL,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_server (server_id),
    INDEX idx_run (run_id),
    CONSTRAINT fk_deploy_backup_server FOREIGN KEY (server_id) REFERENCES adm_deploy_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adm_deploy_comparisons (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    server_id INT NOT NULL,
    tipo ENUM('files','database') NOT NULL,
    summary_json JSON NOT NULL,
    detail_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_server_tipo (server_id, tipo),
    CONSTRAINT fk_deploy_cmp_server FOREIGN KEY (server_id) REFERENCES adm_deploy_servers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
