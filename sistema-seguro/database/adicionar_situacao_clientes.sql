-- Adicionar campo 'situacao' na tabela seguro_clientes
-- Este campo controlará se o cliente está ativo, inativo ou aguardando ativação

-- Verificar se a coluna já existe antes de adicionar
SET @dbname = DATABASE();
SET @tablename = 'seguro_clientes';
SET @columnname = 'situacao';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD ", @columnname, " ENUM('ativo', 'inativo', 'aguardando_ativacao') NOT NULL DEFAULT 'aguardando_ativacao' AFTER porcentagem_recorrencia")
));
PREPARE alterStatement FROM @preparedStatement;
EXECUTE alterStatement;
DEALLOCATE PREPARE alterStatement;

-- Atualizar clientes existentes para 'ativo' se estiverem NULL
UPDATE seguro_clientes 
SET situacao = 'ativo' 
WHERE situacao IS NULL OR situacao = '';

SELECT 
    '✅ Campo situacao verificado/adicionado com sucesso!' as status,
    COUNT(*) as total_clientes,
    SUM(CASE WHEN situacao = 'ativo' THEN 1 ELSE 0 END) as ativos,
    SUM(CASE WHEN situacao = 'inativo' THEN 1 ELSE 0 END) as inativos,
    SUM(CASE WHEN situacao = 'aguardando_ativacao' THEN 1 ELSE 0 END) as aguardando
FROM seguro_clientes;

