-- Corrige tipo ENUM legado que impedia inserir notificacoes plataforma/cnh/etc.
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/notificacoes_tipo_varchar.sql

ALTER TABLE notificacoes MODIFY COLUMN tipo VARCHAR(64) NOT NULL DEFAULT 'alerta';
