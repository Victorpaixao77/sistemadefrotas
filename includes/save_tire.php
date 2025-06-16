<?php
ob_start();
require_once 'config.php';
require_once 'functions.php';

session_start();

error_log('Output buffer at start of save_tire.php: ' . ob_get_contents());

try {
    $pdo = getConnection();
    
    // Recebe os dados do formulário
    $data = json_decode(file_get_contents('php://input'), true);
    error_log('Dados recebidos em save_tire.php: ' . print_r($data, true));
    
    // Prepara os dados
    $id = $data['id'] ?? null;
    $empresa_id = $data['empresa_id'];
    $numero_serie = $data['numero_serie'];
    $marca = $data['marca'];
    $modelo = $data['modelo'];
    $dot = $data['dot'];
    $km_instalacao = $data['km_instalacao'];
    $data_instalacao = $data['data_instalacao'];
    $vida_util_km = $data['vida_util_km'];
    $status_id = $data['status_id'];
    $observacoes = $data['observacoes'] ?: null;
    $medida = $data['medida'];
    $sulco_inicial = $data['sulco_inicial'];
    $numero_recapagens = $data['numero_recapagens'] ?: 0;
    $data_ultima_recapagem = $data['data_ultima_recapagem'] ?: null;
    $lote = $data['lote'] ?: null;
    $data_entrada = $data['data_entrada'] ?: null;
    
    // Inicia a transação
    $pdo->beginTransaction();

    try {
        if ($id) {
            // Atualização
            $sql = "UPDATE pneus SET 
                    empresa_id = ?, numero_serie = ?, marca = ?, 
                    modelo = ?, dot = ?, km_instalacao = ?, data_instalacao = ?,
                    vida_util_km = ?, status_id = ?, observacoes = ?,
                    medida = ?, sulco_inicial = ?, numero_recapagens = ?,
                    data_ultima_recapagem = ?, lote = ?, data_entrada = ?,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = ? AND empresa_id = ?";
            error_log('Query UPDATE save_tire.php: ' . $sql);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id, $numero_serie, $marca,
                $modelo, $dot, $km_instalacao, $data_instalacao,
                $vida_util_km, $status_id, $observacoes,
                $medida, $sulco_inicial, $numero_recapagens,
                $data_ultima_recapagem, $lote, $data_entrada,
                $id, $empresa_id
            ]);
            error_log('UPDATE rowCount: ' . $stmt->rowCount());
        } else {
            // Inserção
            $sql = "INSERT INTO pneus (
                    empresa_id, numero_serie, marca, modelo, dot,
                    km_instalacao, data_instalacao, vida_util_km, status_id,
                    observacoes, medida, sulco_inicial, numero_recapagens,
                    data_ultima_recapagem, lote, data_entrada, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                    CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                )";
            error_log('Query INSERT save_tire.php: ' . $sql);
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $empresa_id, $numero_serie, $marca,
                $modelo, $dot, $km_instalacao, $data_instalacao,
                $vida_util_km, $status_id, $observacoes,
                $medida, $sulco_inicial, $numero_recapagens,
                $data_ultima_recapagem, $lote, $data_entrada
            ]);
            
            $id = $pdo->lastInsertId();
        }
        
        // Confirma a transação
        $pdo->commit();
        
        // Limpa qualquer output buffer antes de enviar JSON de sucesso
        while (ob_get_level()) {
            ob_end_clean();
        }
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
        
    } catch (Exception $e) {
        // Em caso de erro, desfaz a transação
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    error_log('Erro em save_tire.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    // Limpa qualquer output buffer antes de enviar JSON de erro
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} 