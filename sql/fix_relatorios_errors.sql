-- Script para corrigir erros nos relatórios
-- Execute este script no banco de dados sistema_frotas

USE sistema_frotas;

-- 1. Adicionar campo quilometragem à tabela pneus se não existir
SET @dbname = DATABASE();
SET @tablename = "pneus";
SET @columnname = "quilometragem";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column quilometragem already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN quilometragem DECIMAL(10,2) DEFAULT 0 AFTER dot"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. Adicionar campo empresa_id à tabela pneus se não existir
SET @columnname = "empresa_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column empresa_id already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN empresa_id INT DEFAULT 1 AFTER id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 3. Adicionar campo status_id à tabela pneus se não existir
SET @columnname = "status_id";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column status_id already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN status_id INT DEFAULT 2 AFTER empresa_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 4. Adicionar campo km_instalacao à tabela pneus se não existir
SET @columnname = "km_instalacao";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column km_instalacao already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN km_instalacao DECIMAL(10,2) DEFAULT 0 AFTER quilometragem"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 5. Adicionar campo vida_util_km à tabela pneus se não existir
SET @columnname = "vida_util_km";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column vida_util_km already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN vida_util_km INT DEFAULT 80000 AFTER km_instalacao"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 6. Adicionar campo dot à tabela pneus se não existir
SET @columnname = "dot";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column dot already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN dot VARCHAR(20) AFTER numero_serie"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 7. Adicionar campo medida à tabela pneus se não existir
SET @columnname = "medida";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column medida already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN medida VARCHAR(20) AFTER modelo"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 8. Adicionar campo sulco_inicial à tabela pneus se não existir
SET @columnname = "sulco_inicial";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column sulco_inicial already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN sulco_inicial DECIMAL(4,1) DEFAULT 8.0 AFTER medida"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 9. Adicionar campo numero_recapagens à tabela pneus se não existir
SET @columnname = "numero_recapagens";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column numero_recapagens already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN numero_recapagens INT DEFAULT 0 AFTER sulco_inicial"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 10. Adicionar campo data_ultima_recapagem à tabela pneus se não existir
SET @columnname = "data_ultima_recapagem";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column data_ultima_recapagem already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN data_ultima_recapagem DATE AFTER numero_recapagens"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 11. Adicionar campo lote à tabela pneus se não existir
SET @columnname = "lote";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column lote already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN lote VARCHAR(50) AFTER data_ultima_recapagem"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 12. Adicionar campo data_entrada à tabela pneus se não existir
SET @columnname = "data_entrada";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 'Column data_entrada already exists in pneus'",
  "ALTER TABLE pneus ADD COLUMN data_entrada DATE AFTER lote"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 13. Atualizar registros existentes com valores padrão
UPDATE pneus SET empresa_id = 1 WHERE empresa_id IS NULL;
UPDATE pneus SET status_id = 2 WHERE status_id IS NULL;
UPDATE pneus SET quilometragem = 0 WHERE quilometragem IS NULL;
UPDATE pneus SET km_instalacao = 0 WHERE km_instalacao IS NULL;
UPDATE pneus SET vida_util_km = 80000 WHERE vida_util_km IS NULL;
UPDATE pneus SET dot = CONCAT('20', YEAR(NOW()), '01') WHERE dot IS NULL OR dot = '';
UPDATE pneus SET medida = '295/80R22.5' WHERE medida IS NULL OR medida = '';
UPDATE pneus SET sulco_inicial = 8.0 WHERE sulco_inicial IS NULL;
UPDATE pneus SET numero_recapagens = 0 WHERE numero_recapagens IS NULL;
UPDATE pneus SET data_entrada = data_instalacao WHERE data_entrada IS NULL;

-- 14. Verificar se a tabela status_pneus existe e criar se necessário
CREATE TABLE IF NOT EXISTS status_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    cor VARCHAR(20) DEFAULT '#666666',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Inserir status padrão dos pneus se não existirem
INSERT IGNORE INTO status_pneus (id, nome, descricao, cor) VALUES
(1, 'furado', 'Pneu furado ou com danos graves', '#dc3545'),
(2, 'disponivel', 'Pneu disponível para uso', '#28a745'),
(3, 'descartado', 'Pneu descartado/irrecuperável', '#6c757d'),
(4, 'gasto', 'Pneu gasto mas ainda utilizável', '#ffc107'),
(5, 'novo', 'Pneu novo ou em excelente estado', '#17a2b8');

-- 16. Verificar se a tabela posicoes_pneus existe e criar se necessário
CREATE TABLE IF NOT EXISTS posicoes_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nome VARCHAR(50) NOT NULL,
    descricao TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. Inserir posições padrão se não existirem
INSERT IGNORE INTO posicoes_pneus (id, nome, descricao) VALUES
(1, 'Dianteiro Esquerdo', 'Pneu dianteiro esquerdo'),
(2, 'Dianteiro Direito', 'Pneu dianteiro direito'),
(3, 'Tandem 1 Esquerdo', 'Primeiro pneu do tandem esquerdo'),
(4, 'Tandem 1 Direito', 'Primeiro pneu do tandem direito'),
(5, 'Tandem 2 Esquerdo', 'Segundo pneu do tandem esquerdo'),
(6, 'Tandem 2 Direito', 'Segundo pneu do tandem direito'),
(7, 'Tandem 3 Esquerdo', 'Terceiro pneu do tandem esquerdo'),
(8, 'Tandem 3 Direito', 'Terceiro pneu do tandem direito'),
(9, 'Tandem 4 Esquerdo', 'Quarto pneu do tandem esquerdo'),
(10, 'Tandem 4 Direito', 'Quarto pneu do tandem direito'),
(11, 'Traseiro Esquerdo', 'Pneu traseiro esquerdo'),
(12, 'Traseiro Direito', 'Pneu traseiro direito');

-- 18. Verificar se a tabela instalacoes_pneus existe e criar se necessário
CREATE TABLE IF NOT EXISTS instalacoes_pneus (
    id INT(11) NOT NULL AUTO_INCREMENT,
    pneu_id INT(11) NOT NULL,
    veiculo_id INT(11) NOT NULL,
    posicao VARCHAR(20) NULL,
    posicao_id INT(11) NULL,
    data_instalacao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_remocao TIMESTAMP NULL,
    status ENUM('bom', 'gasto', 'furado', 'descartado') DEFAULT 'bom',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_pneu_id (pneu_id),
    INDEX idx_veiculo_id (veiculo_id),
    INDEX idx_data_remocao (data_remocao),
    FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE,
    FOREIGN KEY (veiculo_id) REFERENCES veiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (posicao_id) REFERENCES posicoes_pneus(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. Mostrar estrutura final da tabela pneus
DESCRIBE pneus;

-- 20. Mostrar alguns registros de exemplo
SELECT id, numero_serie, marca, modelo, medida, quilometragem, empresa_id, status_id FROM pneus LIMIT 5; 