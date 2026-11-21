<?php
/**
 * PDF — relatório executivo do Centro de Inteligência para diretoria.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_ia_copilot.php';
require_once __DIR__ . '/../includes/sf_ia_platform.php';

configure_session();
session_start();
require_authentication();

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);

if (!can_view_financial_data()) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Sem permissão para relatório financeiro.';
    exit;
}

$moeda = static fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
$num = static fn($v) => number_format((float) $v, 0, ',', '.');

try {
    $conn = getConnection();
    sf_ia_ensure_schema($conn);

    $empresaNome = (string) ($_SESSION['empresa_nome'] ?? 'Empresa');
    if ($empresaNome === 'Empresa' || $empresaNome === '') {
        $se = $conn->prepare('SELECT nome FROM empresas WHERE id = :id LIMIT 1');
        $se->execute([':id' => $empresa_id]);
        $empresaNome = (string) ($se->fetchColumn() ?: 'Empresa');
    }

    $exec = sf_ia_copilot_executivo($conn, $empresa_id, $moeda, $num);
    $previsoes = sf_ia_previsoes($conn, $empresa_id, $moeda);
    $auditoria = sf_ia_auditoria_list($conn, $empresa_id, 20);

    $html = sf_ia_relatorio_pdf_html($empresa_id, $empresaNome, $exec, $previsoes, $auditoria);
    $fname = 'relatorio_inteligencia_' . date('Y-m-d');
    sf_ia_gerar_pdf($html, $fname);
} catch (Throwable $e) {
    error_log('ia_relatorio_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Não foi possível gerar o PDF. Verifique se composer install foi executado.';
}
