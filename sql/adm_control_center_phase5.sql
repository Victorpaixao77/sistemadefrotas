-- Control Center ADM — Fase 5 (storage cache, integração produto)
-- mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/adm_control_center_phase5.sql

CREATE TABLE IF NOT EXISTS adm_storage_usage (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    empresa_adm_id INT NOT NULL,
    total_mb INT NOT NULL DEFAULT 0,
    fotos_mb INT NOT NULL DEFAULT 0,
    documentos_mb INT NOT NULL DEFAULT 0,
    outros_mb INT NOT NULL DEFAULT 0,
    calculado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa (empresa_adm_id),
    INDEX idx_calculado (calculado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Roadmap: refletir progresso real (idempotente)
UPDATE adm_roadmap SET status = 'desenvolvimento', progresso = 78
    WHERE versao = '2.5.0' AND titulo LIKE '%Control Center%';
UPDATE adm_roadmap SET status = 'desenvolvimento', progresso = 55
    WHERE versao = '2.6.0';
UPDATE adm_roadmap SET status = 'desenvolvimento', progresso = 40
    WHERE versao = '2.7.0';
