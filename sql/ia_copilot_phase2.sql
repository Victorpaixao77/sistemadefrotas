-- Centro de Inteligência — Fase 2 (Tools, métricas, intenções custom)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/ia_copilot_phase2.sql

CREATE TABLE IF NOT EXISTS ia_uso_empresa (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    dia DATE NOT NULL,
    consultas INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_in INT UNSIGNED NOT NULL DEFAULT 0,
    tokens_out INT UNSIGNED NOT NULL DEFAULT 0,
    cache_hits INT UNSIGNED NOT NULL DEFAULT 0,
    llm_calls INT UNSIGNED NOT NULL DEFAULT 0,
    falhas INT UNSIGNED NOT NULL DEFAULT 0,
    custo_estimado_usd DECIMAL(10,4) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ia_uso (empresa_id, dia),
    INDEX idx_ia_uso_dia (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_intencoes_custom (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NULL COMMENT 'NULL = todas empresas',
    padrao VARCHAR(255) NOT NULL,
    intent VARCHAR(64) NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_por INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_ic_empresa (empresa_id, ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_perguntas_pendentes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    pergunta TEXT NOT NULL,
    ocorrencias INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('pendente','mapeada','ignorada') NOT NULL DEFAULT 'pendente',
    intent_mapeada VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ia_pp_status (status, ocorrencias DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_benchmark_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    pergunta VARCHAR(500) NOT NULL,
    modelo VARCHAR(64) NOT NULL,
    intent_detectada VARCHAR(64) NULL,
    tempo_ms INT UNSIGNED NULL,
    tokens INT UNSIGNED NULL,
    precisao_pct TINYINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_bench (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Colunas extras em ia_auditoria (ignorar erro se já existir — aplicar manualmente se necessário)
-- ALTER TABLE ia_auditoria ADD COLUMN ferramentas JSON NULL AFTER fontes;
-- ALTER TABLE ia_auditoria ADD COLUMN raciocinio JSON NULL AFTER ferramentas;
-- ALTER TABLE ia_auditoria ADD COLUMN timeline JSON NULL AFTER raciocinio;
-- ALTER TABLE ia_auditoria ADD COLUMN cache_hit TINYINT(1) NOT NULL DEFAULT 0 AFTER tempo_ms;
-- ALTER TABLE ia_auditoria ADD COLUMN modelo VARCHAR(64) NULL AFTER cache_hit;
-- ALTER TABLE ia_auditoria ADD COLUMN falhou TINYINT(1) NOT NULL DEFAULT 0 AFTER modelo;
-- ALTER TABLE ia_auditoria ADD COLUMN linhas_lidas INT UNSIGNED NULL AFTER falhou;
