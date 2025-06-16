<?php
require_once __DIR__ . '/../includes/db_connect.php';
$conn = getConnection();

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Teste de Queries - Rotas</h2>';

// 1. Frete zerado
$sql1 = "SELECT r.*, v.placa, co.nome AS cidade_origem_nome, cd.nome AS cidade_destino_nome, r.data_rota
        FROM rotas r
        JOIN veiculos v ON r.veiculo_id = v.id
        LEFT JOIN cidades co ON r.cidade_origem_id = co.id
        LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
        WHERE r.frete = 0";
$res1 = $conn->query($sql1)->fetchAll(PDO::FETCH_ASSOC);
echo '<h3>Rotas com frete zerado</h3>';
echo '<pre>' . print_r($res1, true) . '</pre>';

// 2. Carga excedida
$sql2 = "SELECT r.*, v.placa, v.capacidade_carga FROM rotas r
        JOIN veiculos v ON r.veiculo_id = v.id
        WHERE r.peso_carga > v.capacidade_carga";
$res2 = $conn->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
echo '<h3>Rotas com carga excedida</h3>';
echo '<pre>' . print_r($res2, true) . '</pre>';

// 3. Tipos dos campos
$desc = $conn->query("DESCRIBE rotas")->fetchAll(PDO::FETCH_ASSOC);
echo '<h3>Estrutura da tabela rotas</h3>';
echo '<pre>' . print_r($desc, true) . '</pre>';

// 4. Empresa ID das rotas
$res3 = $conn->query("SELECT id, empresa_id FROM rotas")->fetchAll(PDO::FETCH_ASSOC);
echo '<h3>Empresa ID das rotas</h3>';
echo '<pre>' . print_r($res3, true) . '</pre>'; 