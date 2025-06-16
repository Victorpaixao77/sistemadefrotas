-- Create categorias_veiculos table
CREATE TABLE IF NOT EXISTS categorias_veiculos (
    id int(11) NOT NULL AUTO_INCREMENT,
    nome varchar(50) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default categories
INSERT INTO categorias_veiculos (id, nome) VALUES
(1, 'Leve'),
(2, 'Pesado'),
(3, 'Passeio'),
(4, 'Escolar'),
(5, 'Caminhão'),
(6, 'Utilitário'),
(7, 'Especial'); 