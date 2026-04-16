-- Separa token de acesso (curto) e refresh (longo). Execute após create_api_tokens_motorista.sql.
-- MySQL 5.7+ / MariaDB 10.2+

ALTER TABLE api_tokens_motorista
    ADD COLUMN refresh_token VARCHAR(64) NULL DEFAULT NULL COMMENT 'Token só para renovar access' AFTER token,
    ADD COLUMN refresh_expira_em DATETIME NULL DEFAULT NULL COMMENT 'Validade do refresh' AFTER expira_em;

-- Sessões antigas: tratar o token atual como refresh até o utilizador voltar a fazer login “limpo”.
UPDATE api_tokens_motorista
SET refresh_token = token,
    refresh_expira_em = expira_em
WHERE refresh_token IS NULL;

CREATE UNIQUE INDEX uk_refresh_token ON api_tokens_motorista (refresh_token);
