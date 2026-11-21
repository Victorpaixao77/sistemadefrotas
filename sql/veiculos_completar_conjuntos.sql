-- Completa os conjuntos (cavalo + carreta) e acerta identidades incoerentes.
-- Carretas: 1 Simples · 2 Baú · 3 Sider · 4 Graneleira · 5 Tanque · 6 Bitrem · 7 Rodo-trem · 8 Cegonha
--   mysql -h 127.0.0.1 -P 3307 -u root sistema_frotas < sql/veiculos_completar_conjuntos.sql

-- ----- Cavalos identificáveis sem carreta → recebem carreta coerente -----
UPDATE veiculos SET id_carreta = 3 WHERE id = 57;  -- DAF XF 480  → Sider
UPDATE veiculos SET id_carreta = 4 WHERE id = 66;  -- Actros 6x4  → Graneleira
UPDATE veiculos SET id_carreta = 2 WHERE id = 67;  -- Volvo FH    → Baú
UPDATE veiculos SET id_carreta = 3 WHERE id = 68;  -- Scania R450 → Sider

-- ----- id 55: modelo "Fiorino" num Volvo cavalo 6x2 + graneleira é incoerente.
-- Volvo cavalo 6x2 de 2001 → modelo real plausível Volvo FH12.
UPDATE veiculos SET modelo = 'Volvo FH12' WHERE id = 55 AND modelo = 'Fiorino';

-- ----- 76–80: completa a parte mecânica como conjunto pesado (Diesel já setado).
-- Identidade (placa/marca/modelo) permanece placeholder até virem os dados reais.
UPDATE veiculos SET
    id_cavalo        = COALESCE(id_cavalo, 3),     -- Cavalo 6x4
    numero_eixos     = COALESCE(numero_eixos, 6),
    capacidade_carga = COALESCE(capacidade_carga, 30000)
WHERE id IN (76, 77, 78, 79, 80);

UPDATE veiculos SET id_carreta = 3 WHERE id = 76;  -- Sider
UPDATE veiculos SET id_carreta = 4 WHERE id = 77;  -- Graneleira
UPDATE veiculos SET id_carreta = 2 WHERE id = 78;  -- Baú
UPDATE veiculos SET id_carreta = 6 WHERE id = 79;  -- Bitrem
UPDATE veiculos SET id_carreta = 5 WHERE id = 80;  -- Tanque
