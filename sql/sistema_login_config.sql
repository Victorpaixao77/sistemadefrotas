-- Configurações globais do login (compartilhado entre todas as empresas/clientes).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/sistema_login_config.sql

CREATE TABLE IF NOT EXISTS sistema_login_config (
    id INT NOT NULL PRIMARY KEY DEFAULT 1,
    recaptcha_enabled TINYINT(1) NOT NULL DEFAULT 0,
    recaptcha_site_key VARCHAR(120) NULL,
    recaptcha_secret_key VARCHAR(255) NULL,
    recaptcha_apos_tentativas INT NOT NULL DEFAULT 3,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO sistema_login_config (id) VALUES (1);
