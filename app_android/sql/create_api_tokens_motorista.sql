-- Tokens de API para o app Android (motoristas)
-- Execute no banco sistema_frotas. Instalações antigas: use também alter_api_tokens_refresh.sql

CREATE TABLE IF NOT EXISTS api_tokens_motorista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    token VARCHAR(64) NOT NULL COMMENT 'Access token (Bearer)',
    refresh_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'Renovação do access',
    criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL COMMENT 'Expiração do access token',
    refresh_expira_em DATETIME NULL DEFAULT NULL COMMENT 'Expiração do refresh',
    UNIQUE KEY uk_token (token),
    UNIQUE KEY uk_refresh_token (refresh_token),
    KEY idx_motorista (motorista_id),
    KEY idx_expira (expira_em),
    KEY idx_refresh_expira (refresh_expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
