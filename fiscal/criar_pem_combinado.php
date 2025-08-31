<?php
/**
 * 🔗 Criar PEM Combinado
 * 📋 Combina certificado.pem + chave.pem em um arquivo único
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Criar PEM Combinado</title>
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
        <h1>🔗 Criar PEM Combinado</h1>
        <p>Combina certificado.pem + chave.pem em um arquivo único para cURL</p>";

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
        <p><strong>Arquivo atual:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
    </div>";
    
    // Verificar arquivos PEM
    $pasta_certificados = '../uploads/certificados/';
    $cert_pem = $pasta_certificados . 'certificado.pem';
    $key_pem = $pasta_certificados . 'chave.pem';
    $pem_combinado = $pasta_certificados . 'certificado_completo.pem';
    
    echo "<div class='card'>
        <h3>📁 Verificando Arquivos</h3>";
    
    $cert_existe = file_exists($cert_pem);
    $key_existe = file_exists($key_pem);
    
    echo "<p><strong>certificado.pem:</strong> " . ($cert_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
    echo "<p><strong>chave.pem:</strong> " . ($key_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
    
    if ($cert_existe && $key_existe) {
        echo "<p><strong>certificado_completo.pem:</strong> " . (file_exists($pem_combinado) ? '✅ Existe' : '❌ Não existe') . "</p>";
    }
    
    echo "</div>";
    
    if ($cert_existe && $key_existe) {
        echo "<div class='card'>
            <h3>🔄 Criando PEM Combinado</h3>";
        
        // Ler conteúdo dos arquivos
        $conteudo_cert = file_get_contents($cert_pem);
        $conteudo_key = file_get_contents($key_pem);
        
        // Verificar formato
        if (strpos($conteudo_cert, '-----BEGIN CERTIFICATE-----') === false) {
            echo "<p><strong>❌ Erro:</strong> certificado.pem não é um certificado X.509 válido</p>";
        } elseif (strpos($conteudo_key, '-----BEGIN PRIVATE KEY-----') === false && strpos($conteudo_key, '-----BEGIN RSA PRIVATE KEY-----') === false) {
            echo "<p><strong>❌ Erro:</strong> chave.pem não é uma chave privada válida</p>";
        } else {
            // Criar arquivo combinado
            $pem_combinado_conteudo = $conteudo_cert . "\n" . $conteudo_key;
            
            if (file_put_contents($pem_combinado, $pem_combinado_conteudo)) {
                echo "<p><strong>✅ PEM combinado criado com sucesso!</strong></p>";
                echo "<p><strong>Arquivo:</strong> certificado_completo.pem</p>";
                echo "<p><strong>Tamanho:</strong> " . number_format(filesize($pem_combinado) / 1024, 2) . " KB</p>";
                
                // Verificar se foi criado corretamente
                if (file_exists($pem_combinado)) {
                    echo "<p><strong>Status:</strong> ✅ Arquivo criado e verificado</p>";
                    
                    // Mostrar primeiras linhas
                    $linhas = explode("\n", $pem_combinado_conteudo);
                    $primeiras_linhas = array_slice($linhas, 0, 15);
                    echo "<p><strong>Primeiras linhas do arquivo combinado:</strong></p>";
                    echo "<div class='code'>" . htmlspecialchars(implode("\n", $primeiras_linhas)) . "</div>";
                    
                    // Atualizar banco para usar o arquivo combinado
                    echo "<div class='card'>
                        <h3>🔄 Atualizando Banco de Dados</h3>";
                    
                    $stmt = $conn->prepare("UPDATE fiscal_certificados_digitais SET arquivo_certificado = ? WHERE id = ?");
                    $result = $stmt->execute(['certificado_completo.pem', $certificado['id']]);
                    
                    if ($result) {
                        echo "<p><strong>✅ Banco atualizado com sucesso!</strong></p>";
                        echo "<p><strong>Novo arquivo:</strong> certificado_completo.pem</p>";
                        
                        // Verificar se a atualização foi feita
                        $stmt = $conn->prepare("SELECT arquivo_certificado FROM fiscal_certificados_digitais WHERE id = ?");
                        $stmt->execute([$certificado['id']]);
                        $novo_arquivo = $stmt->fetchColumn();
                        
                        echo "<p><strong>Verificação:</strong> Arquivo no banco agora é: <code>$novo_arquivo</code></p>";
                        
                    } else {
                        echo "<p><strong>❌ Erro ao atualizar banco</strong></p>";
                    }
                    
                    echo "</div>";
                    
                    echo "<div class='card success'>
                        <h3>🚀 Próximos Passos</h3>
                        <p>Agora você pode testar a conexão SEFAZ com o certificado combinado:</p>
                        <p><a href='teste_sefaz_final.php' class='btn'>🧪 Testar SEFAZ</a></p>
                    </div>";
                    
                } else {
                    echo "<p><strong>❌ Erro:</strong> Arquivo não foi criado corretamente</p>";
                }
                
            } else {
                echo "<p><strong>❌ Erro:</strong> Não foi possível criar o arquivo PEM combinado</p>";
            }
        }
        
    } else {
        echo "<p><strong>❌ Erro:</strong> Arquivos certificado.pem e/ou chave.pem não encontrados</p>";
        echo "<p>Você precisa ter ambos os arquivos para criar o PEM combinado.</p>";
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
