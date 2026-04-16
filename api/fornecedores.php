<?php
/**
 * CRUD de fornecedores por empresa (empresa_clientes).
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/doc_validators.php';
require_once __DIR__ . '/../includes/csrf.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

$conn = getConnection();
$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
if ($empresa_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Empresa não definida na sessão']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function only_digits(?string $s): string {
    return preg_replace('/\D/', '', (string)$s);
}

function null_if_empty($v) {
    if ($v === null || $v === '') {
        return null;
    }
    if (is_string($v) && trim($v) === '') {
        return null;
    }
    return $v;
}

try {
    switch ($action) {
        case 'list':
            listFornecedores($conn, $empresa_id);
            break;
        case 'csrf_token':
            echo json_encode(['success' => true, 'csrf_token' => csrf_token_get()]);
            break;
        case 'get':
            getFornecedor($conn, $empresa_id);
            break;
        case 'create':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                break;
            }
            api_require_csrf_json();
            createFornecedor($conn, $empresa_id);
            break;
        case 'update':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                break;
            }
            api_require_csrf_json();
            updateFornecedor($conn, $empresa_id);
            break;
        case 'delete':
            if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
                echo json_encode(['success' => false, 'message' => 'Método não permitido']);
                break;
            }
            api_require_csrf_json();
            deleteFornecedor($conn, $empresa_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Ação não especificada']);
    }
} catch (Throwable $e) {
    error_log('fornecedores.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}

/**
 * @return array{whereSql: string, params: array<string, mixed>}
 */
function fornecedores_build_list_filter(int $empresa_id, string $situacao, ?string $tipo, ?string $qRaw): array {
    $params = [':eid' => $empresa_id];
    $sql = ' FROM fornecedores WHERE empresa_id = :eid';
    if ($situacao === 'A' || $situacao === 'I') {
        $sql .= ' AND situacao = :sit';
        $params[':sit'] = $situacao;
    } elseif ($situacao !== 'all') {
        $sql .= " AND situacao = 'A'";
    }
    if ($tipo === 'F' || $tipo === 'J') {
        $sql .= ' AND tipo = :tipo_f';
        $params[':tipo_f'] = $tipo;
    }
    $qRaw = trim((string)$qRaw);
    if ($qRaw !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $qRaw) . '%';
        $params[':q_like'] = $like;
        $qd = only_digits($qRaw);
        $docClause = '';
        if (strlen($qd) >= 1) {
            $params[':q_doc'] = '%' . $qd . '%';
            $docClause = ' OR REPLACE(REPLACE(REPLACE(COALESCE(cpf, \'\'), \'.\', \'\'), \'-\', \'\'), \' \', \'\') LIKE :q_doc
                OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj, \'\'), \'.\', \'\'), \'/\', \'\'), \'-\', \'\'), \' \', \'\') LIKE :q_doc';
        }
        $sql .= ' AND (
            nome LIKE :q_like OR COALESCE(cidade, \'\') LIKE :q_like OR COALESCE(uf, \'\') LIKE :q_like
            OR COALESCE(email, \'\') LIKE :q_like OR COALESCE(bairro, \'\') LIKE :q_like OR COALESCE(endereco, \'\') LIKE :q_like
            ' . $docClause . '
        )';
    }
    return ['whereSql' => $sql, 'params' => $params];
}

