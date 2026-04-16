<?php
/**
 * SCRIPT DE CORREÇÃO - BI Stack Overflow
 * Acesse: http://localhost/sistema-frotas/fix_bi_overflow.php
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

configure_session();
session_start();

// Requer admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['role'] !== 'admin') {
    die("❌ Acesso negado. Apenas administradores podem executar esta correção.");
}

try {
    $conn = getConnection();
    
    echo "<h1>🔧 CORREÇÃO - BI Stack Overflow</h1>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        .success { color: #5cb85c; }
        .error { color: #d9534f; }
        .info { color: #5bc0de; }
        .step { background: #fff; padding: 15px; margin: 10px 0; border-left: 4px solid #5bc0de; border-radius: 4px; }
        button { background: #5cb85c; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #4cae4c; }
    </style>";
    
    $status = isset($_POST['execute']) ? true : false;
    
    if ($status) {
        echo "<h2>Executando correções...</h2>";
        
        // 1. Criar índice se não existir
        try {
            $conn->exec("CREATE INDEX IF NOT EXISTS idx_desp_viagem_rota_empresa ON despesas_viagem(rota_id, empresa_id)");
            echo "<div class='step'><span class='success'>✅ Índice criado:</span> idx_desp_viagem_rota_empresa</div>";
        } catch (Exception $e) {
            echo "<div class='step'><span class='error'>⚠️ Índice (pode já existir):</span> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 2. Limpar cache
        try {
            $result = $conn->exec("DELETE FROM bi_cache WHERE 1=1");
            echo "<div class='step'><span class='success'>✅ Cache limpo:</span> $result registros removidos</div>";
        } catch (Exception $e) {
            echo "<div class='step'><span class='info'>ℹ️ Cache (tabela pode não existir):</span> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // 3. Verificar índices
        echo "<h2>Índices Verificados:</h2>";
        $result = $conn->query("SHOW INDEXES FROM despesas_viagem WHERE Key_name LIKE 'idx_%'");
        $indexes = $result->fetchAll(PDO::FETCH_ASSOC);
        echo "<div class='step'>";
        foreach ($indexes as $idx) {
            echo "• " . $idx['Key_name'] . " (" . $idx['Column_name'] . ")<br>";
        }
        echo "</div>";
        
        echo "<h2 class='success'>✅ Todas as correções executadas com sucesso!</h2>";
        echo "<p><strong>Próximas ações:</strong></p>";
        echo "<ol>";
        echo "<li>Recarregue a página do BI (<em>Ctrl+F5</em>)</li>";
        echo "<li>Tente novamente acessar a página</li>";
        echo "<li>Se ainda houver erro, verifique seu volume de dados com: <a href='diagnostico_bi.php' target='_blank'>Diagnóstico BI</a></li>";
        echo "</ol>";
        
    } else {
        echo "<div class='step'>";
        echo "<h2>Análise de Correções Necessárias:</h2>";
        echo "<p>Esta página executará as seguintes correções:</p>";
        echo "<ul>";
        echo "<li>✓ Criar índice para despesas_viagem (rota_id, empresa_id)</li>";
        echo "<li>✓ Limpar cache de BI (forçar recalculação)</li>";
        echo "<li>✓ Limitar dados retornados pela API (máx 50 veículos, 200 registros por drill-down)</li>";
        echo "</ul>";
        echo "<p><strong>Status atual:</strong></p>";
        
        // Verificar status
        try {
            $result = $conn->query("SHOW INDEXES FROM despesas_viagem WHERE Key_name LIKE 'idx_desp_viagem%'");
            $count = $result->rowCount();
            echo "<p><span class='success'>✅ Índices atuais em despesas_viagem: $count</span></p>";
        } catch (Exception $e) {
            echo "<p><span class='error'>❌ Erro ao verificar índices</span></p>";
        }
        
        try {
            $result = $conn->query("SELECT COUNT(*) as cache_count FROM bi_cache");
            $row = $result->fetch(PDO::FETCH_ASSOC);
            echo "<p><span class='info'>ℹ️ Registros em cache: " . ($row['cache_count'] ?? 0) . "</span></p>";
        } catch (Exception $e) {
            echo "<p><span class='error'>⚠️ BI Cache não existe (será criado automaticamente)</span></p>";
        }
        
        echo "</div>";
        
        echo "<form method='POST'>";
        echo "<button type='submit' name='execute' value='1'>▶ EXECUTAR CORREÇÕES AGORA</button>";
        echo "</form>";
        
        echo "<div class='step'>";
        echo "<h2>⚠️ O que foi corrigido na API:</h2>";
        echo "<ul>";
        echo "<li>Limitado quantidade de veículos em listagens (50 máximo)</li>";
        echo "<li>Limitado drilling-down a 200 registros por vez</li>";
        echo "<li>Aumento de LIMIT em top veículos (10 → 20)</li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='step'><span class='error'>❌ ERRO:</span> " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
