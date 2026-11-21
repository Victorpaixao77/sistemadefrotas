-- Backlog Control Center — 2FA admins, espelhamento IA
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_backlog_features.sql

ALTER TABLE usuarios_adm
    ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS two_factor_confirmed_at DATETIME NULL;
