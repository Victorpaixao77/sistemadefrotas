-- Criação da tabela empresas
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(255) NOT NULL,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    razao_social VARCHAR(255),
    endereco VARCHAR(255),
    cidade VARCHAR(100),
    estado VARCHAR(2),
    cep VARCHAR(9),
    telefone VARCHAR(15),
    email VARCHAR(255),
    logo VARCHAR(255),
    status ENUM('Ativo', 'Inativo') DEFAULT 'Ativo',
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir uma empresa padrão para desenvolvimento
INSERT INTO empresas (nome, cnpj, razao_social, status) 
VALUES ('Empresa Padrão', '00.000.000/0000-00', 'Empresa Padrão LTDA', 'Ativo'); 