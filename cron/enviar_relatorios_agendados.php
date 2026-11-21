<?php
/**
 * Cron: envia por e-mail os relatórios automáticos agendados que estão vencidos.
 *
 * Registrar (Windows Task Scheduler / cron), rodando de hora em hora:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\sistema-frotas\cron\enviar_relatorios_agendados.php
 *
 * Requer SMTP configurado por empresa (Configurações > Segurança).
 */

if (PHP_SAPI !== 'cli') {
    // Evita execução acidental via navegador (mas permite se precisar disparar manualmente autenticado).
    require_once __DIR__ . '/../includes/config.php';
    configure_session();
    session_start();
    if (empty($_SESSION['loggedin'])) {
        http_response_code(403);
        exit('Somente CLI.');
    }
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auto_reports.php';
require_once __DIR__ . '/../includes/security.php';

function ra_next(string $freq): string {
    $base = strtotime('06:00 tomorrow');
    if ($freq === 'semanal') {
        $base = strtotime('monday next week 06:00');
    } elseif ($freq === 'mensal') {
        $base = strtotime('first day of next month 06:00');
    }
    return date('Y-m-d H:i:s', $base);
}

$nomes = [
    'gamificacao_semanal' => 'Gamificação Semanal',
    'ranking_mensal' => 'Ranking Mensal',
    'performance_geral' => 'Performance Geral',
];

$enviados = 0;
$falhas = 0;

try {
    $conn = getConnection();
    $mgr = new AutoReportManager($conn);

    $due = $conn->query(
        'SELECT * FROM relatorios_agendados
         WHERE ativo = 1 AND (proximo_envio IS NULL OR proximo_envio <= NOW())'
    )->fetchAll(PDO::FETCH_ASSOC);

    foreach ($due as $ag) {
        $empresa_id = (int) $ag['empresa_id'];
        $type = (string) $ag['report_type'];

        // Gera o relatório.
        $data = null;
        if ($type === 'gamificacao_semanal') {
            $data = $mgr->generateWeeklyGamificationReport($empresa_id);
        } elseif ($type === 'ranking_mensal') {
            $data = $mgr->generateMonthlyRankingReport($empresa_id);
        } elseif ($type === 'performance_geral') {
            $data = $mgr->generatePerformanceReport($empresa_id);
        }
        if (!$data) {
            $falhas++;
            error_log("agendado #{$ag['id']}: falha ao gerar $type (empresa $empresa_id)");
            continue;
        }

        $html = '<div style="font-family:Arial,sans-serif;">'
            . '<h2>' . htmlspecialchars($nomes[$type] ?? $type) . '</h2>'
            . '<p style="color:#666;">Relatório automático — Frotec · ' . date('d/m/Y') . '</p>'
            . $mgr->generateReportHTML($data)
            . '</div>';

        $cfg = sf_security_config($empresa_id);
        $destinatarios = preg_split('/[,;\s]+/', (string) $ag['destinatarios'], -1, PREG_SPLIT_NO_EMPTY);
        $okAlgum = false;
        foreach ($destinatarios as $to) {
            $to = trim($to);
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            if (sf_smtp_send($cfg, $to, 'Frotec — ' . ($nomes[$type] ?? $type), $html)) {
                $okAlgum = true;
            }
        }

        // Reagenda mesmo se o e-mail falhou (evita loop de reenvio a cada execução).
        $upd = $conn->prepare('UPDATE relatorios_agendados SET ultimo_envio = NOW(), proximo_envio = :p WHERE id = :id');
        $upd->execute([':p' => ra_next((string) $ag['frequencia']), ':id' => (int) $ag['id']]);

        if ($okAlgum) { $enviados++; } else { $falhas++; }
    }

    $msg = "Relatórios agendados: $enviados enviado(s), $falhas falha(s).";
    if (PHP_SAPI === 'cli') { echo $msg . PHP_EOL; }
    error_log($msg);
} catch (Throwable $e) {
    error_log('cron enviar_relatorios_agendados: ' . $e->getMessage());
    if (PHP_SAPI === 'cli') { echo 'Erro: ' . $e->getMessage() . PHP_EOL; }
    exit(1);
}
