<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

if (function_exists('configure_session')) {
    configure_session();
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (function_exists('require_authentication')) {
    require_authentication();
}

$empresa_id = isset($_SESSION['empresa_id']) ? $_SESSION['empresa_id'] : null;
if (!$empresa_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ID da empresa não encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $conn = getConnection();
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        throw new Exception('ID do abastecimento não informado');
    }
    // Campos obrigatórios
    $fields = [
        'veiculo_id', 'motorista_id', 'posto', 'data_abastecimento', 'litros', 'valor_litro', 'valor_total',
        'km_atual', 'tipo_combustivel', 'forma_pagamento', 'rota_id'
    ];
    $data = [];
    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || $_POST[$field] === '') {
            throw new Exception('Campo obrigatório não informado: ' . $field);
        }
        $data[$field] = $_POST[$field];
    }
    $data['observacoes'] = isset($_POST['observacoes']) ? $_POST['observacoes'] : null;
    $data['empresa_id'] = $empresa_id;

    // Upload do comprovante (opcional)
    $comprovante = null;
    if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION);
            $filename = 'comprovante_' . $id . '_' . time() . '.' . $ext;
            $dest = '../../uploads/comprovantes/' . $filename;
            if (!is_dir('../../uploads/comprovantes/')) {
                mkdir('../../uploads/comprovantes/', 0777, true);
            }
            if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $dest)) {
                $comprovante = $filename;
            } else {
                throw new Exception('Falha ao salvar o comprovante');
            }
        } else {
            throw new Exception('Erro no upload do comprovante: ' . $_FILES['comprovante']['error']);
        }
    }

    // Montar SQL de update
    $sql = "UPDATE abastecimentos SET
        veiculo_id = :veiculo_id,
        motorista_id = :motorista_id,
        posto = :posto,
        data_abastecimento = :data_abastecimento,
        litros = :litros,
        valor_litro = :valor_litro,
        valor_total = :valor_total,
        km_atual = :km_atual,
        tipo_combustivel = :tipo_combustivel,
        forma_pagamento = :forma_pagamento,
        rota_id = :rota_id,
        observacoes = :observacoes";
    if ($comprovante) {
        $sql .= ", comprovante = :comprovante";
    }
    $sql .= " WHERE id = :id AND empresa_id = :empresa_id";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':veiculo_id', $data['veiculo_id']);
    $stmt->bindValue(':motorista_id', $data['motorista_id']);
    $stmt->bindValue(':posto', $data['posto']);
    $stmt->bindValue(':data_abastecimento', $data['data_abastecimento']);
    $stmt->bindValue(':litros', $data['litros']);
    $stmt->bindValue(':valor_litro', $data['valor_litro']);
    $stmt->bindValue(':valor_total', $data['valor_total']);
    $stmt->bindValue(':km_atual', $data['km_atual']);
    $stmt->bindValue(':tipo_combustivel', $data['tipo_combustivel']);
    $stmt->bindValue(':forma_pagamento', $data['forma_pagamento']);
    $stmt->bindValue(':rota_id', $data['rota_id']);
    $stmt->bindValue(':observacoes', $data['observacoes']);
    if ($comprovante) {
        $stmt->bindValue(':comprovante', $comprovante);
    }
    $stmt->bindValue(':id', $id);
    $stmt->bindValue(':empresa_id', $empresa_id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Abastecimento atualizado com sucesso']);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} 