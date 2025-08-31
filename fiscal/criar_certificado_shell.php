<?php
/**
 * 🔐 Criar Certificado via Shell OpenSSL
 * 📋 Gera certificado usando comandos OpenSSL diretos
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Criar Certificado via Shell</title>
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
        <h1>🔐 Criar Certificado via Shell OpenSSL</h1>
        <p>Gera certificado usando comandos OpenSSL diretos</p>";

// Verificar se OpenSSL está disponível no sistema
$openssl_version = shell_exec('openssl version 2>&1');
if (empty($openssl_version) || strpos($openssl_version, 'OpenSSL') === false) {
    echo "<div class='card error'>
        <h3>❌ OpenSSL não disponível no sistema</h3>
        <p>O comando OpenSSL não está disponível no sistema.</p>
        <p>Você precisa instalar o OpenSSL ou usar o certificado existente.</p>
    </div>";
    exit;
}

echo "<div class='card info'>
    <h3>📋 Versão OpenSSL</h3>
    <p><strong>Versão:</strong> " . htmlspecialchars(trim($openssl_version)) . "</p>
</div>";

echo "<div class='card info'>
    <h3>📋 Configurações do Certificado</h3>
    <p><strong>CNPJ:</strong> 00.000.000/0001-91 (Homologação NF-e)</p>
    <p><strong>Validade:</strong> 2 anos</p>
    <p><strong>País:</strong> BR (Brasil)</p>
    <p><strong>Estado:</strong> RS (Rio Grande do Sul)</p>
    <p><strong>Cidade:</strong> Porto Alegre</p>
    <p><strong>Organização:</strong> EMPRESA TESTE LTDA</p>
</div>";

// Caminhos dos arquivos
$pasta_certificados = realpath('../uploads/certificados/');
$cert_shell_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_shell.pem';
$key_shell_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_shell.pem';
$config_file = $pasta_certificados . DIRECTORY_SEPARATOR . 'openssl.conf';

echo "<div class='card'>
    <h3>🔑 Passo 1: Criando Arquivo de Configuração OpenSSL</h3>";

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
    echo "<p><strong>✅ Arquivo de configuração criado:</strong> <code>$config_file</code></p>";
} else {
    echo "<p><strong>❌ Erro ao criar arquivo de configuração</strong></p>";
    exit;
}

echo "<h3>🔑 Passo 2: Gerando Chave Privada</h3>";

// Gerar chave privada
$cmd_key = "openssl genrsa -out \"$key_shell_pem\" 2048 2>&1";
$output_key = shell_exec($cmd_key);

if (file_exists($key_shell_pem)) {
    echo "<p><strong>✅ Chave privada gerada:</strong> <code>$key_shell_pem</code></p>";
    echo "<p><strong>Comando executado:</strong> <code>$cmd_key</code></p>";
    if (!empty($output_key)) {
        echo "<p><strong>Saída:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($output_key) . "</div>";
    }
} else {
    echo "<p><strong>❌ Erro ao gerar chave privada</strong></p>";
    echo "<p><strong>Comando:</strong> <code>$cmd_key</code></p>";
    echo "<p><strong>Saída:</strong></p>";
    echo "<div class='code'>" . htmlspecialchars($output_key) . "</div>";
    exit;
}

echo "<h3>📜 Passo 3: Gerando Certificado</h3>";

// Gerar certificado
$cmd_cert = "openssl req -new -x509 -key \"$key_shell_pem\" -out \"$cert_shell_pem\" -days 730 -config \"$config_file\" 2>&1";
$output_cert = shell_exec($cmd_cert);

if (file_exists($cert_shell_pem)) {
    echo "<p><strong>✅ Certificado gerado:</strong> <code>$cert_shell_pem</code></p>";
    echo "<p><strong>Comando executado:</strong> <code>$cmd_cert</code></p>";
    if (!empty($output_cert)) {
        echo "<p><strong>Saída:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($output_cert) . "</div>";
    }
} else {
    echo "<p><strong>❌ Erro ao gerar certificado</strong></p>";
    echo "<p><strong>Comando:</strong> <code>$cmd_cert</code></p>";
    echo "<p><strong>Saída:</strong></p>";
    echo "<div class='code'>" . htmlspecialchars($output_cert) . "</div>";
    exit;
}

echo "<h3>🔍 Passo 4: Verificando Arquivos</h3>";

// Verificar arquivos criados
$cert_existe = file_exists($cert_shell_pem);
$key_existe = file_exists($key_shell_pem);

if ($cert_existe && $key_existe) {
    echo "<p><strong>✅ Todos os arquivos foram criados com sucesso!</strong></p>";
    
    // Verificar tamanhos
    $cert_size = filesize($cert_shell_pem);
    $key_size = filesize($key_shell_pem);
    
    echo "<p><strong>Tamanho do certificado:</strong> " . number_format($cert_size) . " bytes</p>";
    echo "<p><strong>Tamanho da chave:</strong> " . number_format($key_size) . " bytes</p>";
    
    // Verificar se são válidos usando OpenSSL
    $cmd_verify_cert = "openssl x509 -in \"$cert_shell_pem\" -text -noout 2>&1";
    $verify_cert = shell_exec($cmd_verify_cert);
    
    if (strpos($verify_cert, 'Certificate:') !== false) {
        echo "<p><strong>✅ Certificado é válido!</strong></p>";
        
        // Extrair informações básicas
        $cmd_subject = "openssl x509 -in \"$cert_shell_pem\" -subject -noout 2>&1";
        $subject = shell_exec($cmd_subject);
        
        $cmd_dates = "openssl x509 -in \"$cert_shell_pem\" -dates -noout 2>&1";
        $dates = shell_exec($cmd_dates);
        
        echo "<p><strong>Assunto:</strong> " . htmlspecialchars(trim($subject)) . "</p>";
        echo "<p><strong>Datas:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars(trim($dates)) . "</div>";
        
    } else {
        echo "<p><strong>❌ Erro na validação do certificado</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($verify_cert) . "</div>";
    }
    
    // Verificar chave privada
    $cmd_verify_key = "openssl rsa -in \"$key_shell_pem\" -check -noout 2>&1";
    $verify_key = shell_exec($cmd_verify_key);
    
    if (strpos($verify_key, 'RSA key ok') !== false) {
        echo "<p><strong>✅ Chave privada é válida!</strong></p>";
    } else {
        echo "<p><strong>❌ Erro na validação da chave</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($verify_key) . "</div>";
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
    <p>2. Use os arquivos: <code>certificado_shell.pem</code> e <code>chave_shell.pem</code></p>
    <p>3. Teste a conexão com a SEFAZ</p>
</div>";

echo "</div></body></html>";
?>
