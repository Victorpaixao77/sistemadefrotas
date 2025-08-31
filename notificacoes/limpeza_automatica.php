<?php
/**
 * Script de Limpeza Automática de Notificações
 * 
 * Este script deve ser executado via cron job para limpeza automática:
 * 0 2 * * * /usr/bin/php /caminho/para/sistema-frotas/notificacoes/limpeza_automatica.php
 * 
 * Executa diariamente às 2h da manhã
 */

require_once '../includes/db_connect.php';

// Configurações
$dias_para_limpeza = 30; // Remover notificações com mais de 30 dias
$empresas_para_limpar = [1, 2, 3]; // IDs das empresas (ajustar conforme necessário)

try {
    $conn = getConnection();
    
    echo "=== LIMPEZA AUTOMÁTICA DE NOTIFICAÇÕES ===\n";
    echo "Data/Hora: " . date('Y-m-d H:i:s') . "\n\n";
    
    $total_removidas = 0;
    
    foreach ($empresas_para_limpar as $empresa_id) {
        // Remover notificações antigas
        $sql_cleanup = "DELETE FROM notificacoes 
                        WHERE empresa_id = :empresa_id 
                        AND data_criacao < DATE_SUB(NOW(), INTERVAL :dias DAY)";
        
        $stmt_cleanup = $conn->prepare($sql_cleanup);
        $stmt_cleanup->execute([
            'empresa_id' => $empresa_id,
            'dias' => $dias_para_limpeza
        ]);
        
        $removidas = $stmt_cleanup->rowCount();
        $total_removidas += $removidas;
        
        echo "Empresa ID {$empresa_id}: {$removidas} notificações removidas\n";
        
        // Marcar como lidas notificações de rotas/abastecimentos que não estão mais pendentes
        $sql_mark_read = "UPDATE notificacoes n 
                         LEFT JOIN rotas r ON n.referencia_id = r.id AND n.tipo = 'rota'
                         LEFT JOIN abastecimentos a ON n.referencia_id = a.id AND n.tipo = 'abastecimento'
                         SET n.lida = 1 
                         WHERE n.empresa_id = :empresa_id 
                         AND n.lida = 0
                         AND ((n.tipo = 'rota' AND (r.status != 'pendente' OR r.id IS NULL))
                              OR (n.tipo = 'abastecimento' AND (a.status != 'pendente' OR a.id IS NULL)))";
        
        $stmt_mark_read = $conn->prepare($sql_mark_read);
        $stmt_mark_read->execute(['empresa_id' => $empresa_id]);
        
        $marcadas_lidas = $stmt_mark_read->rowCount();
        echo "Empresa ID {$empresa_id}: {$marcadas_lidas} notificações marcadas como lidas\n";
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Total de notificações removidas: {$total_removidas}\n";
    echo "Limpeza concluída com sucesso!\n";
    
    // Log da limpeza
    error_log("Limpeza automática de notificações concluída: {$total_removidas} removidas em " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    error_log("Erro na limpeza automática de notificações: " . $e->getMessage());
}
