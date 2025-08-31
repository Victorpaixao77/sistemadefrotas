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
    if (!isset($_GET['veiculo_id'])) {
        throw new Exception('ID do veículo não fornecido');
    }
    
    // Verificar se o usuário está logado e obter empresa_id da sessão
    if (!isset($_SESSION['empresa_id'])) {
        throw new Exception('Usuário não autenticado ou empresa não identificada');
    }
    
    $veiculo_id = intval($_GET['veiculo_id']);
    $empresa_id = intval($_SESSION['empresa_id']);
    
    // Usar conexão padrão do sistema
    $conn = getConnection();
    
    // Verificar se o veículo pertence à empresa logada
    $stmt = $conn->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$veiculo_id, $empresa_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Veículo não encontrado ou não pertence à sua empresa');
    }
    
    // Estatísticas de pneus ativos (instalados + alocados no modo flexível)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_ativos
        FROM (
            -- Pneus instalados na tabela instalacoes_pneus
            SELECT ip.pneu_id
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            WHERE ip.veiculo_id = ? AND ip.data_remocao IS NULL
            AND p.empresa_id = ?
            
            UNION
            
            -- Pneus alocados no modo flexível
            SELECT apf.pneu_id
            FROM alocacoes_pneus_flexiveis apf
            INNER JOIN pneus p ON apf.pneu_id = p.id
            INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
            WHERE ev.veiculo_id = ? AND p.empresa_id = ?
        ) as pneus_ativos
    ");
    $stmt->execute([$veiculo_id, $empresa_id, $veiculo_id, $empresa_id]);
    $pneusAtivos = $stmt->fetch(PDO::FETCH_ASSOC)['total_ativos'];
    
    // Estatísticas de pneus em manutenção (instalados + flexíveis)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_manutencao
        FROM (
            -- Pneus instalados em manutenção
            SELECT ip.pneu_id
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            INNER JOIN status_pneus s ON p.status_id = s.id
            WHERE ip.veiculo_id = ? AND ip.data_remocao IS NULL 
            AND p.empresa_id = ?
            AND s.nome IN ('Manutenção', 'Calibração', 'Reparo')
            
            UNION
            
            -- Pneus flexíveis em manutenção
            SELECT apf.pneu_id
            FROM alocacoes_pneus_flexiveis apf
            INNER JOIN pneus p ON apf.pneu_id = p.id
            INNER JOIN status_pneus s ON p.status_id = s.id
            INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
            WHERE ev.veiculo_id = ? AND p.empresa_id = ?
            AND s.nome IN ('Manutenção', 'Calibração', 'Reparo')
        ) as pneus_manutencao
    ");
    $stmt->execute([$veiculo_id, $empresa_id, $veiculo_id, $empresa_id]);
    $pneusManutencao = $stmt->fetch(PDO::FETCH_ASSOC)['total_manutencao'];
    
    // Estatísticas de pneus descartados (instalados + flexíveis)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_descartados
        FROM (
            -- Pneus instalados descartados
            SELECT ip.pneu_id
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            INNER JOIN status_pneus s ON p.status_id = s.id
            WHERE ip.veiculo_id = ? AND p.empresa_id = ?
            AND s.nome = 'Descartado'
            
            UNION
            
            -- Pneus flexíveis descartados
            SELECT apf.pneu_id
            FROM alocacoes_pneus_flexiveis apf
            INNER JOIN pneus p ON apf.pneu_id = p.id
            INNER JOIN status_pneus s ON p.status_id = s.id
            INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
            WHERE ev.veiculo_id = ? AND p.empresa_id = ?
            AND s.nome = 'Descartado'
        ) as pneus_descartados
    ");
    $stmt->execute([$veiculo_id, $empresa_id, $veiculo_id, $empresa_id]);
    $pneusDescartados = $stmt->fetch(PDO::FETCH_ASSOC)['total_descartados'];
    
    // Quilometragem média dos pneus ativos (instalados + flexíveis)
    $stmt = $conn->prepare("
        SELECT AVG(km_total) as km_medio
        FROM (
            -- Quilometragem de pneus instalados
            SELECT COALESCE(p.km_instalacao, 0) as km_total
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            WHERE ip.veiculo_id = ? AND ip.data_remocao IS NULL
            AND p.empresa_id = ?
            AND p.km_instalacao IS NOT NULL AND p.km_instalacao > 0
            
            UNION ALL
            
            -- Quilometragem de pneus flexíveis
            SELECT COALESCE(p.km_instalacao, 0) as km_total
            FROM alocacoes_pneus_flexiveis apf
            INNER JOIN pneus p ON apf.pneu_id = p.id
            INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
            WHERE ev.veiculo_id = ? AND p.empresa_id = ?
            AND p.km_instalacao IS NOT NULL AND p.km_instalacao > 0
        ) as quilometragens
    ");
    $stmt->execute([$veiculo_id, $empresa_id, $veiculo_id, $empresa_id]);
    $kmMedio = $stmt->fetch(PDO::FETCH_ASSOC)['km_medio'];
    
    // Total de alocações (histórico completo - instalados + flexíveis)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_alocacoes
        FROM (
            -- Alocações na tabela instalacoes_pneus
            SELECT ip.id
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            WHERE ip.veiculo_id = ? AND p.empresa_id = ?
            
            UNION
            
            -- Alocações na tabela alocacoes_pneus_flexiveis
            SELECT apf.id
            FROM alocacoes_pneus_flexiveis apf
            INNER JOIN pneus p ON apf.pneu_id = p.id
            INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
            WHERE ev.veiculo_id = ? AND p.empresa_id = ?
        ) as todas_alocacoes
    ");
    $stmt->execute([$veiculo_id, $empresa_id, $veiculo_id, $empresa_id]);
    $totalAlocacoes = $stmt->fetch(PDO::FETCH_ASSOC)['total_alocacoes'];
    
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'estatisticas' => [
            'pneusAtivos' => intval($pneusAtivos),
            'pneusManutencao' => intval($pneusManutencao),
            'pneusDescartados' => intval($pneusDescartados),
            'quilometragemMedia' => round($kmMedio ?: 0),
            'totalAlocacoes' => intval($totalAlocacoes)
        ],
        'empresa_id' => $empresa_id,
        'veiculo_id' => $veiculo_id
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