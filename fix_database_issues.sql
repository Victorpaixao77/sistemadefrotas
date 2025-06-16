-- Select the database first
USE sistema_frotas;

-- Verifica se a tabela disponibilidades_motoristas existe
CREATE TABLE IF NOT EXISTS disponibilidades_motoristas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insere valores padrão se a tabela estiver vazia
INSERT IGNORE INTO disponibilidades_motoristas (id, nome, descricao) VALUES
(1, 'Disponível', 'Motorista disponível para novas rotas'),
(2, 'Em Rota', 'Motorista está atualmente em uma rota'),
(3, 'Indisponível', 'Motorista temporariamente indisponível'),
(4, 'Férias', 'Motorista em período de férias'),
(5, 'Licença', 'Motorista em licença');

-- Atualiza a foreign key na tabela motoristas se necessário
ALTER TABLE motoristas
DROP FOREIGN KEY IF EXISTS fk_motoristas_disponibilidade;

ALTER TABLE motoristas
ADD CONSTRAINT fk_motoristas_disponibilidade
FOREIGN KEY (disponibilidade_id)
REFERENCES disponibilidades_motoristas(id)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Add quilometragem column to veiculos table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = "veiculos";
SET @columnname = "quilometragem";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE veiculos ADD COLUMN quilometragem DECIMAL(10,2) DEFAULT 0 AFTER motorista_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update existing records to have a default quilometragem value
UPDATE veiculos SET quilometragem = 0 WHERE quilometragem IS NULL;

-- Add data_conclusao column to manutencoes table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = "manutencoes";
SET @columnname = "data_conclusao";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column data_conclusao already exists'",
  "ALTER TABLE manutencoes ADD COLUMN data_conclusao DATE NULL AFTER data_manutencao"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Update existing records to set data_conclusao based on status
UPDATE manutencoes m
JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
SET m.data_conclusao = 
    CASE 
        WHEN sm.nome = 'Concluída' THEN m.data_manutencao
        ELSE NULL
    END
WHERE m.data_conclusao IS NULL; 