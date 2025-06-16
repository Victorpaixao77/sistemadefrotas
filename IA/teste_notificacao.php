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