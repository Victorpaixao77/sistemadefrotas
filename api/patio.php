<?php
/**
 * API — Gestão de Pátio (vagas + veículo alocado). CRUD + alocar/liberar com CSRF. Por empresa.
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

try {
    if ($method === 'POST') {
        api_require_csrf_json();
        if ($action === 'criar') {
            $nome = trim((string) ($_POST['nome'] ?? ''));
            if ($nome === '') { throw new InvalidArgumentException('Informe o nome/número da vaga.'); }
            $setor = trim((string) ($_POST['setor'] ?? '')) ?: null;
            $st = $conn->prepare("INSERT INTO patio_vagas (empresa_id, nome, setor) VALUES (:e, :n, :s)");
            $st->execute([':e' => $empresa_id, ':n' => $nome, ':s' => $setor]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()]);
            exit;
        }
        if ($action === 'excluir') {
            $st = $conn->prepare("DELETE FROM patio_vagas WHERE id = :id AND empresa_id = :e");
            $st->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        if ($action === 'alocar') {
            $vaga = (int) ($_POST['id'] ?? 0);
            $veic = (int) ($_POST['veiculo_id'] ?? 0);
            // valida veículo da empresa
            $chk = $conn->prepare("SELECT id FROM veiculos WHERE id = :v AND empresa_id = :e");
            $chk->execute([':v' => $veic, ':e' => $empresa_id]);
            if (!$chk->fetchColumn()) { throw new InvalidArgumentException('Veículo inválido.'); }
            // libera esse veículo de qualquer outra vaga antes
            $conn->prepare("UPDATE patio_vagas SET veiculo_id = NULL, status = 'livre' WHERE veiculo_id = :v AND empresa_id = :e")
                ->execute([':v' => $veic, ':e' => $empresa_id]);
            $st = $conn->prepare("UPDATE patio_vagas SET veiculo_id = :v, status = 'ocupada' WHERE id = :id AND empresa_id = :e");
            $st->execute([':v' => $veic, ':id' => $vaga, ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        if ($action === 'liberar') {
            $st = $conn->prepare("UPDATE patio_vagas SET veiculo_id = NULL, status = 'livre' WHERE id = :id AND empresa_id = :e");
            $st->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            exit;
        }
        if ($action === 'status') {
            $novo = in_array($_POST['status'] ?? '', ['livre','ocupada','manutencao','reservada'], true) ? $_POST['status'] : 'livre';
            $sql = $novo === 'ocupada' ? "" : "UPDATE patio_vagas SET status = :s" . ($novo === 'livre' ? ', veiculo_id = NULL' : '') . " WHERE id = :id AND empresa_id = :e";
            if ($sql) { $conn->prepare($sql)->execute([':s' => $novo, ':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]); }
            echo json_encode(['success' => true]);
            exit;
        }
        throw new InvalidArgumentException('Ação inválida.');
    }

    if ($action === 'veiculos') {
        $st = $conn->prepare("SELECT id, CONCAT(placa, ' · ', COALESCE(modelo,'')) nome FROM veiculos WHERE empresa_id = :e ORDER BY placa");
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'veiculos' => $st->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = $conn->prepare("SELECT v.id, v.nome, v.setor, v.status, v.veiculo_id, ve.placa, ve.modelo
        FROM patio_vagas v LEFT JOIN veiculos ve ON ve.id = v.veiculo_id
        WHERE v.empresa_id = :e ORDER BY v.setor IS NULL, v.setor, v.nome");
    $st->execute([':e' => $empresa_id]);
    $vagas = $st->fetchAll(PDO::FETCH_ASSOC);
    $resumo = ['total' => count($vagas), 'livre' => 0, 'ocupada' => 0, 'manutencao' => 0, 'reservada' => 0];
    foreach ($vagas as &$v) { $v['id'] = (int) $v['id']; $v['veiculo_id'] = $v['veiculo_id'] !== null ? (int) $v['veiculo_id'] : null; $resumo[$v['status']] = ($resumo[$v['status']] ?? 0) + 1; }
    unset($v);
    echo json_encode(['success' => true, 'resumo' => $resumo, 'vagas' => $vagas], JSON_UNESCAPED_UNICODE);

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('patio.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro ao processar o pátio.']);
}
