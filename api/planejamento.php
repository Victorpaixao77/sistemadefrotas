<?php
/**
 * Planejamento Logístico — quadro (somente leitura). Mostra o que falta planejar
 * (OTs aprovadas sem rota) + disponibilidade de veículos/motoristas. As gravações
 * (atribuir veículo/motorista e gerar rota) reutilizam a API de Ordens de Transporte.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=UTF-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Empresa não identificada.']);
    exit;
}

try {
    $conn = getConnection();

    // OTs aprovadas ainda sem rota (a planejar).
    $aPlanejar = [];
    try {
        $st = $conn->prepare("SELECT o.id, o.numero, o.cliente_nome, o.origem, o.destino, o.estado_origem, o.estado_destino,
            o.distancia_km, o.peso_carga, o.valor_frete, o.veiculo_id, o.motorista_id, o.data_coleta, o.tipo_carga, o.eixos
            FROM ordens_transporte o
            WHERE o.empresa_id = :e AND o.status = 'aprovada' AND (o.rota_id IS NULL OR o.rota_id = 0)
            ORDER BY (o.data_coleta IS NULL), o.data_coleta ASC, o.created_at ASC LIMIT 200");
        $st->execute([':e' => $empresa_id]);
        $aPlanejar = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* módulo OT pode não existir */ }

    // Veículos (ativos) com flag de ocupado (em viagem agora).
    $veiculos = [];
    $sv = $conn->prepare("SELECT v.id, v.placa, v.modelo,
        (SELECT COUNT(*) FROM rotas r WHERE r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND r.status = 'aprovado'
            AND r.data_saida <= NOW() AND (r.data_chegada IS NULL OR r.data_chegada >= NOW())) AS ocupado
        FROM veiculos v WHERE v.empresa_id = :e AND (v.status_id = 1 OR v.status_id IS NULL) ORDER BY v.placa");
    $sv->execute([':e' => $empresa_id]);
    foreach ($sv->fetchAll(PDO::FETCH_ASSOC) as $v) {
        $v['ocupado'] = ((int) $v['ocupado']) > 0 ? 1 : 0;
        $veiculos[] = $v;
    }

    // Motoristas.
    $sm = $conn->prepare('SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome');
    $sm->execute([':e' => $empresa_id]);
    $motoristas = $sm->fetchAll(PDO::FETCH_ASSOC);

    // KPIs do que está a planejar.
    $kpis = [
        'ots' => count($aPlanejar),
        'km' => 0.0, 'peso' => 0.0, 'frete' => 0.0,
        'veiculos_livres' => 0, 'veiculos_total' => count($veiculos),
    ];
    foreach ($aPlanejar as $o) {
        $kpis['km'] += (float) $o['distancia_km'];
        $kpis['peso'] += (float) $o['peso_carga'];
        $kpis['frete'] += (float) $o['valor_frete'];
    }
    foreach ($veiculos as $v) { if (!$v['ocupado']) { $kpis['veiculos_livres']++; } }

    echo json_encode([
        'success' => true,
        'a_planejar' => $aPlanejar,
        'veiculos' => $veiculos,
        'motoristas' => $motoristas,
        'kpis' => $kpis,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/planejamento.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar o planejamento.']);
}
