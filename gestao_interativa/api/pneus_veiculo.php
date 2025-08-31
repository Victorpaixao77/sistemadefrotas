<?php
header('Content-Type: application/json');

// Prevenir qualquer saída antes do JSON
ob_start();

// Incluir configuração de sessão do sistema principal
require_once __DIR__ . '/../../includes/config.php';

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
    
    // Carregar configurações do banco usando caminho absoluto
    $config = require __DIR__ . '/../config/database.php';
    
    // Criar conexão PDO
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $config['host'],
        $config['port'],
        $config['database'],
        $config['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        $config['options']
    );
    
    // Verificar se o veículo pertence à empresa logada
    $stmt = $pdo->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$veiculo_id, $empresa_id]);
    if ($stmt->rowCount() === 0) {
        throw new Exception('Veículo não encontrado ou não pertence à sua empresa');
    }
    
    // Buscar pneus instalados no veículo (apenas da empresa logada)
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
            ip.posicao,
            ip.data_instalacao as data_alocacao,
            sp.nome as status_nome,
            CASE 
                WHEN p.status_id = 5 THEN 'bom'
                WHEN p.status_id = 4 THEN 'gasto'
                WHEN p.status_id = 1 THEN 'furado'
                WHEN p.status_id = 2 THEN 'reserva'
                WHEN p.status_id = 3 THEN 'descartado'
                ELSE 'gasto'
            END as status
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            LEFT JOIN status_pneus sp ON sp.id = p.status_id
            WHERE ip.veiculo_id = ? 
            AND p.empresa_id = ? -- Filtrar apenas pneus da empresa logada
            AND ip.data_remocao IS NULL
            ORDER BY ip.posicao";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$veiculo_id, $empresa_id]);
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
        if ($pneu['data_alocacao']) {
            $pneu['data_alocacao'] = date('d/m/Y', strtotime($pneu['data_alocacao']));
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
