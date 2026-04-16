<?php
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/upload_comprovante.php';
require_once '../includes/api_json.php';

// Configurar log de erros
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../logs/php_errors.log');

// Garantir que a saída será sempre JSON
header('Content-Type: application/json; charset=utf-8');

// Configurar sessão
configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    api_json_unauthorized();
}

// Obter conexão com o banco de dados
$conn = getConnection();

/** @return bool */
function cp_table_has_column(PDO $conn, string $table, string $column): bool {
    $db = (string) $conn->query('SELECT DATABASE()')->fetchColumn();
    $st = $conn->prepare(
        'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$db, $table, $column]);
    return (int) $st->fetchColumn() > 0;
}

/**
 * Resolve nome do fornecedor e id opcional (cadastro).
 * @return array{0: string, 1: ?int} [nome, fornecedor_id ou null]
 */
function cp_resolve_fornecedor(PDO $conn, int $empresa_id): array {
    $sem = isset($_POST['sem_fornecedor']) && (string) $_POST['sem_fornecedor'] === '1';
    if ($sem) {
        return ['Sem fornecedor', null];
    }
    $fid = isset($_POST['fornecedor_id']) ? (int) $_POST['fornecedor_id'] : 0;
    $nomeHidden = trim((string) ($_POST['fornecedor'] ?? ''));
    $nomeSearch = trim((string) ($_POST['fornecedor_search'] ?? ''));
    if ($fid > 0) {
        $st = $conn->prepare('SELECT id, nome FROM fornecedores WHERE id = ? AND empresa_id = ? LIMIT 1');
        $st->execute([$fid, $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [$row['nome'], (int) $row['id']];
        }
        $fid = 0;
    }
    $nome = $nomeHidden !== '' ? $nomeHidden : $nomeSearch;
    if ($nome === '') {
        throw new Exception('Informe um fornecedor (busca no cadastro) ou marque "Sem fornecedor".');
    }
    return [$nome, null];
}

// Obter ação
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            api_require_csrf_json();
            // Validar dados (fornecedor é opcional se "sem fornecedor" ou preenchido via busca)
            $required_fields = ['descricao', 'valor', 'data_vencimento', 'status_id'];
            
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === null) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            $recibo_arquivo = null;
            if (isset($_FILES['recibo_arquivo']) && $_FILES['recibo_arquivo']['error'] === UPLOAD_ERR_OK) {
                $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                $uploadAbs = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recibos';
                $recibo_arquivo = sf_save_comprovante_upload($_FILES['recibo_arquivo'], $uploadAbs, 'uploads/recibos', true);
            }
            
            $empresa_id = (int) $_SESSION['empresa_id'];
            [$fornecedor_nome, $fornecedor_id] = cp_resolve_fornecedor($conn, $empresa_id);
            $has_fid = cp_table_has_column($conn, 'contas_pagar', 'fornecedor_id');

            // Preparar SQL
            $cols = 'fornecedor, descricao, valor, data_vencimento, data_pagamento, status_id, forma_pagamento_id, banco_id, observacoes, empresa_id, recibo_arquivo';
            $placeholders = '?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?';
            $params = [
                $fornecedor_nome,
                $_POST['descricao'],
                $_POST['valor'],
                $_POST['data_vencimento'],
                !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                $_POST['status_id'],
                !empty($_POST['forma_pagamento_id']) ? $_POST['forma_pagamento_id'] : null,
                !empty($_POST['banco_id']) ? $_POST['banco_id'] : null,
                !empty($_POST['observacoes']) ? $_POST['observacoes'] : null,
                $empresa_id,
                $recibo_arquivo,
            ];
            if ($has_fid) {
                $cols .= ', fornecedor_id';
                $placeholders .= ', ?';
                $params[] = $fornecedor_id;
            }

            $sql = "INSERT INTO contas_pagar ($cols) VALUES ($placeholders)";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Conta adicionada com sucesso']);
            } else {
                throw new Exception("Erro ao adicionar conta");
            }
            break;
            
        case 'update':
            api_require_csrf_json();
            // Validar dados
            $required_fields = ['id', 'descricao', 'valor', 'data_vencimento', 'status_id'];
            
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || $_POST[$field] === '' || $_POST[$field] === null) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            
            $recibo_arquivo = null;
            $upload_dir = (realpath(__DIR__ . '/..') ?: (__DIR__ . '/..')) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recibos' . DIRECTORY_SEPARATOR;
            if (isset($_FILES['recibo_arquivo']) && $_FILES['recibo_arquivo']['error'] === UPLOAD_ERR_OK) {
                $root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                $uploadAbs = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recibos';
                $recibo_arquivo = sf_save_comprovante_upload($_FILES['recibo_arquivo'], $uploadAbs, 'uploads/recibos', true);

                $sql_old = "SELECT recibo_arquivo FROM contas_pagar WHERE id = ? AND empresa_id = ?";
                $stmt_old = $conn->prepare($sql_old);
                $stmt_old->execute([$_POST['id'], $_SESSION['empresa_id']]);
                $old_recibo = $stmt_old->fetch(PDO::FETCH_ASSOC);

                if ($old_recibo && $old_recibo['recibo_arquivo']) {
                    $old_path = $upload_dir . $old_recibo['recibo_arquivo'];
                    if (is_file($old_path)) {
                        @unlink($old_path);
                    }
                }
            }
            
            $empresa_id = (int) $_SESSION['empresa_id'];
            [$fornecedor_nome, $fornecedor_id] = cp_resolve_fornecedor($conn, $empresa_id);
            $has_fid = cp_table_has_column($conn, 'contas_pagar', 'fornecedor_id');

            // Preparar SQL
            $set = 'fornecedor = ?,
                descricao = ?,
                valor = ?,
                data_vencimento = ?,
                data_pagamento = ?,
                status_id = ?,
                forma_pagamento_id = ?,
                banco_id = ?,
                observacoes = ?';
            $params = [
                $fornecedor_nome,
                $_POST['descricao'],
                $_POST['valor'],
                $_POST['data_vencimento'],
                !empty($_POST['data_pagamento']) ? $_POST['data_pagamento'] : null,
                $_POST['status_id'],
                !empty($_POST['forma_pagamento_id']) ? $_POST['forma_pagamento_id'] : null,
                !empty($_POST['banco_id']) ? $_POST['banco_id'] : null,
                !empty($_POST['observacoes']) ? $_POST['observacoes'] : null,
            ];
            if ($has_fid) {
                $set .= ', fornecedor_id = ?';
                $params[] = $fornecedor_id;
            }
            $sql = "UPDATE contas_pagar SET $set" .
                ($recibo_arquivo ? ", recibo_arquivo = ?" : "") . "
            WHERE id = ? AND empresa_id = ?";
            
            if ($recibo_arquivo) {
                $params[] = $recibo_arquivo;
            }
            
            $params[] = $_POST['id'];
            $params[] = $empresa_id;
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Conta atualizada com sucesso']);
            } else {
                throw new Exception("Erro ao atualizar conta");
            }
            break;
            
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                api_json_method_not_allowed('Exclusão exige POST com id e token CSRF.');
            }
            api_require_csrf_json();
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID não fornecido');
            }

            $upload_dir = (realpath(__DIR__ . '/..') ?: (__DIR__ . '/..')) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'recibos' . DIRECTORY_SEPARATOR;

            $sql_recibo = 'SELECT recibo_arquivo FROM contas_pagar WHERE id = ? AND empresa_id = ?';
            $stmt_recibo = $conn->prepare($sql_recibo);
            $stmt_recibo->execute([$id, $_SESSION['empresa_id']]);
            $recibo = $stmt_recibo->fetch(PDO::FETCH_ASSOC);

            if (!$recibo) {
                throw new Exception('Conta não encontrada ou sem permissão.');
            }

            if (!empty($recibo['recibo_arquivo'])) {
                $recibo_path = $upload_dir . $recibo['recibo_arquivo'];
                if (is_file($recibo_path)) {
                    @unlink($recibo_path);
                }
            }

            $sql = 'DELETE FROM contas_pagar WHERE id = ? AND empresa_id = ?';
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id, $_SESSION['empresa_id']]);

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Conta excluída com sucesso']);
            } else {
                throw new Exception('Erro ao excluir conta');
            }
            break;
            
        case 'get':
            // Validar ID
            $id = $_GET['id'] ?? 0;
            if (empty($id)) {
                throw new Exception("ID não fornecido");
            }
            
            // Buscar conta
            $sql = "SELECT cp.*, 
                s.nome as status_nome,
                fp.nome as forma_pagamento_nome,
                b.nome as banco_nome
            FROM contas_pagar cp
            LEFT JOIN status_contas_pagar s ON cp.status_id = s.id
            LEFT JOIN formas_pagamento fp ON cp.forma_pagamento_id = fp.id
            LEFT JOIN bancos b ON cp.banco_id = b.id
            WHERE cp.id = ? AND cp.empresa_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$id, $_SESSION['empresa_id']]);
            $conta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($conta) {
                echo json_encode(['success' => true, 'data' => $conta]);
            } else {
                throw new Exception("Conta não encontrada");
            }
            break;
            
        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    sf_log_debug('contas_pagar_actions: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => 'validation',
    ], JSON_UNESCAPED_UNICODE);
    exit;
} 