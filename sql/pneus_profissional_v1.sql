-- Módulo profissional de pneus v1 — timeline, sulco, financeiro, QR
-- Execute: Get-Content sql/pneus_profissional_v1.sql -Raw | C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root sistema_frotas

-- Colunas financeiras e QR no cadastro
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'valor_compra');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN valor_compra DECIMAL(12,2) NULL DEFAULT NULL AFTER sulco_inicial', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'fornecedor_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN fornecedor_id INT NULL DEFAULT NULL AFTER valor_compra', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'nota_fiscal');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN nota_fiscal VARCHAR(60) NULL DEFAULT NULL AFTER fornecedor_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'garantia_ate');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN garantia_ate DATE NULL DEFAULT NULL AFTER nota_fiscal', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'qr_token');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN qr_token VARCHAR(64) NULL DEFAULT NULL AFTER garantia_ate', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND INDEX_NAME = 'uk_pneus_qr_token');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD UNIQUE KEY uk_pneus_qr_token (qr_token)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneus' AND COLUMN_NAME = 'km_rodado_total');
SET @sql := IF(@c = 0, 'ALTER TABLE pneus ADD COLUMN km_rodado_total INT UNSIGNED NULL DEFAULT 0 COMMENT ''KM acumulado na carcaça'' AFTER km_instalacao', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Medições de sulco (TWI)
CREATE TABLE IF NOT EXISTS pneu_medicoes_sulco (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    pneu_id INT NOT NULL,
    sulco_mm DECIMAL(5,2) NOT NULL,
    km_veiculo INT NULL,
    veiculo_id INT NULL,
    observacoes VARCHAR(500) NULL,
    usuario_id INT NULL,
    data_medicao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pneu_data (pneu_id, data_medicao),
    INDEX idx_empresa (empresa_id),
    CONSTRAINT fk_sulco_pneu FOREIGN KEY (pneu_id) REFERENCES pneus(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de medições de sulco (TWI)';

-- Fornecedor na manutenção de pneu (recapagem)
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_manutencao' AND COLUMN_NAME = 'fornecedor_id');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_manutencao ADD COLUMN fornecedor_id INT NULL DEFAULT NULL AFTER tipo_manutencao_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_manutencao' AND COLUMN_NAME = 'garantia_ate');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_manutencao ADD COLUMN garantia_ate DATE NULL DEFAULT NULL AFTER fornecedor_id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pneu_manutencao' AND COLUMN_NAME = 'sulco_pos_recapagem');
SET @sql := IF(@c = 0, 'ALTER TABLE pneu_manutencao ADD COLUMN sulco_pos_recapagem DECIMAL(5,2) NULL DEFAULT NULL AFTER garantia_ate', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