function listFornecedores(PDO $conn, int $empresa_id): void {
    $situacao = $_GET['situacao'] ?? 'A';
    if (!in_array($situacao, ['A', 'I', 'all'], true)) {
        $situacao = 'A';
    }
    $tipo = $_GET['tipo'] ?? '';
    $tipo = in_array($tipo, ['F', 'J'], true) ? $tipo : '';
    $qRaw = isset($_GET['q']) ? (string)$_GET['q'] : '';

    $filter = fornecedores_build_list_filter($empresa_id, $situacao, $tipo ?: null, $qRaw);
    $whereSql = $filter['whereSql'];
    $params = $filter['params'];

    $countStmt = $conn->prepare('SELECT COUNT(*) ' . $whereSql);
    foreach ($params as $k => $v) {
        $countStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int)$countStmt->fetchColumn();

    $kpiSql = 'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN situacao = \'A\' THEN 1 ELSE 0 END) AS ativos,
        SUM(CASE WHEN situacao = \'I\' THEN 1 ELSE 0 END) AS inativos,
        SUM(CASE WHEN tipo = \'J\' THEN 1 ELSE 0 END) AS pj,
        SUM(CASE WHEN tipo = \'F\' THEN 1 ELSE 0 END) AS pf,
        SUM(CASE WHEN LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(codigo_municipio_ibge, \'\'), \' \', \'\'), \'.\', \'\'), \'-\', \'\'), \'/\', \'\')) = 7 THEN 1 ELSE 0 END) AS ibge_ok,
        SUM(CASE WHEN email IS NOT NULL AND TRIM(email) <> \'\' THEN 1 ELSE 0 END) AS email_ok
        ' . $whereSql;
    $kpiStmt = $conn->prepare($kpiSql);
    foreach ($params as $k => $v) {
        $kpiStmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $kpiStmt->execute();
    $kpi = $kpiStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $all = isset($_GET['all']) && (string)$_GET['all'] === '1';
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 10);
    if (!in_array($perPage, [5, 10, 25, 50, 100], true)) {
        $perPage = 10;
    }
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $offset = ($page - 1) * $perPage;

    $sortKey = isset($_GET['sort']) ? trim((string) $_GET['sort']) : 'nome';
    $allowedSort = [
        'nome' => 'nome',
        'tipo' => 'tipo',
        'documento' => '(CASE WHEN tipo = \'J\' THEN COALESCE(cnpj, \'\') ELSE COALESCE(cpf, \'\') END)',
        'cidade_uf' => 'CONCAT(COALESCE(cidade, \'\'), COALESCE(uf, \'\'))',
        'situacao' => 'situacao',
    ];
    $orderCol = $allowedSort[$sortKey] ?? 'nome';
    $dir = (isset($_GET['dir']) && strtoupper(trim((string) $_GET['dir'])) === 'DESC') ? 'DESC' : 'ASC';

    $sqlList = 'SELECT * ' . $whereSql . ' ORDER BY ' . $orderCol . ' ' . $dir . ', id ASC';
    if (!$all) {
        $sqlList .= ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;
    } else {
        $maxAll = 5000;
        $sqlList .= ' LIMIT ' . $maxAll;
    }

    $stmt = $conn->prepare($sqlList);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [
        'success' => true,
        'fornecedores' => $rows,
        'kpi' => [
            'total' => (int)($kpi['total'] ?? 0),
            'ativos' => (int)($kpi['ativos'] ?? 0),
            'inativos' => (int)($kpi['inativos'] ?? 0),
            'pj' => (int)($kpi['pj'] ?? 0),
            'pf' => (int)($kpi['pf'] ?? 0),
            'ibge_ok' => (int)($kpi['ibge_ok'] ?? 0),
            'email_ok' => (int)($kpi['email_ok'] ?? 0),
        ],
    ];
    if (!$all) {
        $out['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => max(1, $totalPages),
        ];
    }
    echo json_encode($out);
}

function getFornecedor(PDO $conn, int $empresa_id): void {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    $stmt = $conn->prepare('SELECT * FROM fornecedores WHERE id = :id AND empresa_id = :eid LIMIT 1');
    $stmt->execute([':id' => $id, ':eid' => $empresa_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Fornecedor não encontrado']);
        return;
    }
    echo json_encode(['success' => true, 'fornecedor' => $row]);
}

function validateFornecedorInput(array $p, bool $isCreate): array {
    $tipo = $p['tipo'] ?? '';
    if (!in_array($tipo, ['F', 'J'], true)) {
        return ['ok' => false, 'msg' => 'Tipo inválido (use F ou J).'];
    }
    $nome = trim((string)($p['nome'] ?? ''));
    if ($nome === '') {
        return ['ok' => false, 'msg' => 'Nome / razão social é obrigatório.'];
    }
    $cpf = only_digits($p['cpf'] ?? '');
    $cnpj = only_digits($p['cnpj'] ?? '');
    if ($tipo === 'F') {
        if (strlen($cpf) !== 11) {
            return ['ok' => false, 'msg' => 'CPF deve ter 11 dígitos para pessoa física.'];
        }
        if (!doc_validar_cpf($cpf)) {
            return ['ok' => false, 'msg' => 'CPF inválido (dígitos verificadores incorretos).'];
        }
    }
    if ($tipo === 'J') {
        if (strlen($cnpj) !== 14) {
            return ['ok' => false, 'msg' => 'CNPJ deve ter 14 dígitos para pessoa jurídica.'];
        }
        if (!doc_validar_cnpj($cnpj)) {
            return ['ok' => false, 'msg' => 'CNPJ inválido (dígitos verificadores incorretos).'];
        }
    }
    return ['ok' => true, 'tipo' => $tipo, 'nome' => $nome, 'cpf' => $cpf ?: null, 'cnpj' => $cnpj ?: null];
}

function createFornecedor(PDO $conn, int $empresa_id): void {
    $v = validateFornecedorInput($_POST, true);
    if (!$v['ok']) {
        echo json_encode(['success' => false, 'message' => $v['msg']]);
        return;
    }
    // Unicidade por empresa
    if ($v['cnpj']) {
        $st = $conn->prepare('SELECT id FROM fornecedores WHERE empresa_id = :e AND cnpj = :c LIMIT 1');
        $st->execute([':e' => $empresa_id, ':c' => $v['cnpj']]);
        if ($st->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe fornecedor com este CNPJ.']);
            return;
        }
    }
    if ($v['cpf']) {
        $st = $conn->prepare('SELECT id FROM fornecedores WHERE empresa_id = :e AND cpf = :c LIMIT 1');
        $st->execute([':e' => $empresa_id, ':c' => $v['cpf']]);
        if ($st->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe fornecedor com este CPF.']);
            return;
        }
    }

    $sql = "INSERT INTO fornecedores (
        empresa_id, tipo, nome, cpf, cnpj, inscricao_estadual, inscricao_municipal, regime_tributario,
        telefone, email, site, endereco, numero, complemento, bairro, cidade, uf, cep, codigo_municipio_ibge, pais,
        tipo_fornecedor, limite_credito, prazo_pagamento, taxa_multa, taxa_juros, situacao, observacoes
    ) VALUES (
        :empresa_id, :tipo, :nome, :cpf, :cnpj, :ie, :im, :regime,
        :telefone, :email, :site, :endereco, :numero, :complemento, :bairro, :cidade, :uf, :cep, :cMun, :pais,
        :tipo_fornecedor, :limite_credito, :prazo_pagamento, :taxa_multa, :taxa_juros, :situacao, :observacoes
    )";

    $stmt = $conn->prepare($sql);
    $bind = bindCommonFornecedor($empresa_id, $v);
    if ($stmt->execute($bind)) {
        echo json_encode(['success' => true, 'message' => 'Fornecedor cadastrado.', 'id' => (int)$conn->lastInsertId()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao inserir.']);
    }
}

function bindCommonFornecedor(int $empresa_id, array $v): array {
    $sit = $_POST['situacao'] ?? 'A';
    if (!in_array($sit, ['A', 'I'], true)) {
        $sit = 'A';
    }
    return [
        ':empresa_id' => $empresa_id,
        ':tipo' => $v['tipo'],
        ':nome' => $v['nome'],
        ':cpf' => $v['cpf'],
        ':cnpj' => $v['cnpj'],
        ':ie' => null_if_empty($_POST['inscricao_estadual'] ?? null),
        ':im' => null_if_empty($_POST['inscricao_municipal'] ?? null),
        ':regime' => null_if_empty($_POST['regime_tributario'] ?? null),
        ':telefone' => null_if_empty($_POST['telefone'] ?? null),
        ':email' => null_if_empty($_POST['email'] ?? null),
        ':site' => null_if_empty($_POST['site'] ?? null),
        ':endereco' => null_if_empty($_POST['endereco'] ?? null),
        ':numero' => null_if_empty($_POST['numero'] ?? null),
        ':complemento' => null_if_empty($_POST['complemento'] ?? null),
        ':bairro' => null_if_empty($_POST['bairro'] ?? null),
        ':cidade' => null_if_empty($_POST['cidade'] ?? null),
        ':uf' => null_if_empty(strtoupper((string)($_POST['uf'] ?? ''))) ?: null,
        ':cep' => only_digits($_POST['cep'] ?? '') ?: null,
        ':cMun' => only_digits($_POST['codigo_municipio_ibge'] ?? '') ?: null,
        ':pais' => null_if_empty($_POST['pais'] ?? null) ?: 'Brasil',
        ':tipo_fornecedor' => null_if_empty($_POST['tipo_fornecedor'] ?? null),
        ':limite_credito' => (float)str_replace(',', '.', (string)($_POST['limite_credito'] ?? '0')),
        ':prazo_pagamento' => (int)($_POST['prazo_pagamento'] ?? 0),
        ':taxa_multa' => (float)str_replace(',', '.', (string)($_POST['taxa_multa'] ?? '0')),
        ':taxa_juros' => (float)str_replace(',', '.', (string)($_POST['taxa_juros'] ?? '0')),
        ':situacao' => $sit,
        ':observacoes' => null_if_empty($_POST['observacoes'] ?? null),
    ];
}

function updateFornecedor(PDO $conn, int $empresa_id): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    $v = validateFornecedorInput($_POST, false);
    if (!$v['ok']) {
        echo json_encode(['success' => false, 'message' => $v['msg']]);
        return;
    }
    if ($v['cnpj']) {
        $st = $conn->prepare('SELECT id FROM fornecedores WHERE empresa_id = :e AND cnpj = :c AND id <> :id LIMIT 1');
        $st->execute([':e' => $empresa_id, ':c' => $v['cnpj'], ':id' => $id]);
        if ($st->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe outro fornecedor com este CNPJ.']);
            return;
        }
    }
    if ($v['cpf']) {
        $st = $conn->prepare('SELECT id FROM fornecedores WHERE empresa_id = :e AND cpf = :c AND id <> :id LIMIT 1');
        $st->execute([':e' => $empresa_id, ':c' => $v['cpf'], ':id' => $id]);
        if ($st->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Já existe outro fornecedor com este CPF.']);
            return;
        }
    }

    $sql = "UPDATE fornecedores SET
        tipo = :tipo, nome = :nome, cpf = :cpf, cnpj = :cnpj,
        inscricao_estadual = :ie, inscricao_municipal = :im, regime_tributario = :regime,
        telefone = :telefone, email = :email, site = :site,
        endereco = :endereco, numero = :numero, complemento = :complemento, bairro = :bairro,
        cidade = :cidade, uf = :uf, cep = :cep, codigo_municipio_ibge = :cMun, pais = :pais,
        tipo_fornecedor = :tipo_fornecedor, limite_credito = :limite_credito, prazo_pagamento = :prazo_pagamento,
        taxa_multa = :taxa_multa, taxa_juros = :taxa_juros, situacao = :situacao, observacoes = :observacoes
        WHERE id = :id AND empresa_id = :empresa_id";

    $stmt = $conn->prepare($sql);
    $bind = bindCommonFornecedor($empresa_id, $v);
    $bind[':id'] = $id;
    if ($stmt->execute($bind)) {
        echo json_encode(['success' => true, 'message' => 'Fornecedor atualizado.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Falha ao atualizar.']);
    }
}

function deleteFornecedor(PDO $conn, int $empresa_id): void {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        return;
    }
    $stmt = $conn->prepare("UPDATE fornecedores SET situacao = 'I' WHERE id = :id AND empresa_id = :eid");
    $stmt->execute([':id' => $id, ':eid' => $empresa_id]);
    echo json_encode(['success' => true, 'message' => 'Fornecedor inativado.']);
}
