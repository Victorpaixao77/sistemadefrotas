<?php
/**
 * 🧪 Teste SEFAZ - Requisição Real
 * 📋 Testa simulando uma requisição real de NF-e
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - Requisição Real</title>
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
        <h1>🧪 Teste SEFAZ - Requisição Real</h1>
        <p>Testa simulando uma requisição real de NF-e</p>";

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
        <h3>🧪 Testando SEFAZ - Requisição Real</h3>";
    
    // Lista de endpoints para testar com requisições reais
    $endpoints = [
        [
            'nome' => 'NF-e Autorização (SVRS) - Requisição Real',
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
        ],
        [
            'nome' => 'NF-e Status (SVRS) - Sem SOAP Action',
            'url' => 'https://nfe-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx',
            'soap_action' => '',
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
        ]
    ];
    
    foreach ($endpoints as $endpoint) {
        echo "<div class='card'>
            <h3>🌐 " . htmlspecialchars($endpoint['nome']) . "</h3>
            <p><strong>URL:</strong> <code>" . htmlspecialchars($endpoint['url']) . "</code></p>";
        
        if (!empty($endpoint['soap_action'])) {
            echo "<p><strong>SOAP Action:</strong> <code>" . htmlspecialchars($endpoint['soap_action']) . "</code></p>";
        } else {
            echo "<p><strong>SOAP Action:</strong> <em>Nenhum</em></p>";
        }
        
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
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'User-Agent: Sistema-Frotas/1.0'
        ];
        
        if (!empty($endpoint['soap_action'])) {
            $headers[] = 'SOAPAction: "' . $endpoint['soap_action'] . '"';
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
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
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
