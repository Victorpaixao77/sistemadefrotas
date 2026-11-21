-- Correção dos dados de veículos que estavam claramente errados.
-- (Sobrescreve valores existentes — autorizado.)
--   mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/veiculos_correcao_dados.sql

-- id 57 — DAF XF 480: capacidade 39 era 39 t lançada como 39 kg. É cavalo pesado,
-- não "leve". Corrige unidade, marca a tração (6x2) e reclassifica o operacional.
UPDATE veiculos SET
    capacidade_carga     = 39000,
    id_cavalo            = 2,        -- Cavalo Mecânico 6x2
    numero_eixos         = 3,
    capacidade_tanque    = 500,
    consumo_medio        = 2.5,
    custo_operacional_km = 1.00
WHERE id = 57;

-- id 55 — eixos não batia com o cavalo (6x2 = 3 eixos, estava 2).
UPDATE veiculos SET numero_eixos = 3 WHERE id = 55;

-- id 56 — Scania 770 (V8) não existia em 2002 (lançado ~2020). Ajusta ano e
-- alinha a capacidade ao conjunto bitrem (~57 t).
UPDATE veiculos SET ano = 2021, capacidade_carga = 57000 WHERE id = 56;

-- ids 76–80 — importação de Excel incompleta (placas inválidas, sem specs).
-- Identidade real desconhecida; ao menos marca como Diesel e perfil de caminhão
-- pesado (coerente com o alto volume de abastecimento do histórico).
UPDATE veiculos SET
    tipo_combustivel_id  = COALESCE(tipo_combustivel_id, 2),  -- Diesel
    capacidade_tanque    = 500,
    consumo_medio        = 2.5,
    custo_operacional_km = 1.00
WHERE id IN (76, 77, 78, 79, 80);
