<?php
header('Content-Type: application/json');

try {
    if (!isset($_GET['veiculo_id'])) {
        throw new Exception('ID do veículo não fornecido');
    }
    
    $veiculo_id = intval($_GET['veiculo_id']);
    $empresa_id = 1; // Empresa fixa para teste
    
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar histórico de alocações do veículo
    $stmt = $pdo->prepare("
        SELECT 
            ip.id,
            ip.pneu_id,
            ip.veiculo_id,
            ip.posicao,
            ip.data_instalacao,
            ip.data_remocao,
            ip.status,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            s.nome as status_nome
        FROM instalacoes_pneus ip
        INNER JOIN pneus p ON ip.pneu_id = p.id
        INNER JOIN status_pneus s ON p.status_id = s.id
        WHERE ip.veiculo_id = ?
        ORDER BY ip.data_instalacao DESC
    ");
    
    $stmt->execute([$veiculo_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar datas
    foreach ($historico as &$registro) {
        $registro['data_instalacao'] = date('d/m/Y', strtotime($registro['data_instalacao']));
        if ($registro['data_remocao']) {
            $registro['data_remocao'] = date('d/m/Y', strtotime($registro['data_remocao']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'historico' => $historico
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 