-- IA por empresa com finalidade (purpose): 'vision' (leitura de fichas/notas) e
-- 'text' (resumos e textos do sistema). Permite provedor/chave/modelo distintos
-- por finalidade — ex.: Gemini para visão e DeepSeek para resumos.
--
-- Aplicar:
--   mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/vision_ai_purpose.sql
--
-- Idempotente: o app também aplica isto em runtime (sf_vision_ai_ensure_schema).

CREATE TABLE IF NOT EXISTS empresa_vision_ai (
    empresa_id INT UNSIGNED NOT NULL,
    purpose    VARCHAR(20)  NOT NULL DEFAULT 'vision',
    provider   VARCHAR(20)  NOT NULL DEFAULT 'gemini',
    api_key    MEDIUMTEXT   NULL,
    model      VARCHAR(80)  NULL,
    base_url   VARCHAR(255) NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (empresa_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migração de instalações antigas (PK era só empresa_id, sem coluna purpose).
-- Em MySQL não há "ADD COLUMN IF NOT EXISTS" universal; rode os ALTERs abaixo
-- apenas se a tabela já existia sem a coluna `purpose` (ignore os erros de
-- "Duplicate column" / "Multiple primary key" se já estiver migrada):
--
-- ALTER TABLE empresa_vision_ai ADD COLUMN purpose VARCHAR(20) NOT NULL DEFAULT 'vision' AFTER empresa_id;
-- ALTER TABLE empresa_vision_ai DROP PRIMARY KEY, ADD PRIMARY KEY (empresa_id, purpose);
