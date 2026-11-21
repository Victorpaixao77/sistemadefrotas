-- 2FA TOTP para login do app motorista (usuarios_motoristas)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/motorista_app_2fa.sql

ALTER TABLE `usuarios_motoristas`
    ADD COLUMN IF NOT EXISTS `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `two_factor_secret` VARCHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS `two_factor_confirmed_at` DATETIME NULL;
