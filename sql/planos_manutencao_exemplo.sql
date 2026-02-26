-- ============================================
-- Planos de Manutenção - Exemplo para empresa_id = 1
-- Execute após: create_planos_manutencao.sql e com veículos/cadastros da empresa 1
-- Garante km_atual nos veículos e insere planos que batem com os intervalos
-- ============================================

SET @empresa_id = 1;

-- Se a coluna km_atual não existir em veiculos, crie: ALTER TABLE veiculos ADD COLUMN km_atual INT NULL;

-- IDs dos primeiros veículos empresa 1 (recomendado ter pelo menos 3)
SET @v1 = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id ORDER BY id LIMIT 1);
SET @v2 = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id ORDER BY id LIMIT 1 OFFSET 1);
SET @v3 = (SELECT id FROM veiculos WHERE empresa_id = @empresa_id ORDER BY id LIMIT 1 OFFSET 2);

-- km_atual para gerar alertas (próximo/vencido por km)
UPDATE veiculos SET km_atual = COALESCE(km_atual, 105000) WHERE id = @v1;
UPDATE veiculos SET km_atual = COALESCE(km_atual, 118000) WHERE id = @v2;
UPDATE veiculos SET km_atual = COALESCE(km_atual, 95000)  WHERE id = @v3;

-- Garantir componentes e tipos existem
INSERT IGNORE INTO componentes_manutencao (nome) VALUES 
    ('Óleo do motor'), ('Filtro de óleo'), ('Filtro de ar'), ('Freios'), ('Sistema de arrefecimento');
INSERT IGNORE INTO tipos_manutencao (nome) VALUES ('Preventiva'), ('Corretiva');

SET @comp_oleo   = (SELECT id FROM componentes_manutencao WHERE nome LIKE '%Óleo do motor%' OR nome = 'Oleo do motor' LIMIT 1);
SET @comp_filtro_oleo = (SELECT id FROM componentes_manutencao WHERE nome LIKE '%Filtro de óleo%' OR nome LIKE '%Filtro de oleo%' LIMIT 1);
SET @comp_filtro_ar   = (SELECT id FROM componentes_manutencao WHERE nome LIKE '%Filtro de ar%' LIMIT 1);
SET @comp_freios = (SELECT id FROM componentes_manutencao WHERE nome LIKE '%Freios%' LIMIT 1);
SET @comp_arref  = (SELECT id FROM componentes_manutencao WHERE nome LIKE '%arrefecimento%' LIMIT 1);
SET @tipo_prev   = (SELECT id FROM tipos_manutencao WHERE LOWER(nome) LIKE '%preventiva%' LIMIT 1);
SET @tipo_prev   = COALESCE(@tipo_prev, (SELECT id FROM tipos_manutencao ORDER BY id LIMIT 1));

-- Usar primeiro componente/tipo se algum nome não existir
SET @comp_oleo   = COALESCE(@comp_oleo, (SELECT id FROM componentes_manutencao ORDER BY id LIMIT 1));
SET @comp_filtro_oleo = COALESCE(@comp_filtro_oleo, @comp_oleo);
SET @comp_filtro_ar   = COALESCE(@comp_filtro_ar, @comp_oleo);
SET @comp_freios = COALESCE(@comp_freios, @comp_oleo);
SET @comp_arref  = COALESCE(@comp_arref, @comp_oleo);

-- Inserir planos de exemplo (intervalos que batem com km/datas para gerar alertas)
-- Veículo 1: ultimo_km 100000, intervalo 10000 → próximo 110000; se km_atual=105000 → "Próximo em 5.000 km"
-- Veículo 2: ultimo_km 110000, intervalo 10000 → próximo 120000; se km_atual=118000 → "Próximo em 2.000 km"
-- Veículo 3: ultimo_km 90000,  intervalo 10000 → próximo 100000; se km_atual=95000 → "Venceu por 5.000 km" (ou próximo 5.000)
-- E por data: ultima_data + intervalo_dias para aparecer no calendário

INSERT INTO planos_manutencao (empresa_id, veiculo_id, componente_id, tipo_manutencao_id, intervalo_km, intervalo_dias, ultimo_km, ultima_data, ativo)
VALUES
(@empresa_id, @v1, @comp_oleo, @tipo_prev, 10000, 180, 100000, DATE_SUB(CURDATE(), INTERVAL 90 DAY), 1),
(@empresa_id, @v1, @comp_filtro_oleo, @tipo_prev, 10000, NULL, 100000, NULL, 1),
(@empresa_id, @v1, @comp_freios, @tipo_prev, 20000, 365, 95000, DATE_SUB(CURDATE(), INTERVAL 200 DAY), 1),
(@empresa_id, @v2, @comp_oleo, @tipo_prev, 10000, 180, 110000, DATE_SUB(CURDATE(), INTERVAL 60 DAY), 1),
(@empresa_id, @v2, @comp_filtro_ar, @tipo_prev, 15000, NULL, 105000, NULL, 1),
(@empresa_id, @v2, @comp_arref, @tipo_prev, NULL, 365, NULL, DATE_SUB(CURDATE(), INTERVAL 400 DAY), 1),
(@empresa_id, @v3, @comp_oleo, @tipo_prev, 10000, 180, 90000, DATE_SUB(CURDATE(), INTERVAL 150 DAY), 1),
(@empresa_id, @v3, @comp_freios, @tipo_prev, 25000, 365, 80000, DATE_SUB(CURDATE(), INTERVAL 100 DAY), 1)
ON DUPLICATE KEY UPDATE
    intervalo_km = VALUES(intervalo_km),
    intervalo_dias = VALUES(intervalo_dias),
    ultimo_km = VALUES(ultimo_km),
    ultima_data = VALUES(ultima_data),
    ativo = 1,
    updated_at = NOW();

SELECT 'Planos de exemplo inseridos. Veículos com km_atual definido para gerar alertas por km.' AS resultado;
