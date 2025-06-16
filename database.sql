-- Criar banco de dados se não existir
CREATE DATABASE IF NOT EXISTS sistema_frotas;
USE sistema_frotas;

-- Tabela de empresas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    razao_social VARCHAR(255) NOT NULL,
    nome_fantasia VARCHAR(255),
    cnpj VARCHAR(18) NOT NULL UNIQUE,
    inscricao_estadual VARCHAR(20),
    telefone VARCHAR(15),
    email VARCHAR(255),
    endereco TEXT,
    cidade VARCHAR(100),
    estado CHAR(2),
    cep VARCHAR(9),
    responsavel VARCHAR(255),
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    empresa_id INT,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
);

-- Tabela de tipos de veículos
CREATE TABLE IF NOT EXISTS tipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de tipos de combustível
CREATE TABLE IF NOT EXISTS tipos_combustivel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de categorias de veículos
CREATE TABLE IF NOT EXISTS categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de carrocerias
CREATE TABLE IF NOT EXISTS carrocerias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de status
CREATE TABLE IF NOT EXISTS status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de veículos
CREATE TABLE IF NOT EXISTS veiculos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    placa VARCHAR(8) NOT NULL,
    modelo VARCHAR(100) NOT NULL,
    marca VARCHAR(100),
    ano INT,
    chassi VARCHAR(17),
    renavam VARCHAR(11),
    tipo_id INT,
    categoria_id INT,
    carroceria_id INT,
    tipo_combustivel_id INT,
    status_id INT,
    km_atual DECIMAL(10,2) DEFAULT 0,
    capacidade_carga DECIMAL(10,2),
    capacidade_passageiros INT,
    cor VARCHAR(50),
    numero_motor VARCHAR(50),
    potencia_motor VARCHAR(50),
    numero_eixos INT,
    proprietario VARCHAR(255),
    observacoes TEXT,
    foto_veiculo VARCHAR(255),
    documento VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (tipo_id) REFERENCES tipos(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id),
    FOREIGN KEY (carroceria_id) REFERENCES carrocerias(id),
    FOREIGN KEY (tipo_combustivel_id) REFERENCES tipos_combustivel(id),
    FOREIGN KEY (status_id) REFERENCES status(id)
);

-- Tabela de tipos de contrato
CREATE TABLE IF NOT EXISTS tipos_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de disponibilidades
CREATE TABLE IF NOT EXISTS disponibilidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de categorias CNH
CREATE TABLE IF NOT EXISTS categorias_cnh (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(5) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de motoristas
CREATE TABLE IF NOT EXISTS motoristas (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT(11) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    cnh VARCHAR(20),
    data_validade_cnh DATE,
    telefone VARCHAR(20),
    telefone_emergencia VARCHAR(20),
    email VARCHAR(255),
    endereco TEXT,
    data_contratacao DATE,
    observacoes TEXT,
    tipo_contrato_id INT(11),
    disponibilidade_id INT(11),
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    porcentagem_comissao DECIMAL(5,2) DEFAULT 10.00,
    categoria_cnh_id INT(11),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (tipo_contrato_id) REFERENCES tipos_contrato(id),
    FOREIGN KEY (disponibilidade_id) REFERENCES disponibilidades(id),
    FOREIGN KEY (categoria_cnh_id) REFERENCES categorias_cnh(id)
);

-- Tabela de rotas
CREATE TABLE IF NOT EXISTS rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    motorista_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    data DATETIME NOT NULL,
    origem VARCHAR(255) NOT NULL,
    destino VARCHAR(255) NOT NULL,
    km_percorrido DECIMAL(10,2) DEFAULT 0,
    consumo_combustivel DECIMAL(10,2) DEFAULT 0,
    avaliacao DECIMAL(3,1) DEFAULT 0,
    status_id INT NOT NULL,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id),
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
    FOREIGN KEY (status_id) REFERENCES status_rotas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: status_rotas (route status)
CREATE TABLE IF NOT EXISTS status_rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default route status
INSERT INTO status_rotas (nome) VALUES
('Agendada'),
('Em Andamento'),
('Concluída'),
('Cancelada');

-- Table: documentos_motoristas (driver documents)
CREATE TABLE IF NOT EXISTS documentos_motoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    motorista_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    arquivo VARCHAR(255) NOT NULL,
    data_validade DATE,
    status VARCHAR(50) DEFAULT 'Válido',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add new columns to motoristas table if they don't exist
