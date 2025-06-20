<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/conexao.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Método não permitido');
    }
    
    $pneu_id = $_GET['pneu_id'] ?? null;
    
    if (!$pneu_id) {
        throw new Exception('ID do pneu não fornecido');
    }
    
    // Buscar detalhes completos do pneu
    $sql = "
        SELECT 
            p.id,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            s.nome as status,
            p.quilometragem,
            p.data_instalacao,
            p.ultima_manutencao,
            p.observacoes,
            p.empresa_id
        FROM pneus p
        LEFT JOIN status_pneus s ON p.status_id = s.id
        WHERE p.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pneu_id]);
    $pneu = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pneu) {
        throw new Exception('Pneu não encontrado');
    }
    
    // Buscar informações de instalação atual se existir
    $sql_instalacao = "
        SELECT 
            ip.id,
            ip.veiculo_id,
            ip.posicao,
            ip.data_instalacao,
            v.placa,
            v.modelo as modelo_veiculo
        FROM instalacoes_pneus ip
        LEFT JOIN veiculos v ON ip.veiculo_id = v.id
        WHERE ip.pneu_id = ? AND ip.data_remocao IS NULL
        ORDER BY ip.data_instalacao DESC
        LIMIT 1
    ";
    
    $stmt_instalacao = $pdo->prepare($sql_instalacao);
    $stmt_instalacao->execute([$pneu_id]);
    $instalacao = $stmt_instalacao->fetch(PDO::FETCH_ASSOC);
    
    if ($instalacao) {
        $pneu['instalacao_atual'] = $instalacao;
    }
    
    // Buscar histórico de manutenções
    $sql_manutencao = "
        SELECT 
            pm.id,
            pm.tipo_manutencao,
            pm.data_manutencao,
            pm.quilometragem_manutencao,
            pm.observacoes,
            pm.custo
        FROM pneu_manutencao pm
        WHERE pm.pneu_id = ?
        ORDER BY pm.data_manutencao DESC
        LIMIT 5
    ";
    
    $stmt_manutencao = $pdo->prepare($sql_manutencao);
    $stmt_manutencao->execute([$pneu_id]);
    $manutencoes = $stmt_manutencao->fetchAll(PDO::FETCH_ASSOC);
    
    if ($manutencoes) {
        $pneu['manutencoes'] = $manutencoes;
    }
    
    echo json_encode([
        'success' => true,
        'pneu' => $pneu
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API pneu_detalhes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
