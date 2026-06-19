<?php
/**
 * Limpeza automática de notificações (cron).
 * Ex.: 0 2 * * * php /caminho/sistema-frotas/notificacoes/limpeza_automatica.php
 */
require_once dirname(__DIR__) . '/includes/db_connect.php';
require_once dirname(__DIR__) . '/includes/notificacoes_helpers.php';

$dias_para_limpeza = 30;

try {
    $conn = getConnection();
    $empresas = $conn->query('SELECT id FROM empresas ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

    echo "=== LIMPEZA AUTOMÁTICA DE NOTIFICAÇÕES ===\n";
    echo 'Data/Hora: ' . date('Y-m-d H:i:s') . "\n\n";

    $total_removidas = 0;

    foreach ($empresas as $empresa_id) {
        $empresa_id = (int) $empresa_id;
        if ($empresa_id <= 0) {
            continue;
        }

        $stmt_cleanup = $conn->prepare(
            'DELETE FROM notificacoes
             WHERE empresa_id = :empresa_id
             AND lida = 1
             AND data_criacao < DATE_SUB(NOW(), INTERVAL :dias DAY)'
        );
        $stmt_cleanup->execute([':empresa_id' => $empresa_id, ':dias' => $dias_para_limpeza]);
        $removidas = $stmt_cleanup->rowCount();
        $total_removidas += $removidas;

        $sql_mark_read = "UPDATE notificacoes n
                         LEFT JOIN rotas r ON n.referencia_id = r.id AND n.tipo = 'rota'
                         LEFT JOIN abastecimentos a ON n.referencia_id = a.id AND n.tipo = 'abastecimento'
                         SET n.lida = 1
                         WHERE n.empresa_id = :empresa_id
                         AND n.lida = 0
                         AND ((n.tipo = 'rota' AND (r.status != 'pendente' OR r.id IS NULL))
                              OR (n.tipo = 'abastecimento' AND (a.status != 'pendente' OR a.id IS NULL)))";
        $stmt_mark = $conn->prepare($sql_mark_read);
        $stmt_mark->execute([':empresa_id' => $empresa_id]);
        $marcadas = $stmt_mark->rowCount();

        echo "Empresa {$empresa_id}: {$removidas} removidas, {$marcadas} marcadas como lidas\n";
    }

    echo "\nTotal removidas: {$total_removidas}\n";
    error_log("Limpeza automática notificações: {$total_removidas} removidas em " . date('Y-m-d H:i:s'));
} catch (Throwable $e) {
    echo "ERRO\n";
    error_log('limpeza_automatica notificacoes: ' . $e->getMessage());
    exit(1);
}