ALTER TABLE motoristas
ADD COLUMN IF NOT EXISTS data_validade_cnh DATE AFTER cnh,
ADD COLUMN IF NOT EXISTS telefone_emergencia VARCHAR(20) AFTER telefone,
ADD COLUMN IF NOT EXISTS tipo_contrato_id INT AFTER data_contratacao,
ADD COLUMN IF NOT EXISTS disponibilidade_id INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS categoria_cnh_id INT AFTER cnh,
ADD FOREIGN KEY IF NOT EXISTS (tipo_contrato_id) REFERENCES tipos_contrato(id),
ADD FOREIGN KEY IF NOT EXISTS (disponibilidade_id) REFERENCES disponibilidades(id),
ADD FOREIGN KEY IF NOT EXISTS (categoria_cnh_id) REFERENCES categorias_cnh(id);

-- Table: tipos_contrato (contract types)
CREATE TABLE IF NOT EXISTS tipos_contrato (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default contract types
INSERT INTO tipos_contrato (nome) VALUES
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

-- Insert default availability status
INSERT INTO disponibilidades (nome) VALUES
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

-- Insert default CNH categories
INSERT INTO categorias_cnh (nome, descricao) VALUES
('A', 'Motocicletas e triciclos'),
('B', 'Carros de passeio'),
('C', 'Veículos de carga acima de 3.500 kg'),
('D', 'Veículos com mais de 8 passageiros'),
('E', 'Veículos com unidade acoplada acima de 6.000 kg'),
('AB', 'Combinação das categorias A e B'),
('AC', 'Combinação das categorias A e C'),
('AD', 'Combinação das categorias A e D'),
('AE', 'Combinação das categorias A e E');

-- Inserir dados básicos
INSERT INTO tipos (nome) VALUES 
('Caminhão'),
('Van'),
('Carro'),
('Ônibus'),
('Motocicleta');

INSERT INTO tipos_combustivel (nome) VALUES 
('Diesel'),
('Gasolina'),
('Álcool'),
('Flex'),
('GNV');

INSERT INTO categorias (nome) VALUES 
('Leve'),
('Médio'),
('Pesado'),
('Extra Pesado');

INSERT INTO carrocerias (nome) VALUES 
('Baú'),
('Graneleiro'),
('Tanque'),
('Plataforma'),
('Sider');

INSERT INTO status (nome) VALUES 
('Ativo'),
('Manutenção'),
('Inativo'),
('Vendido'),
('Em Viagem');

-- Inserir empresa de exemplo
INSERT INTO empresas (razao_social, nome_fantasia, cnpj, status) 
VALUES ('Empresa Exemplo LTDA', 'Empresa Exemplo', '00.000.000/0000-00', 'ativo');

-- Inserir usuário de exemplo (senha: 123456)
INSERT INTO usuarios (nome, email, senha, empresa_id, status) 
VALUES ('Admin', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'ativo');

-- Inserir dados básicos para as novas tabelas
INSERT INTO tipos_contrato (nome) VALUES 
('CLT'),
('PJ'),
('Temporário'),
('Terceirizado');

INSERT INTO disponibilidades (nome) VALUES 
('Disponível'),
('Em Viagem'),
('Férias'),
('Licença'),
('Afastado');

INSERT INTO categorias_cnh (nome) VALUES 
('A'),
('B'),
('C'),
('D'),
('E'),
('AB'),
('AC'),
('AD'),
('AE'); 

-- Tabela de despesas de viagem
CREATE TABLE IF NOT EXISTS despesas_viagem (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    rota_id INT NOT NULL,
    arla DECIMAL(10,2) NULL,
    pedagios DECIMAL(10,2) NULL,
    caixinha DECIMAL(10,2) NULL,
    estacionamento DECIMAL(10,2) NULL,
    lavagem DECIMAL(10,2) NULL,
    borracharia DECIMAL(10,2) NULL,
    eletrica_mecanica DECIMAL(10,2) NULL,
    adiantamento DECIMAL(10,2) NULL,
    total DECIMAL(10,2) NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'pendente',
    fonte ENUM('motorista', 'admin') NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (rota_id) REFERENCES rotas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 