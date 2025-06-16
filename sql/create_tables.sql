-- Create tipos_veiculos table
CREATE TABLE IF NOT EXISTS tipos_veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    empresa_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create categorias table
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    empresa_id INT NOT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create carrocerias table
CREATE TABLE IF NOT EXISTS carrocerias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default carrocerias if table is empty
INSERT INTO carrocerias (nome) 
SELECT * FROM (
    SELECT 'Baú' AS nome UNION ALL
    SELECT 'Graneleiro' UNION ALL
    SELECT 'Tanque' UNION ALL
    SELECT 'Plataforma' UNION ALL
    SELECT 'Sider' UNION ALL
    SELECT 'Basculante'
) AS tmp
WHERE NOT EXISTS (
    SELECT 1 FROM carrocerias LIMIT 1
);

-- Insert default tipos_veiculos if table is empty
INSERT INTO tipos_veiculos (nome, descricao) 
SELECT * FROM (
    SELECT 'Caminhão', 'Veículo de carga pesada' UNION ALL
    SELECT 'Van', 'Veículo de carga leve/passageiros' UNION ALL
    SELECT 'Ônibus', 'Veículo de passageiros' UNION ALL
    SELECT 'Utilitário', 'Veículo utilitário'
) AS tmp
WHERE NOT EXISTS (
    SELECT 1 FROM tipos_veiculos LIMIT 1
); 