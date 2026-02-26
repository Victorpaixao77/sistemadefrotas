-- Limpeza de dados de demonstração dos módulos fiscais (NF-e, CT-e, MDF-e)
-- ATENÇÃO: isto remove TODOS os registros dessas tabelas.
-- Execute apenas se quiser começar do zero e depois recarregar NF-e reais via XML/SEFAZ.

START TRANSACTION;

DELETE FROM fiscal_cte;
DELETE FROM fiscal_mdfe;
DELETE FROM fiscal_nfe_clientes;

COMMIT;

