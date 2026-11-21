-- Colunas operacionais do veículo para a Simulação de Rota.
-- Aplicar:
--   mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/veiculos_sim_columns.sql
-- Idempotente em runtime via includes/sf_vehicle_sim_columns.php
-- (ignore erros "Duplicate column" se rodar manualmente mais de uma vez).

ALTER TABLE veiculos ADD COLUMN capacidade_tanque    DECIMAL(7,2) NULL DEFAULT NULL;
ALTER TABLE veiculos ADD COLUMN consumo_medio        DECIMAL(5,2) NULL DEFAULT NULL;
ALTER TABLE veiculos ADD COLUMN custo_operacional_km DECIMAL(8,2) NULL DEFAULT NULL;
