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
    
    // Buscar pneus alocados ao veículo
    $stmt = $pdo->prepare("
        SELECT 
            ip.id,
            ip.pneu_id,
            ip.veiculo_id,
            ip.posicao,
            ip.data_instalacao,
            ip.status,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            p.sulco_inicial,
            p.dot,
            p.data_ultima_recapagem,
            s.nome as status_nome
        FROM instalacoes_pneus ip
        INNER JOIN pneus p ON ip.pneu_id = p.id
        INNER JOIN status_pneus s ON p.status_id = s.id
        WHERE ip.veiculo_id = ? 
        AND ip.data_remocao IS NULL
        ORDER BY ip.posicao
    ");
    
    $stmt->execute([$veiculo_id]);
    $pneus_alocados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'pneus_alocados' => $pneus_alocados
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 