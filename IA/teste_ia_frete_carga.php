<?php
// Arquivo de teste para simular a execução da IA e gerar logs detalhados
require_once __DIR__ . '/../includes/db_connect.php';
$conn = getConnection();
$empresa_id = 1;

// Ativar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar buffer de saída
ob_start();

// Incluir o script da IA
require_once __DIR__ . '/ia_regras.php';

// Capturar a saída do script
$output = ob_get_clean();

// Exibir logs
echo '<h2>Logs da IA</h2>';
echo '<pre>' . $output . '</pre>';

// Exibir logs do PHP
echo '<h2>Logs do PHP</h2>';
echo '<pre>';
$log_file = ini_get('error_log');
if (file_exists($log_file)) {
    echo file_get_contents($log_file);
} else {
    echo "Arquivo de log não encontrado: " . $log_file;
}
echo '</pre>';

header('Content-Type: text/html; charset=utf-8');
echo '<h2>Teste IA - Frete Zerado e Carga Excedida</h2>';

// --- Frete zerado ---
try {
    echo '<h3>Frete zerado</h3>';
    $sql = "SELECT r.*, v.placa, co.nome AS cidade_origem_nome, cd.nome AS cidade_destino_nome, r.data_rota
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE r.frete = 0";
    $count_frete_zerado = 0;
    foreach ($conn->query($sql) as $row) {
        $count_frete_zerado++;
        echo "<pre>FRETE ZERADO: " . print_r($row, true) . "</pre>";
    }
    echo "<b>Total encontrados: $count_frete_zerado</b><br>";
} catch (Exception $e) {
    echo "<b>Erro em frete zerado:</b> " . $e->getMessage();
}

// --- Carga excedida ---
try {
    echo '<h3>Carga excedida</h3>';
    $sql = "SELECT r.*, v.placa, v.capacidade_carga FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE r.peso_carga > v.capacidade_carga";
    $count_carga_excedida = 0;
    foreach ($conn->query($sql) as $row) {
        $count_carga_excedida++;
        echo "<pre>CARGA EXCEDIDA: " . print_r($row, true) . "</pre>";
    }
    echo "<b>Total encontrados: $count_carga_excedida</b><br>";
} catch (Exception $e) {
    echo "<b>Erro em carga excedida:</b> " . $e->getMessage();
} 