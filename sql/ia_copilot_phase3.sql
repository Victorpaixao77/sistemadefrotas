-- Centro de Inteligência — Fase 3 (roteador, métricas avançadas, suporte)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/ia_copilot_phase3.sql

CREATE TABLE IF NOT EXISTS ia_metricas_diarias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    dia DATE NOT NULL,
    intent VARCHAR(64) NOT NULL,
    consultas INT UNSIGNED NOT NULL DEFAULT 0,
    falhas INT UNSIGNED NOT NULL DEFAULT 0,
    tempo_ms_total INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_ia_md (empresa_id, dia, intent),
    INDEX idx_ia_md_dia (dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ia_suporte_traces (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NULL,
    pergunta VARCHAR(500) NOT NULL,
    intent VARCHAR(64) NULL,
    payload JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ia_st_empresa (empresa_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- purpose 'classify' na empresa_vision_ai (opcional — fallback para text/vision)
-- INSERT IGNORE não aplicável; gestor configura em Configurações quando quiser modelo barato dedicado.
