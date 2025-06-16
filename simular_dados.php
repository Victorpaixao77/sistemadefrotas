<?php
// simular_dados.php
require_once 'includes/conexao.php'; // Corrigido o caminho

$empresa_id = 1;
$veiculo_id = 55;
$motorista_id = 48;

// 1. Rota com eficiência baixa (<70%)
$pdo->query("INSERT INTO rotas (empresa_id, veiculo_id, motorista_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status, total_km) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), 1000, 2000, 1000, 65, 20, 5000, 'aprovado', 1000)");
$rota_id1 = $pdo->lastInsertId();

// 2. Rota com percentual de KM vazio alto (>30%)
$pdo->query("INSERT INTO rotas (empresa_id, veiculo_id, motorista_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status, total_km) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), 2000, 3000, 1000, 80, 40, 6000, 'aprovado', 1000)");
$rota_id2 = $pdo->lastInsertId();

// 3. Rota com frete menor que despesas
$pdo->query("INSERT INTO rotas (empresa_id, veiculo_id, motorista_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status, total_km) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 3000, 4000, 1000, 90, 10, 3000, 'aprovado', 1000)");
$rota_id3 = $pdo->lastInsertId();
$pdo->query("INSERT INTO despesas_viagem (empresa_id, rota_id, arla, pedagios, caixinha, estacionamento, lavagem, borracharia, eletrica_mecanica, adiantamento, status, fonte) VALUES (
    $empresa_id, $rota_id3, 500, 500, 500, 500, 500, 500, 500, 500, 'aprovado', 'gestor')");

// 4. Rota com desvio de KM percorrido (>50km)
$pdo->query("INSERT INTO rotas (empresa_id, veiculo_id, motorista_id, data_saida, data_chegada, km_saida, km_chegada, distancia_km, eficiencia_viagem, percentual_vazio, frete, status, total_km) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, NOW(), DATE_ADD(NOW(), INTERVAL 4 DAY), 4000, 5555, 1000, 85, 15, 7000, 'aprovado', 1555)");
$rota_id4 = $pdo->lastInsertId();

// 5. Abastecimento para rota 1
$pdo->query("INSERT INTO abastecimentos (empresa_id, veiculo_id, motorista_id, posto, data_abastecimento, litros, valor_litro, valor_total, km_atual, tipo_combustivel, forma_pagamento, rota_id, status, fonte) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, 'Posto Teste', NOW(), 100, 5.5, 550, 2000, 'Diesel', 'Cartão', $rota_id1, 'aprovado', 'gestor')");

// 6. Despesa fixa vencendo logo
$pdo->query("INSERT INTO despesas_fixas (empresa_id, veiculo_id, valor, vencimento, ano_referencia, forma_pagamento_id, status_pagamento_id, descricao) VALUES (
    $empresa_id, $veiculo_id, 1200, DATE_ADD(NOW(), INTERVAL 2 DAY), YEAR(NOW()), 1, 1, 'Seguro do caminhão')");

// 7. Manutenção próxima
$pdo->query("INSERT INTO manutencoes (empresa_id, veiculo_id, data_manutencao, descricao, valor, km_atual, custo_total, descricao_servico, tipo_manutencao_id, componente_id, status_manutencao_id) VALUES (
    $empresa_id, $veiculo_id, DATE_ADD(NOW(), INTERVAL 3 DAY), 'Troca de óleo', 800, 2100, 800, 'Troca de óleo e filtro', 1, 1, 1)");

// 8. Abastecimento com valor alto para análise de consumo
$pdo->query("INSERT INTO abastecimentos (empresa_id, veiculo_id, motorista_id, posto, data_abastecimento, litros, valor_litro, valor_total, km_atual, tipo_combustivel, forma_pagamento, rota_id, status, fonte) VALUES (
    $empresa_id, $veiculo_id, $motorista_id, 'Posto Caro', NOW(), 200, 7.0, 1400, 3000, 'Diesel', 'Dinheiro', $rota_id2, 'aprovado', 'gestor')");

// 9. Despesa de viagem alta para rota 2
$pdo->query("INSERT INTO despesas_viagem (empresa_id, rota_id, arla, pedagios, caixinha, estacionamento, lavagem, borracharia, eletrica_mecanica, adiantamento, status, fonte) VALUES (
    $empresa_id, $rota_id2, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 1000, 'aprovado', 'gestor')");

// 10. Despesa fixa atrasada
$pdo->query("INSERT INTO despesas_fixas (empresa_id, veiculo_id, valor, vencimento, ano_referencia, forma_pagamento_id, status_pagamento_id, descricao) VALUES (
    $empresa_id, $veiculo_id, 900, DATE_SUB(NOW(), INTERVAL 5 DAY), YEAR(NOW()), 1, 1, 'IPVA atrasado')");

echo 'Dados de simulação inseridos com sucesso!'; 