-- Criar tabela eixo_pneus se não existir
CREATE TABLE IF NOT EXISTS eixo_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    eixo_id INT(11) NULL,
    veiculo_id INT(11) NULL,
    pneu_id INT(11) NULL,
    posicao_id INT(11) NULL,
    data_alocacao TIMESTAMP NULL,
    km_alocacao DECIMAL(10,2) DEFAULT 0.00,
    data_desalocacao TIMESTAMP NULL,
    km_desalocacao DECIMAL(10,2) NULL,
    status ENUM('desalocado', 'alocado') DEFAULT 'desalocado',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_eixo_id (eixo_id),
    INDEX idx_veiculo_id (veiculo_id),
    INDEX idx_pneu_id (pneu_id),
    INDEX idx_status (status),
    FOREIGN KEY (eixo_id) REFERENCES eixos(id) ON DELETE SET NULL,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE SET NULL,
    FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela pneus_alocacao se não existir (para histórico)
CREATE TABLE IF NOT EXISTS pneus_alocacao (
    id INT(11) NOT NULL AUTO_INCREMENT,
    veiculo_id INT(11) NOT NULL,
    pneu_id INT(11) NOT NULL,
    posicao_id INT(11) NULL,
    data_alocacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    km_alocacao DECIMAL(10,2) DEFAULT 0.00,
    data_desalocacao TIMESTAMP NULL,
    km_desalocacao DECIMAL(10,2) NULL,
    status ENUM('alocado', 'desalocado') DEFAULT 'alocado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_veiculo_id (veiculo_id),
    INDEX idx_pneu_id (pneu_id),
    INDEX idx_status (status),
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir registros básicos em eixo_pneus para pneus existentes que não estão alocados
INSERT IGNORE INTO eixo_pneus (pneu_id, status, created_at, updated_at)
SELECT 
    p.id as pneu_id,
    'desalocado' as status,
    p.created_at,
    p.updated_at
FROM pneus p
LEFT JOIN eixo_pneus ep ON p.id = ep.pneu_id
WHERE ep.pneu_id IS NULL; 