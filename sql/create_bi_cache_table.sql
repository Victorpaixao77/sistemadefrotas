-- =====================================================
-- Cache de indicadores do BI (performance)
-- Sistema de Gestão de Frotas
-- =====================================================
-- Uso: API performance_indicators.php grava resposta por
-- empresa + visão + ano + mês. TTL padrão 15 min.
-- =====================================================

CREATE TABLE IF NOT EXISTS bi_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    cache_key VARCHAR(120) NOT NULL,
    payload LONGTEXT NOT NULL,
    expires_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_bi_cache_key (empresa_id, cache_key),
    INDEX idx_expires (expires_at),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Cache de indicadores BI por empresa/visão/período';
