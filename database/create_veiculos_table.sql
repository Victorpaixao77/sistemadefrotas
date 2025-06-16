-- Criação da tabela veiculos
CREATE TABLE IF NOT EXISTS veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    placa VARCHAR(8) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(50) NOT NULL,
    ano INT NOT NULL,
    status_id INT NOT NULL DEFAULT 1,
    motorista_id INT,
    quilometragem DECIMAL(10,2) DEFAULT 0,
    chassi VARCHAR(50),
    renavam VARCHAR(11),
    cor VARCHAR(30),
    capacidade_carga DECIMAL(10,2),
    tipo_combustivel VARCHAR(30),
    data_aquisicao DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresa_clientes(id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id),
    FOREIGN KEY (status_id) REFERENCES status_veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 