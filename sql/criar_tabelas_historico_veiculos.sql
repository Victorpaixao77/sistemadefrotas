-- ===============================================
-- CONSULTAS PARA HISTÓRICO DE VEÍCULOS
-- ===============================================

-- NOTA: Não criamos tabelas ou views adicionais
-- Usamos apenas as tabelas existentes: rotas, abastecimentos, manutencoes, veiculos

-- EXEMPLOS DE CONSULTAS DIRETAS:
-- ===============================================

/*
-- 1. Histórico de quilometragem (usando tabelas existentes):

-- Dados de viagens (rotas)
SELECT 
    r.id,
    COALESCE(r.total_km, r.distancia_km, 0) as quilometragem,
    r.km_saida,
    r.km_chegada,
    r.data_saida as data_registro,
    'viagem' as tipo_registro,
    CONCAT('Viagem: ', COALESCE(co.nome, r.estado_origem), ' → ', COALESCE(cd.nome, r.estado_destino)) as observacoes
FROM rotas r
LEFT JOIN cidades co ON r.cidade_origem_id = co.id
LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
WHERE r.veiculo_id = ? 
AND r.status = 'aprovado'
ORDER BY r.data_saida DESC;

-- Dados de abastecimentos
SELECT 
    a.id,
    a.km_atual as quilometragem,
    a.data_abastecimento as data_registro,
    'abastecimento' as tipo_registro,
    CONCAT('Abastecimento: ', a.posto, ' - ', a.litros, 'L') as observacoes
FROM abastecimentos a
WHERE a.veiculo_id = ? 
AND a.status = 'aprovado'
AND a.km_atual IS NOT NULL
ORDER BY a.data_abastecimento DESC;

-- Dados de manutenções
SELECT 
    m.id,
    m.km_atual as quilometragem,
    m.data_manutencao as data_registro,
    'manutencao' as tipo_registro,
    CONCAT('Manutenção: ', LEFT(m.descricao, 50)) as observacoes
FROM manutencoes m
WHERE m.veiculo_id = ? 
AND m.km_atual IS NOT NULL
ORDER BY m.data_manutencao DESC;

-- 2. Calcular KM percorrida em período:
SELECT COALESCE(SUM(COALESCE(total_km, distancia_km, 0)), 0) as km_percorrida
FROM rotas 
WHERE veiculo_id = ? 
AND DATE(data_saida) BETWEEN ? AND ?
AND status = 'aprovado';

-- 3. Dados completos do veículo:
SELECT v.*, s.nome as status_nome, tc.nome as tipo_combustivel_nome
FROM veiculos v
LEFT JOIN status_veiculos s ON v.status_id = s.id
LEFT JOIN tipos_combustivel tc ON v.tipo_combustivel_id = tc.id
WHERE v.id = ? AND v.empresa_id = ?;
*/
