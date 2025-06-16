-- Tabela de Usuários Motoristas
CREATE TABLE IF NOT EXISTS usuarios_motoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    senha VARCHAR(255) NOT NULL,
    status ENUM('ativo', 'inativo') DEFAULT 'ativo',
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
    foto_perfil VARCHAR(255) DEFAULT 'default.jpg',
    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Rotas
CREATE TABLE IF NOT EXISTS rotas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT NOT NULL,
    origem VARCHAR(100) NOT NULL,
    destino VARCHAR(100) NOT NULL,
    km_rodado DECIMAL(10,2) NOT NULL,
    data_rota DATE NOT NULL,
    observacoes TEXT,
    status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    fonte ENUM('motorista', 'sistema') DEFAULT 'motorista',
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Abastecimentos
CREATE TABLE IF NOT EXISTS abastecimentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT NOT NULL,
    data_abastecimento DATE NOT NULL,
    tipo_combustivel VARCHAR(50) NOT NULL,
    quantidade DECIMAL(10,2) NOT NULL,
    valor_litro DECIMAL(10,2) NOT NULL,
    valor_total DECIMAL(10,2) NOT NULL,
    km_atual INT NOT NULL,
    posto VARCHAR(100),
    observacoes TEXT,
    status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    fonte ENUM('motorista', 'sistema') DEFAULT 'motorista',
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Checklists
CREATE TABLE IF NOT EXISTS checklists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    veiculo_id INT NOT NULL,
    data_checklist DATE NOT NULL,
    tipo_checklist ENUM('diario', 'semanal', 'mensal') NOT NULL,
    km_atual INT NOT NULL,
    observacoes TEXT,
    status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    fonte ENUM('motorista', 'sistema') DEFAULT 'motorista',
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Itens do Checklist
CREATE TABLE IF NOT EXISTS checklist_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checklist_id INT NOT NULL,
    item VARCHAR(50) NOT NULL,
    status ENUM('ok', 'nok', 'na') NOT NULL,
    FOREIGN KEY (checklist_id) REFERENCES checklists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabela de Logs do Motorista
CREATE TABLE IF NOT EXISTS logs_motorista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    acao VARCHAR(50) NOT NULL,
    detalhes TEXT,
    data_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (motorista_id) REFERENCES usuarios_motoristas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Índices
CREATE INDEX idx_rotas_veiculo ON rotas(veiculo_id);
CREATE INDEX idx_rotas_status ON rotas(status);
CREATE INDEX idx_abastecimentos_veiculo ON abastecimentos(veiculo_id);
CREATE INDEX idx_abastecimentos_status ON abastecimentos(status);
CREATE INDEX idx_checklists_veiculo ON checklists(veiculo_id);
CREATE INDEX idx_checklists_status ON checklists(status);
CREATE INDEX idx_checklist_itens_checklist ON checklist_itens(checklist_id);
CREATE INDEX idx_logs_motorista ON logs_motorista(motorista_id, data_registro);

-- Inserir motorista de teste
INSERT INTO usuarios_motoristas (empresa_id, nome, senha, status) VALUES
(1, 'Motorista Teste', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ativo'); 