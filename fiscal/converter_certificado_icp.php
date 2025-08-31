<?php
/**
 * 🔐 Converter Certificado ICP-Brasil
 * 📋 Converte certificados .pfx/.p12 para .pem para uso com SEFAZ
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Converter Certificado ICP-Brasil</title>
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
        .upload-form { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .form-group { margin: 15px 0; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .btn { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔐 Converter Certificado ICP-Brasil</h1>
        <p>Converte certificados .pfx/.p12 para .pem para uso com SEFAZ</p>";

// Verificar se OpenSSL está disponível
if (!extension_loaded('openssl')) {
    echo "<div class='card error'>
        <h3>❌ OpenSSL não disponível</h3>
        <p>A extensão OpenSSL não está habilitada no PHP.</p>
        <p>Você precisa habilitar a extensão OpenSSL no php.ini</p>
    </div>";
    exit;
}

// Verificar se OpenSSL CLI está disponível
$openssl_version = shell_exec('openssl version 2>&1');
if (strpos($openssl_version, 'OpenSSL') === false) {
    echo "<div class='card error'>
        <h3>❌ OpenSSL CLI não disponível</h3>
        <p>O comando OpenSSL não está disponível no sistema.</p>
        <p>Execute: <code>fiscal/instalar_openssl.ps1</code></p>
    </div>";
    exit;
}

echo "<div class='card info'>
    <h3>📋 Sobre Certificados ICP-Brasil</h3>
    <p><strong>Certificados A1:</strong> Arquivos .pfx/.p12 com chave privada e certificado</p>
    <p><strong>Certificados A3:</strong> Token ou cartão (não funcionam direto no XAMPP)</p>
    <p><strong>Formato PEM:</strong> Certificado e chave separados para uso com cURL</p>
    <p><strong>SEFAZ:</strong> Só aceita certificados ICP-Brasil válidos</p>
</div>";

// Processar upload se existir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['certificado'])) {
    $uploaded_file = $_FILES['certificado'];
    $senha = $_POST['senha'] ?? '';
    
    echo "<div class='card'>
        <h3>🔄 Processando Certificado</h3>";
    
    // Verificar arquivo
    if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
        echo "<p><strong>❌ Erro no upload:</strong> " . $uploaded_file['error'] . "</p>";
    } elseif (!in_array(strtolower(pathinfo($uploaded_file['name'], PATHINFO_EXTENSION)), ['pfx', 'p12'])) {
        echo "<p><strong>❌ Formato inválido:</strong> Use apenas arquivos .pfx ou .p12</p>";
    } else {
        // Criar pasta de certificados se não existir
        $pasta_certificados = realpath('../uploads/certificados/');
        if (!$pasta_certificados) {
            mkdir('../uploads/certificados/', 0755, true);
            $pasta_certificados = realpath('../uploads/certificados/');
        }
        
        // Salvar arquivo temporário
        $temp_file = $pasta_certificados . DIRECTORY_SEPARATOR . 'temp_cert.pfx';
        if (move_uploaded_file($uploaded_file['tmp_name'], $temp_file)) {
            echo "<p><strong>✅ Arquivo carregado:</strong> " . $uploaded_file['name'] . "</p>";
            
            // Converter usando OpenSSL
            echo "<h4>🔧 Convertendo para formato PEM...</h4>";
            
            // Extrair chave privada
            $chave_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'chave_icp.pem';
            $cmd_chave = "openssl pkcs12 -in \"$temp_file\" -nocerts -out \"$chave_pem\" -nodes";
            if ($senha) {
                $cmd_chave .= " -passin pass:\"$senha\"";
            }
            $cmd_chave .= " 2>&1";
            
            $resultado_chave = shell_exec($cmd_chave);
            echo "<p><strong>Comando chave:</strong> <code>" . htmlspecialchars($cmd_chave) . "</code></p>";
            
            if (file_exists($chave_pem) && filesize($chave_pem) > 0) {
                echo "<p><strong>✅ Chave privada extraída:</strong> " . basename($chave_pem) . "</p>";
                
                // Extrair certificado
                $cert_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'certificado_icp.pem';
                $cmd_cert = "openssl pkcs12 -in \"$temp_file\" -clcerts -nokeys -out \"$cert_pem\"";
                if ($senha) {
                    $cmd_cert .= " -passin pass:\"$senha\"";
                }
                $cmd_cert .= " 2>&1";
                
                $resultado_cert = shell_exec($cmd_cert);
                echo "<p><strong>Comando certificado:</strong> <code>" . htmlspecialchars($cmd_cert) . "</code></p>";
                
                if (file_exists($cert_pem) && filesize($cert_pem) > 0) {
                    echo "<p><strong>✅ Certificado extraído:</strong> " . basename($cert_pem) . "</p>";
                    
                    // Extrair cadeia (opcional)
                    $cadeia_pem = $pasta_certificados . DIRECTORY_SEPARATOR . 'cadeia_icp.pem';
                    $cmd_cadeia = "openssl pkcs12 -in \"$temp_file\" -cacerts -nokeys -out \"$cadeia_pem\"";
                    if ($senha) {
                        $cmd_cadeia .= " -passin pass:\"$senha\"";
                    }
                    $cmd_cadeia .= " 2>&1";
                    
                    $resultado_cadeia = shell_exec($cmd_cadeia);
                    if (file_exists($cadeia_pem) && filesize($cadeia_pem) > 0) {
                        echo "<p><strong>✅ Cadeia de certificados extraída:</strong> " . basename($cadeia_pem) . "</p>";
                    } else {
                        echo "<p><strong>⚠️ Cadeia não extraída</strong> (pode ser normal)</p>";
                    }
                    
                    // Verificar arquivos
                    echo "<h4>🔍 Verificando Arquivos</h4>";
                    
                    $cert_size = filesize($cert_pem);
                    $key_size = filesize($chave_pem);
                    
                    echo "<p><strong>Tamanho do certificado:</strong> " . number_format($cert_size) . " bytes</p>";
                    echo "<p><strong>Tamanho da chave:</strong> " . number_format($key_size) . " bytes</p>";
                    
                    // Verificar se são válidos
                    $cert_info = openssl_x509_read(file_get_contents($cert_pem));
                    $key_info = openssl_pkey_get_private(file_get_contents($chave_pem));
                    
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
                        
                        echo "<div class='card success'>
                            <h3>🎉 Conversão Concluída com Sucesso!</h3>
                            <p><strong>Arquivos gerados:</strong></p>
                            <ul>
                                <li><code>certificado_icp.pem</code> - Certificado público</li>
                                <li><code>chave_icp.pem</code> - Chave privada</li>
                                <li><code>cadeia_icp.pem</code> - Cadeia de certificados (se disponível)</li>
                            </ul>
                            <p><strong>Próximo passo:</strong> Testar com a SEFAZ usando cURL</p>
                        </div>";
                        
                    } else {
                        echo "<p><strong>❌ Erro na validação dos arquivos</strong></p>";
                        echo "<p><strong>Erro certificado:</strong> " . openssl_error_string() . "</p>";
                    }
                    
                } else {
                    echo "<p><strong>❌ Erro ao extrair certificado</strong></p>";
                    echo "<p><strong>Resultado:</strong> <code>" . htmlspecialchars($resultado_cert) . "</code></p>";
                }
                
            } else {
                echo "<p><strong>❌ Erro ao extrair chave privada</strong></p>";
                echo "<p><strong>Resultado:</strong> <code>" . htmlspecialchars($resultado_chave) . "</code></p>";
                echo "<p><strong>💡 Dica:</strong> Verifique se a senha está correta</p>";
            }
            
            // Limpar arquivo temporário
            unlink($temp_file);
            
        } else {
            echo "<p><strong>❌ Erro ao salvar arquivo temporário</strong></p>";
        }
    }
    
    echo "</div>";
    
} else {
    // Formulário de upload
    echo "<div class='card upload-form'>
        <h3>📤 Upload do Certificado ICP-Brasil</h3>
        <form method='POST' enctype='multipart/form-data'>
            <div class='form-group'>
                <label for='certificado'>Selecione o arquivo .pfx ou .p12:</label>
                <input type='file' id='certificado' name='certificado' accept='.pfx,.p12' required>
            </div>
            <div class='form-group'>
                <label for='senha'>Senha do certificado (se houver):</label>
                <input type='password' id='senha' name='senha' placeholder='Deixe em branco se não houver senha'>
            </div>
            <button type='submit' class='btn'>🔐 Converter Certificado</button>
        </form>
    </div>";
    
    // Verificar certificados existentes
    echo "<div class='card info'>
        <h3>📋 Certificados Disponíveis</h3>";
    
    $pasta_certificados = realpath('../uploads/certificados/');
    if ($pasta_certificados && is_dir($pasta_certificados)) {
        $arquivos = glob($pasta_certificados . DIRECTORY_SEPARATOR . '*.pem');
        if (!empty($arquivos)) {
            echo "<p><strong>Arquivos .pem encontrados:</strong></p>";
            foreach ($arquivos as $arquivo) {
                $nome = basename($arquivo);
                $tamanho = filesize($arquivo);
                echo "<p><strong>📄 $nome:</strong> " . number_format($tamanho) . " bytes</p>";
            }
        } else {
            echo "<p><strong>❌ Nenhum arquivo .pem encontrado</strong></p>";
        }
    } else {
        echo "<p><strong>❌ Pasta de certificados não encontrada</strong></p>";
    }
    
    echo "</div>";
}

echo "<div class='card warning'>
    <h3>⚠️ Importante</h3>
    <p><strong>Certificados de teste:</strong> Não funcionam com SEFAZ</p>
    <p><strong>Certificados reais:</strong> Precisam ser ICP-Brasil válidos</p>
    <p><strong>Formato:</strong> .pfx ou .p12 com chave privada</p>
    <p><strong>Uso:</strong> Apenas para desenvolvimento/homologação</p>
</div>";

echo "<div class='card success'>
    <h3>🎯 Próximos Passos</h3>
    <p>1. ✅ Converter certificado ICP-Brasil para .pem</p>
    <p>2. 🧪 Testar com a SEFAZ usando cURL</p>
    <p>3. 🔐 Configurar no sistema</p>
    <p>4. 🚀 Testar emissão de NF-e</p>
</div>";

echo "</div></body></html>";
?>
