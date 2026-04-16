-- Períodos de disponibilidade (férias, licenças, etc.) para calendário
CREATE TABLE IF NOT EXISTS motorista_periodos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    tipo ENUM('ferias','licenca','afastamento','outro') NOT NULL DEFAULT 'outro',
    data_inicio DATE NOT NULL,
    data_fim DATE NOT NULL,
    observacao VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_motorista (motorista_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_periodo (data_inicio, data_fim),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
