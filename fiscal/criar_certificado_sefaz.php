<?php
/**
 * 🔐 Criar Certificado SEFAZ Compatível
 * 📋 Gera certificado com extensões específicas para SEFAZ
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Criar Certificado SEFAZ</title>
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
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔐 Criar Certificado SEFAZ Compatível</h1>
        <p>Gera certificado com extensões específicas para aceitação pela SEFAZ</p>";

// Verificar se OpenSSL está disponível
if (!extension_loaded('openssl')) {
    echo "<div class='card error'>
        <h3>❌ OpenSSL não disponível</h3>
        <p>A extensão OpenSSL não está habilitada no PHP.</p>
        <p>Você precisa habilitar a extensão OpenSSL no php.ini</p>
    </div>";
    exit;
}

echo "<div class='card info'>
    <h3>📋 Configurações do Certificado SEFAZ</h3>
    <p><strong>CNPJ:</strong> 00.000.000/0001-91 (Homologação NF-e)</p>
    <p><strong>Validade:</strong> 2 anos</p>
    <p><strong>País:</strong> BR (Brasil)</p>
    <p><strong>Estado:</strong> RS (Rio Grande do Sul)</p>
    <p><strong>Cidade:</strong> Porto Alegre</p>
    <p><strong>Organização:</strong> EMPRESA TESTE LTDA</p>
    <p><strong>Extensões:</strong> X.509 v3 com extensões específicas para SEFAZ</p>
</div>";

// Configurações do certificado
$config = [
    'countryName' => 'BR',
    'stateOrProvinceName' => 'RS',
    'localityName' => 'Porto Alegre',
    'organizationName' => 'EMPRESA TESTE LTDA',
    'organizationalUnitName' => 'TI',
    'commonName' => '00.000.000/0001-91',
    'emailAddress' => 'teste@empresa.com.br'
];

// Gerar chave privada
echo "<div class='card'>
    <h3>🔑 Passo 1: Gerando Chave Privada RSA 2048 bits</h3>";

$private_key = openssl_pkey_new([
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
    'digest_alg' => 'sha256'
]);

if ($private_key === false) {
    echo "<p><strong>❌ Erro ao gerar chave privada:</strong></p>";
    echo "<div class='code'>" . openssl_error_string() . "</div>";
    exit;
}

echo "<p><strong>✅ Chave privada RSA 2048 bits gerada com sucesso!</strong></p>";

// Gerar certificado com extensões específicas
echo "<h3>📜 Passo 2: Gerando Certificado X.509 v3 com Extensões SEFAZ</h3>";

// Criar configuração OpenSSL com extensões específicas
$openssl_config = [
    'config' => [
        'digest_alg' => 'sha256',
        'x509_extensions' => 'v3_ca',
        'req_extensions' => 'v3_req',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'encrypt_key' => false
    ]
];

// Criar CSR com extensões
$dn = [
    'countryName' => $config['countryName'],
    'stateOrProvinceName' => $config['stateOrProvinceName'],
    'localityName' => $config['localityName'],
    'organizationName' => $config['organizationName'],
    'organizationalUnitName' => $config['organizationalUnitName'],
    'commonName' => $config['commonName'],
    'emailAddress' => $config['emailAddress']
];

$csr = openssl_csr_new($dn, $private_key, $openssl_config);

if ($csr === false) {
    echo "<p><strong>❌ Erro ao gerar CSR:</strong></p>";
    echo "<div class='code'>" . openssl_error_string() . "</div>";
    exit;
}

// Criar configuração para extensões
$extensions_config = [
    'basicConstraints' => 'CA:FALSE',
    'keyUsage' => 'digitalSignature, keyEncipherment, dataEncipherment',
    'extendedKeyUsage' => 'clientAuth, emailProtection',
    'subjectAltName' => 'DNS:localhost,IP:127.0.0.1',
    'subjectKeyIdentifier' => 'hash',
    'authorityKeyIdentifier' => 'keyid:always,issuer:always',
    'certificatePolicies' => '1.3.6.1.4.1.311.42.1',
    'nsComment' => 'OpenSSL Generated Certificate for SEFAZ Testing'
];

// Auto-assinar o certificado com extensões
$cert = openssl_csr_sign($csr, null, $private_key, 730, $extensions_config);

if ($cert === false) {
    echo "<p><strong>❌ Erro ao assinar certificado:</strong></p>";
    echo "<div class='code'>" . openssl_error_string() . "</div>";
    exit;
}

echo "<p><strong>✅ Certificado X.509 v3 com extensões gerado com sucesso!</strong></p>";

// Salvar arquivos
echo "<h3>💾 Passo 3: Salvando Arquivos</h3>";

$pasta_certificados = realpath('../uploads/certificados/');
$cert_sefaz_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_sefaz.pem';
$key_sefaz_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_sefaz.pem';

// Salvar certificado
if (openssl_x509_export($cert, $cert_pem)) {
    if (file_put_contents($cert_sefaz_pem, $cert_pem)) {
        echo "<p><strong>✅ Certificado salvo:</strong> <code>$cert_sefaz_pem</code></p>";
    } else {
        echo "<p><strong>❌ Erro ao salvar certificado</strong></p>";
    }
} else {
    echo "<p><strong>❌ Erro ao exportar certificado</strong></p>";
}

// Salvar chave privada
if (openssl_pkey_export($private_key, $key_pem)) {
    if (file_put_contents($key_sefaz_pem, $key_pem)) {
        echo "<p><strong>✅ Chave privada salva:</strong> <code>$key_sefaz_pem</code></p>";
    } else {
        echo "<p><strong>❌ Erro ao salvar chave privada</strong></p>";
    }
} else {
    echo "<p><strong>❌ Erro ao exportar chave privada</strong></p>";
}

// Verificar arquivos criados
echo "<h3>🔍 Passo 4: Verificando Arquivos</h3>";

$cert_existe = file_exists($cert_sefaz_pem);
$key_existe = file_exists($key_sefaz_pem);

if ($cert_existe && $key_existe) {
    echo "<p><strong>✅ Todos os arquivos foram criados com sucesso!</strong></p>";
    
    // Verificar tamanhos
    $cert_size = filesize($cert_sefaz_pem);
    $key_size = filesize($key_sefaz_pem);
    
    echo "<p><strong>Tamanho do certificado:</strong> " . number_format($cert_size) . " bytes</p>";
    echo "<p><strong>Tamanho da chave:</strong> " . number_format($key_size) . " bytes</p>";
    
    // Verificar se são válidos
    $cert_info = openssl_x509_read(file_get_contents($cert_sefaz_pem));
    $key_info = openssl_pkey_get_private(file_get_contents($key_sefaz_pem));
    
    if ($cert_info && $key_info) {
        echo "<p><strong>✅ Certificado e chave são válidos!</strong></p>";
        
        // Mostrar informações detalhadas do certificado
        $cert_details = openssl_x509_parse($cert_info);
        echo "<p><strong>CNPJ:</strong> " . $cert_details['subject']['commonName'] . "</p>";
        echo "<p><strong>Organização:</strong> " . $cert_details['subject']['organizationName'] . "</p>";
        echo "<p><strong>Validade:</strong> " . date('d/m/Y', $cert_details['validFrom_time_t']) . " até " . date('d/m/Y', $cert_details['validTo_time_t']) . "</p>";
        
        // Mostrar extensões
        if (isset($cert_details['extensions'])) {
            echo "<p><strong>Extensões X.509 v3:</strong></p>";
            echo "<div class='code'>";
            foreach ($cert_details['extensions'] as $ext_name => $ext_value) {
                echo "$ext_name: $ext_value\n";
            }
            echo "</div>";
        }
        
    } else {
        echo "<p><strong>❌ Erro na validação dos arquivos</strong></p>";
    }
    
} else {
    echo "<p><strong>❌ Erro: Nem todos os arquivos foram criados</strong></p>";
}

// Limpar recursos
openssl_free_key($private_key);
openssl_x509_free($cert);

echo "<div class='card success'>
    <h3>🎯 Próximos Passos</h3>
    <p>1. Execute o teste SEFAZ com o novo certificado</p>
    <p>2. Use os arquivos: <code>certificado_sefaz.pem</code> e <code>chave_sefaz.pem</code></p>
    <p>3. Teste a conexão com a SEFAZ</p>
    <p>4. Se ainda der HTTP 403, pode ser necessário certificado real da ICP-Brasil</p>
</div>";

echo "<div class='card info'>
    <h3>💡 Sobre Certificados SEFAZ</h3>
    <p><strong>Certificados de teste:</strong> Podem ser rejeitados pela SEFAZ</p>
    <p><strong>Certificados reais:</strong> Precisam ser emitidos por Autoridade Certificadora válida</p>
    <p><strong>ICP-Brasil:</strong> Certificados válidos para produção</p>
    <p><strong>Homologação:</strong> Alguns certificados de teste podem funcionar</p>
</div>";

echo "</div></body></html>";
?>
