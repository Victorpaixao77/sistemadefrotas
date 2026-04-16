-- =============================================================================
-- Queries de custo/km e vida útil – baseadas em pneu_movimentacoes
-- Executar após criar e popular pneu_movimentacoes. Filtre por empresa_id na app.
-- =============================================================================

-- 3.1 KM total rodado por pneu
SELECT
    pneu_id,
    SUM(km_rodado) AS km_total
FROM pneu_movimentacoes
WHERE tipo IN ('remocao', 'descarte')
  AND km_rodado IS NOT NULL
GROUP BY pneu_id;

-- 3.2 Custo total por pneu (compra + recapagens + manutenções)
SELECT
    pneu_id,
    SUM(custo) AS custo_total
FROM pneu_movimentacoes
WHERE custo > 0
GROUP BY pneu_id;

-- 3.3 Custo por km (individual) – por empresa
SELECT
    p.id AS pneu_id,
    p.empresa_id,
    SUM(m.custo) / NULLIF(SUM(CASE WHEN m.km_rodado IS NOT NULL THEN m.km_rodado ELSE 0 END), 0) AS custo_por_km
FROM pneus p
JOIN pneu_movimentacoes m ON m.pneu_id = p.id AND m.empresa_id = p.empresa_id
WHERE m.km_rodado IS NOT NULL
GROUP BY p.id, p.empresa_id;

-- 3.4 Vida útil (%) por sulco (sulco mínimo 3mm; compatível MySQL 5.x)
SELECT
    p.id,
    p.empresa_id,
    ultimo.sulco_mm AS sulco_atual,
    ROUND(
        ((ultimo.sulco_mm - 3) / NULLIF(p.sulco_inicial - 3, 0)) * 100,
        2
    ) AS vida_percentual
FROM pneus p
JOIN (
    SELECT m1.pneu_id, m1.empresa_id, m1.sulco_mm
    FROM pneu_movimentacoes m1
    INNER JOIN (
        SELECT pneu_id, empresa_id, MAX(data_movimentacao) AS data_max
        FROM pneu_movimentacoes
        WHERE sulco_mm IS NOT NULL
        GROUP BY pneu_id, empresa_id
    ) m2 ON m1.pneu_id = m2.pneu_id AND m1.empresa_id = m2.empresa_id AND m1.data_movimentacao = m2.data_max
    WHERE m1.sulco_mm IS NOT NULL
) ultimo ON ultimo.pneu_id = p.id AND ultimo.empresa_id = p.empresa_id;

-- 4.4 Média de km por eixo (base para comparação “desgaste acima da média”)
SELECT
    eixo_id,
    AVG(km_rodado) AS media_km
FROM pneu_movimentacoes
WHERE tipo = 'remocao'
  AND eixo_id IS NOT NULL
  AND km_rodado IS NOT NULL
GROUP BY eixo_id;
