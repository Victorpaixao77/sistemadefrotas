<?php
/**
 * 🔍 Verificador de Certificado PEM
 * 📋 Verifica arquivos e cria chave privada se necessário
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Verificador de Certificado PEM</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
        .file-list { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Verificador de Certificado PEM</h1>
        <p>Verifica arquivos e cria chave privada se necessário</p>";

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
        <p><strong>Arquivo atual:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
        <p><strong>Senha:</strong> " . (empty($senha) ? '❌ Não informada' : '✅ Configurada') . "</p>
    </div>";
    
    // Verificar arquivos na pasta
    $pasta_certificados = '../uploads/certificados/';
    $arquivos = scandir($pasta_certificados);
    $arquivos_pem = array_filter($arquivos, function($arquivo) {
        return pathinfo($arquivo, PATHINFO_EXTENSION) === 'pem';
    });
    
    echo "<div class='card'>
        <h3>📁 Arquivos na Pasta de Certificados</h3>
        <div class='file-list'>";
    
    if (empty($arquivos_pem)) {
        echo "<p><strong>❌ Nenhum arquivo .pem encontrado</strong></p>";
    } else {
        echo "<p><strong>✅ Arquivos .pem encontrados:</strong></p>";
        foreach ($arquivos_pem as $arquivo) {
            $caminho_completo = $pasta_certificados . $arquivo;
            $tamanho = file_exists($caminho_completo) ? filesize($caminho_completo) : 0;
            $status = file_exists($caminho_completo) ? '✅ Existe' : '❌ Não existe';
            
            echo "<p><strong>$arquivo</strong> - $status - " . number_format($tamanho / 1024, 2) . " KB</p>";
        }
    }
    
    echo "</div></div>";
    
    // Verificar se temos certificado e chave
    $cert_pem = null;
    $key_pem = null;
    
    foreach ($arquivos_pem as $arquivo) {
        $caminho_completo = $pasta_certificados . $arquivo;
        if (file_exists($caminho_completo)) {
            $conteudo = file_get_contents($caminho_completo);
            if (strpos($conteudo, '-----BEGIN CERTIFICATE-----') !== false) {
                $cert_pem = $caminho_completo;
            } elseif (strpos($conteudo, '-----BEGIN PRIVATE KEY-----') !== false) {
                $key_pem = $caminho_completo;
            }
        }
    }
    
    echo "<div class='card " . ($cert_pem && $key_pem ? 'success' : 'warning') . "'>
        <h3>🔐 Status dos Arquivos PEM</h3>
        <p><strong>Certificado (.pem):</strong> " . ($cert_pem ? '✅ Encontrado' : '❌ Não encontrado') . "</p>
        <p><strong>Chave privada (.pem):</strong> " . ($key_pem ? '✅ Encontrada' : '❌ Não encontrada') . "</p>
    </div>";
    
    // Se não temos chave privada, tentar criar
    if (!$key_pem && $cert_pem && !empty($senha)) {
        echo "<div class='card'>
            <h3>🔄 Criando Chave Privada</h3>";
        
        // Tentar extrair chave do PFX original
        $pfx_original = '../uploads/certificados/' . $certificado['arquivo_certificado'];
        if (file_exists($pfx_original) && function_exists('openssl_pkcs12_read')) {
            echo "<div class='info'>
                <h4>📁 Tentando extrair chave do PFX original...</h4>";
            
            $pfx_content = file_get_contents($pfx_original);
            $certs = [];
            $result = openssl_pkcs12_read($pfx_content, $certs, $senha);
            
            if ($result && isset($certs['pkey'])) {
                // Criar arquivo de chave privada
                $nome_base = pathinfo($certificado['arquivo_certificado'], PATHINFO_FILENAME);
                $key_path = '../uploads/certificados/' . $nome_base . '_key.pem';
                
                $key_saved = file_put_contents($key_path, $certs['pkey']);
                
                if ($key_saved) {
                    echo "<p><strong>✅ Chave privada criada com sucesso!</strong></p>";
                    echo "<p><strong>Arquivo:</strong> <code>$key_path</code></p>";
                    $key_pem = $key_path;
                } else {
                    echo "<p><strong>❌ Erro ao salvar chave privada</strong></p>";
                }
            } else {
                echo "<p><strong>❌ Erro ao extrair chave do PFX</strong></p>";
                echo "<p><strong>Erro OpenSSL:</strong> " . openssl_error_string() . "</p>";
            }
        } else {
            echo "<p><strong>❌ PFX original não encontrado ou OpenSSL não disponível</strong></p>";
        }
        
        echo "</div>";
    }
    
    // Resumo final
    echo "<div class='card info'>
        <h3>📋 Resumo Final</h3>
        <ul>
            <li><strong>Certificado PEM:</strong> " . ($cert_pem ? '✅ Disponível' : '❌ Não encontrado') . "</li>
            <li><strong>Chave privada PEM:</strong> " . ($key_pem ? '✅ Disponível' : '❌ Não encontrada') . "</li>
            <li><strong>Status geral:</strong> " . ($cert_pem && $key_pem ? '✅ Pronto para uso' : '⚠️ Incompleto') . "</li>
        </ul>
    </div>";
    
    if ($cert_pem && $key_pem) {
        echo "<div class='card success'>
            <h3>🚀 Próximos Passos</h3>
            <p>Agora você pode testar a conexão SEFAZ:</p>
            <p><a href='teste_sefaz_final.php' class='btn'>🧪 Testar SEFAZ</a></p>
        </div>";
    } else {
        echo "<div class='card error'>
            <h3>❌ Problema Detectado</h3>
            <p>Você precisa ter tanto o certificado quanto a chave privada em formato PEM.</p>
            <p><strong>Solução manual:</strong></p>
            <div class='code'>
                # No PowerShell/CMD, navegue para a pasta:<br>
                cd C:\\xampp\\htdocs\\sistema-frotas\\uploads\\certificados<br><br>
                # Converta o certificado:<br>
                openssl pkcs12 -in " . htmlspecialchars($certificado['arquivo_certificado']) . " -out certificado.pem -clcerts -nokeys<br>
                openssl pkcs12 -in " . htmlspecialchars($certificado['arquivo_certificado']) . " -out chave.pem -nocerts -nodes<br><br>
                # Digite a senha quando solicitado
            </div>
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
