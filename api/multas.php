<?php
// Corrigir caminhos dos includes
$base_path = dirname(__DIR__);
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';
require_once $base_path . '/includes/db_connect.php';

configure_session();
session_start();

// Verificar se o usuário está autenticado ou se empresa_id foi fornecido
$empresa_id = null;
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['empresa_id'])) {
    $empresa_id = $_SESSION['empresa_id'];
} elseif (isset($_GET['empresa_id']) && is_numeric($_GET['empresa_id'])) {
    $empresa_id = (int)$_GET['empresa_id'];
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$conn = getConnection();
header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            createMulta($conn);
            break;
        case 'update':
            updateMulta($conn);
            break;
        case 'delete':
            deleteMulta($conn);
            break;
        case 'get':
            getMulta($conn);
            break;
        case 'list':
            listMultas($conn);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro na API de multas: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor: ' . $e->getMessage()]);
}

function handleComprovanteUpload($field = 'comprovante') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $upload_dir = dirname(__DIR__) . '/uploads/comprovantes/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $dest = $upload_dir . $filename;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) {
        return $filename;
    }
    return null;
}

function createMulta($conn) {
    error_log("=== INICIANDO CRIAÇÃO DE MULTA ===");
    error_log("POST data: " . print_r($_POST, true));
    error_log("FILES data: " . print_r($_FILES, true));
    
    $empresa_id = $_SESSION['empresa_id'];
    error_log("Empresa ID: " . $empresa_id);
    
    $required_fields = ['data_infracao', 'veiculo_id', 'motorista_id', 'tipo_infracao', 'valor', 'status_pagamento'];
    foreach ($required_fields as $field) {
        error_log("Verificando campo: $field = " . ($_POST[$field] ?? 'NULL'));
        if (empty($_POST[$field])) {
            error_log("❌ Campo obrigatório faltando: $field");
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo $field é obrigatório"]);
            return;
        }
    }
    $comprovante = handleComprovanteUpload();
    $veiculo_id = $_POST['veiculo_id'];
    $motorista_id = $_POST['motorista_id'];
    $rota_id = $_POST['rota_id'] ?: null;
    $data_infracao = $_POST['data_infracao'];
    $tipo_infracao = $_POST['tipo_infracao'];
    $descricao = $_POST['descricao'] ?: null;
    $pontos = $_POST['pontos'] ?: 0;
    $valor = $_POST['valor'];
    $status_pagamento = $_POST['status_pagamento'];
    $vencimento = $_POST['vencimento'] ?: null;
    $data_pagamento = $_POST['data_pagamento'] ?: null;
    $sql = "INSERT INTO multas (
        empresa_id, veiculo_id, motorista_id, rota_id, data_infracao, 
        tipo_infracao, descricao, pontos, valor, status_pagamento, 
        vencimento, data_pagamento, comprovante
    ) VALUES (
        :empresa_id, :veiculo_id, :motorista_id, :rota_id, :data_infracao,
        :tipo_infracao, :descricao, :pontos, :valor, :status_pagamento,
        :vencimento, :data_pagamento, :comprovante
    )";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':veiculo_id', $veiculo_id);
    $stmt->bindParam(':motorista_id', $motorista_id);
    $stmt->bindParam(':rota_id', $rota_id);
    $stmt->bindParam(':data_infracao', $data_infracao);
    $stmt->bindParam(':tipo_infracao', $tipo_infracao);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':pontos', $pontos);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':status_pagamento', $status_pagamento);
    $stmt->bindParam(':vencimento', $vencimento);
    $stmt->bindParam(':data_pagamento', $data_pagamento);
    $stmt->bindParam(':comprovante', $comprovante);
    error_log("Executando SQL INSERT...");
    if ($stmt->execute()) {
        $multa_id = $conn->lastInsertId();
        error_log("✅ Multa criada com sucesso. ID: " . $multa_id);
        echo json_encode(['success' => true, 'message' => 'Multa cadastrada com sucesso', 'id' => $multa_id]);
    } else {
        $error_info = $stmt->errorInfo();
        error_log("❌ Erro ao executar SQL: " . print_r($error_info, true));
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar multa: ' . $error_info[2]]);
    }
}

