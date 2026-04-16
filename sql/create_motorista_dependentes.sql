-- Dependentes / beneficiários (planos de saúde, etc.)
CREATE TABLE IF NOT EXISTS motorista_dependentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    parentesco VARCHAR(50) NULL COMMENT 'Cônjuge, Filho(a), etc.',
    data_nascimento DATE NULL,
    cpf VARCHAR(14) NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_motorista (motorista_id),
    INDEX idx_empresa (empresa_id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
