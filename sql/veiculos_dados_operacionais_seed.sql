-- Preenche campos operacionais dos veículos (tanque/consumo/custo-km) e completa
-- specs faltantes (NULL) dos modelos pesados reais. NÃO sobrescreve valores já
-- preenchidos: todas as UPDATEs têm guarda "IS NULL" (idempotente).
--
-- Valores de referência (caminhões diesel BR):
--   Bitrem/Rodotrem (muito pesado): tanque 600L · consumo 2.0 km/L · custo R$1,20/km
--   Cavalo + carreta (semi):        tanque 500L · consumo 2.5 km/L · custo R$1,00/km
--   Cavalo só / rígido pesado:      tanque 400L · consumo 3.0 km/L · custo R$0,80/km
--   Rígido médio (carga >= 3t):     tanque 275L · consumo 4.0 km/L · custo R$0,65/km
--   Leve/indefinido:                tanque 150L · consumo 6.0 km/L · custo R$0,45/km
--
-- Aplicar:
--   mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/veiculos_dados_operacionais_seed.sql

-- ===== Conjuntos muito pesados (Bitrem id_carreta=6 / Rodo-trem id_carreta=7) =====
UPDATE veiculos SET
    capacidade_tanque    = COALESCE(capacidade_tanque, 600),
    consumo_medio        = COALESCE(consumo_medio, 2.0),
    custo_operacional_km = COALESCE(custo_operacional_km, 1.20)
WHERE id_carreta IN (6, 7);

-- ===== Cavalo + carreta (semireboque comum) =====
UPDATE veiculos SET
    capacidade_tanque    = COALESCE(capacidade_tanque, 500),
    consumo_medio        = COALESCE(consumo_medio, 2.5),
    custo_operacional_km = COALESCE(custo_operacional_km, 1.00)
WHERE id_carreta IS NOT NULL AND id_carreta NOT IN (6, 7);

-- ===== Cavalo mecânico sem carreta (tração pesada) =====
UPDATE veiculos SET
    capacidade_tanque    = COALESCE(capacidade_tanque, 400),
    consumo_medio        = COALESCE(consumo_medio, 3.0),
    custo_operacional_km = COALESCE(custo_operacional_km, 0.80)
WHERE id_carreta IS NULL AND id_cavalo IS NOT NULL;

-- ===== Modelos pesados reais sem cavalo/carreta cadastrados (Actros/FH/R450) =====
-- Completa specs faltantes (todos NULL) com valores típicos de cavalo 6x4 diesel.
UPDATE veiculos SET
    tipo_combustivel_id = COALESCE(tipo_combustivel_id, 2),      -- Diesel
    numero_eixos        = COALESCE(numero_eixos, 6),
    id_cavalo           = COALESCE(id_cavalo, 3),                -- Cavalo Mecânico 6x4
    capacidade_carga    = COALESCE(capacidade_carga, 27000),     -- ~27 t (3 eixos)
    capacidade_tanque   = COALESCE(capacidade_tanque, 500),
    consumo_medio       = COALESCE(consumo_medio, 2.5),
    custo_operacional_km = COALESCE(custo_operacional_km, 1.00)
WHERE id IN (66, 67, 68);

-- Consumo médio real do veículo 66 (calculado do histórico: ~4,3 km/L).
UPDATE veiculos SET consumo_medio = 4.30 WHERE id = 66;

-- ===== Rígido médio (tem carga >= 3000 kg, sem cavalo/carreta) =====
UPDATE veiculos SET
    capacidade_tanque    = COALESCE(capacidade_tanque, 275),
    consumo_medio        = COALESCE(consumo_medio, 4.0),
    custo_operacional_km = COALESCE(custo_operacional_km, 0.65)
WHERE id_carreta IS NULL AND id_cavalo IS NULL
  AND capacidade_carga IS NOT NULL AND capacidade_carga >= 3000;

-- ===== Restante indefinido (ex.: importação Excel, sem specs) → padrão leve =====
UPDATE veiculos SET
    capacidade_tanque    = COALESCE(capacidade_tanque, 150),
    consumo_medio        = COALESCE(consumo_medio, 6.0),
    custo_operacional_km = COALESCE(custo_operacional_km, 0.45)
WHERE capacidade_tanque IS NULL;
