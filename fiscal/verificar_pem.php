<?php
/**
 * 🔍 Verificar Arquivos PEM
 * 📋 Analisa o conteúdo e formato dos certificados
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificar Arquivos PEM</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1200px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 11px; overflow-x: auto; max-height: 300px; overflow-y: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificar Arquivos PEM</h1>
        <p>Analisa o conteúdo e formato dos certificados</p>";

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
        <p><strong>Tipo:</strong> " . htmlspecialchars($certificado['tipo_certificado']) . "</p>
    </div>";
    
    // Verificar arquivos PEM
    $pasta_certificados = '../uploads/certificados/';
    $cert_pem = $pasta_certificados . 'certificado.pem';
    $key_pem = $pasta_certificados . 'chave.pem';
    
    // 1. Verificar certificado.pem
    echo "<div class='card'>
        <h3>🔐 Arquivo: certificado.pem</h3>";
    
    if (file_exists($cert_pem)) {
        $conteudo_cert = file_get_contents($cert_pem);
        $tamanho_cert = filesize($cert_pem);
        $modificacao_cert = date('d/m/Y H:i:s', filemtime($cert_pem));
        
        echo "<p><strong>Status:</strong> ✅ Existe</p>";
        echo "<p><strong>Tamanho:</strong> " . number_format($tamanho_cert / 1024, 2) . " KB</p>";
        echo "<p><strong>Modificação:</strong> $modificacao_cert</p>";
        
        // Verificar formato
        if (strpos($conteudo_cert, '-----BEGIN CERTIFICATE-----') !== false) {
            echo "<p><strong>Formato:</strong> ✅ Certificado X.509 válido</p>";
        } else {
            echo "<p><strong>Formato:</strong> ❌ Não é um certificado X.509 válido</p>";
        }
        
        // Mostrar primeiras linhas
        $linhas = explode("\n", $conteudo_cert);
        $primeiras_linhas = array_slice($linhas, 0, 10);
        echo "<p><strong>Primeiras linhas:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars(implode("\n", $primeiras_linhas)) . "</div>";
        
    } else {
        echo "<p><strong>Status:</strong> ❌ Não existe</p>";
    }
    
    echo "</div>";
    
    // 2. Verificar chave.pem
    echo "<div class='card'>
        <h3>🔑 Arquivo: chave.pem</h3>";
    
    if (file_exists($key_pem)) {
        $conteudo_key = file_get_contents($key_pem);
        $tamanho_key = filesize($key_pem);
        $modificacao_key = date('d/m/Y H:i:s', filemtime($key_pem));
        
        echo "<p><strong>Status:</strong> ✅ Existe</p>";
        echo "<p><strong>Tamanho:</strong> " . number_format($tamanho_key / 1024, 2) . " KB</p>";
        echo "<p><strong>Modificação:</strong> $modificacao_key</p>";
        
        // Verificar formato
        if (strpos($conteudo_key, '-----BEGIN PRIVATE KEY-----') !== false) {
            echo "<p><strong>Formato:</strong> ✅ Chave privada válida</p>";
        } elseif (strpos($conteudo_key, '-----BEGIN RSA PRIVATE KEY-----') !== false) {
            echo "<p><strong>Formato:</strong> ✅ Chave RSA privada válida</p>";
        } else {
            echo "<p><strong>Formato:</strong> ❌ Não é uma chave privada válida</p>";
        }
        
        // Mostrar primeiras linhas
        $linhas = explode("\n", $conteudo_key);
        $primeiras_linhas = array_slice($linhas, 0, 10);
        echo "<p><strong>Primeiras linhas:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars(implode("\n", $primeiras_linhas)) . "</div>";
        
    } else {
        echo "<p><strong>Status:</strong> ❌ Não existe</p>";
    }
    
    echo "</div>";
    
    // 3. Teste de validação OpenSSL
    echo "<div class='card'>
        <h3>🧪 Teste de Validação OpenSSL</h3>";
    
    if (function_exists('openssl_x509_read')) {
        echo "<p><strong>OpenSSL:</strong> ✅ Disponível</p>";
        
        if (file_exists($cert_pem) && file_exists($key_pem)) {
            // Tentar ler o certificado
            $cert_data = openssl_x509_read($conteudo_cert);
            if ($cert_data !== false) {
                $cert_info = openssl_x509_parse($cert_data);
                echo "<p><strong>Certificado:</strong> ✅ Válido</p>";
                echo "<p><strong>Assunto:</strong> " . htmlspecialchars($cert_info['subject']['CN'] ?? 'N/A') . "</p>";
                echo "<p><strong>Emissor:</strong> " . htmlspecialchars($cert_info['issuer']['CN'] ?? 'N/A') . "</p>";
                echo "<p><strong>Válido até:</strong> " . date('d/m/Y', $cert_info['validTo_time_t']) . "</p>";
            } else {
                echo "<p><strong>Certificado:</strong> ❌ Inválido</p>";
            }
            
            // Tentar ler a chave privada
            $key_data = openssl_pkey_get_private($conteudo_key);
            if ($key_data !== false) {
                $key_info = openssl_pkey_get_details($key_data);
                echo "<p><strong>Chave privada:</strong> ✅ Válida</p>";
                echo "<p><strong>Tipo:</strong> " . htmlspecialchars($key_info['type'] === OPENSSL_KEYTYPE_RSA ? 'RSA' : 'Outro') . "</p>";
                echo "<p><strong>Tamanho da chave:</strong> " . $key_info['bits'] . " bits</p>";
            } else {
                echo "<p><strong>Chave privada:</strong> ❌ Inválida</p>";
                echo "<p><strong>Erro OpenSSL:</strong> " . openssl_error_string() . "</p>";
            }
        }
        
    } else {
        echo "<p><strong>OpenSSL:</strong> ❌ Não disponível</p>";
    }
    
    echo "</div>";
    
    // 4. Resumo e soluções
    echo "<div class='card info'>
        <h3>📋 Resumo e Soluções</h3>";
    
    if (file_exists($cert_pem) && file_exists($key_pem)) {
        echo "<p><strong>✅ Arquivos encontrados:</strong></p>
        <ul>
            <li>certificado.pem - " . number_format(filesize($cert_pem) / 1024, 2) . " KB</li>
            <li>chave.pem - " . number_format(filesize($key_pem) / 1024, 2) . " KB</li>
        </ul>";
        
        echo "<p><strong>🚀 Próximos passos:</strong></p>
        <p><a href='teste_sefaz_final.php' class='btn'>🧪 Testar SEFAZ</a></p>";
        
    } else {
        echo "<p><strong>❌ Problemas encontrados:</strong></p>";
        
        if (!file_exists($cert_pem)) {
            echo "<p>• certificado.pem não encontrado</p>";
        }
        
        if (!file_exists($key_pem)) {
            echo "<p>• chave.pem não encontrado</p>";
        }
        
        echo "<p><strong>💡 Soluções:</strong></p>
        <p>• Verifique se os arquivos foram criados corretamente</p>
        <p>• Use o script de conversão manual</p>";
    }
    
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='card error'>
        <h3>❌ Erro no Sistema</h3>
        <p><strong>Erro:</strong> " . $e->getMessage() . "</p>
    </div>";
}

echo "</div></body></html>";
?>
