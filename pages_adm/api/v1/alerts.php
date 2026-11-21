<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';

$pdo = adm_api_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adm_api_boot(true);
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';
    if ($id > 0 && $acao === 'resolver') {
        $pdo->prepare("UPDATE adm_alertas SET status = 'resolvido', resolvido_em = NOW() WHERE id = ?")
            ->execute([$id]);
        adm_api_ok(['id' => $id]);
    }
    api_json_error('Ação inválida', 400);
}

$status = $_GET['status'] ?? 'aberto';
if ($status === 'todos') {
    $rows = $pdo->query("
        SELECT a.*, ea.razao_social
        FROM adm_alertas a
        LEFT JOIN empresa_adm ea ON ea.id = a.empresa_adm_id
        ORDER BY FIELD(prioridade,'emergencia','critico','aviso','info'), created_at DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stStatus = $status === 'resolvido' ? 'resolvido' : 'aberto';
    $stmt = $pdo->prepare("
        SELECT a.*, ea.razao_social
        FROM adm_alertas a
        LEFT JOIN empresa_adm ea ON ea.id = a.empresa_adm_id
        WHERE a.status = ?
        ORDER BY FIELD(a.prioridade,'emergencia','critico','aviso','info'), a.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$stStatus]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

adm_api_ok(['alertas' => $rows]);
