<?php
/**
 * 🔐 Criar Certificado - Versão PHP Fix
 * 📋 Gera certificado resolvendo problemas de permissão
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Criar Certificado - PHP Fix</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔐 Criar Certificado - PHP Fix</h1>
        <p>Gera certificado resolvendo problemas de permissão do PHP</p>";

// Verificar configurações do PHP
echo "<div class='card info'>
    <h3>🔍 Configurações do PHP</h3>";

// Verificar funções disponíveis
$functions = ['shell_exec', 'exec', 'system', 'passthru'];
$available_functions = [];
foreach ($functions as $func) {
    if (function_exists($func) && !in_array($func, explode(',', ini_get('disable_functions')))) {
        $available_functions[] = $func;
    }
}

if (empty($available_functions)) {
    echo "<p><strong>❌ Nenhuma função de shell disponível!</strong></p>";
    echo "<p>Funções desabilitadas: " . ini_get('disable_functions') . "</p>";
    echo "<p>Você precisa habilitar shell_exec no php.ini</p>";
    exit;
}

echo "<p><strong>✅ Funções disponíveis:</strong> " . implode(', ', $available_functions) . "</p>";

// Verificar safe_mode
if (ini_get('safe_mode')) {
    echo "<p><strong>⚠️ Safe Mode ativado:</strong> " . ini_get('safe_mode') . "</p>";
} else {
    echo "<p><strong>✅ Safe Mode desativado</strong></p>";
}

// Verificar open_basedir
$open_basedir = ini_get('open_basedir');
if ($open_basedir) {
    echo "<p><strong>⚠️ Open Basedir restrito:</strong> $open_basedir</p>";
} else {
    echo "<p><strong>✅ Open Basedir não restrito</strong></p>";
}

echo "</div>";

// Tentar diferentes métodos para executar OpenSSL
echo "<div class='card'>
    <h3>🔧 Testando Métodos de Execução</h3>";

$openssl_found = false;
$openssl_path = '';

// Método 1: Verificar PATH do sistema
echo "<h4>📋 Método 1: Verificando PATH do sistema</h4>";

$env_path = getenv('PATH');
if ($env_path) {
    echo "<p><strong>PATH do sistema:</strong> $env_path</p>";
    
    // Tentar encontrar OpenSSL no PATH
    $paths = explode(PATH_SEPARATOR, $env_path);
    foreach ($paths as $path) {
        $openssl_exe = $path . DIRECTORY_SEPARATOR . 'openssl.exe';
        if (file_exists($openssl_exe)) {
            $openssl_path = $openssl_exe;
            $openssl_found = true;
            echo "<p><strong>✅ OpenSSL encontrado:</strong> $openssl_path</p>";
            break;
        }
    }
    
    if (!$openssl_found) {
        echo "<p><strong>❌ OpenSSL não encontrado no PATH</strong></p>";
    }
} else {
    echo "<p><strong>❌ Variável PATH não encontrada</strong></p>";
}

// Método 2: Verificar locais comuns
if (!$openssl_found) {
    echo "<h4>📋 Método 2: Verificando locais comuns</h4>";
    
    $common_paths = [
        'C:\Program Files\OpenSSL-Win64\bin\openssl.exe',
        'C:\Program Files\OpenSSL-Win32\bin\openssl.exe',
        'C:\OpenSSL-Win64\bin\openssl.exe',
        'C:\OpenSSL-Win32\bin\openssl.exe',
        'C:\xampp\bin\openssl.exe',
        'C:\wamp\bin\openssl.exe'
    ];
    
    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            $openssl_path = $path;
            $openssl_found = true;
            echo "<p><strong>✅ OpenSSL encontrado:</strong> $openssl_path</p>";
            break;
        }
    }
    
    if (!$openssl_found) {
        echo "<p><strong>❌ OpenSSL não encontrado em locais comuns</strong></p>";
    }
}

// Método 3: Tentar executar comando
if (!$openssl_found) {
    echo "<h4>📋 Método 3: Tentando executar comando</h4>";
    
    $test_command = 'openssl version 2>&1';
    $output = shell_exec($test_command);
    
    if ($output && strpos($output, 'OpenSSL') !== false) {
        $openssl_found = true;
        $openssl_path = 'openssl';
        echo "<p><strong>✅ OpenSSL executável via comando:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($output) . "</div>";
    } else {
        echo "<p><strong>❌ Falha ao executar comando OpenSSL</strong></p>";
        if ($output) {
            echo "<p><strong>Saída:</strong></p>";
            echo "<div class='code'>" . htmlspecialchars($output) . "</div>";
        }
    }
}

echo "</div>";

// Se OpenSSL foi encontrado, gerar certificado
if ($openssl_found) {
    echo "<div class='card success'>
        <h3>🎯 OpenSSL Funcionando! Gerando Certificado...</h3>";
    
    // Caminhos dos arquivos
    $pasta_certificados = realpath('../uploads/certificados/');
    $cert_php_fix_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_php_fix.pem';
    $key_php_fix_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_php_fix.pem';
    $config_file = $pasta_certificados . DIRECTORY_SEPARATOR . 'openssl_php.conf';
    
    echo "<h4>🔑 Passo 1: Criando arquivo de configuração</h4>";
    
    // Criar arquivo de configuração OpenSSL
    $openssl_config = "[req]
default_bits = 2048
default_keyfile = server-key.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[req_distinguished_name]
C = BR
ST = RS
L = Porto Alegre
O = EMPRESA TESTE LTDA
OU = TI
CN = 00.000.000/0001-91
emailAddress = teste@empresa.com.br

[v3_req]
basicConstraints = CA:FALSE
keyUsage = nonRepudiation, digitalSignature, keyEncipherment
subjectAltName = @alt_names

[alt_names]
DNS.1 = localhost
DNS.2 = *.localhost
IP.1 = 127.0.0.1";

    if (file_put_contents($config_file, $openssl_config)) {
        echo "<p><strong>✅ Arquivo de configuração criado</strong></p>";
    } else {
        echo "<p><strong>❌ Erro ao criar arquivo de configuração</strong></p>";
        exit;
    }
    
    echo "<h4>🔑 Passo 2: Gerando chave privada</h4>";
    
    // Gerar chave privada
    $cmd_key = "\"$openssl_path\" genrsa -out \"$key_php_fix_pem\" 2048 2>&1";
    $output_key = shell_exec($cmd_key);
    
    if (file_exists($key_php_fix_pem)) {
        echo "<p><strong>✅ Chave privada gerada</strong></p>";
        echo "<p><strong>Comando:</strong> <code>$cmd_key</code></p>";
    } else {
        echo "<p><strong>❌ Erro ao gerar chave privada</strong></p>";
        echo "<p><strong>Saída:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($output_key) . "</div>";
        exit;
    }
    
    echo "<h4>📜 Passo 3: Gerando certificado</h4>";
    
    // Gerar certificado
    $cmd_cert = "\"$openssl_path\" req -new -x509 -key \"$key_php_fix_pem\" -out \"$cert_php_fix_pem\" -days 730 -config \"$config_file\" 2>&1";
    $output_cert = shell_exec($cmd_cert);
    
    if (file_exists($cert_php_fix_pem)) {
        echo "<p><strong>✅ Certificado gerado</strong></p>";
        echo "<p><strong>Comando:</strong> <code>$cmd_cert</code></p>";
    } else {
        echo "<p><strong>❌ Erro ao gerar certificado</strong></p>";
        echo "<p><strong>Saída:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($output_cert) . "</div>";
        exit;
    }
    
    echo "<h4>🔍 Passo 4: Verificando arquivos</h4>";
    
    // Verificar arquivos criados
    $cert_existe = file_exists($cert_php_fix_pem);
    $key_existe = file_exists($key_php_fix_pem);
    
    if ($cert_existe && $key_existe) {
        echo "<p><strong>✅ Todos os arquivos foram criados com sucesso!</strong></p>";
        
        // Verificar tamanhos
        $cert_size = filesize($cert_php_fix_pem);
        $key_size = filesize($key_php_fix_pem);
        
        echo "<p><strong>Tamanho do certificado:</strong> " . number_format($cert_size) . " bytes</p>";
        echo "<p><strong>Tamanho da chave:</strong> " . number_format($key_size) . " bytes</p>";
        
        // Verificar se são válidos
        $cmd_verify = "\"$openssl_path\" x509 -in \"$cert_php_fix_pem\" -subject -noout 2>&1";
        $verify_output = shell_exec($cmd_verify);
        
        if ($verify_output && strpos($verify_output, 'subject=') !== false) {
            echo "<p><strong>✅ Certificado é válido!</strong></p>";
            echo "<p><strong>Assunto:</strong> " . htmlspecialchars(trim($verify_output)) . "</p>";
        } else {
            echo "<p><strong>❌ Erro na validação do certificado</strong></p>";
            echo "<div class='code'>" . htmlspecialchars($verify_output) . "</div>";
        }
        
    } else {
        echo "<p><strong>❌ Erro: Nem todos os arquivos foram criados</strong></p>";
    }
    
    // Limpar arquivo de configuração temporário
    if (file_exists($config_file)) {
        unlink($config_file);
        echo "<p><strong>🗑️ Arquivo de configuração temporário removido</strong></p>";
    }
    
    echo "<div class='card success'>
        <h3>🎯 Próximos Passos</h3>
        <p>1. Execute o teste SEFAZ com o novo certificado</p>
        <p>2. Use os arquivos: <code>certificado_php_fix.pem</code> e <code>chave_php_fix.pem</code></p>
        <p>3. Teste a conexão com a SEFAZ</p>
    </div>";
    
} else {
    echo "<div class='card error'>
        <h3>❌ OpenSSL não pôde ser executado</h3>
        <p>Mesmo com OpenSSL instalado, o PHP não consegue executá-lo.</p>
        <h4>💡 Soluções possíveis:</h4>
        <p><strong>1. Verificar php.ini:</strong></p>
        <ul>
            <li>Habilitar: <code>shell_exec</code></li>
            <li>Desabilitar: <code>safe_mode</code></li>
            <li>Configurar: <code>open_basedir</code></li>
        </ul>
        <p><strong>2. Verificar permissões do servidor web</strong></p>
        <p><strong>3. Usar certificado existente</strong></p>
    </div>";
}

echo "</div></body></html>";
?>
