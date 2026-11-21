<?php
/**
 * Admin — fila de perguntas não entendidas e intenções custom do Copiloto.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/adm_auth.php';
require_once SF_PROJECT_ROOT . '/includes/sf_ia_platform.php';
require_once SF_PROJECT_ROOT . '/includes/sf_ia_metrics.php';
require_once SF_PROJECT_ROOT . '/includes/sf_ia_router.php';
require_once SF_PROJECT_ROOT . '/includes/sf_ia_actions.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim((string) ($_GET['action'] ?? $_POST['action'] ?? 'listar_pendentes'));

try {
    sf_ia_metrics_ensure_schema($pdo);

    if ($action === 'listar_pendentes') {
        if (!sf_ia_table_exists($pdo, 'ia_perguntas_pendentes')) {
            echo json_encode(['success' => true, 'data' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $st = $pdo->query("SELECT id, empresa_id, pergunta, ocorrencias, status, intent_mapeada, created_at, updated_at
            FROM ia_perguntas_pendentes WHERE status = 'pendente' ORDER BY ocorrencias DESC, id DESC LIMIT 200");
        echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC) ?: []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'mapear') {
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $intent = preg_replace('/[^a-z_]/', '', (string) ($_POST['intent'] ?? ''));
        // normaliza igual ao classificador (minúsculo + sem acento) para o padrão casar
        $padrao = strtr(mb_strtolower(trim((string) ($_POST['padrao'] ?? '')), 'UTF-8'),
            ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        $empresaId = ($_POST['empresa_id'] ?? '') === '' ? null : (int) $_POST['empresa_id'];
        if ($id < 1 || $intent === '' || $padrao === '') {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $st = $pdo->prepare('SELECT pergunta, empresa_id FROM ia_perguntas_pendentes WHERE id = :id AND status = \'pendente\' LIMIT 1');
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'error' => 'Pergunta não encontrada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $empFinal = $empresaId ?? (int) $row['empresa_id'];
        $pdo->prepare('INSERT INTO ia_intencoes_custom (empresa_id, padrao, intent, criado_por) VALUES (:e, :p, :i, :u)')
            ->execute([':e' => $empFinal > 0 ? $empFinal : null, ':p' => mb_substr($padrao, 0, 255), ':i' => $intent, ':u' => (int) ($_SESSION['admin_id'] ?? 0) ?: null]);
        $pdo->prepare("UPDATE ia_perguntas_pendentes SET status = 'mapeada', intent_mapeada = :i, updated_at = NOW() WHERE id = :id")
            ->execute([':i' => $intent, ':id' => $id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'ignorar') {
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'CSRF inválido.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE ia_perguntas_pendentes SET status = 'ignorada', updated_at = NOW() WHERE id = :id")
            ->execute([':id' => $id]);
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'saude_global') {
        $data = sf_ia_metrics_saude($pdo, null);
        $data['custos_global'] = sf_ia_metrics_custos_global($pdo, 30);
        echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'listar_traces') {
        $emp = (int) ($_GET['empresa_id'] ?? 0);
        echo json_encode(['success' => true, 'data' => sf_ia_support_traces_list($pdo, $emp > 0 ? $emp : null, 80)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'benchmark_admin') {
        require_once SF_PROJECT_ROOT . '/includes/sf_ia_router.php';
        $emp = (int) ($_GET['empresa_id'] ?? $_POST['empresa_id'] ?? 0);
        if ($emp < 1) {
            echo json_encode(['success' => false, 'error' => 'Informe empresa_id.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $modelos = array_filter(array_map('trim', explode(',', (string) ($_GET['modelos'] ?? 'gpt-4o-mini'))));
        echo json_encode(['success' => true, 'data' => sf_ia_router_benchmark_suite_run($pdo, $emp, $modelos ?: ['gpt-4o-mini'])], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ação inválida.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('ia_copilot_ops: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno.'], JSON_UNESCAPED_UNICODE);
}
