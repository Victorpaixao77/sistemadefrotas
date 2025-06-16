<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

try {
    $conn = getConnection();
    
    // Verificar rotas pendentes
    $sql_rotas = "SELECT r.id, r.origem, r.destino, v.placa, m.nome as motorista 
                  FROM rotas r 
                  JOIN veiculos v ON r.veiculo_id = v.id 
                  JOIN motoristas m ON r.motorista_id = m.id 
                  WHERE r.status = 'pendente' 
                  AND r.data_inicio <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
    
    $stmt_rotas = $conn->prepare($sql_rotas);
    $stmt_rotas->execute();
    $rotas_pendentes = $stmt_rotas->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar abastecimentos pendentes
    $sql_abastecimentos = "SELECT a.id, v.placa, m.nome as motorista 
                          FROM abastecimentos a 
                          JOIN veiculos v ON a.veiculo_id = v.id 
                          JOIN motoristas m ON a.motorista_id = m.id 
                          WHERE a.status = 'pendente' 
                          AND a.data_abastecimento <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
    
    $stmt_abastecimentos = $conn->prepare($sql_abastecimentos);
    $stmt_abastecimentos->execute();
    $abastecimentos_pendentes = $stmt_abastecimentos->fetchAll(PDO::FETCH_ASSOC);
    
    // Gerar notificações para rotas pendentes
    foreach ($rotas_pendentes as $rota) {
        // Verificar se já existe notificação para esta rota
        $sql_check = "SELECT id FROM notificacoes 
                     WHERE tipo = 'rota' 
                     AND referencia_id = :rota_id 
                     AND data_criacao >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':rota_id', $rota['id']);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() == 0) {
            $titulo = "Rota Pendente";
            $mensagem = "Rota {$rota['origem']} → {$rota['destino']} com veículo {$rota['placa']} está pendente";
            $ia_mensagem = "Verifique se o motorista {$rota['motorista']} está preparado para iniciar a rota.";
            
            $sql_insert = "INSERT INTO notificacoes (tipo, titulo, mensagem, referencia_id, ia_mensagem) 
                          VALUES ('rota', :titulo, :mensagem, :referencia_id, :ia_mensagem)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindParam(':titulo', $titulo);
            $stmt_insert->bindParam(':mensagem', $mensagem);
            $stmt_insert->bindParam(':referencia_id', $rota['id']);
            $stmt_insert->bindParam(':ia_mensagem', $ia_mensagem);
            $stmt_insert->execute();
        }
    }
    
    // Gerar notificações para abastecimentos pendentes
    foreach ($abastecimentos_pendentes as $abastecimento) {
        // Verificar se já existe notificação para este abastecimento
        $sql_check = "SELECT id FROM notificacoes 
                     WHERE tipo = 'abastecimento' 
                     AND referencia_id = :abastecimento_id 
                     AND data_criacao >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':abastecimento_id', $abastecimento['id']);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() == 0) {
            $titulo = "Abastecimento Pendente";
            $mensagem = "Veículo {$abastecimento['placa']} precisa ser abastecido";
            $ia_mensagem = "O motorista {$abastecimento['motorista']} deve registrar o abastecimento em breve.";
            
            $sql_insert = "INSERT INTO notificacoes (tipo, titulo, mensagem, referencia_id, ia_mensagem) 
                          VALUES ('abastecimento', :titulo, :mensagem, :referencia_id, :ia_mensagem)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bindParam(':titulo', $titulo);
            $stmt_insert->bindParam(':mensagem', $mensagem);
            $stmt_insert->bindParam(':referencia_id', $abastecimento['id']);
            $stmt_insert->bindParam(':ia_mensagem', $ia_mensagem);
            $stmt_insert->execute();
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Verificação de pendências concluída']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar pendências: ' . $e->getMessage()]);
} 