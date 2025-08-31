<?php
/**
 * 🔧 Corrigir Banco de Dados
 * 📋 Atualiza caminhos para arquivos PEM corretos
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Corrigir Banco de Dados</title>
    <meta charset='utf-8'>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
        .container { max-width: 1000px; margin: 0 auto; }
        .card { background: white; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { border-left: 4px solid #28a745; }
        .error { border-left: 4px solid #dc3545; }
        .warning { border-left: 4px solid #ffc107; }
        .info { border-left: 4px solid #17a2b8; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Corrigir Banco de Dados</h1>
        <p>Atualiza caminhos para arquivos PEM corretos</p>";

try {
    $conn = getConnection();
    $empresa_id = 1;
    
    // Buscar certificado digital atual
    $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $certificado = $conn->fetch(PDO::FETCH_ASSOC);
    
    if (!$certificado) {
        echo "<div class='card error'>
            <h3>❌ Certificado não encontrado</h3>
            <p>Nenhum certificado digital ativo encontrado para a empresa ID: $empresa_id</p>
        </div>";
        exit;
    }
    
    echo "<div class='card info'>
        <h3>📋 Certificado Atual no Banco</h3>
        <p><strong>ID:</strong> " . $certificado['id'] . "</p>
        <p><strong>Nome:</strong> " . htmlspecialchars($certificado['nome_certificado']) . "</p>
        <p><strong>Arquivo atual:</strong> " . htmlspecialchars($certificado['arquivo_certificado']) . "</p>
    </div>";
    
    // Verificar arquivos PEM existentes
    $pasta_certificados = '../uploads/certificados/';
    $cert_pem = $pasta_certificados . 'certificado.pem';
    $key_pem = $pasta_certificados . 'chave.pem';
    
    echo "<div class='card'>
        <h3>📁 Verificando Arquivos PEM</h3>
        <p><strong>Certificado PEM:</strong> " . (file_exists($cert_pem) ? '✅ Existe' : '❌ Não existe') . " - <code>$cert_pem</code></p>
        <p><strong>Chave privada PEM:</strong> " . (file_exists($key_pem) ? '✅ Existe' : '❌ Não existe') . " - <code>$key_pem</code></p>
    </div>";
    
    if (file_exists($cert_pem) && file_exists($key_pem)) {
        echo "<div class='card'>
            <h3>🔄 Atualizando Banco de Dados</h3>";
        
        // Atualizar banco para apontar para certificado.pem
        $stmt = $conn->prepare("UPDATE fiscal_certificados_digitais SET arquivo_certificado = ? WHERE id = ?");
        $result = $stmt->execute(['certificado.pem', $certificado['id']]);
        
        if ($result) {
            echo "<p><strong>✅ Banco atualizado com sucesso!</strong></p>";
            echo "<p><strong>Novo arquivo:</strong> certificado.pem</p>";
            
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
            <p>Agora você pode testar a conexão SEFAZ:</p>
            <p><a href='teste_sefaz_final.php' class='btn'>🧪 Testar SEFAZ</a></p>
        </div>";
        
    } else {
        echo "<div class='card error'>
            <h3>❌ Arquivos PEM não encontrados</h3>
            <p>Você precisa ter os arquivos <code>certificado.pem</code> e <code>chave.pem</code> na pasta <code>uploads/certificados/</code></p>
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
