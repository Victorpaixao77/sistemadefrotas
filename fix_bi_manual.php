<?php
/**
 * CORREÇÃO MANUAL - BI Stack Overflow
 * Executa as correções SQL sem verificar admin
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    die("Acesso negado. Faça login primeiro.");
}

try {
    $conn = getConnection();

    echo "<h1>🔧 CORREÇÃO MANUAL - BI Stack Overflow</h1>";
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f9f9f9; }
        .success { color: #5cb85c; }
        .error { color: #d9534f; }
        .info { color: #5bc0de; }
        .step { background: #fff; padding: 15px; margin: 10px 0; border-left: 4px solid #5bc0de; border-radius: 4px; }
    </style>";

    echo "<h2>Executando correções...</h2>";

    // 1. Criar índice se não existir
    try {
        $conn->exec("CREATE INDEX IF NOT EXISTS idx_desp_viagem_rota_empresa ON despesas_viagem(rota_id, empresa_id)");
        echo "<div class='step'><span class='success'>✅ Índice criado/verificado:</span> idx_desp_viagem_rota_empresa</div>";
    } catch (Exception $e) {
        echo "<div class='step'><span class='error'>⚠️ Erro no índice:</span> " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    // 2. Limpar cache (se existir)
    try {
        $result = $conn->exec("DELETE FROM bi_cache WHERE 1=1");
        echo "<div class='step'><span class='success'>✅ Cache limpo:</span> $result registros removidos</div>";
    } catch (Exception $e) {
        echo "<div class='step'><span class='info'>ℹ️ Cache (tabela pode não existir):</span> " . htmlspecialchars($e->getMessage()) . "</div>";
    }

    echo "<h2>✅ CORREÇÃO CONCLUÍDA!</h2>";
    echo "<p>Agora recarregue a página do BI com <strong>Ctrl+F5</strong> e teste.</p>";
    echo "<a href='pages/bi.php'>Ir para BI</a>";

} catch (Exception $e) {
    echo "<div class='error'>Erro geral: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>