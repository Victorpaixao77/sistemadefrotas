-- Adicionar pneus existentes ao estoque
INSERT INTO estoque_pneus (pneu_id, status_id, disponivel)
SELECT id, status_id, 1
FROM pneus p
WHERE NOT EXISTS (
    SELECT 1 
    FROM estoque_pneus ep 
    WHERE ep.pneu_id = p.id
); 