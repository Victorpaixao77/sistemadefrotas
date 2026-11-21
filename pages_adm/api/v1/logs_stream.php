<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/lib/adm_api.php';

$pdo = adm_api_boot();

$empresa = isset($_GET['empresa']) ? (int) $_GET['empresa'] : 0;
$modulo = isset($_GET['modulo']) ? substr((string) $_GET['modulo'], 0, 64) : '';
$tipo = isset($_GET['tipo']) ? substr((string) $_GET['tipo'], 0, 64) : '';
$busca = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 50)));

$where = ['1=1'];
$params = [];

if ($empresa > 0) {
    $where[] = '(e.empresa_cliente_id = :emp OR e.empresa_adm_id = :emp2)';
    $params[':emp'] = $empresa;
    $params[':emp2'] = $empresa;
}
if ($modulo !== '') {
    $where[] = 'e.modulo = :mod';
    $params[':mod'] = $modulo;
}
if ($tipo !== '') {
    $where[] = 'e.tipo = :tipo';
    $params[':tipo'] = $tipo;
}
if ($busca !== '') {
    $where[] = 'e.mensagem LIKE :q';
    $params[':q'] = '%' . $busca . '%';
}

$sql = "SELECT e.*, ec.razao_social AS empresa_nome
        FROM adm_eventos e
        LEFT JOIN empresa_clientes ec ON ec.id = e.empresa_cliente_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY e.created_at DESC LIMIT {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$lines = [];
foreach ($rows as $r) {
    $lines[] = [
        'hora' => date('H:i', strtotime((string) $r['created_at'])),
        'empresa' => $r['empresa_nome'] ?: 'Plataforma',
        'mensagem' => ($r['modulo'] ? '[' . $r['modulo'] . '] ' : '') . $r['mensagem'],
        'severidade' => $r['severidade'],
        'tipo' => $r['tipo'],
    ];
}

// Mesclar log_acessos recentes
try {
    $la = $pdo->query("
        SELECT l.data_acesso, l.tipo_acesso, u.nome, ec.razao_social
        FROM log_acessos l
        LEFT JOIN usuarios u ON u.id = l.usuario_id
        LEFT JOIN empresa_clientes ec ON ec.id = l.empresa_id
        ORDER BY l.data_acesso DESC LIMIT 30
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($la as $l) {
        $lines[] = [
            'hora' => date('H:i', strtotime((string) $l['data_acesso'])),
            'empresa' => $l['razao_social'] ?: '—',
            'mensagem' => '[auth] ' . $l['tipo_acesso'] . ($l['nome'] ? ' — ' . $l['nome'] : ''),
            'severidade' => 'info',
            'tipo' => 'login',
        ];
    }
    usort($lines, static fn ($a, $b) => strcmp($b['hora'], $a['hora']));
    $lines = array_slice($lines, 0, $limit);
} catch (Throwable $e) {
}

adm_api_ok(['lines' => $lines]);
