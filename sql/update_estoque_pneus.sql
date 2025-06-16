-- Inserir pneus existentes que não estão na tabela estoque_pneus
INSERT INTO estoque_pneus (pneu_id, status_id, disponivel, created_at, updated_at)
SELECT 
    p.id as pneu_id,
    p.status_id,
    CASE 
        WHEN EXISTS (
            SELECT 1 FROM pneus_alocacao pa 
            WHERE pa.pneu_id = p.id 
            AND pa.status = 'alocado'
        ) THEN 0
        ELSE 1
    END as disponivel,
    p.created_at,
    p.updated_at
FROM pneus p
LEFT JOIN estoque_pneus ep ON p.id = ep.pneu_id
WHERE ep.pneu_id IS NULL; 