-- Create tipos (vehicle types) table
CREATE TABLE IF NOT EXISTS tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create tipos_combustivel (fuel types) table
CREATE TABLE IF NOT EXISTS tipos_combustivel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create categorias (vehicle categories) table
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create carrocerias (body types) table
CREATE TABLE IF NOT EXISTS carrocerias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create status table
CREATE TABLE IF NOT EXISTS status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default data
INSERT INTO tipos (nome) VALUES 
('Truck'),
('Van'),
('Car'),
('Bus'),
('Motorcycle');

INSERT INTO tipos_combustivel (nome) VALUES 
('Diesel'),
('Gasoline'),
('Ethanol'),
('Electric'),
('Hybrid');

INSERT INTO categorias (nome) VALUES 
('Light'),
('Medium'),
('Heavy'),
('Special');

INSERT INTO carrocerias (nome) VALUES 
('Box'),
('Platform'),
('Tank'),
('Refrigerated'),
('Container');

INSERT INTO status (nome) VALUES 
('Active'),
('Inactive'),
('Maintenance'),
('Reserved'); 