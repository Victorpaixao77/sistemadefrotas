<?php
/**
 * Workflow de Aprovações — pendências (OTs em cotação) + decisão registrada (trilha).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=UTF-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Empresa não identificada.']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function apr_ensure(PDO $conn): void {
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS aprovacoes (
            id INT AUTO_INCREMENT PRIMARY KEY, empresa_id INT NOT NULL, entity_type VARCHAR(40) NOT NULL,
            entity_id INT NOT NULL, entity_ref VARCHAR(40) NULL, descricao VARCHAR(255) NULL,
            decisao ENUM("aprovado","rejeitado") NOT NULL, aprovador_id INT NULL, aprovador_nome VARCHAR(120) NULL,
            comentario TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_emp (empresa_id, created_at), KEY idx_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

try {
    $conn = getConnection();
    apr_ensure($conn);

    if ($action === 'pendentes') {
        // Pendências = Ordens de Transporte aguardando aprovação (status cotacao).
        $rows = [];
        try {
            $st = $conn->prepare("SELECT o.id, o.numero, o.cliente_nome, o.origem, o.destino, o.estado_origem, o.estado_destino,
                o.valor_frete, o.created_at, v.placa, m.nome AS motorista
                FROM ordens_transporte o
                LEFT JOIN veiculos v ON v.id = o.veiculo_id
                LEFT JOIN motoristas m ON m.id = o.motorista_id
                WHERE o.empresa_id = :e AND o.status = 'cotacao'
                ORDER BY o.created_at ASC LIMIT 200");
            $st->execute([':e' => $empresa_id]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { /* módulo OT pode não existir */ }
        echo json_encode(['success' => true, 'pendentes' => $rows]);
        exit;
    }

    if ($action === 'historico') {
        $st = $conn->prepare('SELECT * FROM aprovacoes WHERE empresa_id = :e ORDER BY created_at DESC LIMIT 200');
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'historico' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
        exit;
    }

    if ($action === 'decidir') {
        $otId = (int) ($_POST['ot_id'] ?? 0);
        $decisao = ($_POST['decisao'] ?? '') === 'aprovado' ? 'aprovado' : (($_POST['decisao'] ?? '') === 'rejeitado' ? 'rejeitado' : '');
        if (!$decisao) { throw new InvalidArgumentException('Decisão inválida.'); }
        $comentario = trim((string) ($_POST['comentario'] ?? ''));

        $st = $conn->prepare("SELECT numero, cliente_nome, origem, destino, estado_origem, estado_destino, valor_frete, status
            FROM ordens_transporte WHERE id = :id AND empresa_id = :e");
        $st->execute([':id' => $otId, ':e' => $empresa_id]);
        $ot = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ot) { throw new InvalidArgumentException('OT não encontrada.'); }
        if ($ot['status'] !== 'cotacao') { throw new InvalidArgumentException('Esta OT não está mais pendente.'); }

        $novoStatus = $decisao === 'aprovado' ? 'aprovada' : 'cancelada';
        $conn->prepare('UPDATE ordens_transporte SET status = :s WHERE id = :id AND empresa_id = :e')
             ->execute([':s' => $novoStatus, ':id' => $otId, ':e' => $empresa_id]);

        $trecho = ($ot['origem'] ?: $ot['estado_origem']) . ' → ' . ($ot['destino'] ?: $ot['estado_destino']);
        $desc = trim(($ot['cliente_nome'] ? $ot['cliente_nome'] . ' · ' : '') . $trecho . ' · R$ ' . number_format((float) $ot['valor_frete'], 2, ',', '.'));
        $nome = (string) ($_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['user_nome'] ?? 'Usuário');
        $conn->prepare('INSERT INTO aprovacoes (empresa_id, entity_type, entity_id, entity_ref, descricao, decisao, aprovador_id, aprovador_nome, comentario)
            VALUES (:e, "ordem_transporte", :eid, :ref, :desc, :dec, :aid, :anome, :com)')
             ->execute([
                ':e' => $empresa_id, ':eid' => $otId, ':ref' => $ot['numero'], ':desc' => $desc, ':dec' => $decisao,
                ':aid' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null, ':anome' => $nome,
                ':com' => $comentario !== '' ? $comentario : null,
             ]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/aprovacoes.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar a aprovação.']);
}
