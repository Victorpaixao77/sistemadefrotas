<?php
header('Content-Type: application/json');

// Prevenir qualquer saída antes do JSON
ob_start();

// Incluir configuração de sessão do sistema principal
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db_connect.php';

// Iniciar sessão se não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    configure_session();
    session_start();
}

try {
    // Verificar se o usuário está logado e obter empresa_id da sessão
    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Usuário não autenticado ou empresa não identificada');
    }
    
    $empresa_id = intval($_SESSION['empresa_id']);
    
    // Usar a mesma conexão do sistema principal
    $pdo = getConnection();
    
    // Buscar pneus disponíveis (não alocados) - APENAS da empresa logada
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
            WHERE p.empresa_id = ? -- Filtrar apenas pneus da empresa logada
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
    
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'pneus' => $pneus,
        'empresa_id' => $empresa_id // Para debug
    ]);
    
} catch (Exception $e) {
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 