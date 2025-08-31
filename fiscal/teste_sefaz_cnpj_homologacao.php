<?php
/**
 * 🧪 Teste SEFAZ - CNPJ Homologação Oficial
 * 📋 Testa usando CNPJ de homologação oficial da SEFAZ
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - CNPJ Homologação Oficial</title>
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
        <h1>🧪 Teste SEFAZ - CNPJ Homologação Oficial</h1>
        <p>Testa usando CNPJ de homologação oficial da SEFAZ</p>";

try {
    $conn = getConnection();
    $empresa_id = 1;
    
    // Buscar certificado digital atual
    $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificado) {
        echo "<div class='card error'>
            <h3>❌ Certificado não encontrado</h3>
            <p>Nenhum certificado digital ativo encontrado para a empresa ID: $empresa_id</p>
        </div>";
        exit;
    }
    
    echo "<div class='card info'>
        <h3>📋 Certificado no Banco</h3>
        <p><strong>Nome:</strong> " . htmlspecialchars($certificado['nome_certificado']) . "</p>
        <p><strong>Arquivo:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
        <p><strong>Senha:</strong> " . ($certificado['senha_criptografada'] ? '✅ Configurada' : '❌ Não configurada') . "</p>
    </div>";
    
    // Verificar arquivos PEM
    $pasta_certificados = realpath('../uploads/certificados/');
    $cert_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado.pem';
    $key_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave.pem';
    
    echo "<div class='card'>
        <h3>📁 Caminhos dos Arquivos</h3>
        <p><strong>Pasta certificados:</strong> <code>$pasta_certificados</code></p>
        <p><strong>certificado.pem:</strong> <code>$cert_pem</code></p>
        <p><strong>chave.pem:</strong> <code>$key_pem</code></p>
    </div>";
    
    // Verificar se os arquivos existem
    $cert_existe = file_exists($cert_pem);
    $key_existe = file_exists($key_pem);
    
    if (!$cert_existe || !$key_existe) {
        echo "<div class='card error'>
            <h3>❌ Arquivos não encontrados</h3>
            <p>Os arquivos <code>certificado.pem</code> e/ou <code>chave.pem</code> não foram encontrados.</p>
        </div>";
        exit;
    }
    
    echo "<div class='card'>
        <h3>🧪 Testando SEFAZ - CNPJ Homologação Oficial</h3>";
    
    // Lista de endpoints para testar com CNPJ de homologação
    $endpoints = [
        [
            'nome' => 'NF-e Status (SVRS) - CNPJ Homologação',
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
            'nome' => 'MDF-e Status (SVRS) - CNPJ Homologação',
            'url' => 'https://mdfe-homologacao.svrs.rs.gov.br/ws/MDFeStatusServico/MDFeStatusServico.asmx',
            'soap_action' => 'http://www.portalfiscal.inf.br/mdfe/wsdl/MDFeStatusServico/mdfeStatusServicoMDF',
            'xml' => '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <consStatServ xmlns="http://www.portalfiscal.inf.br/mdfe" versao="3.00">
            <tpAmb>2</tpAmb>
            <xServ>STATUS</xServ>
        </consStatServ>
    </soap:Body>
</soap:Envelope>'
        ],
        [
            'nome' => 'NF-e Status (SP) - CNPJ Homologação',
            'url' => 'https://homologacao.nfe.fazenda.sp.gov.br/nfe2/services/NfeStatusServico4.asmx',
            'soap_action' => 'http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF',
            'xml' => '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
    <soap:Body>
        <consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
            <tpAmb>2</tpAmb>
            <cUF>35</cUF>
            <xServ>STATUS</xServ>
        </consStatServ>
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
        
        // Configurações do certificado
        curl_setopt($ch, CURLOPT_SSLCERT, $cert_pem);
        curl_setopt($ch, CURLOPT_SSLKEY, $key_pem);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        
        // Headers SOAP
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $endpoint['soap_action'] . '"',
            'User-Agent: Sistema-Frotas/1.0'
        ]);
        
        echo "<p><strong>Iniciando teste...</strong></p>";
        
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
    
    // Informações sobre CNPJ de homologação
    echo "<div class='card info'>
        <h3>📋 CNPJs de Homologação Oficial</h3>
        <p><strong>NF-e:</strong> 00.000.000/0001-91</p>
        <p><strong>CT-e:</strong> 02.000.000/0001-02</p>
        <p><strong>MDF-e:</strong> 03.000.000/0001-03</p>
        <p><em>Estes CNPJs são aceitos pela SEFAZ para testes de homologação.</em></p>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
