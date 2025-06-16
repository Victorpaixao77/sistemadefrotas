-- Create tipos_veiculos table
CREATE TABLE IF NOT EXISTS tipos_veiculos (
    id int(11) NOT NULL AUTO_INCREMENT,
    nome varchar(50) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default vehicle types
INSERT INTO tipos_veiculos (id, nome) VALUES
(1, 'Carro'),
(2, 'Moto'),
(3, 'Caminhão'),
(4, 'Ônibus'),
(5, 'Outro'); 