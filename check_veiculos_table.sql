USE sistema_frotas;

-- Check if quilometragem column exists and add it if not
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
  "SELECT 'Column quilometragem already exists'",
  "ALTER TABLE veiculos ADD COLUMN quilometragem DECIMAL(10,2) DEFAULT 0 AFTER motorista_id"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Show table structure
SHOW CREATE TABLE veiculos;

-- Show sample data
SELECT id, placa, quilometragem FROM veiculos LIMIT 5; 