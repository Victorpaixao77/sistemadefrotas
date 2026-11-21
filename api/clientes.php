<?php
/**
 * Clientes / Embarcadores (CRM) — CRUD. Integra com Ordens de Transporte
 * (contagem de OTs por cliente via cnpj_cpf) e serve o select da OT.
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

try {
    $conn = getConnection();
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS clientes (
            id INT AUTO_INCREMENT PRIMARY KEY, empresa_id INT NOT NULL,
            tipo_pessoa ENUM("PJ","PF") NOT NULL DEFAULT "PJ", razao_social VARCHAR(160) NOT NULL,
            nome_fantasia VARCHAR(160) NULL, cnpj_cpf VARCHAR(20) NULL, inscricao_estadual VARCHAR(30) NULL,
            contato_nome VARCHAR(120) NULL, telefone VARCHAR(30) NULL, email VARCHAR(120) NULL,
            endereco VARCHAR(200) NULL, cidade VARCHAR(120) NULL, uf VARCHAR(2) NULL,
            status ENUM("ativo","prospect","inativo") NOT NULL DEFAULT "ativo", observacoes TEXT NULL, criado_por INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_emp_status (empresa_id, status), KEY idx_doc (empresa_id, cnpj_cpf)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ($action === 'options') {
        $st = $conn->prepare('SELECT id, razao_social, nome_fantasia, cnpj_cpf FROM clientes WHERE empresa_id = :e AND status <> "inativo" ORDER BY razao_social LIMIT 1000');
        $st->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'clientes' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'list') {
        $status = (string) ($_GET['status'] ?? '');
        $busca = trim((string) ($_GET['busca'] ?? ''));
        // conta OTs por cliente (integração) via cnpj_cpf
        $hasOT = (int) $conn->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ordens_transporte'")->fetchColumn() > 0;
        $otSel = $hasOT
            ? '(SELECT COUNT(*) FROM ordens_transporte o WHERE o.empresa_id = c.empresa_id AND o.cliente_doc IS NOT NULL AND o.cliente_doc <> "" AND o.cliente_doc = c.cnpj_cpf)'
            : '0';
        $sql = "SELECT c.*, $otSel AS ots FROM clientes c WHERE c.empresa_id = :e";
        $p = [':e' => $empresa_id];
        if (in_array($status, ['ativo', 'prospect', 'inativo'], true)) { $sql .= ' AND c.status = :s'; $p[':s'] = $status; }
        if ($busca !== '') { $sql .= ' AND (c.razao_social LIKE :b OR c.nome_fantasia LIKE :b OR c.cnpj_cpf LIKE :b)'; $p[':b'] = '%' . $busca . '%'; }
        $sql .= ' ORDER BY c.razao_social LIMIT 500';
        $st = $conn->prepare($sql); $st->execute($p);
        echo json_encode(['success' => true, 'clientes' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get') {
        $st = $conn->prepare('SELECT * FROM clientes WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => (int) ($_GET['id'] ?? 0), ':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['success' => true, 'cliente' => $row] : ['success' => false, 'error' => 'Não encontrado.']);
        exit;
    }

    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $razao = trim((string) ($_POST['razao_social'] ?? ''));
        if ($razao === '') { throw new InvalidArgumentException('Informe a razão social / nome.'); }
        $campos = [
            'tipo_pessoa' => ($_POST['tipo_pessoa'] ?? 'PJ') === 'PF' ? 'PF' : 'PJ',
            'razao_social' => $razao,
            'nome_fantasia' => trim((string) ($_POST['nome_fantasia'] ?? '')) ?: null,
            'cnpj_cpf' => trim((string) ($_POST['cnpj_cpf'] ?? '')) ?: null,
            'inscricao_estadual' => trim((string) ($_POST['inscricao_estadual'] ?? '')) ?: null,
            'contato_nome' => trim((string) ($_POST['contato_nome'] ?? '')) ?: null,
            'telefone' => trim((string) ($_POST['telefone'] ?? '')) ?: null,
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'endereco' => trim((string) ($_POST['endereco'] ?? '')) ?: null,
            'cidade' => trim((string) ($_POST['cidade'] ?? '')) ?: null,
            'uf' => strtoupper(substr(trim((string) ($_POST['uf'] ?? '')), 0, 2)) ?: null,
            'status' => in_array($_POST['status'] ?? '', ['ativo', 'prospect', 'inativo'], true) ? $_POST['status'] : 'ativo',
            'observacoes' => trim((string) ($_POST['observacoes'] ?? '')) ?: null,
        ];
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
            $st = $conn->prepare("UPDATE clientes SET $set WHERE id = :id AND empresa_id = :e");
            $st->execute($campos + [':id' => $id, ':e' => $empresa_id]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $cols = array_keys($campos);
            $ph = implode(', ', array_map(fn($k) => ":$k", $cols));
            $st = $conn->prepare('INSERT INTO clientes (empresa_id, criado_por, ' . implode(', ', $cols) . ') VALUES (:e, :cp, ' . $ph . ')');
            $st->execute($campos + [':e' => $empresa_id, ':cp' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()]);
        }
        exit;
    }

    if ($action === 'status') {
        $novo = (string) ($_POST['status'] ?? '');
        if (!in_array($novo, ['ativo', 'prospect', 'inativo'], true)) { throw new InvalidArgumentException('Status inválido.'); }
        $st = $conn->prepare('UPDATE clientes SET status = :s WHERE id = :id AND empresa_id = :e');
        $st->execute([':s' => $novo, ':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $st = $conn->prepare('DELETE FROM clientes WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/clientes.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar o cliente.']);
}
