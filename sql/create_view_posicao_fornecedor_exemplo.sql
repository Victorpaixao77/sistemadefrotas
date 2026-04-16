-- Exemplo de VIEW apenas para registros já vinculados por fornecedor_id.
-- Totais "em aberto" vs "pago" vêm de regras de negócio (data_pagamento + status); ajuste conforme seu status_contas_pagar.

CREATE OR REPLACE VIEW vw_contas_pagar_resumo_fornecedor AS
SELECT
    f.id AS fornecedor_id,
    f.empresa_id,
    f.nome AS fornecedor_nome,
    COUNT(cp.id) AS qtd_contas,
    SUM(CASE WHEN cp.data_pagamento IS NULL THEN cp.valor ELSE 0 END) AS total_sem_pagamento,
    SUM(CASE WHEN cp.data_pagamento IS NOT NULL THEN cp.valor ELSE 0 END) AS total_com_pagamento_registrado,
    SUM(cp.valor) AS total_geral
FROM fornecedores f
LEFT JOIN contas_pagar cp
    ON cp.fornecedor_id = f.id
    AND cp.empresa_id = f.empresa_id
GROUP BY f.id, f.empresa_id, f.nome;

-- Uso: SELECT * FROM vw_contas_pagar_resumo_fornecedor WHERE empresa_id = 1 AND fornecedor_id = 5;
