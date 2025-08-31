<?php
/**
 * 🧪 Teste SEFAZ com Novo Certificado
 * 📋 Testa conexão usando certificado gerado via PHP Fix
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - Novo Certificado</title>
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
        .response { background: #f1f3f4; padding: 15px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Teste SEFAZ - Novo Certificado</h1>
        <p>Testando conexão com SEFAZ usando certificado gerado via PHP Fix</p>";

// Verificar se certificado existe
$pasta_certificados = realpath('../uploads/certificados/');
$cert_file = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_php_fix.pem';
$key_file = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_php_fix.pem';

echo "<div class='card info'>
    <h3>📋 Verificação de Arquivos</h3>";

if (file_exists($cert_file) && file_exists($key_file)) {
    echo "<p><strong>✅ Certificado encontrado:</strong> " . basename($cert_file) . "</p>";
    echo "<p><strong>✅ Chave privada encontrada:</strong> " . basename($key_file) . "</p>";
    
    // Verificar tamanhos
    $cert_size = filesize($cert_file);
    $key_size = filesize($key_file);
    echo "<p><strong>Tamanho do certificado:</strong> " . number_format($cert_size) . " bytes</p>";
    echo "<p><strong>Tamanho da chave:</strong> " . number_format($key_size) . " bytes</p>";
    
} else {
    echo "<p><strong>❌ Arquivos de certificado não encontrados!</strong></p>";
    echo "<p>Execute primeiro: <code>criar_certificado_php_fix.php</code></p>";
    exit;
}

echo "</div>";

// Configurações de teste
$config = [
    'ambiente' => 'homologacao',
    'uf' => 'RS',
    'versao' => '4.00',
    'timeout' => 30
];

echo "<div class='card info'>
    <h3>⚙️ Configurações de Teste</h3>
    <p><strong>Ambiente:</strong> " . strtoupper($config['ambiente']) . "</p>
    <p><strong>UF:</strong> {$config['uf']}</p>
    <p><strong>Versão NF-e:</strong> {$config['versao']}</p>
    <p><strong>Timeout:</strong> {$config['timeout']} segundos</p>
</div>";

// URLs de teste
$urls = [
    'RS' => [
        'homologacao' => 'https://nfe-homologacao.sefaz.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx',
        'producao' => 'https://nfe.sefaz.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx'
    ]
];

$test_url = $urls[$config['uf']][$config['ambiente']];

echo "<div class='card'>
    <h3>🌐 Testando Conexão SEFAZ</h3>
    <p><strong>URL de teste:</strong> <code>$test_url</code></p>";

// Teste 1: Conexão básica sem certificado
echo "<h4>🔍 Teste 1: Conexão básica (sem certificado)</h4>";

$context_basic = stream_context_create([
    'http' => [
        'timeout' => $config['timeout'],
        'user_agent' => 'Sistema-Frotas/1.0'
    ]
]);

$start_time = microtime(true);
$response_basic = @file_get_contents($test_url, false, $context_basic);
$end_time = microtime(true);
$time_basic = round(($end_time - $start_time) * 1000, 2);

if ($response_basic !== false) {
    echo "<p><strong>✅ Conexão básica bem-sucedida!</strong></p>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_basic} ms</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_basic) . " bytes</p>";
    
    // Verificar se é SOAP válido
    if (strpos($response_basic, 'soap:Envelope') !== false) {
        echo "<p><strong>✅ Resposta SOAP válida</strong></p>";
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
    }
    
} else {
    echo "<p><strong>❌ Conexão básica falhou</strong></p>";
    $error = error_get_last();
    if ($error) {
        echo "<p><strong>Erro:</strong> " . $error['message'] . "</p>";
    }
}

// Teste 2: Conexão com certificado
echo "<h4>🔐 Teste 2: Conexão com certificado</h4>";

$context_cert = stream_context_create([
    'ssl' => [
        'local_cert' => $cert_file,
        'local_pk' => $key_file,
        'passphrase' => '',
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ],
    'http' => [
        'timeout' => $config['timeout'],
        'user_agent' => 'Sistema-Frotas/1.0'
    ]
]);

$start_time = microtime(true);
$response_cert = @file_get_contents($test_url, false, $context_cert);
$end_time = microtime(true);
$time_cert = round(($end_time - $start_time) * 1000, 2);

if ($response_cert !== false) {
    echo "<p><strong>✅ Conexão com certificado bem-sucedida!</strong></p>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_cert} ms</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_cert) . " bytes</p>";
    
    // Verificar se é SOAP válido
    if (strpos($response_cert, 'soap:Envelope') !== false) {
        echo "<p><strong>✅ Resposta SOAP válida com certificado</strong></p>";
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
    }
    
    // Mostrar resposta
    echo "<p><strong>Resposta completa:</strong></p>";
    echo "<div class='response'>" . htmlspecialchars($response_cert) . "</div>";
    
} else {
    echo "<p><strong>❌ Conexão com certificado falhou</strong></p>";
    $error = error_get_last();
    if ($error) {
        echo "<p><strong>Erro:</strong> " . $error['message'] . "</p>";
    }
}

// Teste 3: Teste de status do serviço
echo "<h4>📊 Teste 3: Status do Serviço NF-e</h4>";

$soap_request = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4">
    <soap:Body>
        <nfe:nfeStatusServicoNF>
            <nfe:nfeDadosMsg>
                <consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
                    <tpAmb>2</tpAmb>
                    <xServ>STATUS</xServ>
                </consStatServ>
            </nfe:nfeDadosMsg>
        </nfe:nfeStatusServicoNF>
    </soap:Body>
</soap:Envelope>';

$context_soap = stream_context_create([
    'ssl' => [
        'local_cert' => $cert_file,
        'local_pk' => $key_file,
        'passphrase' => '',
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ],
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF',
            'Content-Length: ' . strlen($soap_request)
        ],
        'content' => $soap_request,
        'timeout' => $config['timeout']
    ]
]);

$start_time = microtime(true);
$response_soap = @file_get_contents($test_url, false, $context_soap);
$end_time = microtime(true);
$time_soap = round(($end_time - $start_time) * 1000, 2);

if ($response_soap !== false) {
    echo "<p><strong>✅ Requisição SOAP bem-sucedida!</strong></p>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_soap} ms</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_soap) . " bytes</p>";
    
    // Verificar se é SOAP válido
    if (strpos($response_soap, 'soap:Envelope') !== false) {
        echo "<p><strong>✅ Resposta SOAP válida</strong></p>";
        
        // Extrair informações básicas
        if (strpos($response_soap, 'cStat') !== false) {
            echo "<p><strong>✅ Resposta contém código de status</strong></p>";
        }
        
        if (strpos($response_soap, 'xMotivo') !== false) {
            echo "<p><strong>✅ Resposta contém motivo</strong></p>";
        }
        
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
    }
    
    // Mostrar resposta
    echo "<p><strong>Resposta SOAP:</strong></p>";
    echo "<div class='response'>" . htmlspecialchars($response_soap) . "</div>";
    
} else {
    echo "<p><strong>❌ Requisição SOAP falhou</strong></p>";
    $error = error_get_last();
    if ($error) {
        echo "<p><strong>Erro:</strong> " . $error['message'] . "</p>";
    }
}

echo "</div>";

// Resumo dos testes
echo "<div class='card success'>
    <h3>📊 Resumo dos Testes</h3>";

if (isset($response_basic) && $response_basic !== false) {
    echo "<p><strong>✅ Teste 1 (Conexão básica):</strong> SUCESSO - {$time_basic} ms</p>";
} else {
    echo "<p><strong>❌ Teste 1 (Conexão básica):</strong> FALHOU</p>";
}

if (isset($response_cert) && $response_cert !== false) {
    echo "<p><strong>✅ Teste 2 (Com certificado):</strong> SUCESSO - {$time_cert} ms</p>";
} else {
    echo "<p><strong>❌ Teste 2 (Com certificado):</strong> FALHOU</p>";
}

if (isset($response_soap) && $response_soap !== false) {
    echo "<p><strong>✅ Teste 3 (SOAP):</strong> SUCESSO - {$time_soap} ms</p>";
} else {
    echo "<p><strong>❌ Teste 3 (SOAP):</strong> FALHOU</p>";
}

echo "</div>";

// Próximos passos
echo "<div class='card info'>
    <h3>🎯 Próximos Passos</h3>
    <p><strong>1. ✅ Certificado gerado com sucesso</strong></p>
    <p><strong>2. ✅ Teste de conexão realizado</strong></p>
    <p><strong>3. 🚀 Agora você pode:</strong></p>
    <ul>
        <li>Integrar o certificado no seu sistema</li>
        <li>Testar emissão de NF-e</li>
        <li>Validar com SEFAZ</li>
        <li>Configurar ambiente de produção</li>
    </ul>
</div>";

echo "</div></body></html>";
?>
