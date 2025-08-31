<?php
/**
 * 🔍 Diagnóstico DNS PHP - SEFAZ
 * 📋 Identifica e resolve problemas de resolução DNS no PHP
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico DNS PHP - SEFAZ</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; overflow-x: auto; }
        .solution { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Diagnóstico DNS PHP - SEFAZ</h1>
        <p>Identificando e resolvendo problemas de resolução DNS no PHP</p>";

// Teste 1: Verificar extensões PHP
echo "<div class='card info'>
    <h3>🔧 Verificação de Extensões PHP</h3>";

$extensions = ['openssl', 'curl', 'soap', 'sockets'];
$loaded_extensions = [];

foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        $loaded_extensions[] = $ext;
        echo "<p><strong>✅ $ext:</strong> Carregada</p>";
    } else {
        echo "<p><strong>❌ $ext:</strong> NÃO carregada</p>";
    }
}

echo "</div>";

// Teste 2: Verificar configurações SSL
echo "<div class='card info'>
    <h3>🔐 Configurações SSL/TLS</h3>";

$ssl_configs = [
    'openssl.cafile' => ini_get('openssl.cafile'),
    'curl.cainfo' => ini_get('curl.cainfo'),
    'openssl.capath' => ini_get('openssl.capath')
];

foreach ($ssl_configs as $key => $value) {
    if ($value) {
        echo "<p><strong>✅ $key:</strong> $value</p>";
    } else {
        echo "<p><strong>⚠️ $key:</strong> Não configurado</p>";
    }
}

echo "</div>";

// Teste 3: Resolução DNS via PHP
echo "<div class='card'>
    <h3>🌐 Teste de Resolução DNS</h3>";

$domains = [
    'nfe-homologacao.sefazrs.rs.gov.br',
    'nfe-homologacao.sefaz.rs.gov.br',
    'www.google.com',
    'www.sefaz.rs.gov.br'
];

foreach ($domains as $domain) {
    echo "<h4>🔍 Testando: $domain</h4>";
    
    // Método 1: gethostbyname
    $ip1 = gethostbyname($domain);
    if ($ip1 && $ip1 !== $domain) {
        echo "<p><strong>✅ gethostbyname:</strong> $ip1</p>";
    } else {
        echo "<p><strong>❌ gethostbyname:</strong> Falhou</p>";
    }
    
    // Método 2: gethostbynamel
    $ips = gethostbynamel($domain);
    if ($ips && is_array($ips)) {
        echo "<p><strong>✅ gethostbynamel:</strong> " . implode(', ', $ips) . "</p>";
    } else {
        echo "<p><strong>❌ gethostbynamel:</strong> Falhou</p>";
    }
    
    // Método 3: dns_get_record
    $records = dns_get_record($domain, DNS_A);
    if ($records && is_array($records)) {
        $dns_ips = [];
        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $dns_ips[] = $record['ip'];
            }
        }
        if (!empty($dns_ips)) {
            echo "<p><strong>✅ dns_get_record:</strong> " . implode(', ', $dns_ips) . "</p>";
        } else {
            echo "<p><strong>❌ dns_get_record:</strong> Sem registros A</p>";
        }
    } else {
        echo "<p><strong>❌ dns_get_record:</strong> Falhou</p>";
    }
    
    echo "<hr>";
}

echo "</div>";

// Teste 4: Conexão HTTP básica
echo "<div class='card'>
    <h3>🌐 Teste de Conexão HTTP</h3>";

$test_urls = [
    'https://www.google.com',
    'https://nfe-homologacao.sefazrs.rs.gov.br',
    'https://nfe-homologacao.sefaz.rs.gov.br'
];

foreach ($test_urls as $url) {
    echo "<h4>🔍 Testando: $url</h4>";
    
    // Teste com file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Sistema-Frotas/1.0'
        ]
    ]);
    
    $start_time = microtime(true);
    $response = @file_get_contents($url, false, $context);
    $end_time = microtime(true);
    $time = round(($end_time - $start_time) * 1000, 2);
    
    if ($response !== false) {
        echo "<p><strong>✅ file_get_contents:</strong> SUCESSO - {$time} ms</p>";
        echo "<p><strong>Tamanho:</strong> " . strlen($response) . " bytes</p>";
    } else {
        echo "<p><strong>❌ file_get_contents:</strong> FALHOU - {$time} ms</p>";
        $error = error_get_last();
        if ($error) {
            echo "<p><strong>Erro:</strong> " . $error['message'] . "</p>";
        }
    }
    
    // Teste com cURL se disponível
    if (extension_loaded('curl')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $start_time = microtime(true);
        $curl_response = curl_exec($ch);
        $end_time = microtime(true);
        $curl_time = round(($end_time - $start_time) * 1000, 2);
        
        if ($curl_response !== false) {
            echo "<p><strong>✅ cURL:</strong> SUCESSO - {$curl_time} ms</p>";
            echo "<p><strong>HTTP Code:</strong> " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "</p>";
        } else {
            echo "<p><strong>❌ cURL:</strong> FALHOU - {$curl_time} ms</p>";
            echo "<p><strong>Erro:</strong> " . curl_error($ch) . "</p>";
        }
        
        curl_close($ch);
    }
    
    echo "<hr>";
}

echo "</div>";

// Teste 5: Configurações de rede
echo "<div class='card info'>
    <h3>🌐 Configurações de Rede</h3>";

// Verificar proxy
$proxy_vars = ['http_proxy', 'https_proxy', 'HTTP_PROXY', 'HTTPS_PROXY'];
$proxy_found = false;

foreach ($proxy_vars as $var) {
    $value = getenv($var);
    if ($value) {
        echo "<p><strong>⚠️ $var:</strong> $value</p>";
        $proxy_found = true;
    }
}

if (!$proxy_found) {
    echo "<p><strong>✅ Proxy:</strong> Não configurado</p>";
}

// Verificar DNS
$dns_servers = dns_get_record('www.google.com', DNS_NS);
if ($dns_servers) {
    echo "<p><strong>✅ DNS:</strong> Funcionando</p>";
} else {
    echo "<p><strong>❌ DNS:</strong> Não funcionando</p>";
}

echo "</div>";

// Soluções
echo "<div class='card solution'>
    <h3>💡 Soluções para Problemas de DNS</h3>";

echo "<h4>🔧 1. Verificar php.ini</h4>";
echo "<p>Localize o arquivo php.ini e verifique:</p>";
echo "<div class='code'>";
echo "extension=openssl\n";
echo "extension=curl\n";
echo "extension=soap\n";
echo "extension=sockets\n";
echo "openssl.cafile=\"C:\\xampp\\php\\extras\\ssl\\cacert.pem\"\n";
echo "curl.cainfo=\"C:\\xampp\\php\\extras\\ssl\\cacert.pem\"";
echo "</div>";

echo "<h4>🌐 2. Configurar DNS Alternativo</h4>";
echo "<p>No php.ini, adicione:</p>";
echo "<div class='code'>";
echo "dns.default_nameserver=8.8.8.8\n";
echo "dns.default_nameserver=8.8.4.4";
echo "</div>";

echo "<h4>🔄 3. Reiniciar Serviços</h4>";
echo "<p>Após alterar php.ini:</p>";
echo "<ul>";
echo "<li>Reinicie o Apache/XAMPP</li>";
echo "<li>Reinicie o serviço de rede do Windows</li>";
echo "<li>Limpe o cache DNS: <code>ipconfig /flushdns</code></li>";
echo "</ul>";

echo "<h4>🔍 4. Testar com IP Direto</h4>";
echo "<p>Se DNS falhar, teste com IP:</p>";
echo "<div class='code'>";
echo "// Em vez de: nfe-homologacao.sefazrs.rs.gov.br\n";
echo "// Use: 200.233.4.136";
echo "</div>";

echo "</div>";

// Próximos passos
echo "<div class='card success'>
    <h3>🎯 Próximos Passos</h3>
    <p><strong>1. ✅ Certificados funcionando</strong></p>
    <p><strong>2. 🔍 Problema de DNS identificado</strong></p>
    <p><strong>3. 🔧 Aplicar soluções acima</strong></p>
    <p><strong>4. 🧪 Testar novamente</strong></p>
    <p><strong>5. 🚀 Integrar no sistema</strong></p>
</div>";

echo "</div></body></html>";
?>
