-- Tabela de checklists
CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    motorista_id INT NOT NULL,
    veiculo_id INT NOT NULL,
    data_checklist DATETIME NOT NULL,
    tipo_checklist ENUM('diario', 'semanal', 'mensal') NOT NULL,
    km_atual DECIMAL(10,2) NOT NULL,
    observacoes TEXT,
    status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    fonte ENUM('motorista', 'admin') DEFAULT 'motorista',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id),
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de infrações dos motoristas
CREATE TABLE IF NOT EXISTS infracoes_motoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    motorista_id INT NOT NULL,
    data_infracao DATE NOT NULL,
    tipo_infracao VARCHAR(100) NOT NULL,
    descricao TEXT,
    valor_multa DECIMAL(10,2),
    pontos_cnh INT,
    status ENUM('pendente', 'pago', 'contestado') DEFAULT 'pendente',
    data_vencimento DATE,
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir alguns dados de exemplo para testes
INSERT INTO checklists (empresa_id, motorista_id, veiculo_id, data_checklist, tipo_checklist, km_atual, status) 
SELECT 
    1, -- empresa_id
    m.id, -- motorista_id
    v.id, -- veiculo_id
    CURDATE(), -- data_checklist
    'diario', -- tipo_checklist
    v.km_atual, -- km_atual
    'pendente' -- status
FROM motoristas m
CROSS JOIN veiculos v
WHERE m.empresa_id = 1 AND v.empresa_id = 1
LIMIT 5;

INSERT INTO infracoes_motoristas (empresa_id, motorista_id, data_infracao, tipo_infracao, descricao, valor_multa, pontos_cnh, status)
SELECT 
    1, -- empresa_id
    m.id, -- motorista_id
    CURDATE(), -- data_infracao
    'Excesso de Velocidade', -- tipo_infracao
    'Multa por excesso de velocidade', -- descricao
    195.23, -- valor_multa
    5, -- pontos_cnh
    'pendente' -- status
FROM motoristas m
WHERE m.empresa_id = 1
LIMIT 3; 