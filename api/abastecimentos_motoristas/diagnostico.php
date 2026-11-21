<?php
require_once __DIR__ . '/../../includes/sf_api_guard_debug.php';
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/../../includes/config.php';
configure_session();
session_start();
require_authentication();
echo "----------------\n";
echo "Data/Hora: ".date('Y-m-d H:i:s')."\n";
echo "Versão do PHP: ".phpversion()."\n";
echo "\n";

// Teste de execução PHP
$ok = true;
try {
    $test = 2 + 2;
    echo "Teste de execução PHP: 2 + 2 = $test\n";
    if ($test !== 4) {
        $ok = false;
        echo "[ERRO] PHP não está executando corretamente!\n";
    }
} catch (Exception $e) {
    $ok = false;
    echo "[ERRO] Exceção ao executar PHP: "."\n";
}

// Teste de permissão de arquivo
$filename = __FILE__;
echo "Arquivo atual: $filename\n";
echo "Permissões do arquivo: ".substr(sprintf('%o', fileperms($filename)), -4)."\n";

echo "\n";
if ($ok) {
    echo "[OK] PHP está sendo processado corretamente nesta pasta!\n";
} else {
    echo "[ERRO] PHP NÃO está sendo processado corretamente nesta pasta!\n";
}

echo "\n";
echo "Se você está vendo este texto, o PHP está funcionando.\n";
echo "Se você está vendo o código-fonte, o PHP NÃO está funcionando nesta pasta.\n"; 