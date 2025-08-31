<?php
/**
 * 🔐 Teste SEFAZ Final - Conversão + Teste
 * 📋 Converte certificado e testa SEFAZ em uma única execução
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Teste SEFAZ Final</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .step { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔐 Teste SEFAZ Final - Conversão + Teste</h1>
        <p>Converte certificado PFX para PEM e testa SEFAZ automaticamente</p>";

try {
    $conn = getConnection();
    $empresa_id = 1;
    
    // Buscar certificado digital
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
    
    $caminho_certificado = '../uploads/certificados/' . $certificado['arquivo_certificado'];
    $senha = $certificado['senha_criptografada'] ?? '';
    
    echo "<div class='card info'>
        <h3>📋 Informações do Certificado</h3>
        <p><strong>Nome:</strong> " . htmlspecialchars($certificado['nome_certificado']) . "</p>
        <p><strong>Arquivo:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
        <p><strong>Senha:</strong> " . (empty($senha) ? '❌ Não informada' : '✅ Configurada') . "</p>
    </div>";
    
    if (empty($senha)) {
        echo "<div class='card error'>
            <h3>❌ Senha não informada</h3>
            <p>Para converter o certificado, você precisa informar a senha.</p>
            <p><a href='../pages/configuracoes.php' class='btn'>Configurar senha</a></p>
        </div>";
        exit;
    }
    
    // Passo 1: Tentar converter PFX para PEM
    echo "<div class='card'>
        <h3>🔄 Passo 1: Convertendo Certificado PFX → PEM</h3>";
    
    $extensao = pathinfo($caminho_certificado, PATHINFO_EXTENSION);
    if ($extensao === 'pfx' || $extensao === 'p12') {
        echo "<div class='step'>
            <h4>📁 Tentando conversão automática...</h4>";
        
        // Tentar converter usando OpenSSL PHP
        if (function_exists('openssl_pkcs12_read')) {
            $pfx_content = file_get_contents($caminho_certificado);
            if ($pfx_content) {
                $certs = [];
                $result = openssl_pkcs12_read($pfx_content, $certs, $senha);
                
                if ($result) {
                    // Salvar arquivos PEM
                    $cert_pem_path = '../uploads/certificados/' . pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '.pem';
                    $key_pem_path = '../uploads/certificados/' . pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '_key.pem';
                    
                    $cert_saved = file_put_contents($cert_pem_path, $certs['cert']);
                    $key_saved = file_put_contents($key_pem_path, $certs['pkey']);
                    
                    if ($cert_saved && $key_saved) {
                        echo "<p><strong>✅ Conversão automática realizada!</strong></p>";
                        echo "<p><strong>Certificado PEM:</strong> <code>$cert_pem_path</code></p>";
                        echo "<p><strong>Chave privada PEM:</strong> <code>$key_pem_path</code></p>";
                        
                        // Atualizar banco
                        $stmt = $conn->prepare("UPDATE fiscal_certificados_digitais SET arquivo_certificado = ? WHERE id = ?");
                        $stmt->execute([pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME) . '.pem', $certificado['id']]);
                        
                        $caminho_certificado = $cert_pem_path;
                        $caminho_chave = $key_pem_path;
                        $conversao_sucesso = true;
                    } else {
                        echo "<p><strong>❌ Erro ao salvar arquivos PEM</strong></p>";
                        $conversao_sucesso = false;
                    }
                } else {
                    echo "<p><strong>❌ Erro na conversão OpenSSL:</strong> " . openssl_error_string() . "</p>";
                    echo "<p><strong>💡 Solução manual:</strong></p>";
                    echo "<div class='code'>
                        # No PowerShell/CMD, navegue para a pasta:<br>
                        cd C:\\xampp\\htdocs\\sistema-frotas\\uploads\\certificados<br><br>
                        # Converta o certificado:<br>
                        openssl pkcs12 -in " . htmlspecialchars($certificado['arquivo_certificado']) . " -out certificado.pem -clcerts -nokeys<br>
                        openssl pkcs12 -in " . htmlspecialchars($certificado['arquivo_certificado']) . " -out chave.pem -nocerts -nodes<br><br>
                        # Digite a senha quando solicitado
                    </div>";
                    $conversao_sucesso = false;
                }
            } else {
                echo "<p><strong>❌ Erro ao ler arquivo PFX</strong></p>";
                $conversao_sucesso = false;
            }
        } else {
            echo "<p><strong>❌ OpenSSL PHP não disponível</strong></p>";
            echo "<p><strong>💡 Use a conversão manual acima</strong></p>";
            $conversao_sucesso = false;
        }
    } else {
        echo "<p><strong>✅ Certificado já está em formato compatível: $extensao</strong></p>";
        $conversao_sucesso = true;
        $caminho_chave = str_replace('.pem', '_key.pem', $caminho_certificado);
    }
    
    echo "</div>";
    
    // Passo 2: Testar SEFAZ
    echo "<div class='card'>
        <h3>🧪 Passo 2: Testando SEFAZ com Certificado</h3>";
    
    if ($conversao_sucesso && file_exists($caminho_certificado)) {
        echo "<div class='step'>
            <h4>🌐 Testando NF-e Status (Homologação SVRS)</h4>";
        
        // URL correta para status de serviço
        $url = "https://nfe-homologacao.svrs.rs.gov.br/ws/NfeStatusServico/NfeStatusServico4.asmx";
        
        // XML SOAP correto para consStatServ
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"
               xmlns:nfe="http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4">
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
        
        echo "<div class='code'><strong>XML SOAP Enviado:</strong><br>" . htmlspecialchars($xml) . "</div>";
        echo "<div class='code'><strong>URL:</strong> $url</div>";
        
        $inicio = microtime(true);
        
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            // Configurar certificado
            curl_setopt($ch, CURLOPT_SSLCERT, $caminho_certificado);
            if (file_exists($caminho_chave)) {
                curl_setopt($ch, CURLOPT_SSLKEY, $caminho_chave);
            }
            
            // Headers SOAP corretos
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: "http://www.portalfiscal.inf.br/nfe/wsdl/NfeStatusServico4/nfeStatusServicoNF"',
                'User-Agent: Sistema-Frotas/1.0'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $ssl_error = curl_errno($ch);
            $tempo = round((microtime(true) - $inicio) * 1000, 2);
            
            curl_close($ch);
            
            echo "<p><strong>Tempo:</strong> {$tempo}ms</p>";
            echo "<p><strong>HTTP Code:</strong> $http_code</p>";
            
            if ($error) {
                echo "<p class='status offline'>❌ Erro de Conexão</p>";
                echo "<p><strong>Erro:</strong> $error</p>";
                
                if ($ssl_error === CURLE_SSL_CONNECT_ERROR || $ssl_error === CURLE_SSL_CERTPROBLEM) {
                    echo "<p><strong>Diagnóstico:</strong> ⚠️ Problema com certificado SSL/TLS</p>";
                    echo "<p><strong>Sugestão:</strong> Verifique se os arquivos PEM foram criados corretamente</p>";
                }
            } else {
                if ($http_code == 200) {
                    echo "<p class='status online'>✅ Serviço Online (HTTP 200)</p>";
                    echo "<p><strong>Status:</strong> <span style='color: #28a745;'>✅ SEFAZ respondendo!</span></p>";
                    
                    // Mostrar resposta
                    $resposta_curta = substr($response, 0, 1000);
                    echo "<div class='code'><strong>Resposta SEFAZ:</strong><br>" . htmlspecialchars($resposta_curta) . "</div>";
                    
                } elseif ($http_code == 500) {
                    echo "<p class='status warning'>⚠️ Serviço Online (HTTP 500)</p>";
                    echo "<p><strong>Status:</strong> <span style='color: #28a745;'>✅ SEFAZ online (erro SOAP é normal)</span></p>";
                    
                    if (strpos($response, 'soap:Fault') !== false) {
                        echo "<p><strong>Diagnóstico:</strong> ✅ Erro SOAP válido (serviço funcionando)</p>";
                    }
                    
                    $resposta_curta = substr($response, 0, 500);
                    echo "<div class='code'><strong>Resposta (primeiros 500 chars):</strong><br>" . htmlspecialchars($resposta_curta) . "</div>";
                    
                } elseif ($http_code == 403) {
                    echo "<p class='status warning'>⚠️ Serviço Online (HTTP 403)</p>";
                    echo "<p><strong>Diagnóstico:</strong> ⚠️ Serviço rejeitou (pode precisar de certificado válido)</p>";
                    
                } else {
                    echo "<p class='status offline'>❌ Status Inesperado (HTTP $http_code)</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p class='status offline'>❌ Exceção</p>";
            echo "<p><strong>Erro:</strong> " . $e->getMessage() . "</p>";
        }
        
    } else {
        echo "<p><strong>❌ Certificado não disponível para teste</strong></p>";
        echo "<p>Complete a conversão primeiro.</p>";
    }
    
    echo "</div>";
    
    // Resumo final
    echo "<div class='card info'>
        <h3>📋 Resumo da Execução</h3>
        <ul>
            <li><strong>Conversão:</strong> " . ($conversao_sucesso ? '✅ Sucesso' : '❌ Falhou') . "</li>
            <li><strong>Certificado PEM:</strong> " . (file_exists($caminho_certificado) ? '✅ Disponível' : '❌ Não encontrado') . "</li>
            <li><strong>Chave privada:</strong> " . (isset($caminho_chave) && file_exists($caminho_chave) ? '✅ Disponível' : '❌ Não encontrada') . "</li>
        </ul>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
