<?php
/**
 * Arquiva notificações antigas: move da tabela viva `notificacoes` para
 * `notificacoes_arquivo` (preservando o histórico) e remove as antigas da
 * tabela viva, que cresce sem limite (dezenas de milhares de linhas).
 *
 * SEGURO POR PADRÃO: roda em DRY-RUN — apenas relata quanto seria arquivado.
 * Só apaga/move de fato quando você passar a flag --apply.
 *
 * Uso:
 *   php cron/arquivar_notificacoes.php                 # dry-run, retenção 90 dias
 *   php cron/arquivar_notificacoes.php --dias=120      # dry-run, retenção 120 dias
 *   php cron/arquivar_notificacoes.php --apply         # arquiva de fato (90 dias)
 *   php cron/arquivar_notificacoes.php --apply --dias=180
 *
 * Cron sugerido (semanal):
 *   php /caminho/cron/arquivar_notificacoes.php --apply --dias=90
 *
 * Pré-requisito: aplicar sql/notificacoes_arquivo.sql (cria a tabela de arquivo).
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
require_once $root . '/includes/config.php';
require_once $root . '/includes/db_connect.php';

// ---- Argumentos ----
$apply = in_array('--apply', $argv, true);
$dias = 90;
foreach ($argv as $a) {
    if (preg_match('/^--dias=(\d+)$/', $a, $m)) {
        $dias = max(1, (int) $m[1]);
    }
}
$lote = 5000; // tamanho do batch (evita transações/locks gigantes)

$conn = getConnection();
$limite = (new DateTime())->modify("-{$dias} days")->format('Y-m-d H:i:s');

echo "== Arquivar notificações ==\n";
echo "Retenção: {$dias} dias (mantém >= {$limite})\n";
echo "Modo: " . ($apply ? "APLICAR (move + apaga)" : "DRY-RUN (só relatório)") . "\n";

// Confere a tabela de arquivo.
$temArquivo = (bool) $conn->query(
    "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notificacoes_arquivo' LIMIT 1"
)->fetchColumn();
if ($apply && !$temArquivo) {
    fwrite(STDERR, "ERRO: tabela notificacoes_arquivo não existe. Rode antes: sql/notificacoes_arquivo.sql\n");
    exit(1);
}

// Quantas seriam arquivadas?
$st = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE data_criacao < :lim");
$st->execute([':lim' => $limite]);
$totalAlvo = (int) $st->fetchColumn();
echo "Notificações além da retenção: {$totalAlvo}\n";

if (!$apply) {
    echo "DRY-RUN: nada foi alterado. Use --apply para executar.\n";
    exit(0);
}
if ($totalAlvo === 0) {
    echo "Nada a arquivar.\n";
    exit(0);
}

// ---- Arquivamento em lotes ----
$movidas = 0;
while (true) {
    $conn->beginTransaction();
    try {
        // Copia um lote para o arquivo.
        $ins = $conn->prepare(
            "INSERT INTO notificacoes_arquivo
             SELECT * FROM notificacoes WHERE data_criacao < :lim ORDER BY id ASC LIMIT {$lote}"
        );
        $ins->execute([':lim' => $limite]);
        $n = $ins->rowCount();
        if ($n === 0) { $conn->commit(); break; }

        // Apaga exatamente o mesmo lote da tabela viva.
        $del = $conn->prepare(
            "DELETE FROM notificacoes WHERE data_criacao < :lim ORDER BY id ASC LIMIT {$lote}"
        );
        $del->execute([':lim' => $limite]);

        $conn->commit();
        $movidas += $n;
        echo "  lote: +{$n} (acum. {$movidas}/{$totalAlvo})\n";
        usleep(50000); // alívio de 50ms entre lotes
    } catch (Throwable $e) {
        $conn->rollBack();
        fwrite(STDERR, "ERRO no lote: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "Concluído. Arquivadas {$movidas} notificações.\n";
// Opcional: otimizar a tabela viva (descomente se desejar reclamar espaço).
// $conn->query("OPTIMIZE TABLE notificacoes");
exit(0);
