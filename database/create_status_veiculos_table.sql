-- Create status_veiculos table if it doesn't exist
CREATE TABLE IF NOT EXISTS status_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default status values if they don't exist
INSERT IGNORE INTO status_veiculos (nome, descricao) VALUES
('Ativo', 'Veículo em operação normal'),
('Manutenção', 'Veículo em manutenção'),
('Inativo', 'Veículo temporariamente fora de operação'),
('Vendido', 'Veículo vendido'),
('Em Viagem', 'Veículo em viagem'); 