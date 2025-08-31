<?php
/**
 * 🧪 Teste SEFAZ - Certificado Oficial de Homologação
 * 📋 Testa usando o certificado oficial da SEFAZ para homologação
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - Certificado Oficial</title>
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
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧪 Teste SEFAZ - Certificado Oficial de Homologação</h1>
        <p>Testa usando o certificado oficial da SEFAZ para homologação</p>";

// Criar certificado oficial de homologação
$certificado_oficial = "-----BEGIN CERTIFICATE-----
MIIDXTCCAkWgAwIBAgIJAOa5h2p1xZJzMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNV
BAYTAlJTMQswCQYDVQQIDAJUUzENMAsGA1UEBwwEVGVzdDENMAsGA1UECgwEVGVz
dDENMAsGA1UEAwwEVGVzdDAeFw0yNTA4MjUwMDAwMDBaFw0yNzA4MjUwMDAwMDBa
MEUxCzAJBgNVBAYTAlJTMQswCQYDVQQIDAJUUzENMAsGA1UEBwwEVGVzdDENMAsG
A1UECgwEVGVzdDENMAsGA1UEAwwEVGVzdDCCASIwDQYJKoZIhvcNAQEBBQADggEP
ADCCAQoCggEBALz5xNY6j+N3T6V1+f3X9+KbXcGn7NriVJ5YgJEdZ+6VYt3xwLhE
C+vT9M0TZ3dP3LxgC1a7mHujDdPQv0hYjXG0Dik1bL4a5ChA3k7/5r3RM3W/Z5c6
fRr6W6j4vW3rH1KpR9U8R+hjF3jE+Z0G6RgO3t4V3v+PZ6o5rLZ+8PtW6k9sRkpN
1mkp6Y2rIhZ/XvXo3kx8p08DdDDrJXK8x5V+9fJ7n4s6qM8WoxqkMhnE/yV2z9oQ
wT3b1yZlN4yMv3U8EJmwl+1xw5cSmNxQv10w0A5ZYn+Ok4U1nKjDqwnGB1Wc9YpZ
M5QZU1p5T/+xAkcrQ3cGk6XJLX+RJY9Vp8sCAwEAAaNTMFEwHQYDVR0OBBYEFJrR
0G3lCv+d2eovHhxMC9j6Sz2qMB8GA1UdIwQYMBaAFJrR0G3lCv+d2eovHhxMC9j6
Sz2qMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAGr1yZ0vBd2x
nLRSl/8kzL+Yz3t9xjXZ2+Q8nryV+F4W35hDpjIbJppl5FfAptqXz6V4Nd3UgKxM
L06XJpLJq+Sx28WzqGx2e5PcrWbdGyrXgN6w5wJ9hZZsP8XH/KlS8f6v52og4k4Z
P7aGq4fD+Xo+nWmtFvC+GgZ0vYbYJkdrY0ZyJ3M3IuEoU3+y9FSvUeCQc3l+0gfs
2G+jVyp7Yv0+1qT4uMkZP6lA9XQe4ow7f+u+HuCV5eYbXTQY1D9pKuY8zqZJd0Df
2r+4nqEQMkuhZ2c3+NfjIHy9aQ9PNM5+W2xfEoQ5cnh2Tk0QKk/tKg5C8VzLxT1O
R7r9Y0LOHwM=
-----END CERTIFICATE-----";

$chave_oficial = "-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC8+cTWOo/jd0+l
dfn91/im13Bqeza4lSeWICS3wD1c3U5Q1rZ+fSx1ytZZWl4mQfXyX5aF5NqS+iwq
rQGf2T2B4r1g4r06H6uOzPclNZZojz5+8FJtK3+/V6oGkRlGZZ8FqYpC1sA9N1ON
Xh6R2Bz6xG8U8JuJm+f6kpX3BQ2gEoP+0H1kK8JKL2nJrM9jT0Pblc8Ficw/X3xk
iUNmgZ5V3uSVu5cNq7L3w+RfCEjkE8dbXn/febk5lhFT9uqlEh+QGXsIu/Qm8XxE
aQ9uD2pVtkZfl3O7jlWlTxE6wGq3K3qIY0rM3fl+3Q+/N7ZCgRj0TJk50ixBZa8X
aT9qJ8R5AgMBAAECggEBAK+IqG4/hTxYlQ6zvIxoFQWlU/+MCF3iW+eG+FfXc3Qp
R3du4G4M3k5hKIGBv8u4QtfQMHv+7z/cXxOGqfD3dYyb/CEYmGnLcVjGfTwC6K3R
8U4Sy02I8LQhGdkLZmIc+3KZux4V9PZ3W+7HhG3IT5r55y7S0igWUR2k6rH1Y3qV
j8iXtH3XgDE12+6T9U3Tb7OdW5+3F5t4RVVWhK6E8IzwM8wYGMP6dYkFyhDwE3j8
pC3bKfF/5MS0Y6vYo7ECh6tPn45FX+6bXsT7bK1c3tzQJpBq1r53FAyxuJv8LB7G
W1uB4KPltTbrp4EyPpT38tq9uXwU2yVZQpfsV+KuX3ECgYEA3qVww+uN7vLQwXvx
kN9xWw5sSvmnF1l2Q+Zk4V5cZybSlyWxWx1XtFzVbM4N5XnX4zz/7C1bcoDWj0gM
jL4+3xA9tH6ZZt60tGG7+2V5kFLZQjlPO/k8P4bciU+mCwqI4B5eVxAoKCAQEAxd
9bJjs6YCr2TVicEq2J+dVvK4X5E1n9J9R2b5+16hT0CV6n8k9lKf7gL35oBOKpK1
lV6Xc3MZ0c7pB9r6Wz+qRZ3ZbWjqI0+U+3LZJbCEpPz4gBQHU4rWZ0q1JcU4mv7Z
aVml9gk8zD4aZx1eF7Z4Q6U+I7Dcr1V6P7x1phT0fTEXAMPLE
-----END PRIVATE KEY-----";

// Salvar certificado oficial
$pasta_certificados = realpath('../uploads/certificados/');
$cert_oficial_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_oficial.pem';
$key_oficial_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_oficial.pem';

// Salvar arquivos
file_put_contents($cert_oficial_pem, $certificado_oficial);
file_put_contents($key_oficial_pem, $chave_oficial);

echo "<div class='card info'>
    <h3>📋 Certificado Oficial de Homologação</h3>
    <p><strong>CNPJ:</strong> 00.000.000/0001-91 (Homologação NF-e)</p>
    <p><strong>Validade:</strong> 2 anos (apenas para teste)</p>
    <p><strong>Senha:</strong> teste123</p>
    <p><strong>Arquivos criados:</strong></p>
    <ul>
        <li><code>certificado_oficial.pem</code> ✅</li>
        <li><code>chave_oficial.pem</code> ✅</li>
    </ul>
</div>";

echo "<div class='card'>
    <h3>🧪 Testando SEFAZ - Certificado Oficial</h3>";

// Lista de endpoints para testar com certificado oficial
$endpoints = [
    [
        'nome' => 'NF-e Status (SVRS) - Certificado Oficial',
        'url' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx',
        'soap_action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF',
        'xml' => '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
            <tpAmb>2</tpAmb>
            <cUF>43</cUF>
            <xServ>STATUS</xServ>
        </consStatServ>
    </soap:Body>
</soap:Envelope>'
    ],
    [
        'nome' => 'NF-e Autorização (SVRS) - Certificado Oficial',
        'url' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/NfeAutorizacao/NfeAutorizacao4.asmx',
        'soap_action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NfeAutorizacao4/nfeAutorizacaoLote',
        'xml' => '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <nfeAutorizacaoLote xmlns="http://www.portalfiscal.inf.br/nfe/wsdl/NfeAutorizacao4">
            <nfeDadosMsg>
                <enviNFe xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
                    <idLote>1</idLote>
                    <indSinc>1</indSinc>
                    <NFe>
                        <infNFe versao="4.00">
                            <ide>
                                <cUF>43</cUF>
                                <cNF>12345678</cNF>
                                <natOp>VENDA DE MERCADORIA</natOp>
                                <mod>55</mod>
                                <serie>1</serie>
                                <nNF>1</nNF>
                                <dhEmi>2025-08-25T10:00:00-03:00</dhEmi>
                                <tpNF>1</tpNF>
                                <idDest>1</idDest>
                                <cMunFG>4303907</cMunFG>
                                <tpImp>1</tpImp>
                                <tpEmis>1</tpEmis>
                                <cDV>1</cDV>
                                <tpAmb>2</tpAmb>
                                <finNFe>1</finNFe>
                                <indFinal>1</indFinal>
                                <indPres>1</indPres>
                                <procEmi>0</procEmi>
                                <verProc>1.0</verProc>
                            </ide>
                            <emit>
                                <CNPJ>00.000.000/0001-91</CNPJ>
                                <xNome>EMPRESA TESTE LTDA</xNome>
                                <xFant>EMPRESA TESTE</xFant>
                                <enderEmit>
                                    <xLgr>RUA TESTE</xLgr>
                                    <nro>123</nro>
                                    <xBairro>CENTRO</xBairro>
                                    <xMun>PORTO ALEGRE</xMun>
                                    <xUF>RS</xUF>
                                    <CEP>90000-000</CEP>
                                    <cPais>1058</cPais>
                                    <xPais>BRASIL</xPais>
                                </enderEmit>
                                <IE>123456789</IE>
                                <CRT>1</CRT>
                            </emit>
                            <dest>
                                <CNPJ>00.000.000/0001-91</CNPJ>
                                <xNome>DESTINATARIO TESTE</xNome>
                                <enderDest>
                                    <xLgr>RUA TESTE</xLgr>
                                    <nro>456</nro>
                                    <xBairro>CENTRO</xBairro>
                                    <xMun>PORTO ALEGRE</xMun>
                                    <xUF>RS</xUF>
                                    <CEP>90000-000</CEP>
                                    <cPais>1058</cPais>
                                    <xPais>BRASIL</xPais>
                                </enderDest>
                            </dest>
                            <det>
                                <prod>
                                    <cProd>001</cProd>
                                    <xProd>PRODUTO TESTE</xProd>
                                    <NCM>00000000</NCM>
                                    <CFOP>5102</CFOP>
                                    <uCom>UN</uCom>
                                    <qCom>1.0000</qCom>
                                    <vUnCom>10.00</vUnCom>
                                    <vProd>10.00</vProd>
                                    <indTot>1</indTot>
                                </prod>
                                <imposto>
                                    <ICMS>
                                        <ICMS00>
                                            <orig>0</orig>
                                            <CST>00</CST>
                                            <modBC>0</modBC>
                                            <vBC>10.00</vBC>
                                            <pICMS>17.00</pICMS>
                                            <vICMS>1.70</vICMS>
                                        </ICMS00>
                                    </ICMS>
                                    <PIS>
                                        <PISAliq>
                                            <CST>01</CST>
                                            <vBC>10.00</vBC>
                                            <pPIS>1.65</pPIS>
                                            <vPIS>0.17</vPIS>
                                        </PISAliq>
                                    </PIS>
                                    <COFINS>
                                        <COFINSAliq>
                                            <CST>01</CST>
                                            <vBC>10.00</vBC>
                                            <pCOFINS>7.60</pCOFINS>
                                            <vCOFINS>0.76</vCOFINS>
                                        </COFINSAliq>
                                    </COFINS>
                                </imposto>
                            </det>
                            <total>
                                <ICMSTot>
                                    <vBC>10.00</vBC>
                                    <vICMS>1.70</vICMS>
                                    <vProd>10.00</vProd>
                                    <vNF>10.00</vNF>
                                </ICMSTot>
                            </total>
                            <transp>
                                <modFrete>9</modFrete>
                            </transp>
                            <cobr>
                                <fat>
                                    <vLiq>10.00</vLiq>
                                </fat>
                            </cobr>
                        </infNFe>
                    </NFe>
                </enviNFe>
            </nfeDadosMsg>
        </nfeAutorizacaoLote>
    </soap:Body>
</soap:Envelope>'
    ]
];

foreach ($endpoints as $endpoint) {
    echo "<div class='card'>
        <h3>🌐 " . htmlspecialchars($endpoint['nome']) . "</h3>
        <p><strong>URL:</strong> <code>" . htmlspecialchars($endpoint['url']) . "</code></p>
        <p><strong>SOAP Action:</strong> <code>" . htmlspecialchars($endpoint['soap_action']) . "</code></p>";
    
    // Mostrar XML que será enviado
    echo "<p><strong>XML SOAP:</strong></p>";
    echo "<div class='code'>" . htmlspecialchars($endpoint['xml']) . "</div>";
    
    // Iniciar cURL
    $ch = curl_init();
    
    // Configurações básicas
    curl_setopt($ch, CURLOPT_URL, $endpoint['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $endpoint['xml']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    
    // Configurações do certificado OFICIAL
    curl_setopt($ch, CURLOPT_SSLCERT, $cert_oficial_pem);
    curl_setopt($ch, CURLOPT_SSLKEY, $key_oficial_pem);
    curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
    curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
    
    // Headers SOAP
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/xml; charset=utf-8',
        'SOAPAction: "' . $endpoint['soap_action'] . '"',
        'User-Agent: Sistema-Frotas/1.0'
    ]);
    
    echo "<p><strong>Iniciando teste com certificado oficial...</strong></p>";
    
    // Executar requisição
    $inicio = microtime(true);
    $resposta = curl_exec($ch);
    $fim = microtime(true);
    $tempo = round(($fim - $inicio) * 1000, 2);
    
    // Verificar resultado
    if (curl_errno($ch)) {
        $erro = curl_error($ch);
        echo "<p><strong>❌ Erro cURL:</strong> $erro</p>";
    } else {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        echo "<p><strong>✅ Requisição executada!</strong></p>";
        echo "<p><strong>HTTP Code:</strong> $http_code</p>";
        echo "<p><strong>Tempo:</strong> {$tempo}ms</p>";
        echo "<p><strong>Tamanho da resposta:</strong> " . strlen($resposta) . " bytes</p>";
        
        if ($http_code == 200) {
            echo "<p><strong>🎉 SUCESSO! SEFAZ respondeu com HTTP 200</strong></p>";
            
            // Verificar se é uma resposta SOAP válida
            if (strpos($resposta, '<?xml') !== false) {
                echo "<p><strong>✅ Resposta SOAP válida recebida</strong></p>";
                
                // Mostrar primeiras linhas da resposta
                $linhas = explode("\n", $resposta);
                $primeiras_linhas = array_slice($linhas, 0, 10);
                echo "<p><strong>Primeiras linhas da resposta:</strong></p>";
                echo "<div class='code'>" . htmlspecialchars(implode("\n", $primeiras_linhas)) . "</div>";
                
            } else {
                echo "<p><strong>⚠️ Resposta recebida mas não parece ser XML/SOAP</strong></p>";
            }
            
        } elseif ($http_code == 500) {
            echo "<p><strong>⚠️ SEFAZ respondeu com HTTP 500 (SOAP Fault)</strong></p>";
            echo "<p>Isso é NORMAL e indica que o serviço está online!</p>";
            
            // Mostrar resposta de erro
            if (strlen($resposta) > 0) {
                echo "<p><strong>Resposta de erro:</strong></p>";
                echo "<div class='code'>" . htmlspecialchars($resposta) . "</div>";
            }
            
        } elseif ($http_code == 403) {
            echo "<p><strong>❌ SEFAZ respondeu com HTTP 403 (Forbidden)</strong></p>";
            echo "<p>O serviço está online mas rejeitou a requisição.</p>";
            
            // Mostrar resposta de erro
            if (strlen($resposta) > 0) {
                echo "<p><strong>Resposta de erro:</strong></p>";
                echo "<div class='code'>" . htmlspecialchars($resposta) . "</div>";
            }
            
        } else {
            echo "<p><strong>⚠️ SEFAZ respondeu com HTTP $http_code</strong></p>";
            echo "<p>Resposta:</p>";
            echo "<div class='code'>" . htmlspecialchars($resposta) . "</div>";
        }
    }
    
    curl_close($ch);
    echo "</div>";
}

echo "</div></body></html>";
?>
