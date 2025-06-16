<?php
require_once '../config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['motorista_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $conn = getConnection();
    
    // Obter dados do formulário
    $empresa_id = $_SESSION['empresa_id'];
    $motorista_id = $_SESSION['motorista_id'];
    $veiculo_id = $_POST['veiculo_id'];
    $rota_id = $_POST['rota_id'];
    $posto = $_POST['posto'];
    $data_abastecimento = $_POST['data_abastecimento'];
    $litros = $_POST['quantidade'];
    $valor_litro = $_POST['preco_litro'];
    $valor_total = $_POST['valor_total'];
    $km_atual = $_POST['km_atual'];
    $tipo_combustivel = $_POST['tipo_combustivel'];
    $forma_pagamento = $_POST['forma_pagamento'];
    $observacoes = $_POST['observacoes'] ?? null;
    
    // Processar comprovante se enviado
    $comprovante = null;
    if (isset($_FILES['comprovante']) && $_FILES['comprovante']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/comprovantes/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['comprovante']['name'], PATHINFO_EXTENSION));
        $file_name = uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['comprovante']['tmp_name'], $file_path)) {
            $comprovante = 'uploads/comprovantes/' . $file_name;
        }
    }
    
    // Inserir abastecimento
    $sql = "INSERT INTO abastecimentos (
                empresa_id, veiculo_id, motorista_id, rota_id,
                posto, data_abastecimento, litros, valor_litro,
                valor_total, km_atual, tipo_combustivel, forma_pagamento,
                observacoes, comprovante, status, fonte
            ) VALUES (
                :empresa_id, :veiculo_id, :motorista_id, :rota_id,
                :posto, :data_abastecimento, :litros, :valor_litro,
                :valor_total, :km_atual, :tipo_combustivel, :forma_pagamento,
                :observacoes, :comprovante, 'pendente', 'motorista'
            )";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        'empresa_id' => $empresa_id,
        'veiculo_id' => $veiculo_id,
        'motorista_id' => $motorista_id,
        'rota_id' => $rota_id,
        'posto' => $posto,
        'data_abastecimento' => $data_abastecimento,
        'litros' => $litros,
        'valor_litro' => $valor_litro,
        'valor_total' => $valor_total,
        'km_atual' => $km_atual,
        'tipo_combustivel' => $tipo_combustivel,
        'forma_pagamento' => $forma_pagamento,
        'observacoes' => $observacoes,
        'comprovante' => $comprovante
    ]);
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Abastecimento registrado com sucesso!'
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao registrar abastecimento: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao registrar abastecimento: ' . $e->getMessage()
    ]);
} 