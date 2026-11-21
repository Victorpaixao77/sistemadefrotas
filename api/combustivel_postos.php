<?php
/**
 * API — Gestão de Diesel / Ranking de Postos.
 * Analítico dos abastecimentos por posto (preço médio ponderado, litros, gasto,
 * frequência) + evolução do preço por mês. Somente leitura, filtrado por empresa.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';

configure_session();
session_start();
require_authentication();
if (function_exists('session_write_close')) { session_write_close(); }

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

// Período: default últimos 12 meses (mesma convenção do resto do sistema).
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d', strtotime('-12 months'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) { $data_inicio = date('Y-m-d', strtotime('-12 months')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) { $data_fim = date('Y-m-d'); }
$tipo = trim((string) ($_GET['tipo_combustivel'] ?? ''));

try {
    // Cláusula de tipo de combustível montada condicionalmente (evita placeholder repetido
    // com EMULATE_PREPARES=false). Cada consulta recebe seu próprio :tc quando aplicável.
    $tipoSql = $tipo !== '' ? ' AND tipo_combustivel = :tc ' : '';
    $bind = function (PDOStatement $st) use ($empresa_id, $data_inicio, $data_fim, $tipo, $tipoSql): void {
        $st->bindValue(':e', $empresa_id, PDO::PARAM_INT);
        $st->bindValue(':di', $data_inicio . ' 00:00:00');
        $st->bindValue(':df', $data_fim . ' 23:59:59');
        if ($tipoSql !== '') { $st->bindValue(':tc', $tipo); }
    };

    // 1) Resumo (KPIs) — preço médio PONDERADO (gasto/litros), mais fiel que a média simples.
    $sqlResumo = "SELECT
            COUNT(*) AS total_abast,
            COUNT(DISTINCT posto) AS qtd_postos,
            ROUND(SUM(valor_total), 2) AS gasto_total,
            ROUND(SUM(litros), 2) AS litros_total,
            ROUND(SUM(valor_total) / NULLIF(SUM(litros), 0), 3) AS preco_medio
        FROM abastecimentos
        WHERE empresa_id = :e AND data_abastecimento BETWEEN :di AND :df {$tipoSql}";
    $st = $conn->prepare($sqlResumo); $bind($st); $st->execute();
    $resumo = $st->fetch(PDO::FETCH_ASSOC) ?: [];

    // 2) Ranking de postos.
    $sqlRank = "SELECT
            posto,
            COUNT(*) AS n_abast,
            ROUND(SUM(litros), 2) AS litros,
            ROUND(SUM(valor_total), 2) AS total,
            ROUND(SUM(valor_total) / NULLIF(SUM(litros), 0), 3) AS preco_medio
        FROM abastecimentos
        WHERE empresa_id = :e AND data_abastecimento BETWEEN :di AND :df
          AND posto IS NOT NULL AND posto <> '' {$tipoSql}
        GROUP BY posto
        ORDER BY total DESC";
    $st = $conn->prepare($sqlRank); $bind($st); $st->execute();
    $ranking = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 3) Evolução do preço médio por mês.
    $sqlEvol = "SELECT DATE_FORMAT(data_abastecimento, '%Y-%m') AS ym,
            ROUND(SUM(valor_total) / NULLIF(SUM(litros), 0), 3) AS preco
        FROM abastecimentos
        WHERE empresa_id = :e AND data_abastecimento BETWEEN :di AND :df {$tipoSql}
        GROUP BY ym ORDER BY ym";
    $st = $conn->prepare($sqlEvol); $bind($st); $st->execute();
    $evol = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // 4) Tipos de combustível disponíveis (para o filtro).
    $st = $conn->prepare("SELECT DISTINCT tipo_combustivel FROM abastecimentos
        WHERE empresa_id = :e AND tipo_combustivel IS NOT NULL AND tipo_combustivel <> '' ORDER BY 1");
    $st->bindValue(':e', $empresa_id, PDO::PARAM_INT); $st->execute();
    $tipos = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'tipo_combustivel');

    // Destaques calculados no PHP (com piso mínimo de volume para "mais barato" não pegar outlier).
    $gasto = (float) ($resumo['gasto_total'] ?? 0);
    $litros_min = 0;
    foreach ($ranking as $r) { $litros_min += (float) $r['litros']; }
    $piso = $litros_min > 0 ? ($litros_min / max(count($ranking), 1)) * 0.15 : 0; // 15% da média
    $mais_usado = null; $mais_barato = null; $maior_gasto = null;
    foreach ($ranking as $r) {
        $r['pct_gasto'] = $gasto > 0 ? round(((float) $r['total'] / $gasto) * 100, 1) : 0;
        if ($mais_usado === null || (int) $r['n_abast'] > (int) $mais_usado['n_abast']) { $mais_usado = $r; }
        if ($maior_gasto === null || (float) $r['total'] > (float) $maior_gasto['total']) { $maior_gasto = $r; }
        if ((float) $r['litros'] >= $piso && (float) $r['preco_medio'] > 0) {
            if ($mais_barato === null || (float) $r['preco_medio'] < (float) $mais_barato['preco_medio']) { $mais_barato = $r; }
        }
    }
    // injeta pct_gasto na lista final
    foreach ($ranking as &$r) { $r['pct_gasto'] = $gasto > 0 ? round(((float) $r['total'] / $gasto) * 100, 1) : 0; }
    unset($r);

    echo json_encode([
        'success' => true,
        'periodo' => ['inicio' => $data_inicio, 'fim' => $data_fim, 'tipo' => $tipo],
        'resumo' => [
            'total_abast' => (int) ($resumo['total_abast'] ?? 0),
            'qtd_postos' => (int) ($resumo['qtd_postos'] ?? 0),
            'gasto_total' => (float) ($resumo['gasto_total'] ?? 0),
            'litros_total' => (float) ($resumo['litros_total'] ?? 0),
            'preco_medio' => (float) ($resumo['preco_medio'] ?? 0),
        ],
        'destaques' => [
            'mais_usado' => $mais_usado,
            'mais_barato' => $mais_barato,
            'maior_gasto' => $maior_gasto,
        ],
        'ranking' => $ranking,
        'evolucao' => $evol,
        'tipos' => $tipos,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('combustivel_postos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao carregar dados de postos.']);
}
