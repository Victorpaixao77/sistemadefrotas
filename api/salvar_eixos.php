<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
require_authentication();

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método não permitido.');
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $veiculo_id = isset($input['veiculo_id']) ? intval($input['veiculo_id']) : 0;
    $eixos_cavalo = isset($input['eixos_cavalo']) ? $input['eixos_cavalo'] : [];
    $eixos_carreta = isset($input['eixos_carreta']) ? $input['eixos_carreta'] : [];
    if (!$veiculo_id) {
        throw new Exception('Veículo não informado.');
    }
    $pdo = getConnection();
    
    // Inicia a transação
    $pdo->beginTransaction();
    
    try {
        // Remove eixos antigos e suas posições
        $pdo->prepare('DELETE FROM eixo_pneus WHERE eixo_id IN (SELECT id FROM eixos WHERE veiculo_id = ?)')->execute([$veiculo_id]);
    $pdo->prepare('DELETE FROM eixos WHERE veiculo_id = ?')->execute([$veiculo_id]);
        
        // Mapeamento de posições para o cavalo
        $posicoes_cavalo = [
            1 => [1, 2], // Dianteiro Esquerdo, Dianteiro Direito
            2 => [6, 7], // Eixo 1 - Lado Esquerdo, Eixo 1 - Lado Direito
            3 => [8, 9], // Eixo 2 - Lado Esquerdo, Eixo 2 - Lado Direito
            4 => [12, 13], // Eixo 3 - Lado Esquerdo, Eixo 3 - Lado Direito
            5 => [14, 15], // Eixo 4 - Lado Esquerdo, Eixo 4 - Lado Direito
        ];
        
        // Mapeamento de posições para a carreta
        $posicoes_carreta = [
            1 => [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
            2 => [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
            3 => [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
        ];
        
        // Inserir eixos do cavalo (posições 1-10)
        $eixo_pneus_ids = [];
    foreach ($eixos_cavalo as $idx => $qtd) {
            $posicao_id = $idx + 1; // IDs 1-10 para o cavalo
            
            // Inserir o eixo
            $stmt = $pdo->prepare('INSERT INTO eixos (veiculo_id, posicao_id, quantidade_pneus) VALUES (?, ?, ?)');
            $stmt->execute([$veiculo_id, $posicao_id, $qtd]);
            $eixo_id = $pdo->lastInsertId();
            
            // Criar registros em eixo_pneus para cada pneu do eixo
            for ($i = 0; $i < $qtd; $i++) {
                // Criar o registro em eixo_pneus
                $stmt = $pdo->prepare('INSERT INTO eixo_pneus (
                    eixo_id,
                    veiculo_id,
                    status
                ) VALUES (?, ?, "desalocado")');
                $stmt->execute([$eixo_id, $veiculo_id]);
                $eixo_pneus_ids[] = $pdo->lastInsertId();
    }
        }
        
        // Inserir eixos da carreta (posições 11+)
    foreach ($eixos_carreta as $idx => $qtd) {
            $posicao_id = $idx + 1; // IDs 1+ para a carreta
            
            // Inserir o eixo
            $stmt = $pdo->prepare('INSERT INTO eixos (veiculo_id, posicao_id, quantidade_pneus) VALUES (?, ?, ?)');
            $stmt->execute([$veiculo_id, $posicao_id + 10, $qtd]); // +10 para manter a separação com o cavalo
            $eixo_id = $pdo->lastInsertId();
            
            // Criar registros em eixo_pneus para cada pneu do eixo
            for ($i = 0; $i < $qtd; $i++) {
                // Criar o registro em eixo_pneus
                $stmt = $pdo->prepare('INSERT INTO eixo_pneus (
                    eixo_id,
                    veiculo_id,
                    status
                ) VALUES (?, ?, "desalocado")');
                $stmt->execute([$eixo_id, $veiculo_id]);
                $eixo_pneus_ids[] = $pdo->lastInsertId();
            }
        }
        
        // Confirma a transação
        $pdo->commit();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Eixos salvos com sucesso!',
            'eixo_pneus_ids' => $eixo_pneus_ids
        ]);
    } catch (Exception $e) {
        // Em caso de erro, desfaz a transação
        $pdo->rollBack();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 