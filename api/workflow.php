<?php
/**
 * API — Workflow de aprovação configurável.
 * Config dos níveis + fila de solicitações que avançam nível a nível. CSRF nas mutações. Por empresa.
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
$usuario = (string) ($_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['user_nome'] ?? $_SESSION['username'] ?? 'Usuário');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function wf_niveis(PDO $conn, int $empresa_id): array {
    $st = $conn->prepare("SELECT id, ordem, nome FROM workflow_niveis WHERE empresa_id = :e ORDER BY ordem, id");
    $st->execute([':e' => $empresa_id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($method === 'POST') {
        api_require_csrf_json();
        if ($action === 'nivel_criar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if ($nome === '') { throw new InvalidArgumentException('Informe o nome do nível.'); }
            $ordem = (int) ($conn->query("SELECT COALESCE(MAX(ordem),0)+1 FROM workflow_niveis WHERE empresa_id={$empresa_id}")->fetchColumn());
            $st = $conn->prepare("INSERT INTO workflow_niveis (empresa_id, ordem, nome) VALUES (:e, :o, :n)");
            $st->execute([':e' => $empresa_id, ':o' => $ordem, ':n' => $nome]);
            echo json_encode(['success' => true]); exit;
        }
        if ($action === 'nivel_excluir') {
            $conn->prepare("DELETE FROM workflow_niveis WHERE id = :id AND empresa_id = :e")->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
            echo json_encode(['success' => true]); exit;
        }
        if ($action === 'solicitar') {
            $titulo = trim((string) ($_POST['titulo'] ?? ''));
            if ($titulo === '') { throw new InvalidArgumentException('Informe o título da solicitação.'); }
            $niveis = wf_niveis($conn, $empresa_id);
            if (!$niveis) { throw new InvalidArgumentException('Configure pelo menos um nível de aprovação antes.'); }
            $primeiro = (int) $niveis[0]['ordem'];
            $valor = ($_POST['valor'] ?? '') !== '' ? (float) str_replace(',', '.', (string) $_POST['valor']) : null;
            $st = $conn->prepare("INSERT INTO workflow_solicitacoes (empresa_id, titulo, tipo, valor, descricao, nivel_atual, solicitante)
                VALUES (:e, :t, :tp, :v, :d, :n, :s)");
            $st->execute([':e' => $empresa_id, ':t' => $titulo, ':tp' => trim((string) ($_POST['tipo'] ?? '')) ?: null,
                ':v' => $valor, ':d' => trim((string) ($_POST['descricao'] ?? '')) ?: null, ':n' => $primeiro, ':s' => $usuario]);
            echo json_encode(['success' => true]); exit;
        }
        if ($action === 'decidir') {
            $id = (int) ($_POST['id'] ?? 0);
            $decisao = ($_POST['decisao'] ?? '') === 'aprovar' ? 'aprovado' : 'rejeitado';
            $coment = trim((string) ($_POST['comentario'] ?? '')) ?: null;
            $st = $conn->prepare("SELECT * FROM workflow_solicitacoes WHERE id = :id AND empresa_id = :e AND status = 'pendente'");
            $st->execute([':id' => $id, ':e' => $empresa_id]);
            $sol = $st->fetch(PDO::FETCH_ASSOC);
            if (!$sol) { throw new InvalidArgumentException('Solicitação não encontrada ou já finalizada.'); }
            $niveis = wf_niveis($conn, $empresa_id);
            $atualOrdem = (int) $sol['nivel_atual'];
            $nivelNome = 'Nível'; $proxOrdem = null;
            foreach ($niveis as $i => $n) {
                if ((int) $n['ordem'] === $atualOrdem) { $nivelNome = $n['nome']; if (isset($niveis[$i + 1])) $proxOrdem = (int) $niveis[$i + 1]['ordem']; }
            }
            $conn->prepare("INSERT INTO workflow_historico (solicitacao_id, empresa_id, nivel_ordem, nivel_nome, decisao, aprovador, comentario)
                VALUES (:s, :e, :o, :nn, :d, :a, :c)")
                ->execute([':s' => $id, ':e' => $empresa_id, ':o' => $atualOrdem, ':nn' => $nivelNome, ':d' => $decisao, ':a' => $usuario, ':c' => $coment]);
            if ($decisao === 'rejeitado') {
                $conn->prepare("UPDATE workflow_solicitacoes SET status = 'rejeitada' WHERE id = :id AND empresa_id = :e")->execute([':id' => $id, ':e' => $empresa_id]);
            } elseif ($proxOrdem !== null) {
                $conn->prepare("UPDATE workflow_solicitacoes SET nivel_atual = :o WHERE id = :id AND empresa_id = :e")->execute([':o' => $proxOrdem, ':id' => $id, ':e' => $empresa_id]);
            } else {
                $conn->prepare("UPDATE workflow_solicitacoes SET status = 'aprovada' WHERE id = :id AND empresa_id = :e")->execute([':id' => $id, ':e' => $empresa_id]);
            }
            echo json_encode(['success' => true]); exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    }

    if ($action === 'config') {
        echo json_encode(['success' => true, 'niveis' => wf_niveis($conn, $empresa_id)], JSON_UNESCAPED_UNICODE); exit;
    }
    if ($action === 'historico') {
        $st = $conn->prepare("SELECT nivel_nome, decisao, aprovador, comentario, data FROM workflow_historico WHERE solicitacao_id = :s AND empresa_id = :e ORDER BY id");
        $st->execute([':s' => (int) ($_GET['id'] ?? 0), ':e' => $empresa_id]);
        echo json_encode(['success' => true, 'historico' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE); exit;
    }

    // list solicitações + nível atual (nome)
    $niveis = wf_niveis($conn, $empresa_id);
    $mapa = []; foreach ($niveis as $n) { $mapa[(int) $n['ordem']] = $n['nome']; }
    $st = $conn->prepare("SELECT id, titulo, tipo, valor, descricao, status, nivel_atual, solicitante, created_at FROM workflow_solicitacoes WHERE empresa_id = :e ORDER BY (status='pendente') DESC, id DESC");
    $st->execute([':e' => $empresa_id]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $pend = $apr = $rej = 0;
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id']; $r['valor'] = $r['valor'] !== null ? (float) $r['valor'] : null;
        $r['nivel_nome'] = $mapa[(int) $r['nivel_atual']] ?? ('Nível ' . $r['nivel_atual']);
        if ($r['status'] === 'pendente') $pend++; elseif ($r['status'] === 'aprovada') $apr++; else $rej++;
    }
    unset($r);
    echo json_encode(['success' => true, 'resumo' => ['pendentes' => $pend, 'aprovadas' => $apr, 'rejeitadas' => $rej], 'niveis' => $niveis, 'solicitacoes' => $rows], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('workflow.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar o workflow.']);
}
