-- Criar tabela status_pneus se não existir
CREATE TABLE IF NOT EXISTS status_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    cor VARCHAR(20) DEFAULT '#666666',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir status padrão dos pneus
INSERT IGNORE INTO status_pneus (id, nome, descricao, cor) VALUES
(1, 'furado', 'Pneu furado ou com danos graves', '#dc3545'),
(2, 'disponivel', 'Pneu disponível para uso', '#28a745'),
(3, 'descartado', 'Pneu descartado/irrecuperável', '#6c757d'),
(4, 'gasto', 'Pneu gasto mas ainda utilizável', '#ffc107'),
(5, 'novo', 'Pneu novo ou em excelente estado', '#17a2b8');

-- Verificar se a tabela estoque_pneus existe e criar se necessário
CREATE TABLE IF NOT EXISTS estoque_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    pneu_id INT(11) NOT NULL,
    status_id INT(11) NULL,
    disponivel TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_pneu (pneu_id),
    FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE,
    FOREIGN KEY (status_id) REFERENCES status_pneus(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir pneus existentes na tabela estoque_pneus se não existirem
INSERT IGNORE INTO estoque_pneus (pneu_id, status_id, disponivel, created_at, updated_at)
SELECT 
    p.id as pneu_id,
    p.status_id,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM instalacoes_pneus ip 
            WHERE ip.pneu_id = p.id 
            AND ip.data_remocao IS NULL
        ) THEN 0
        ELSE 1
    END as disponivel,
    p.created_at,
    p.updated_at
FROM pneus p
LEFT JOIN estoque_pneus ep ON p.id = ep.pneu_id
WHERE ep.pneu_id IS NULL;
