-- First ensure the status_veiculos table exists
CREATE TABLE IF NOT EXISTS status_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default status values if they don't exist
INSERT IGNORE INTO status_veiculos (id, nome, descricao) VALUES
(1, 'Ativo', 'Veículo em operação normal'),
(2, 'Manutenção', 'Veículo em manutenção'),
(3, 'Inativo', 'Veículo temporariamente fora de operação'),
(4, 'Vendido', 'Veículo vendido'),
(5, 'Em Viagem', 'Veículo em viagem');

-- Add status_id column if it doesn't exist
ALTER TABLE veiculos
ADD COLUMN IF NOT EXISTS status_id INT NOT NULL DEFAULT 1 AFTER status;

-- Update status_id based on the old status ENUM value
UPDATE veiculos
SET status_id = CASE 
    WHEN status = 'Ativo' THEN 1
    WHEN status = 'Manutenção' THEN 2
    WHEN status = 'Inativo' THEN 3
    ELSE 1
END;

-- Add foreign key constraint
ALTER TABLE veiculos
ADD CONSTRAINT fk_veiculos_status
FOREIGN KEY (status_id) REFERENCES status_veiculos(id);

-- Drop the old status column
ALTER TABLE veiculos
DROP COLUMN status; 