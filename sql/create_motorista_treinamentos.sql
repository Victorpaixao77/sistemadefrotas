-- Treinamentos / capacitações dos motoristas
CREATE TABLE IF NOT EXISTS motorista_treinamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    nome_curso VARCHAR(200) NOT NULL,
    instituicao VARCHAR(150) NULL,
    data_conclusao DATE NULL,
    carga_horaria INT NULL COMMENT 'Horas',
    certificado_arquivo VARCHAR(255) NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_motorista (motorista_id),
    INDEX idx_empresa (empresa_id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
