<?php
// Script para testar e corrigir problemas de codificação nos logs

echo "=== TESTE DE CODIFICAÇÃO DE LOGS ===\n";

// Teste 1: Verificar configurações atuais
echo "\n1. Configurações atuais:\n";
echo "Default charset: " . ini_get('default_charset') . "\n";
echo "Internal encoding: " . mb_internal_encoding() . "\n";
echo "Locale: " . setlocale(LC_ALL, 0) . "\n";
echo "Error log path: " . ini_get('error_log') . "\n";
echo "Log errors: " . ini_get('log_errors') . "\n";

// Teste 2: Testar escrita de log
echo "\n2. Testando escrita de log...\n";
$test_message = "TESTE DE LOG - " . date('Y-m-d H:i:s') . " - Caracteres especiais: áéíóú çãõ";
error_log($test_message);

// Teste 3: Verificar se o arquivo foi criado/modificado
$log_file = __DIR__ . '/logs/php_errors.log';
if (file_exists($log_file)) {
    echo "Arquivo de log existe: " . $log_file . "\n";
    echo "Tamanho: " . filesize($log_file) . " bytes\n";
    echo "Última modificação: " . date('Y-m-d H:i:s', filemtime($log_file)) . "\n";
    
    // Teste 4: Ler as últimas linhas do arquivo
    echo "\n3. Últimas 5 linhas do arquivo de log:\n";
    $lines = file($log_file);
    $last_lines = array_slice($lines, -5);
    foreach ($last_lines as $line) {
        echo "Linha: " . trim($line) . "\n";
    }
} else {
    echo "ERRO: Arquivo de log não encontrado!\n";
}

// Teste 5: Verificar se há caracteres corrompidos
echo "\n4. Verificando caracteres corrompidos...\n";
if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    $corrupted_chars = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', $content, $matches);
    echo "Caracteres corrompidos encontrados: " . $corrupted_chars . "\n";
    
    if ($corrupted_chars > 0) {
        echo "AVISO: Arquivo contém caracteres corrompidos!\n";
    }
}

// Teste 6: Configurar codificação correta
echo "\n5. Configurando codificação correta...\n";
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Teste 7: Testar escrita com codificação correta
echo "\n6. Testando escrita com codificação UTF-8...\n";
$test_message_utf8 = "TESTE UTF-8 - " . date('Y-m-d H:i:s') . " - Caracteres: áéíóú çãõ";
error_log($test_message_utf8);

echo "\n=== FIM DO TESTE ===\n";
?> 