function updateMulta($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    $multa_id = $_POST['id'] ?? null;
    if (!$multa_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da multa é obrigatório']);
        return;
    }
    $check_sql = "SELECT id, comprovante FROM multas WHERE id = :id AND empresa_id = :empresa_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $multa_id);
    $check_stmt->bindParam(':empresa_id', $empresa_id);
    $check_stmt->execute();
    $row = $check_stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Multa não encontrada']);
        return;
    }
    $comprovante = $row['comprovante'];
    $novo_comprovante = handleComprovanteUpload();
    if ($novo_comprovante) {
        $comprovante = $novo_comprovante;
    }
    $veiculo_id = $_POST['veiculo_id'];
    $motorista_id = $_POST['motorista_id'];
    $rota_id = $_POST['rota_id'] ?: null;
    $data_infracao = $_POST['data_infracao'];
    $tipo_infracao = $_POST['tipo_infracao'];
    $descricao = $_POST['descricao'] ?: null;
    $pontos = $_POST['pontos'] ?: 0;
    $valor = $_POST['valor'];
    $status_pagamento = $_POST['status_pagamento'];
    $vencimento = $_POST['vencimento'] ?: null;
    $data_pagamento = $_POST['data_pagamento'] ?: null;
    $sql = "UPDATE multas SET 
        veiculo_id = :veiculo_id,
        motorista_id = :motorista_id,
        rota_id = :rota_id,
        data_infracao = :data_infracao,
        tipo_infracao = :tipo_infracao,
        descricao = :descricao,
        pontos = :pontos,
        valor = :valor,
        status_pagamento = :status_pagamento,
        vencimento = :vencimento,
        data_pagamento = :data_pagamento,
        comprovante = :comprovante
        WHERE id = :id AND empresa_id = :empresa_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $multa_id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':veiculo_id', $veiculo_id);
    $stmt->bindParam(':motorista_id', $motorista_id);
    $stmt->bindParam(':rota_id', $rota_id);
    $stmt->bindParam(':data_infracao', $data_infracao);
    $stmt->bindParam(':tipo_infracao', $tipo_infracao);
    $stmt->bindParam(':descricao', $descricao);
    $stmt->bindParam(':pontos', $pontos);
    $stmt->bindParam(':valor', $valor);
    $stmt->bindParam(':status_pagamento', $status_pagamento);
    $stmt->bindParam(':vencimento', $vencimento);
    $stmt->bindParam(':data_pagamento', $data_pagamento);
    $stmt->bindParam(':comprovante', $comprovante);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Multa atualizada com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar multa']);
    }
}

function deleteMulta($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    $multa_id = $_POST['id'] ?? null;

    if (!$multa_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da multa é obrigatório']);
        return;
    }

    // Verificar se a multa pertence à empresa
    $check_sql = "SELECT id FROM multas WHERE id = :id AND empresa_id = :empresa_id";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bindParam(':id', $multa_id);
    $check_stmt->bindParam(':empresa_id', $empresa_id);
    $check_stmt->execute();

    if (!$check_stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Multa não encontrada']);
        return;
    }

    $sql = "DELETE FROM multas WHERE id = :id AND empresa_id = :empresa_id";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $multa_id);
    $stmt->bindParam(':empresa_id', $empresa_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Multa excluída com sucesso']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir multa']);
    }
}

function getMulta($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    $multa_id = $_GET['id'] ?? null;

    if (!$multa_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da multa é obrigatório']);
        return;
    }

    $sql = "SELECT m.*, v.placa as veiculo_placa, mo.nome as motorista_nome, 
                   CONCAT('Rota #', r.id) as rota_codigo
            FROM multas m
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            LEFT JOIN motoristas mo ON m.motorista_id = mo.id
            LEFT JOIN rotas r ON m.rota_id = r.id
            WHERE m.id = :id AND m.empresa_id = :empresa_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':id', $multa_id);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();

    $multa = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($multa) {
        echo json_encode(['success' => true, 'multa' => $multa]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Multa não encontrada']);
    }
}

function listMultas($conn) {
    $empresa_id = $_SESSION['empresa_id'];
    $page = $_GET['page'] ?? 1;
    $limit = $_GET['limit'] ?? 10;
    $offset = ($page - 1) * $limit;

    // Contar total
    $count_sql = "SELECT COUNT(*) as total FROM multas WHERE empresa_id = :empresa_id";
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bindParam(':empresa_id', $empresa_id);
    $count_stmt->execute();
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Buscar multas
    $sql = "SELECT m.*, v.placa as veiculo_placa, mo.nome as motorista_nome, 
                   CONCAT('Rota #', r.id) as rota_codigo
            FROM multas m
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            LEFT JOIN motoristas mo ON m.motorista_id = mo.id
            LEFT JOIN rotas r ON m.rota_id = r.id
            WHERE m.empresa_id = :empresa_id
            ORDER BY m.data_infracao DESC, m.id DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $multas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'multas' => $multas,
        'total' => $total,
        'page' => $page,
        'total_pages' => ceil($total / $limit)
    ]);
}
?> 