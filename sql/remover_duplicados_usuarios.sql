-- Script para remover registros duplicados da tabela usuarios
-- Mantém apenas o registro mais antigo (menor ID) de cada email

-- ATENÇÃO: Faça backup do banco antes de executar!

-- Verificar duplicados primeiro
SELECT email, COUNT(*) as total, GROUP_CONCAT(id ORDER BY id) as ids
FROM usuarios 
GROUP BY LOWER(email)
HAVING total > 1;

-- Remover duplicados (descomente para executar)
-- DELETE u1 FROM usuarios u1
-- INNER JOIN usuarios u2 
-- WHERE u1.email = u2.email 
-- AND u1.id > u2.id;

-- Ou usando subquery (mais seguro)
-- DELETE FROM usuarios 
-- WHERE id NOT IN (
--     SELECT min_id FROM (
--         SELECT MIN(id) as min_id
--         FROM usuarios
--         GROUP BY LOWER(email)
--     ) AS temp
-- );
