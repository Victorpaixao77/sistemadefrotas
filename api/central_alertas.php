<?php
/**
 * Central de Alertas — lista, item a item, os alertas que hoje ficam espalhados
 * (CNH, documentos, multas, pneus, GPS). Somente leitura, tudo de fontes reais.
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

$alertas = [];
function add(&$arr, $cat, $nivel, $titulo, $detalhe, $link, $data = null) {
    $arr[] = ['categoria' => $cat, 'nivel' => $nivel, 'titulo' => $titulo, 'detalhe' => $detalhe, 'link' => $link, 'data' => $data];
}

try {
    $conn = getConnection();

    // ---- CNH ----
    $st = $conn->prepare("SELECT nome, data_validade_cnh,
        DATEDIFF(data_validade_cnh, CURDATE()) dias
        FROM motoristas
        WHERE empresa_id = :e AND data_validade_cnh IS NOT NULL AND data_validade_cnh <> '0000-00-00'
          AND data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY data_validade_cnh ASC LIMIT 60");
    $st->execute([':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $dias = (int) $r['dias'];
        $niv = $dias < 0 ? 'critico' : 'aviso';
        $det = $dias < 0 ? ('CNH vencida há ' . abs($dias) . ' dia(s)') : ('CNH vence em ' . $dias . ' dia(s)');
        add($alertas, 'CNH', $niv, $r['nome'], $det, 'gestao_motoristas.php', $r['data_validade_cnh']);
    }

    // ---- Documentos do motorista ----
    try {
        $st = $conn->prepare("SELECT m.nome, d.tipo, d.data_validade, DATEDIFF(d.data_validade, CURDATE()) dias
            FROM documentos_motoristas d JOIN motoristas m ON m.id = d.motorista_id AND m.empresa_id = d.empresa_id
            WHERE d.empresa_id = :e AND d.data_validade IS NOT NULL
              AND d.data_validade <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY d.data_validade ASC LIMIT 60");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dias = (int) $r['dias'];
            $niv = $dias < 0 ? 'critico' : 'aviso';
            $det = ($r['tipo'] ?: 'Documento') . ' — ' . ($dias < 0 ? ('vencido há ' . abs($dias) . 'd') : ('vence em ' . $dias . 'd'));
            add($alertas, 'Documentos', $niv, $r['nome'], $det, 'gestao_motoristas.php', $r['data_validade']);
        }
    } catch (PDOException $e) { /* tabela pode não existir */ }

    // ---- Multas pendentes ----
    $st = $conn->prepare("SELECT v.placa, mo.nome motorista, mu.descricao, mu.valor, mu.data_infracao
        FROM multas mu
        LEFT JOIN veiculos v ON v.id = mu.veiculo_id
        LEFT JOIN motoristas mo ON mo.id = mu.motorista_id
        WHERE mu.empresa_id = :e AND mu.status_pagamento = 'pendente'
        ORDER BY mu.data_infracao DESC LIMIT 60");
    $st->execute([':e' => $empresa_id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $tit = ($r['placa'] ?: 'Multa') . ($r['motorista'] ? ' · ' . $r['motorista'] : '');
        $det = trim(($r['descricao'] ?: 'Infração') . ' — R$ ' . number_format((float) $r['valor'], 2, ',', '.'));
        add($alertas, 'Multas', 'aviso', $tit, $det, 'multas.php', $r['data_infracao']);
    }

    // ---- Pneus críticos ----
    try {
        $st = $conn->prepare("SELECT numero_serie, marca, modelo, km_instalacao, vida_util_km
            FROM pneus WHERE empresa_id = :e
              AND ((vida_util_km > 0 AND km_instalacao >= vida_util_km) OR km_instalacao > 80000)
            ORDER BY km_instalacao DESC LIMIT 60");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tit = trim(($r['marca'] ?: '') . ' ' . ($r['modelo'] ?: '')) ?: ('Pneu ' . $r['numero_serie']);
            $det = 'Série ' . $r['numero_serie'] . ' · ' . number_format((float) $r['km_instalacao'], 0, ',', '.') . ' km';
            add($alertas, 'Pneus', 'critico', $tit, $det, 'pneus_dashboard.php');
        }
    } catch (PDOException $e) { /* ignora */ }

    // ---- GPS (24h) ----
    try {
        $st = $conn->prepare("SELECT v.placa, g.tipo, g.mensagem, g.data_hora
            FROM gps_alertas_operacionais g LEFT JOIN veiculos v ON v.id = g.veiculo_id
            WHERE g.empresa_id = :e AND g.data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY g.data_hora DESC LIMIT 60");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $tit = ($r['placa'] ?: 'Veículo') . ' · ' . ($r['tipo'] ?: 'GPS');
            add($alertas, 'GPS', 'aviso', $tit, $r['mensagem'] ?: 'Alerta de GPS', 'mapa_frota.php', $r['data_hora']);
        }
    } catch (PDOException $e) { /* ignora */ }

    // ---- Implementos: licenciamento / seguro vencendo ----
    try {
        $st = $conn->prepare("SELECT placa, tipo, vencimento_licenciamento, vencimento_seguro,
            DATEDIFF(vencimento_licenciamento, CURDATE()) dl, DATEDIFF(vencimento_seguro, CURDATE()) ds
            FROM implementos WHERE empresa_id = :e AND status <> 'inativo'
              AND ((vencimento_licenciamento IS NOT NULL AND vencimento_licenciamento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                OR (vencimento_seguro IS NOT NULL AND vencimento_seguro <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)))
            ORDER BY placa LIMIT 60");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            if ($r['vencimento_licenciamento'] !== null && (int) $r['dl'] <= 30) {
                $niv = (int) $r['dl'] < 0 ? 'critico' : 'aviso';
                add($alertas, 'Implementos', $niv, $r['placa'] . ' · ' . $r['tipo'], 'Licenciamento ' . ((int) $r['dl'] < 0 ? 'vencido há ' . abs((int) $r['dl']) . 'd' : 'vence em ' . (int) $r['dl'] . 'd'), 'implementos.php', $r['vencimento_licenciamento']);
            }
            if ($r['vencimento_seguro'] !== null && (int) $r['ds'] <= 30) {
                $niv = (int) $r['ds'] < 0 ? 'critico' : 'aviso';
                add($alertas, 'Implementos', $niv, $r['placa'] . ' · ' . $r['tipo'], 'Seguro ' . ((int) $r['ds'] < 0 ? 'vencido há ' . abs((int) $r['ds']) . 'd' : 'vence em ' . (int) $r['ds'] . 'd'), 'implementos.php', $r['vencimento_seguro']);
            }
        }
    } catch (PDOException $e) { /* tabela pode não existir ainda */ }

    // ---- Manutenção: revisão atrasada (km desde a última >= 10.000) ----
    try {
        $st = $conn->prepare("SELECT placa, modelo, (km_atual - km_ultima_manutencao) AS km_desde
            FROM veiculos WHERE empresa_id = :e AND (status_id = 1 OR status_id IS NULL)
              AND km_atual IS NOT NULL AND km_ultima_manutencao IS NOT NULL
              AND (km_atual - km_ultima_manutencao) >= 10000
            ORDER BY km_desde DESC LIMIT 60");
        $st->execute([':e' => $empresa_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            add($alertas, 'Manutenção', 'aviso', $r['placa'] . ($r['modelo'] ? ' · ' . $r['modelo'] : ''),
                number_format((float) $r['km_desde'], 0, ',', '.') . ' km desde a última revisão', 'manutencao_preditiva.php');
        }
    } catch (PDOException $e) { /* ignora */ }

    // Resumo por categoria + nível
    $porCategoria = [];
    $criticos = 0;
    foreach ($alertas as $a) {
        $porCategoria[$a['categoria']] = ($porCategoria[$a['categoria']] ?? 0) + 1;
        if ($a['nivel'] === 'critico') { $criticos++; }
    }

    echo json_encode([
        'success' => true,
        'total' => count($alertas),
        'criticos' => $criticos,
        'por_categoria' => $porCategoria,
        'alertas' => $alertas,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('api/central_alertas.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar alertas.']);
}
