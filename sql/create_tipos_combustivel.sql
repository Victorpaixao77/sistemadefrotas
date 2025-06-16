-- Create tipos_combustivel table
CREATE TABLE IF NOT EXISTS tipos_combustivel (
    id int(11) NOT NULL AUTO_INCREMENT,
    nome varchar(50) NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default fuel types
INSERT INTO tipos_combustivel (id, nome) VALUES
(1, 'Gasolina'),
(2, 'Diesel'),
(3, 'Etanol'),
(4, 'GNV'),
(5, 'Elétrico'); 