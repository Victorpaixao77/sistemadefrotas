<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Verificar se a sessão já está ativa antes de iniciá-la
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obter empresa_id da sessão
$empresa_id = $_SESSION['empresa_id'] ?? 1;

try {
    $conn = getConnection();
    
    // LIMPEZA AUTOMÁTICA: Remover notificações antigas (mais de 30 dias)
    $sql_cleanup = "DELETE FROM notificacoes 
                    WHERE empresa_id = :empresa_id 
                    AND data_criacao < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt_cleanup = $conn->prepare($sql_cleanup);
    $stmt_cleanup->execute(['empresa_id' => $empresa_id]);
    
    // Verificar rotas pendentes (apenas rotas ativas e recentes)
    $sql_rotas = "SELECT r.id, r.origem, r.destino, v.placa, m.nome as motorista, r.data_inicio
                  FROM rotas r 
                  JOIN veiculos v ON r.veiculo_id = v.id 
                  JOIN motoristas m ON r.motorista_id = m.id 
                  WHERE v.empresa_id = :empresa_id
                  AND r.status = 'pendente' 
                  AND r.data_inicio >= CURDATE() 
                  AND r.data_inicio <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                  AND r.data_inicio >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; // Apenas rotas da última semana
    
    $stmt_rotas = $conn->prepare($sql_rotas);
    $stmt_rotas->execute(['empresa_id' => $empresa_id]);
    $rotas_pendentes = $stmt_rotas->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar abastecimentos pendentes (apenas abastecimentos recentes)
    $sql_abastecimentos = "SELECT a.id, v.placa, m.nome as motorista, a.data_abastecimento
                          FROM abastecimentos a 
                          JOIN veiculos v ON a.veiculo_id = v.id 
                          JOIN motoristas m ON a.motorista_id = m.id 
                          WHERE v.empresa_id = :empresa_id
                          AND a.status = 'pendente' 
                          AND a.data_abastecimento >= CURDATE()
                          AND a.data_abastecimento <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
                          AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 7 DAY)"; // Apenas da última semana
    
    $stmt_abastecimentos = $conn->prepare($sql_abastecimentos);
    $stmt_abastecimentos->execute(['empresa_id' => $empresa_id]);
    $abastecimentos_pendentes = $stmt_abastecimentos->fetchAll(PDO::FETCH_ASSOC);
    
    // Gerar notificações para rotas pendentes
    foreach ($rotas_pendentes as $rota) {
        // Verificar se já existe notificação para esta rota (últimas 48 horas)
        $sql_check = "SELECT id FROM notificacoes 
                     WHERE empresa_id = :empresa_id
                     AND tipo = 'rota' 
                     AND referencia_id = :rota_id 
                     AND data_criacao >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([
            'empresa_id' => $empresa_id,
            'rota_id' => $rota['id']
        ]);
        
        if ($stmt_check->rowCount() == 0) {
            $titulo = "Rota Pendente";
            $mensagem = "Rota {$rota['origem']} → {$rota['destino']} com veículo {$rota['placa']} está pendente";
            $ia_mensagem = "Verifique se o motorista {$rota['motorista']} está preparado para iniciar a rota.";
            
            $sql_insert = "INSERT INTO notificacoes (empresa_id, tipo, titulo, mensagem, referencia_id, ia_mensagem, data_criacao, lida) 
                          VALUES (:empresa_id, 'rota', :titulo, :mensagem, :referencia_id, :ia_mensagem, NOW(), 0)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->execute([
                'empresa_id' => $empresa_id,
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'referencia_id' => $rota['id'],
                'ia_mensagem' => $ia_mensagem
            ]);
        }
    }
    
    // Gerar notificações para abastecimentos pendentes
    foreach ($abastecimentos_pendentes as $abastecimento) {
        // Verificar se já existe notificação para este abastecimento (últimas 48 horas)
        $sql_check = "SELECT id FROM notificacoes 
                     WHERE empresa_id = :empresa_id
                     AND tipo = 'abastecimento' 
                     AND referencia_id = :abastecimento_id 
                     AND data_criacao >= DATE_SUB(NOW(), INTERVAL 48 HOUR)";
        
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->execute([
            'empresa_id' => $empresa_id,
            'abastecimento_id' => $abastecimento['id']
        ]);
        
        if ($stmt_check->rowCount() == 0) {
            $titulo = "Abastecimento Pendente";
            $mensagem = "Veículo {$abastecimento['placa']} precisa ser abastecido";
            $ia_mensagem = "O motorista {$abastecimento['motorista']} deve registrar o abastecimento em breve.";
            
            $sql_insert = "INSERT INTO notificacoes (empresa_id, tipo, titulo, mensagem, referencia_id, ia_mensagem, data_criacao, lida) 
                          VALUES (:empresa_id, 'abastecimento', :titulo, :mensagem, :referencia_id, :ia_mensagem, NOW(), 0)";
            
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->execute([
                'empresa_id' => $empresa_id,
                'titulo' => $titulo,
                'mensagem' => $mensagem,
                'referencia_id' => $abastecimento['id'],
                'ia_mensagem' => $ia_mensagem
            ]);
        }
    }
    
    // LIMPEZA ADICIONAL: Marcar como lidas notificações de rotas/abastecimentos que não estão mais pendentes
    $sql_mark_read = "UPDATE notificacoes n 
                     LEFT JOIN rotas r ON n.referencia_id = r.id AND n.tipo = 'rota'
                     LEFT JOIN abastecimentos a ON n.referencia_id = a.id AND n.tipo = 'abastecimento'
                     SET n.lida = 1 
                     WHERE n.empresa_id = :empresa_id 
                     AND n.lida = 0
                     AND ((n.tipo = 'rota' AND (r.status != 'pendente' OR r.id IS NULL))
                          OR (n.tipo = 'abastecimento' AND (a.status != 'pendente' OR a.id IS NULL)))";
    
    $stmt_mark_read = $conn->prepare($sql_mark_read);
    $stmt_mark_read->execute(['empresa_id' => $empresa_id]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Verificação de pendências concluída',
        'rotas_pendentes' => count($rotas_pendentes),
        'abastecimentos_pendentes' => count($abastecimentos_pendentes)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro ao verificar pendências: ' . $e->getMessage()]);
} 