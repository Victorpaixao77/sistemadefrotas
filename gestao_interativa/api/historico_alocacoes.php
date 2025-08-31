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
    $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : null;
    
    // Usar conexão padrão do sistema
    $conn = getConnection();
    
    // Buscar histórico de alocações da empresa logada
    if ($veiculo_id) {
        // Histórico específico do veículo
        $sql = "SELECT 
                ip.id,
                ip.data_instalacao,
                ip.data_remocao,
                ip.posicao,
                p.numero_serie,
                p.marca,
                p.modelo,
                p.medida,
                v.placa,
                v.modelo as modelo_veiculo,
                sp.nome as status_nome
                FROM instalacoes_pneus ip
                INNER JOIN pneus p ON ip.pneu_id = p.id
                INNER JOIN veiculos v ON ip.veiculo_id = v.id
                LEFT JOIN status_pneus sp ON p.status_id = sp.id
                WHERE p.empresa_id = ? AND ip.veiculo_id = ?
                ORDER BY ip.data_instalacao DESC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$empresa_id, $veiculo_id]);
    } else {
        // Histórico geral da empresa
        $sql = "SELECT 
                ip.id,
                ip.data_instalacao,
                ip.data_remocao,
                ip.posicao,
                p.numero_serie,
                p.marca,
                p.modelo,
                p.medida,
                v.placa,
                v.modelo as modelo_veiculo,
                sp.nome as status_nome
                FROM instalacoes_pneus ip
                INNER JOIN pneus p ON ip.pneu_id = p.id
                INNER JOIN veiculos v ON ip.veiculo_id = v.id
                LEFT JOIN status_pneus sp ON p.status_id = sp.id
                WHERE p.empresa_id = ?
                ORDER BY ip.data_instalacao DESC
                LIMIT 50";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$empresa_id]);
    }
    
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatar datas
    foreach ($historico as &$item) {
        if ($item['data_instalacao']) {
            $item['data_instalacao'] = date('d/m/Y H:i', strtotime($item['data_instalacao']));
        }
        if ($item['data_remocao']) {
            $item['data_remocao'] = date('d/m/Y H:i', strtotime($item['data_remocao']));
        }
    }
    
    // Limpar qualquer saída anterior
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'historico' => $historico,
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