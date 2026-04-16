-- Favoritos: motoristas marcados como favoritos por usuário
CREATE TABLE IF NOT EXISTS motorista_favoritos (
    usuario_id INT NOT NULL,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, motorista_id),
    INDEX idx_usuario (usuario_id),
    INDEX idx_motorista (motorista_id),
    INDEX idx_empresa (empresa_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
