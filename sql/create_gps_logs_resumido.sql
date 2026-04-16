-- Agregação diária por veículo (reduz retenção de gps_logs bruto após cron)
CREATE TABLE IF NOT EXISTS gps_logs_resumido (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    veiculo_id INT UNSIGNED NOT NULL,
    data DATE NOT NULL,
    total_pontos INT UNSIGNED NOT NULL DEFAULT 0,
    distancia_km DECIMAL(10,2) NOT NULL DEFAULT 0,
    tempo_movimento_seg INT UNSIGNED NOT NULL DEFAULT 0,
    tempo_parado_seg INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_emp_veic_data (empresa_id, veiculo_id, data),
    KEY idx_empresa_data (empresa_id, data)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
