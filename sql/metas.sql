-- Metas operacionais/financeiras (por motorista, veículo ou gerais).
-- Aplicar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/metas.sql
CREATE TABLE IF NOT EXISTS metas (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id    INT NOT NULL,
    titulo        VARCHAR(160) NOT NULL,
    tipo_alvo     ENUM('geral','motorista','veiculo') NOT NULL DEFAULT 'geral',
    alvo_id       INT NULL,                     -- id do motorista/veículo quando aplicável
    indicador     ENUM('faturamento','rotas','km','sla') NOT NULL DEFAULT 'faturamento',
    periodo_mes   VARCHAR(7) NOT NULL,          -- 'YYYY-MM'
    valor_meta    DECIMAL(14,2) NOT NULL DEFAULT 0,
    observacoes   VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metas_empresa (empresa_id),
    INDEX idx_metas_periodo (empresa_id, periodo_mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
