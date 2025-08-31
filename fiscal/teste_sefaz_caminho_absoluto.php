<?php
/**
 * 🧪 Teste SEFAZ com Caminho Absoluto
 * 📋 Testa usando caminhos absolutos para os certificados
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ - Caminho Absoluto</title>
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
        <h1>🧪 Teste SEFAZ - Caminho Absoluto</h1>
        <p>Testa usando caminhos absolutos para os certificados</p>";

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
    $pem_combinado = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_completo.pem';
    
    echo "<div class='card'>
        <h3>📁 Caminhos dos Arquivos</h3>
        <p><strong>Pasta certificados:</strong> <code>$pasta_certificados</code></p>
        <p><strong>certificado.pem:</strong> <code>$cert_pem</code></p>
        <p><strong>chave.pem:</strong> <code>$key_pem</code></p>
        <p><strong>certificado_completo.pem:</strong> <code>$pem_combinado</code></p>
    </div>";
    
    // Verificar se os arquivos existem
    echo "<div class='card'>
        <h3>🔍 Verificação de Arquivos</h3>";
    
    $cert_existe = file_exists($cert_pem);
    $key_existe = file_exists($key_pem);
    $completo_existe = file_exists($pem_combinado);
    
    echo "<p><strong>certificado.pem:</strong> " . ($cert_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
    echo "<p><strong>chave.pem:</strong> " . ($key_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
    echo "<p><strong>certificado_completo.pem:</strong> " . ($completo_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
    
    // Verificar permissões
    if ($completo_existe) {
        $permissoes = fileperms($pem_combinado);
        $permissoes_oct = substr(sprintf('%o', $permissoes), -4);
        $legivel = is_readable($pem_combinado);
        
        echo "<p><strong>Permissões:</strong> <code>$permissoes_oct</code></p>";
        echo "<p><strong>Legível pelo PHP:</strong> " . ($legivel ? '✅ Sim' : '❌ Não') . "</p>";
    }
    
    echo "</div>";
    
    if ($completo_existe) {
        echo "<div class='card'>
            <h3>🧪 Testando SEFAZ com Caminho Absoluto</h3>";
        
        // Testar SEFAZ usando caminho absoluto
        $url = "https://nfe-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx";
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4">
    <soap:Header/>
    <soap:Body>
        <nfe:nfeDadosMsg>
            <consStatServ xmlns="http://www.portalfiscal.inf.br/nfe" versao="4.00">
                <tpAmb>2</tpAmb>
                <cUF>43</cUF>
                <xServ>STATUS</xServ>
            </consStatServ>
        </nfe:nfeDadosMsg>
    </soap:Body>
</soap:Envelope>';
        
        echo "<p><strong>URL:</strong> <code>$url</code></p>";
        echo "<p><strong>Certificado:</strong> <code>$pem_combinado</code></p>";
        
        // Iniciar cURL
        $ch = curl_init();
        
        // Configurações básicas
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        // Configurações do certificado
        curl_setopt($ch, CURLOPT_SSLCERT, $pem_combinado);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        
        // Headers SOAP
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF"',
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
            $info = curl_getinfo($ch);
            
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
                
            } else {
                echo "<p><strong>⚠️ SEFAZ respondeu com HTTP $http_code</strong></p>";
                echo "<p>Resposta:</p>";
                echo "<div class='code'>" . htmlspecialchars($resposta) . "</div>";
            }
        }
        
        curl_close($ch);
        
        echo "</div>";
        
    } else {
        echo "<div class='card error'>
            <h3>❌ Arquivo não encontrado</h3>
            <p>O arquivo <code>certificado_completo.pem</code> não foi encontrado no caminho especificado.</p>
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
