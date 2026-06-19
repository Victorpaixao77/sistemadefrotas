-- Cria linhas em estoque_pneus para pneus que ainda não possuem registro.
-- Executar: mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/backfill_estoque_pneus.sql

INSERT INTO estoque_pneus (pneu_id, status_id, disponivel, created_at, updated_at)
SELECT
    p.id,
    COALESCE(p.status_id, 2) AS status_id,
    CASE
        WHEN COALESCE(p.status_id, 2) IN (
            SELECT sp.id FROM status_pneus sp
            WHERE LOWER(TRIM(sp.nome)) IN ('reserva', 'novo', 'usado')
        )
        AND NOT EXISTS (
            SELECT 1 FROM instalacoes_pneus ip
            WHERE ip.pneu_id = p.id AND ip.data_remocao IS NULL
        )
        AND NOT EXISTS (
            SELECT 1 FROM alocacoes_pneus_flexiveis apf
            WHERE apf.pneu_id = p.id AND apf.ativo = 1
        )
        AND NOT EXISTS (
            SELECT 1 FROM pneus_alocacao pa
            WHERE pa.pneu_id = p.id AND pa.status = 'alocado'
        )
        AND NOT EXISTS (
            SELECT 1 FROM eixo_pneus ep
            WHERE ep.pneu_id = p.id AND ep.status = 'alocado'
        )
        THEN 1
        ELSE 0
    END AS disponivel,
    NOW(),
    NOW()
FROM pneus p
LEFT JOIN estoque_pneus ep ON ep.pneu_id = p.id
WHERE ep.pneu_id IS NULL;
