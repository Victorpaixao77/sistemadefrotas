-- Table: tipos_contrato (contract types)
CREATE TABLE IF NOT EXISTS tipos_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default contract types if not exists
INSERT IGNORE INTO tipos_contrato (nome) VALUES
('CLT'),
('PJ'),
('Autônomo'),
('Temporário');

-- Table: disponibilidades (availability status)
CREATE TABLE IF NOT EXISTS disponibilidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default availability status if not exists
INSERT IGNORE INTO disponibilidades (nome) VALUES
('Ativo'),
('Em Viagem'),
('Férias'),
('Licença'),
('Afastado');

-- Table: categorias_cnh (driver's license categories)
CREATE TABLE IF NOT EXISTS categorias_cnh (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(10) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default CNH categories if not exists
INSERT IGNORE INTO categorias_cnh (nome, descricao) VALUES
('A', 'Motocicletas e triciclos'),
('B', 'Carros de passeio'),
('C', 'Veículos de carga acima de 3.500 kg'),
('D', 'Veículos com mais de 8 passageiros'),
('E', 'Veículos com unidade acoplada acima de 6.000 kg'),
('AB', 'Combinação das categorias A e B'),
('AC', 'Combinação das categorias A e C'),
('AD', 'Combinação das categorias A e D'),
('AE', 'Combinação das categorias A e E');

-- Table: motoristas (drivers)
CREATE TABLE IF NOT EXISTS motoristas (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT(11) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    cnh VARCHAR(20),
    categoria_cnh_id INT(11),
    data_validade_cnh DATE,
    telefone VARCHAR(20),
    telefone_emergencia VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    data_contratacao DATE,
    tipo_contrato_id INT(11),
    disponibilidade_id INT(11) DEFAULT 1,
    porcentagem_comissao DECIMAL(5,2) DEFAULT 10.00,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (tipo_contrato_id) REFERENCES tipos_contrato(id),
    FOREIGN KEY (disponibilidade_id) REFERENCES disponibilidades(id),
    FOREIGN KEY (categoria_cnh_id) REFERENCES categorias_cnh(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 