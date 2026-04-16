-- Tabela de log de alterações dos motoristas (histórico de alterações)
-- Execute no banco do sistema de frotas
-- Se a tabela empresas não existir, remova a linha FOREIGN KEY (empresa_id)...

CREATE TABLE IF NOT EXISTS motoristas_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    motorista_id INT NOT NULL,
    empresa_id INT NOT NULL,
    acao ENUM('create','update','delete') NOT NULL DEFAULT 'update',
    descricao TEXT NULL COMMENT 'Resumo da alteração (ex: Nome, CPF, CNH alterados)',
    dados_anteriores JSON NULL COMMENT 'Valores antes da alteração (opcional)',
    dados_novos JSON NULL COMMENT 'Valores após alteração (opcional)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_motorista (motorista_id),
    INDEX idx_empresa (empresa_id),
    INDEX idx_created (created_at),
    FOREIGN KEY (motorista_id) REFERENCES motoristas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
