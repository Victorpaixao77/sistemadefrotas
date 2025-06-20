<?php
header('Content-Type: application/json');

try {
    $empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 1;
    
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Buscar pneus disponíveis (não alocados)
    $sql = "SELECT 
            p.id,
            p.numero_serie,
            p.marca,
            p.modelo,
            p.medida,
            p.sulco_inicial,
            p.dot,
            p.km_instalacao,
            p.data_instalacao,
            p.vida_util_km,
            p.numero_recapagens,
            p.data_ultima_recapagem,
            p.lote,
            p.data_entrada,
            p.observacoes,
            sp.nome as status_nome,
            CASE 
                WHEN p.status_id = 5 THEN 'bom'
                WHEN p.status_id = 4 THEN 'gasto'
                WHEN p.status_id = 1 THEN 'furado'
                WHEN p.status_id = 2 THEN 'reserva'
                WHEN p.status_id = 3 THEN 'descartado'
                ELSE 'gasto'
            END as status
            FROM pneus p
            LEFT JOIN status_pneus sp ON sp.id = p.status_id
            WHERE p.empresa_id = ?
            AND p.id NOT IN (
                SELECT pneu_id 
                FROM instalacoes_pneus 
                WHERE data_remocao IS NULL
            )
            AND p.status_id IN (2, 5) -- Apenas pneus em bom estado ou reserva
            ORDER BY p.numero_serie";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    
    $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar dados para exibição
    foreach ($pneus as &$pneu) {
        $pneu['sulco_inicial'] = number_format($pneu['sulco_inicial'], 1);
        if ($pneu['data_ultima_recapagem']) {
            $pneu['data_ultima_recapagem'] = date('d/m/Y', strtotime($pneu['data_ultima_recapagem']));
        }
        if ($pneu['data_instalacao']) {
            $pneu['data_instalacao'] = date('d/m/Y', strtotime($pneu['data_instalacao']));
        }
        if ($pneu['data_entrada']) {
            $pneu['data_entrada'] = date('d/m/Y', strtotime($pneu['data_entrada']));
        }
    }
    
    echo json_encode([
        'success' => true,
        'pneus' => $pneus
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 