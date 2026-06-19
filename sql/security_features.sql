-- =====================================================================
-- Recursos de segurança: 2FA, políticas, tentativas de login, whitelist
-- de IP, geo-bloqueio e detecção de novo acesso.
-- (MariaDB suporta ADD COLUMN IF NOT EXISTS — seguro reexecutar.)
-- =====================================================================

-- 2FA por usuário (TOTP)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL;

-- Políticas de segurança por empresa
CREATE TABLE IF NOT EXISTS configuracoes_seguranca (
    empresa_id INT PRIMARY KEY,
    idle_timeout_min INT NOT NULL DEFAULT 0,              -- 0 = desativado
    login_max_tentativas INT NOT NULL DEFAULT 5,
    login_bloqueio_min INT NOT NULL DEFAULT 15,
    senha_forte TINYINT(1) NOT NULL DEFAULT 1,
    two_factor_obrigatorio TINYINT(1) NOT NULL DEFAULT 0,
    recaptcha_enabled TINYINT(1) NOT NULL DEFAULT 0,
    recaptcha_site_key VARCHAR(120) NULL,
    recaptcha_secret_key VARCHAR(120) NULL,
    recaptcha_apos_tentativas INT NOT NULL DEFAULT 3,
    novo_acesso_email TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(160) NULL,
    smtp_port INT NULL,
    smtp_user VARCHAR(160) NULL,
    smtp_pass VARCHAR(255) NULL,
    smtp_secure VARCHAR(10) NULL,                         -- '', 'ssl', 'tls'
    smtp_from VARCHAR(160) NULL,
    ip_whitelist_enabled TINYINT(1) NOT NULL DEFAULT 0,
    geo_block_enabled TINYINT(1) NOT NULL DEFAULT 0,
    geo_paises_permitidos VARCHAR(255) NOT NULL DEFAULT 'BR',
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tentativas de login (bloqueio persistente por e-mail+IP + gatilho reCAPTCHA)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    tentativas INT NOT NULL DEFAULT 0,
    bloqueado_ate DATETIME NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_attempt (email, ip),
    KEY idx_login_bloqueio (bloqueado_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Whitelist de IP para administradores
CREATE TABLE IF NOT EXISTS admin_ip_whitelist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    descricao VARCHAR(160) NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ipwl_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dispositivos/locais conhecidos (detecção de novo acesso)
CREATE TABLE IF NOT EXISTS acessos_conhecidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    user_agent_hash CHAR(40) NOT NULL,
    cidade VARCHAR(120) NULL,
    pais VARCHAR(8) NULL,
    primeiro_acesso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acesso TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_acesso_dev (usuario_id, ip, user_agent_hash),
    KEY idx_acesso_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
