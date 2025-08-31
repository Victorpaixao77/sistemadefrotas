-- A tabela fiscal_eventos_fiscais já existe com a estrutura correta
-- Verificar se a tabela está correta
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'fiscal_eventos_fiscais' 
    AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

-- A estrutura atual da tabela fiscal_eventos_fiscais é:
-- 1. id (int, AUTO_INCREMENT, PRIMARY KEY)
-- 2. empresa_id (int, NOT NULL, INDEX)
-- 3. tipo_evento (ENUM('cancelamento', 'encerramento', 'cce', 'inutilizacao', 'manifestacao'))
-- 4. documento_tipo (ENUM('nfe', 'cte', 'mdfe'))
-- 5. documento_id (int, NOT NULL, INDEX)
-- 6. protocolo_evento (varchar(50), NULL)
-- 7. justificativa (text, NULL)
-- 8. xml_evento (longtext, NULL)
-- 9. xml_retorno (longtext, NULL)
-- 10. status (ENUM('pendente', 'aceito', 'rejeitado'), DEFAULT 'pendente')
-- 11. data_evento (timestamp, DEFAULT current_timestamp())
-- 12. data_processamento (timestamp, NULL)
-- 13. usuario_id (int, NULL)
-- 14. observacoes (text, NULL)
