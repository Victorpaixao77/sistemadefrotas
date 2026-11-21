<?php
/**
 * API — Contratos (clientes, seguros, locações, financiamentos...).
 * Lista com dias para vencer + resumo; cria/exclui/muda status com CSRF. Por empresa.
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
$TIPOS = ['cliente','fornecedor','seguro','locacao','financiamento','leasing','prestador','outro'];

try {
    if ($method === 'POST') {
        api_require_csrf_json();
        if ($action === 'criar') {
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            if ($titulo === '') { throw new InvalidArgumentException('Informe um título.'); }
            $tipo = in_array($_POST['tipo'] ?? '', $TIPOS, true) ? $_POST['tipo'] : 'outro';
            $parte = trim((string) ($_POST['parte'] ?? '')) ?: null;
            $valor = $_POST['valor'] !== '' && isset($_POST['valor']) ? (float) str_replace(',', '.', (string) $_POST['valor']) : null;
            $ini = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['data_inicio'] ?? '')) ? $_POST['data_inicio'] : null;
            $fim = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_POST['data_fim'] ?? '')) ? $_POST['data_fim'] : null;
            $st = $conn->prepare("INSERT INTO contratos (empresa_id, titulo, tipo, parte, valor, data_inicio, data_fim, observacoes)
                VALUES (:e, :t, :tp, :p, :v, :di, :df, :o)");
            $st->execute([':e' => $empresa_id, ':t' => $titulo, ':tp' => $tipo, ':p' => $parte, ':v' => $valor,
                ':di' => $ini, ':df' => $fim, ':o' => trim((string) ($_POST['observacoes'] ?? '')) ?: null]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()]);
            exit;
        }
        if ($action === 'excluir') {
            $st = $conn->prepare("DELETE FROM contratos WHERE id = :id AND empresa_id = :e");
            $st->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        if ($action === 'status') {
            $novo = in_array($_POST['status'] ?? '', ['ativo','encerrado','cancelado'], true) ? $_POST['status'] : 'ativo';
            $st = $conn->prepare("UPDATE contratos SET status = :s WHERE id = :id AND empresa_id = :e");
            $st->execute([':s' => $novo, ':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    }

    $st = $conn->prepare("SELECT id, titulo, tipo, parte, valor, data_inicio, data_fim, status, observacoes,
            CASE WHEN data_fim IS NOT NULL THEN DATEDIFF(data_fim, CURDATE()) END AS dias
        FROM contratos WHERE empresa_id = :e ORDER BY (data_fim IS NULL), data_fim ASC, id DESC");
    $st->execute([':e' => $empresa_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $ativos = $vencendo = $vencidos = 0; $valorTotal = 0;
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id']; $r['valor'] = $r['valor'] !== null ? (float) $r['valor'] : null;
        $r['dias'] = $r['dias'] !== null ? (int) $r['dias'] : null;
        if ($r['status'] === 'ativo') { $ativos++; if ($r['valor']) $valorTotal += $r['valor']; }
        if ($r['status'] === 'ativo' && $r['dias'] !== null) {
            if ($r['dias'] < 0) $vencidos++;
            elseif ($r['dias'] <= 30) $vencendo++;
        }
    }
    unset($r);
    echo json_encode(['success' => true, 'resumo' => ['ativos' => $ativos, 'vencendo' => $vencendo, 'vencidos' => $vencidos, 'valor_total' => round($valorTotal, 2)], 'contratos' => $rows], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('contratos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar os contratos.']);
}
