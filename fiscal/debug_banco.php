<?php
/**
 * 🔍 Debug do Banco de Dados
 * 📋 Mostra exatamente o que está configurado
 */

require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug do Banco de Dados</title>
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
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Debug do Banco de Dados</h1>
        <p>Mostra exatamente o que está configurado no sistema</p>";

try {
    $conn = getConnection();
    $empresa_id = 1;
    
    // 1. Verificar tabela fiscal_certificados_digitais
    echo "<div class='card'>
        <h3>📋 Tabela: fiscal_certificados_digitais</h3>";
    
    $stmt = $conn->prepare("SELECT * FROM fiscal_certificados_digitais WHERE empresa_id = ? AND ativo = 1 ORDER BY data_vencimento DESC LIMIT 1");
    $stmt->execute([$empresa_id]);
    $certificado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($certificado) {
        echo "<table class='table'>
            <tr><th>Campo</th><th>Valor</th></tr>";
        
        foreach ($certificado as $campo => $valor) {
            $valor_display = is_null($valor) ? '<em>NULL</em>' : htmlspecialchars($valor);
            echo "<tr><td><strong>$campo</strong></td><td>$valor_display</td></tr>";
        }
        
        echo "</table>";
        
        // Verificar se o arquivo existe
        $caminho_banco = '../uploads/certificados/' . $certificado['arquivo_certificado'];
        $arquivo_existe = file_exists($caminho_banco);
        
        echo "<p><strong>Status do arquivo:</strong> " . ($arquivo_existe ? '✅ Existe' : '❌ Não existe') . "</p>";
        echo "<p><strong>Caminho completo:</strong> <code>$caminho_banco</code></p>";
        
    } else {
        echo "<p><strong>❌ Nenhum certificado encontrado</strong></p>";
    }
    
    echo "</div>";
    
    // 2. Verificar arquivos na pasta
    echo "<div class='card'>
        <h3>📁 Arquivos na Pasta uploads/certificados/</h3>";
    
    $pasta_certificados = '../uploads/certificados/';
    $arquivos = scandir($pasta_certificados);
    
    echo "<table class='table'>
        <tr><th>Arquivo</th><th>Tamanho</th><th>Última Modificação</th><th>Status</th></tr>";
    
    foreach ($arquivos as $arquivo) {
        if ($arquivo !== '.' && $arquivo !== '..') {
            $caminho_completo = $pasta_certificados . $arquivo;
            $tamanho = file_exists($caminho_completo) ? filesize($caminho_completo) : 0;
            $modificacao = file_exists($caminho_completo) ? date('d/m/Y H:i:s', filemtime($caminho_completo)) : 'N/A';
            $status = file_exists($caminho_completo) ? '✅ Existe' : '❌ Não existe';
            
            echo "<tr>
                <td><strong>$arquivo</strong></td>
                <td>" . number_format($tamanho / 1024, 2) . " KB</td>
                <td>$modificacao</td>
                <td>$status</td>
            </tr>";
        }
    }
    
    echo "</table></div>";
    
    // 3. Verificar configurações fiscais
    echo "<div class='card'>
        <h3>⚙️ Tabela: fiscal_config_empresa</h3>";
    
    $stmt = $conn->prepare("SELECT * FROM fiscal_config_empresa WHERE empresa_id = ?");
    $stmt->execute([$empresa_id]);
    $config_fiscal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config_fiscal) {
        echo "<table class='table'>
            <tr><th>Campo</th><th>Valor</th></tr>";
        
        foreach ($config_fiscal as $campo => $valor) {
            $valor_display = is_null($valor) ? '<em>NULL</em>' : htmlspecialchars($valor);
            echo "<tr><td><strong>$campo</strong></td><td>$valor_display</td></tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p><strong>❌ Nenhuma configuração fiscal encontrada</strong></p>";
    }
    
    echo "</div>";
    
    // 4. Resumo e solução
    echo "<div class='card info'>
        <h3>📋 Resumo da Situação</h3>";
    
    if ($certificado) {
        $arquivo_banco = $certificado['arquivo_certificado'];
        $extensao = pathinfo($arquivo_banco, PATHINFO_EXTENSION);
        
        echo "<p><strong>Arquivo no banco:</strong> $arquivo_banco</p>";
        echo "<p><strong>Extensão:</strong> $extensao</p>";
        
        if ($extensao === 'pfx' || $extensao === 'p12') {
            echo "<p><strong>❌ PROBLEMA IDENTIFICADO:</strong></p>";
            echo "<ul>
                <li>Banco aponta para arquivo <strong>.$extensao</strong></li>
                <li>Mas você tem arquivos <strong>.pem</strong> na pasta</li>
                <li>Script tenta usar arquivo que não existe mais</li>
            </ul>";
            
            echo "<p><strong>💡 SOLUÇÃO:</strong></p>";
            echo "<p>Você precisa atualizar o banco para apontar para <code>certificado.pem</code></p>";
            
            echo "<div class='code'>
                UPDATE fiscal_certificados_digitais 
                SET arquivo_certificado = 'certificado.pem' 
                WHERE id = " . $certificado['id'] . ";
            </div>";
            
            echo "<p><a href='corrigir_banco.php' class='btn'>🔧 Corrigir Banco</a></p>";
            
        } elseif ($extensao === 'pem') {
            echo "<p><strong>✅ SITUAÇÃO CORRETA:</strong></p>";
            echo "<ul>
                <li>Banco já aponta para arquivo <strong>.pem</strong></li>
                <li>Arquivos <strong>.pem</strong> existem na pasta</li>
                <li>Sistema deve funcionar corretamente</li>
            </ul>";
            
            echo "<p><a href='teste_sefaz_final.php' class='btn'>🧪 Testar SEFAZ</a></p>";
            
        } else {
            echo "<p><strong>⚠️ EXTENSÃO DESCONHECIDA:</strong> $extensao</p>";
        }
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
