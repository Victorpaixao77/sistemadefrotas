-- Criar tabela de pneus
CREATE TABLE IF NOT EXISTS pneus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT NOT NULL,
    posicao VARCHAR(20) NOT NULL,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    numero_serie VARCHAR(50),
    sulco DECIMAL(4,1) NOT NULL,
    pressao DECIMAL(4,1) NOT NULL,
    temperatura DECIMAL(4,1),
    data_instalacao DATE NOT NULL,
    data_troca DATE,
    ultima_rotacao DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir alguns dados de exemplo
INSERT INTO pneus (veiculo_id, posicao, marca, modelo, numero_serie, sulco, pressao, temperatura, data_instalacao, data_troca, ultima_rotacao) VALUES
(1, 'dianteiro_1', 'Michelin', 'X Line Energy', 'M123456', 6.5, 35.0, 32.0, '2024-01-01', '2024-10-10', '2024-03-01'),
(1, 'dianteiro_2', 'Michelin', 'X Line Energy', 'M123457', 6.4, 35.0, 32.0, '2024-01-01', '2024-10-10', '2024-03-01'),
(1, 'tandem_1', 'Pirelli', 'FH01', 'P123456', 2.5, 32.0, 35.0, '2023-06-01', '2023-12-01', '2023-09-01'),
(1, 'tandem_2', 'Pirelli', 'FH01', 'P123457', 2.4, 32.0, 35.0, '2023-06-01', '2023-12-01', '2023-09-01'),
(1, 'tandem_3', 'Goodyear', 'Fuelmax', 'G123456', 1.0, 15.0, 28.0, '2022-01-01', '2022-05-10', '2022-02-01'),
(1, 'tandem_4', 'Goodyear', 'Fuelmax', 'G123457', 7.0, 34.0, 30.0, '2024-01-01', '2024-01-01', '2024-01-01'),
(1, 'tandem_5', 'Goodyear', 'Fuelmax', 'G123458', 6.9, 34.0, 30.0, '2024-01-01', '2024-01-01', '2024-01-01'),
(1, 'tandem_6', 'Goodyear', 'Fuelmax', 'G123459', 7.1, 34.0, 30.0, '2024-01-01', '2024-01-01', '2024-01-01'),
(1, 'tandem_7', 'Continental', 'EcoPlus', 'C123456', 2.0, 31.0, 33.0, '2022-05-01', '2022-08-15', '2022-06-01'),
(1, 'tandem_8', 'Continental', 'EcoPlus', 'C123457', 6.8, 33.0, 33.0, '2024-01-01', '2024-03-15', '2024-01-01'),
(1, 'traseiro_1', 'Continental', 'EcoPlus', 'C123458', 6.8, 33.0, 33.0, '2024-01-01', '2024-03-15', '2024-01-01'),
(1, 'traseiro_2', 'Continental', 'EcoPlus', 'C123459', 6.9, 33.0, 33.0, '2024-01-01', '2024-03-15', '2024-01-01'); 