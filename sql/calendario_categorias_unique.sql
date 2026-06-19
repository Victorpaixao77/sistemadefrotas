-- Categorias do calendário: nome único por empresa (não global).
-- Execute: Get-Content sql/calendario_categorias_unique.sql -Raw | C:\xampp\mysql\bin\mysql.exe -h 127.0.0.1 -P 3307 -u root sistema_frotas

-- Remove UNIQUE antigo em `nome` (nome do índice varia)
SET @drop_idx := (
    SELECT INDEX_NAME
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'categorias_calendario'
      AND COLUMN_NAME = 'nome'
      AND NON_UNIQUE = 0
      AND SEQ_IN_INDEX = 1
      AND INDEX_NAME <> 'PRIMARY'
      AND INDEX_NAME <> 'uk_categoria_empresa_nome'
    LIMIT 1
);

SET @drop_sql := IF(
    @drop_idx IS NOT NULL,
    CONCAT('ALTER TABLE categorias_calendario DROP INDEX `', @drop_idx, '`'),
    'SELECT 1'
);
PREPARE stmt_drop FROM @drop_sql;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

-- Deduplica categorias (mantém o menor id por empresa + nome)
UPDATE calendario_eventos ce
INNER JOIN categorias_calendario dup ON ce.categoria_id = dup.id
INNER JOIN categorias_calendario keep
    ON keep.empresa_id = dup.empresa_id AND keep.nome = dup.nome AND keep.id < dup.id
SET ce.categoria_id = keep.id;

DELETE dup FROM categorias_calendario dup
INNER JOIN categorias_calendario keep
    ON keep.empresa_id = dup.empresa_id AND keep.nome = dup.nome AND keep.id < dup.id;

-- Adiciona unique composto se ainda não existir
SET @has_uk := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'categorias_calendario'
      AND INDEX_NAME = 'uk_categoria_empresa_nome'
);

SET @add_sql := IF(
    @has_uk = 0,
    'ALTER TABLE categorias_calendario ADD UNIQUE KEY uk_categoria_empresa_nome (empresa_id, nome)',
    'SELECT 1'
);
PREPARE stmt_add FROM @add_sql;
EXECUTE stmt_add;
DEALLOCATE PREPARE stmt_add;
