<?php
/**
 * API — Metas (por motorista, veículo ou gerais).
 * Lista metas com o realizado calculado do dado real; cria/exclui com CSRF. Por empresa.
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=utf-8');

$conn = getConnection();
$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) { http_response_code(401); echo json_encode(['success' => false, 'message' => 'Não autorizado']); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

/** Calcula o realizado de uma meta a partir das rotas do mês. */
function meta_realizado(PDO $conn, int $empresa_id, array $m): float {
    $ini = $m['periodo_mes'] . '-01 00:00:00';
    $fim = date('Y-m-t 23:59:59', strtotime($ini));
    $params = [':e' => $empresa_id, ':di' => $ini, ':df' => $fim];
    $filtro = '';
    if ($m['tipo_alvo'] === 'motorista' && $m['alvo_id']) { $filtro = ' AND motorista_id = :a '; $params[':a'] = (int) $m['alvo_id']; }
    if ($m['tipo_alvo'] === 'veiculo' && $m['alvo_id']) { $filtro = ' AND veiculo_id = :a '; $params[':a'] = (int) $m['alvo_id']; }
    switch ($m['indicador']) {
        case 'rotas': $sel = 'COUNT(*)'; break;
        case 'km': $sel = 'COALESCE(SUM(distancia_km),0)'; break;
        case 'sla': $sel = 'ROUND(AVG(CASE WHEN no_prazo=1 THEN 100 ELSE 0 END),1)'; break;
        default: $sel = 'COALESCE(SUM(frete),0)'; break;
    }
    $st = $conn->prepare("SELECT {$sel} FROM rotas WHERE empresa_id = :e AND data_rota BETWEEN :di AND :df {$filtro}");
    $st->execute($params);
    return (float) $st->fetchColumn();
}

try {
    if ($method === 'POST') {
        api_require_csrf_json();
        if ($action === 'criar') {
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            $tipo = in_array($_POST['tipo_alvo'] ?? '', ['geral', 'motorista', 'veiculo'], true) ? $_POST['tipo_alvo'] : 'geral';
            $indicador = in_array($_POST['indicador'] ?? '', ['faturamento', 'rotas', 'km', 'sla'], true) ? $_POST['indicador'] : 'faturamento';
            $periodo = preg_match('/^\d{4}-\d{2}$/', (string) ($_POST['periodo_mes'] ?? '')) ? $_POST['periodo_mes'] : date('Y-m');
            $alvo = ($tipo !== 'geral' && !empty($_POST['alvo_id'])) ? (int) $_POST['alvo_id'] : null;
            $valor = (float) str_replace(',', '.', (string) ($_POST['valor_meta'] ?? 0));
            if ($titulo === '') { throw new InvalidArgumentException('Informe um título para a meta.'); }
            if ($valor <= 0) { throw new InvalidArgumentException('Informe um valor de meta maior que zero.'); }
            $st = $conn->prepare("INSERT INTO metas (empresa_id, titulo, tipo_alvo, alvo_id, indicador, periodo_mes, valor_meta, observacoes)
                VALUES (:e, :t, :ta, :a, :i, :p, :v, :o)");
            $st->execute([':e' => $empresa_id, ':t' => $titulo, ':ta' => $tipo, ':a' => $alvo, ':i' => $indicador,
                ':p' => $periodo, ':v' => $valor, ':o' => trim((string) ($_POST['observacoes'] ?? '')) ?: null]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()]);
            exit;
        }
        if ($action === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            $st = $conn->prepare("DELETE FROM metas WHERE id = :id AND empresa_id = :e");
            $st->execute([':id' => $id, ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    }

    if ($action === 'alvos') {
        $tipo = $_GET['tipo'] ?? 'motorista';
        if ($tipo === 'veiculo') {
            $st = $conn->prepare("SELECT id, CONCAT(placa, ' · ', COALESCE(modelo,'')) nome FROM veiculos WHERE empresa_id = :e ORDER BY placa");
        } else {
            $st = $conn->prepare("SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome");
        }
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'alvos' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // list
    $st = $conn->prepare("SELECT m.*,
            CASE m.tipo_alvo
                WHEN 'motorista' THEN (SELECT nome FROM motoristas WHERE id = m.alvo_id)
                WHEN 'veiculo' THEN (SELECT placa FROM veiculos WHERE id = m.alvo_id)
                ELSE 'Geral' END AS alvo_nome
        FROM metas m WHERE m.empresa_id = :e ORDER BY m.periodo_mes DESC, m.id DESC");
    $st->execute([':e' => $empresa_id]);
    $metas = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $real = meta_realizado($conn, $empresa_id, $m);
        $meta = (float) $m['valor_meta'];
        $metas[] = [
            'id' => (int) $m['id'], 'titulo' => $m['titulo'], 'tipo_alvo' => $m['tipo_alvo'],
            'alvo_nome' => $m['alvo_nome'] ?: '—', 'indicador' => $m['indicador'], 'periodo_mes' => $m['periodo_mes'],
            'valor_meta' => $meta, 'realizado' => round($real, 2),
            'pct' => $meta > 0 ? round($real / $meta * 100, 1) : 0,
            'observacoes' => $m['observacoes'],
        ];
    }
    echo json_encode(['success' => true, 'metas' => $metas], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('metas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar as metas.']);
}
