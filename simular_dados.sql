-- Simulação de dados para acionar as novas regras de IA

-- 1. Rotas com eficiência da viagem abaixo de 70%
INSERT INTO rotas (empresa_id, motorista_id, veiculo_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status) VALUES
(1, 1, 1, NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), 1000, 2000, 1000, 65, 20, 5000, 'concluida');

-- 2. Rotas com percentual de KM vazio alto (> 30%)
INSERT INTO rotas (empresa_id, motorista_id, veiculo_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status) VALUES
(1, 2, 2, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 2000, 3000, 1000, 80, 40, 6000, 'concluida');

-- 3. Rotas com custo superior ao frete
INSERT INTO rotas (empresa_id, motorista_id, veiculo_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status) VALUES
(1, 3, 3, NOW(), DATE_ADD(NOW(), INTERVAL 4 DAY), 3000, 4000, 1000, 90, 10, 3000, 'concluida');

INSERT INTO despesas_viagem (rota_id, valor) VALUES (LAST_INSERT_ID(), 4000);

-- 4. Rotas com desvio de KM percorrido (> 50 km)
INSERT INTO rotas (empresa_id, motorista_id, veiculo_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status) VALUES
(1, 4, 4, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY), 4000, 5500, 1000, 85, 15, 7000, 'concluida');

-- 5. Motoristas com múltiplos checklists pendentes (> 3)
INSERT INTO checklists (motorista_id, status) VALUES
(5, 'pendente'),
(5, 'pendente'),
(5, 'pendente'),
(5, 'pendente');

-- 6. Manutenções agendadas para os próximos 5 dias
INSERT INTO manutencoes (veiculo_id, data_prevista, status) VALUES
(5, DATE_ADD(NOW(), INTERVAL 3 DAY), 'agendada'); 