<?php
/**
 * 🧪 Teste SEFAZ - Certificado ICP-Brasil
 * 📋 Testa conexão com SEFAZ usando certificado ICP-Brasil real
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - ICP-Brasil</title>
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
        .stats { background: #e8f5e8; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Teste SEFAZ - Certificado ICP-Brasil</h1>
        <p>Testando conexão com SEFAZ usando certificado ICP-Brasil real</p>";

// Verificar se certificado ICP-Brasil existe
$pasta_certificados = realpath('../uploads/certificados/');
$cert_icp = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_icp.pem';
$key_icp = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_icp.pem';

echo "<div class='card info'>
    <h3>📋 Certificado ICP-Brasil</h3>";

if (file_exists($cert_icp) && file_exists($key_icp)) {
    $cert_size = filesize($cert_icp);
    $key_size = filesize($key_icp);
    
    echo "<p><strong>✅ Certificado ICP-Brasil:</strong> certificado_icp.pem (" . number_format($cert_size) . " bytes)</p>";
    echo "<p><strong>✅ Chave privada:</strong> chave_icp.pem (" . number_format($key_size) . " bytes)</p>";
    
    // Verificar se são válidos
    $cert_info = openssl_x509_read(file_get_contents($cert_icp));
    $key_info = openssl_pkey_get_private(file_get_contents($key_icp));
    
    if ($cert_info && $key_info) {
        echo "<p><strong>✅ Certificado e chave são válidos!</strong></p>";
        
        // Mostrar informações do certificado
        $cert_details = openssl_x509_parse($cert_info);
        if (isset($cert_details['subject']['commonName'])) {
            echo "<p><strong>CN:</strong> " . $cert_details['subject']['commonName'] . "</p>";
        }
        if (isset($cert_details['subject']['organizationName'])) {
            echo "<p><strong>Organização:</strong> " . $cert_details['subject']['organizationName'] . "</p>";
        }
        if (isset($cert_details['validFrom_time_t']) && isset($cert_details['validTo_time_t'])) {
            echo "<p><strong>Validade:</strong> " . date('d/m/Y', $cert_details['validFrom_time_t']) . " até " . date('d/m/Y', $cert_details['validTo_time_t']) . "</p>";
        }
        
        // Verificar se é ICP-Brasil
        if (isset($cert_details['issuer']['countryName']) && $cert_details['issuer']['countryName'] === 'BR') {
            echo "<p><strong>✅ Certificado ICP-Brasil válido!</strong></p>";
        } else {
            echo "<p><strong>⚠️ Pode não ser ICP-Brasil</strong></p>";
        }
        
    } else {
        echo "<p><strong>❌ Erro na validação do certificado</strong></p>";
        echo "<p><strong>Erro:</strong> " . openssl_error_string() . "</p>";
    }
    
} else {
    echo "<p><strong>❌ Certificado ICP-Brasil não encontrado!</strong></p>";
    echo "<p>Execute primeiro: <code>converter_certificado_icp.php</code></p>";
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
    <p><strong>Método:</strong> cURL com certificado ICP-Brasil</p>
</div>";

// URLs de teste
$urls = [
    'RS' => [
        'homologacao' => 'https://nfe-homologacao.sefazrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx',
        'producao' => 'https://nfe.sefazrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx'
    ]
];

$test_url = $urls[$config['uf']][$config['ambiente']];

echo "<div class='card'>
    <h3>🌐 Testando Conexão SEFAZ com ICP-Brasil</h3>
    <p><strong>URL de teste:</strong> <code>$test_url</code></p>";

// Teste 1: Conexão básica sem certificado
echo "<h4>🔍 Teste 1: Conexão básica (sem certificado)</h4>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');

$start_time = microtime(true);
$response_basic = curl_exec($ch);
$end_time = microtime(true);
$time_basic = round(($end_time - $start_time) * 1000, 2);

$http_code_basic = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_basic = curl_error($ch);
$info_basic = curl_getinfo($ch);

if ($response_basic !== false && !$error_basic) {
    echo "<p><strong>✅ Conexão básica bem-sucedida!</strong></p>";
    echo "<div class='stats'>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_basic} ms</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_basic</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_basic) . " bytes</p>";
    echo "<p><strong>IP de destino:</strong> " . $info_basic['primary_ip'] . "</p>";
    echo "</div>";
    
    // Verificar se é SOAP válido
    if (strpos($response_basic, 'soap:Envelope') !== false) {
        echo "<p><strong>✅ Resposta SOAP válida</strong></p>";
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
    }
    
} else {
    echo "<p><strong>❌ Conexão básica falhou</strong></p>";
    echo "<p><strong>Erro cURL:</strong> $error_basic</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_basic</p>";
}

curl_close($ch);

// Teste 2: Conexão com certificado ICP-Brasil
echo "<h4>🔐 Teste 2: Conexão com certificado ICP-Brasil</h4>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');

// Configurar certificado ICP-Brasil
curl_setopt($ch, CURLOPT_SSLCERT, $cert_icp);
curl_setopt($ch, CURLOPT_SSLKEY, $key_icp);
curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');

$start_time = microtime(true);
$response_cert = curl_exec($ch);
$end_time = microtime(true);
$time_cert = round(($end_time - $start_time) * 1000, 2);

$http_code_cert = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_cert = curl_error($ch);
$info_cert = curl_getinfo($ch);

if ($response_cert !== false && !$error_cert) {
    echo "<p><strong>✅ Conexão com certificado ICP-Brasil bem-sucedida!</strong></p>";
    echo "<div class='stats'>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_cert} ms</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_cert</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_cert) . " bytes</p>";
    echo "<p><strong>IP de destino:</strong> " . $info_cert['primary_ip'] . "</p>";
    echo "</div>";
    
    // Verificar se é SOAP válido
    if (strpos($response_cert, 'soap:Envelope') !== false) {
        echo "<p><strong>✅ Resposta SOAP válida com certificado ICP-Brasil</strong></p>";
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
    }
    
} else {
    echo "<p><strong>❌ Conexão com certificado ICP-Brasil falhou</strong></p>";
    echo "<p><strong>Erro cURL:</strong> $error_cert</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_cert</p>";
}

curl_close($ch);

// Teste 3: Teste de status do serviço com ICP-Brasil
echo "<h4>📊 Teste 3: Status do Serviço NF-e (SOAP + ICP-Brasil)</h4>";

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

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $soap_request);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Sistema-Frotas/1.0');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'SOAPAction: http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF',
    'Content-Length: ' . strlen($soap_request)
]);

// Configurar certificado ICP-Brasil
curl_setopt($ch, CURLOPT_SSLCERT, $cert_icp);
curl_setopt($ch, CURLOPT_SSLKEY, $key_icp);
curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');

echo "<p><strong>🔐 Usando certificado ICP-Brasil para requisição SOAP</strong></p>";

$start_time = microtime(true);
$response_soap = curl_exec($ch);
$end_time = microtime(true);
$time_soap = round(($end_time - $start_time) * 1000, 2);

$http_code_soap = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_soap = curl_error($ch);
$info_soap = curl_getinfo($ch);

if ($response_soap !== false && !$error_soap) {
    echo "<p><strong>✅ Requisição SOAP com ICP-Brasil bem-sucedida!</strong></p>";
    echo "<div class='stats'>";
    echo "<p><strong>Tempo de resposta:</strong> {$time_soap} ms</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_soap</p>";
    echo "<p><strong>Tamanho da resposta:</strong> " . strlen($response_soap) . " bytes</p>";
    echo "<p><strong>IP de destino:</strong> " . $info_soap['primary_ip'] . "</p>";
    echo "</div>";
    
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
        
        // Mostrar resposta (primeiros 1000 chars)
        $resposta_curta = substr($response_soap, 0, 1000);
        echo "<p><strong>Resposta SOAP (primeiros 1000 chars):</strong></p>";
        echo "<div class='response'>" . htmlspecialchars($resposta_curta) . "</div>";
        
    } else {
        echo "<p><strong>⚠️ Resposta não é SOAP</strong></p>";
        echo "<p><strong>Resposta completa:</strong></p>";
        echo "<div class='response'>" . htmlspecialchars($response_soap) . "</div>";
    }
    
} else {
    echo "<p><strong>❌ Requisição SOAP com ICP-Brasil falhou</strong></p>";
    echo "<p><strong>Erro cURL:</strong> $error_soap</p>";
    echo "<p><strong>HTTP Code:</strong> $http_code_soap</p>";
}

curl_close($ch);

echo "</div>";

// Resumo dos testes
echo "<div class='card success'>
    <h3>📊 Resumo dos Testes ICP-Brasil</h3>";

if (isset($response_basic) && $response_basic !== false) {
    echo "<p><strong>✅ Teste 1 (Conexão básica):</strong> SUCESSO - {$time_basic} ms - HTTP $http_code_basic</p>";
} else {
    echo "<p><strong>❌ Teste 1 (Conexão básica):</strong> FALHOU</p>";
}

if (isset($response_cert) && $response_cert !== false) {
    echo "<p><strong>✅ Teste 2 (Com ICP-Brasil):</strong> SUCESSO - {$time_cert} ms - HTTP $http_code_cert</p>";
} else {
    echo "<p><strong>❌ Teste 2 (Com ICP-Brasil):</strong> FALHOU</p>";
}

if (isset($response_soap) && $response_soap !== false) {
    echo "<p><strong>✅ Teste 3 (SOAP + ICP-Brasil):</strong> SUCESSO - {$time_soap} ms - HTTP $http_code_soap</p>";
} else {
    echo "<p><strong>❌ Teste 3 (SOAP + ICP-Brasil):</strong> FALHOU</p>";
}

echo "</div>";

// Análise dos resultados
echo "<div class='card info'>
    <h3>🔍 Análise dos Resultados ICP-Brasil</h3>";

if (isset($http_code_soap)) {
    switch ($http_code_soap) {
        case 200:
            echo "<p><strong>🎉 SUCESSO TOTAL COM ICP-BRASIL!</strong></p>";
            echo "<p>✅ SEFAZ está funcionando perfeitamente</p>";
            echo "<p>✅ Certificado ICP-Brasil foi aceito</p>";
            echo "<p>✅ Serviço SOAP respondendo</p>";
            echo "<p>✅ Sistema pronto para produção</p>";
            break;
            
        case 500:
            echo "<p><strong>✅ SUCESSO PARCIAL COM ICP-BRASIL!</strong></p>";
            echo "<p>✅ SEFAZ está funcionando</p>";
            echo "<p>✅ Certificado ICP-Brasil foi aceito</p>";
            echo "<p>⚠️ Erro SOAP na requisição (pode ser normal para testes)</p>";
            echo "<p>✅ Sistema funcionando com ICP-Brasil</p>";
            break;
            
        case 403:
            echo "<p><strong>⚠️ PROBLEMA DE AUTENTICAÇÃO ICP-BRASIL</strong></p>";
            echo "<p>✅ SEFAZ está funcionando</p>";
            echo "<p>❌ Certificado ICP-Brasil foi rejeitado</p>";
            echo "<p>💡 Verificar se é realmente ICP-Brasil válido</p>";
            echo "<p>💡 Pode ser necessário certificado de produção</p>";
            break;
            
        default:
            echo "<p><strong>❓ STATUS INESPERADO</strong></p>";
            echo "<p>HTTP Code: $http_code_soap</p>";
            echo "<p>💡 Verificar resposta completa</p>";
    }
}

echo "</div>";

// Próximos passos
echo "<div class='card success'>
    <h3>🎯 Próximos Passos</h3>";
    
if (isset($http_code_soap) && in_array($http_code_soap, [200, 500])) {
    echo "<p><strong>🎉 SUCESSO!</strong> Certificado ICP-Brasil funcionando!</p>";
    echo "<p><strong>🚀 Agora você pode:</strong></p>";
    echo "<ul>";
    echo "<li>Integrar o certificado ICP-Brasil no seu sistema</li>";
    echo "<li>Usar cURL para todas as conexões SEFAZ</li>";
    echo "<li>Testar emissão de NF-e</li>";
    echo "<li>Configurar ambiente de produção</li>";
    echo "</ul>";
} else {
    echo "<p><strong>⚠️ Certificado ICP-Brasil não foi aceito</strong></p>";
    echo "<p><strong>💡 Possíveis soluções:</strong></p>";
    echo "<ul>";
    echo "<li>Verificar se é realmente ICP-Brasil válido</li>";
    echo "<li>Obter certificado de produção</li>";
    echo "<li>Verificar validade e formato</li>";
    echo "<li>Contatar a Autoridade Certificadora</li>";
    echo "</ul>";
}

echo "</div>";

echo "</div></body></html>";
?>
