<?php
require_once '../includes/config.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/functions.php';

configure_session();
session_start();

require_authentication();
require_once '../includes/permissions.php';
require_permission('access_tire_management');
require_once '../includes/csrf.php';

$csrf_token = csrf_token_get();
$page_title = "Gestão Interativa de Pneus";
$is_modern = !isset($_GET['classic']) || (string) $_GET['classic'] !== '1';

try {
    // Usar o sistema de conexão centralizado
    $conn = getConnection();
    
    // Buscar veículos com colunas corretas
    $stmt = $conn->prepare("SELECT id, placa, modelo, marca, numero_eixos FROM veiculos WHERE empresa_id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $veiculos = $stmt->fetchAll();
    
    // Buscar posições de pneus disponíveis
    $stmt = $conn->prepare("SELECT id, nome FROM posicoes_pneus ORDER BY nome");
    $stmt->execute();
    $posicoes = $stmt->fetchAll();
    
    $pneusDisponiveis = [];
    $pneusEmUso = [];
    $pneusManutencao = [];
    
    try {
        require_once __DIR__ . '/../includes/pneus_helpers.php';
        $conn = getConnection();
        $empresa_id = (int) $_SESSION['empresa_id'];
        $idsDisp = pneus_status_ids_disponiveis($conn);
        $inPh = implode(',', array_fill(0, count($idsDisp), '?'));
        $naoAlocado = pneus_sql_nao_alocado('p.id');

        $stmt = $conn->prepare("SELECT p.*, sp.nome as status_nome 
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              WHERE p.empresa_id = ? AND p.status_id IN ({$inPh})
                              AND {$naoAlocado}");
        $stmt->execute(array_merge([$empresa_id], $idsDisp));
        $pneusDisponiveis = $stmt->fetchAll();
        
        // Pneus em uso: legado (instalacoes_pneus) + modo flexível, sem duplicar por pneu_id
        $pneusEmUso = [];
        $idsEmUso = [];
        
        $stmt = $conn->prepare("SELECT p.*, sp.nome as status_nome, ip.veiculo_id, ip.posicao_id
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              INNER JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                              WHERE p.empresa_id = ? AND ip.data_remocao IS NULL");
        $stmt->execute([$_SESSION['empresa_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int) $row['id'];
            if (!isset($idsEmUso[$pid])) {
                $idsEmUso[$pid] = true;
                $pneusEmUso[] = $row;
            }
        }

        $stmt = $conn->prepare("SELECT apf.pneu_id, apf.slot_id, p.id, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome
                              FROM alocacoes_pneus_flexiveis apf
                              INNER JOIN pneus p ON apf.pneu_id = p.id
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id
                              WHERE apf.empresa_id = ? AND apf.ativo = 1 AND apf.data_remocao IS NULL");
        $stmt->execute([$_SESSION['empresa_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $pid = (int) ($row['pneu_id'] ?? $row['id']);
            if (!isset($idsEmUso[$pid])) {
                $idsEmUso[$pid] = true;
                $pneusEmUso[] = $row;
            }
        }
        
        // Pneus em manutenção (por nome de status, não ID fixo)
        $pneusManutencao = [];
        $statusManutId = pneus_status_id_manutencao($conn);
        if ($statusManutId) {
        $stmt = $conn->prepare("SELECT p.*, sp.nome as status_nome 
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                                  WHERE p.empresa_id = ? AND p.status_id = ?");
            $stmt->execute([$_SESSION['empresa_id'], $statusManutId]);
        $pneusManutencao = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar pneus: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Erro na conexão com banco (gestao_interativa): " . $e->getMessage());
    $error = "Erro ao conectar com o banco de dados. Tente novamente mais tarde.";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <script>window.__SF_CSRF__ = <?php echo json_encode($csrf_token); ?>;</script>
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/ia_pneus_avancado.css">
    <link rel="stylesheet" href="../css/pneus_timeline.css">
    <?php if ($is_modern): ?>
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <?php endif; ?>
    
    <style>
        /* Variáveis CSS adicionais para compatibilidade */
        :root {
            --primary-color: var(--accent-primary);
            --success-color: var(--accent-success);
            --warning-color: var(--accent-warning);
            --danger-color: var(--accent-danger);
            --primary-bg: rgba(59, 130, 246, 0.1);
            --success-bg: rgba(16, 185, 129, 0.1);
            --warning-bg: rgba(245, 158, 11, 0.1);
            --danger-bg: rgba(239, 68, 68, 0.1);
        }
        
        /* Garantir que o conteúdo não ultrapasse a tela quando o sidebar expande */
        body {
            overflow-x: hidden;
        }
        
        .app-container {
            width: 100%;
            max-width: 100vw;
            overflow-x: hidden;
        }
        
        .main-content {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        .dashboard-content {
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        /* Garantir que elementos com largura fixa se ajustem */
        #area-montagem-flex {
            max-width: 100%;
            overflow-x: auto;
            box-sizing: border-box;
        }
        
        .vehicle-section {
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .vehicle-selector {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background: var(--bg-primary);
            color: var(--text-primary);
            margin-bottom: 20px;
        }
        
        .vehicle-info {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--bg-primary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .vehicle-image {
            width: 120px;
            height: 80px;
            background: var(--bg-secondary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--text-secondary);
            border: 2px dashed var(--border-color);
        }
        
        .vehicle-details {
            flex: 1;
        }
        
        .vehicle-type {
            font-size: 18px;
            font-weight: bold;
            color: var(--text-primary);
        }
        
        .vehicle-plate {
            font-size: 16px;
            color: var(--text-secondary);
        }
        
        .tire-grid-container {
            position: relative;
        }
        
        .tire-grid {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
            position: relative;
        }

        .legend-color.alert {
    background: #ffd700 !important;
    border: 1px solidrgba(184, 135, 11, 0), 135, 11, 0) !important;
}
        
        /* Layout para caminhão truck (6x2) */
        .tire-grid.truck-6x2 {
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }
        
        /* Layout para caminhão truck (6x4) */
        .tire-grid.truck-6x4 {
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }
        
        /* Layout para carreta */
        .tire-grid.trailer {
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(2, 1fr);
        }
        
        /* Layout para cavalo mecânico */
        .tire-grid.tractor {
            grid-template-columns: repeat(6, 1fr);
            grid-template-rows: repeat(1, 1fr);
        }
        
        /* ===== SOMBRAS COLORIDAS PARA PNEUS ===== */
        .pneu-flex.pneu-bom {
            box-shadow: 0 4px 12px rgba(39, 174, 96, 0.4), 0 2px 4px rgba(39, 174, 96, 0.2);
            border: 2px solid rgba(39, 174, 96, 0.3);
            transition: all 0.3s ease;
        }
        
        .pneu-flex.pneu-bom:hover {
            box-shadow: 0 6px 16px rgba(39, 174, 96, 0.6), 0 4px 8px rgba(39, 174, 96, 0.3);
            transform: translateY(-2px);
        }
        
        .pneu-flex.pneu-critico {
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4), 0 2px 4px rgba(231, 76, 60, 0.2);
            border: 2px solid rgba(231, 76, 60, 0.3);
            animation: pulse-critico 2s infinite;
            transition: all 0.3s ease;
        }
        
        .pneu-flex.pneu-critico:hover {
            box-shadow: 0 6px 16px rgba(231, 76, 60, 0.6), 0 4px 8px rgba(231, 76, 60, 0.3);
            transform: translateY(-2px);
        }
        
        /* ===== NOVOS ALERTAS DE PNEUS ===== */
        .pneu-flex.pneu-alerta {
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4), 0 2px 4px rgba(245, 158, 11, 0.2);
            border: 2px solid rgba(245, 158, 11, 0.3);
            animation: pulse-alerta 3s infinite;
            transition: all 0.3s ease;
        }
        
        .pneu-flex.pneu-alerta:hover {
            box-shadow: 0 6px 16px rgba(245, 158, 11, 0.6), 0 4px 8px rgba(245, 158, 11, 0.3);
            transform: translateY(-2px);
        }
        
        .pneu-flex.pneu-pressao {
            box-shadow: 0 4px 12px rgba(156, 39, 176, 0.4), 0 2px 4px rgba(156, 39, 176, 0.2);
            border: 2px solid rgba(156, 39, 176, 0.3);
            animation: pulse-pressao 2.5s infinite;
            transition: all 0.3s ease;
        }
        
        .pneu-flex.pneu-pressao:hover {
            box-shadow: 0 6px 16px rgba(156, 39, 176, 0.6), 0 4px 8px rgba(156, 39, 176, 0.3);
            transform: translateY(-2px);
        }
        
        @keyframes pulse-critico {
            0% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4), 0 2px 4px rgba(231, 76, 60, 0.2); }
            50% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.6), 0 2px 4px rgba(231, 76, 60, 0.4); }
            100% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4), 0 2px 4px rgba(231, 76, 60, 0.2); }
        }
        
        @keyframes pulse-alerta {
            0% { box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4), 0 2px 4px rgba(245, 158, 11, 0.2); }
            50% { box-shadow: 0 4px 12px rgba(245, 158, 11, 0.6), 0 2px 4px rgba(245, 158, 11, 0.4); }
            100% { box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4), 0 2px 4px rgba(245, 158, 11, 0.2); }
        }
        
        @keyframes pulse-pressao {
            0% { box-shadow: 0 4px 12px rgba(156, 39, 176, 0.4), 0 2px 4px rgba(156, 39, 176, 0.2); }
            50% { box-shadow: 0 4px 12px rgba(156, 39, 176, 0.6), 0 2px 4px rgba(156, 39, 176, 0.4); }
            100% { box-shadow: 0 4px 12px rgba(156, 39, 176, 0.4), 0 2px 4px rgba(156, 39, 176, 0.2); }
        }
        
        /* ===== INDICADORES DE ALERTA ===== */
        .alerta-indicator {
            position: absolute;
            top: -8px;
            right: -8px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
            z-index: 10;
            animation: pulse-indicator 1.5s infinite;
        }
        
        .alerta-indicator.critico {
            background: #e74c3c;
        }
        
        .alerta-indicator.atencao {
            background: #f39c12;
        }
        
        .alerta-indicator.pressao {
            background: #9c27b0;
        }
        
        @keyframes pulse-indicator {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* ===== LEGENDA DE ALERTAS ===== */
        .legend-alertas {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        
        .legend-alerta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-primary);
            padding: 4px 8px;
            border-radius: 4px;
            background: var(--bg-primary);
        }
        
        .legend-alerta-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }
        
        .legend-alerta-color.critico { background: #e74c3c; }
        .legend-alerta-color.atencao { background: #f39c12; }
        .legend-alerta-color.pressao { background: #9c27b0; }
        
        .tire-slot {
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .tire-slot:hover {
            border-color: var(--primary-color);
            box-shadow: 0 4px 15px rgba(0,123,255,0.2);
            transform: translateY(-2px);
        }
        
        .tire-slot.occupied {
            background: var(--success-bg);
            border-color: var(--success-color);
        }
        
        .tire-slot.maintenance {
            background: var(--warning-bg);
            border-color: var(--warning-color);
        }
        
        .tire-slot.critical {
            background: var(--danger-bg);
            border-color: var(--danger-color);
        }
        
        .tire-position {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .tire-info {
            font-size: 14px;
            font-weight: bold;
            color: var(--text-primary);
            line-height: 1.2;
        }
        
        .tire-details {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 10px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tire-slot:hover .tire-details {
            opacity: 1;
            visibility: visible;
        }
        
        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-primary);
        }
        
        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
        
        .legend-color.available { 
            background: var(--bg-primary); 
            border: 2px solid var(--border-color); 
        }
        .legend-color.occupied { 
            background: var(--success-bg); 
            border: 2px solid var(--success-color); 
        }
        .legend-color.maintenance { 
            background: var(--warning-bg); 
            border: 2px solid var(--warning-color); 
        }
        .legend-color.critical { 
            background: var(--danger-bg); 
            border: 2px solid var(--danger-color); 
        }
        
        .history-section, .stats-section {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-em-uso { background: var(--success-bg); color: var(--success-color); }
        .status-manutencao { background: var(--warning-bg); color: var(--warning-color); }
        .status-disponivel { background: var(--primary-bg); color: var(--primary-color); }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: var(--text-secondary);
        }
        
        .error {
            background: var(--danger-bg);
            color: var(--danger-color);
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        @media (max-width: 768px) {
            .tire-grid.truck-6x2,
            .tire-grid.truck-6x4,
            .tire-grid.trailer,
            .tire-grid.tractor {
                grid-template-columns: repeat(3, 1fr);
                grid-template-rows: auto;
            }
            
            .vehicle-info {
                flex-direction: column;
                text-align: center;
            }
            
            .legend {
                flex-direction: column;
                align-items: center;
            }
        }
        
        /* Garantir que os cards de dashboard fiquem lado a lado */
        .dashboard-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            gap: 20px !important;
            margin-bottom: 30px !important;
        }
        
        .dashboard-card {
            min-width: 250px !important;
            max-width: none !important;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) !important;
            }
        }
        
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        
        @media (max-width: 480px) {
            .dashboard-grid {
                grid-template-columns: 1fr !important;
            }
        }

        body.gestao-interativa-modern .dashboard-header {
            display: none;
        }
        body.gestao-interativa-modern .dashboard-content.fornc-page {
            padding-top: 8px;
        }
        body.gestao-interativa-modern .vehicle-section > h2.gi-section-title {
            display: none;
        }
        body.gestao-interativa-modern .gi-toolbar-actions {
            justify-content: flex-end;
            margin-bottom: 0.75rem;
        }
        
        /* Layout da seção principal */
        .main-content-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-top: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
            box-sizing: border-box;
        }
        
        @media (max-width: 1200px) {
            .main-content-grid {
                grid-template-columns: 1fr 250px;
            }
        }
        
        @media (max-width: 768px) {
            .main-content-grid {
                grid-template-columns: 1fr;
            }
        }

        .pneu-circular.flexivel {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: radial-gradient(ellipse at 60% 40%, #444 60%, #222 100%);
            box-shadow: 0 2px 8px #0008, 0 0 0 4px #111 inset;
            border: 3px solid #666;
            position: absolute;
            cursor: grab;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: bold;
            font-size: 1.1em;
            z-index: 2;
            transition: box-shadow 0.2s;
        }
        .pneu-circular.flexivel:hover {
            box-shadow: 0 4px 16px #0005;
        }
        .pneu-circular.flexivel span {
            z-index: 2;
            font-size: 1em;
        }
        .pneu-circular.flexivel button {
            z-index: 3;
        }

        #componente-veiculo-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 10px;
            width: 100%;
            height: 500px;
            transition: transform 0.6s ease;
            transform-origin: center center;
        }
        .veiculo-flex {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 48px;
            position: relative;
            height: 100%;
            width: 320px;
        }
        .veiculo-flex .linha-central {
            position: absolute;
            width: 8px;
            top: 0;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            background: #111;
            z-index: 0;
            border-radius: 4px;
        }
        .eixo-flex {
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 10px 0;
            gap: 32px;
            width: 100%;
        }
        .linha-eixo-flex {
            position: absolute;
            height: 8px;
            background: #111;
            top: 50%;
            transform: translateY(-50%);
            z-index: 0;
            border-radius: 5px;
        }
        .eixo-flex[data-pneus="2"] .linha-eixo-flex {
            width: 100px;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .eixo-flex[data-pneus="4"] .linha-eixo-flex {
            width: 180px;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .pneu-flex {
            width: 32px;
            height: 64px;
            background: radial-gradient(#333, #111);
            border-radius: 12px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s, box-shadow 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.6);
            position: relative;
            z-index: 2;
        }
        .pneu-flex:hover {
            transform: scale(1.1);
            background: radial-gradient(#555, #222);
        }
        .eixo-numero-flex {
            position: absolute;
            left: 50%;
            top: 100%;
            transform: translate(-50%, 0);
            background: #222;
            color: #fff;
            font-weight: bold;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            border: 2px solid #fff8;
            z-index: 3;
        }

        .bloco-veiculo-flex {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            min-width: 340px;
            max-width: 340px;
        }
        #componente-cavalo-wrapper, #componente-carreta-wrapper {
            width: 340px;
            max-width: 100%;
            height: 500px;
            display: flex;
            box-sizing: border-box;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }

        /* Legendas de status para o modo flexível, igual ao padrão */
        #legenda-flexivel {
            position: absolute;
            right: 32px;
            top: 32px;
            z-index: 10;
            background: rgba(30,30,40,0.95);
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0003;
        }
        #legenda-flexivel div {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
        }
        #legenda-flexivel div span {
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 4px;
        }
        #legenda-flexivel div span.available {
            background: #2ecc40;
        }
        #legenda-flexivel div span.occupied {
            background: #3498db;
        }
        #legenda-flexivel div span.maintenance {
            background: #f1c40f;
        }
        #legenda-flexivel div span.critical {
            background: #e74c3c;
        }

        /* Adicionar sombra verde nos pneus alocados */
        .pneu-flex[data-ocupado='true'] {
            box-shadow: 0 0 0 3px #2ecc40, 0 4px 8px rgba(0,0,0,0.6);
        }
        .pneu-flex[data-ocupado='true']:hover {
            box-shadow: 0 0 0 3px #27ae60, 0 4px 8px rgba(0,0,0,0.6);
        }
        .pneu-flex[data-ocupado='false']:hover::after {
            content: 'Clique para gerenciar seu pneu';
            position: absolute;
            left: 50%;
            top: 110%;
            transform: translateX(-50%);
            background: #3498db;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 13px;
            white-space: nowrap;
            z-index: 10;
            box-shadow: 0 2px 8px #0003;
        }

        /* Drag & Drop Avançado */
        .pneu-flex.draggable {
            cursor: grab;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .pneu-flex.draggable:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 12px rgba(0,0,0,0.8);
        }
        
        .pneu-flex.dragging {
            cursor: grabbing;
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 8px 16px rgba(0,0,0,0.9);
            z-index: 1000;
            opacity: 0.8;
        }
        
        .pneu-flex.drop-zone {
            border: 2px dashed #3498db;
            background: rgba(52, 152, 219, 0.1);
            transition: all 0.3s;
        }
        
        .pneu-flex.drop-zone.valid {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }
        
        .pneu-flex.drop-zone.invalid {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }
        
        /* Área de Estoque para Drop */
        .area-estoque {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 120px;
            height: 80px;
            background: linear-gradient(45deg, #2ecc71, #27ae60);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 100;
            transition: all 0.3s;
        }
        
        .area-estoque:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
        }
        
        .area-estoque.drop-active {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            transform: scale(1.1);
        }
        
        /* Tooltips Inteligentes */
        .tooltip-avancado {
            position: absolute;
            background: rgba(0, 0, 0, 0.95);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            max-width: 250px;
            z-index: 10000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .tooltip-avancado.show {
            opacity: 1;
        }
        
        .tooltip-avancado .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .tooltip-avancado .status-bom { background: #27ae60; }
        .tooltip-avancado .status-gasto { background: #f39c12; }
        .tooltip-avancado .status-critico { background: #e74c3c; }
        
        /* Feedback Visual para Drag */
        .drag-feedback {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            z-index: 10001;
            pointer-events: none;
        }
        
        /* Validação Visual */
        .validation-indicator {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid white;
            z-index: 10;
        }
        
        .validation-indicator.valid {
            background: #27ae60;
        }
        
        .validation-indicator.invalid {
            background: #e74c3c;
        }
        
        /* ===== MODAL DE NOTIFICAÇÃO IA ===== */
        .modal-notificacao-ia {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content-ia {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 0;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s ease;
        }
        
        .modal-content-ia.critico {
            border-left: 5px solid #e74c3c;
        }
        
        .modal-content-ia.atencao {
            border-left: 5px solid #f39c12;
        }
        
        .modal-content-ia.sucesso {
            border-left: 5px solid #27ae60;
        }
        
        .modal-header-ia {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header-ia h3 {
            margin: 0;
            color: var(--text-primary);
            font-size: 18px;
        }
        
        .close-ia {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }
        
        .close-ia:hover {
            color: var(--text-primary);
        }
        
        .modal-body-ia {
            padding: 20px;
        }
        
        .modal-body-ia p {
            margin: 0 0 20px 0;
            line-height: 1.6;
            color: var(--text-primary);
            white-space: pre-line;
        }
        
        .acoes-ia {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .acoes-ia button {
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            background: var(--accent-primary);
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .acoes-ia button:hover {
            background: var(--accent-primary-dark);
            transform: translateY(-1px);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Estilos para Dashboard Actions - usando estilos globais do styles.css */
        
        /* Estilos para Modais */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s;
        }
        
        .modal-content {
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: var(--text-primary);
            font-size: 1.5em;
        }
        
        .close-modal {
            font-size: 28px;
            font-weight: bold;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: var(--text-primary);
        }
        
        .modal-body {
            padding: 20px;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px;
            }
            
            #componente-cavalo-wrapper, #componente-carreta-wrapper {
                width: 100%;
                max-width: 300px;
            }
            
            #area-montagem-flex {
                flex-direction: column;
                gap: 32px;
            }
        }
        
        /* Ajustar quando o sidebar está expandido */
        @media (max-width: 1400px) {
            #componente-cavalo-wrapper, #componente-carreta-wrapper {
                width: 100%;
                max-width: 300px;
            }
            
            #area-montagem-flex {
                gap: 32px;
            }
        }
        
        /* Garantir que elementos não ultrapassem a largura disponível */
        .bloco-veiculo-flex {
            max-width: 100%;
            box-sizing: border-box;
        }

        /* Itens dos modais de escolha (giChoose) — herdam o tema do sistema */
        .gi-choose-item {
            display: block;
            width: 100%;
            text-align: left;
            padding: 11px 14px;
            border: none;
            background: none;
            border-radius: 8px;
            cursor: pointer;
            color: var(--text-primary);
            font-size: 0.9rem;
        }
        .gi-choose-item:hover {
            background: var(--bg-tertiary, rgba(127, 127, 127, 0.12));
        }
        .gi-choose-label {
            display: block;
            font-weight: 500;
        }
        .gi-choose-desc {
            display: block;
            font-size: 0.78rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .gi-prompt-input {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            background: var(--input-bg, var(--card-bg, var(--bg-secondary)));
            color: var(--text-primary);
            font-size: 0.9rem;
            box-sizing: border-box;
        }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="<?php echo $is_modern ? 'gestao-interativa-modern' : ''; ?>">
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content<?php echo $is_modern ? ' fornc-page' : ''; ?>">
                <?php if (!$is_modern): ?>
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="dashboard-actions">
                        <div class="view-controls">
                            <button id="filterBtn" class="btn-restore-layout" title="Filtros">
                                <i class="fas fa-filter"></i>
                            </button>
                            <button id="exportBtn" class="btn-toggle-layout" title="Exportar">
                                <i class="fas fa-file-export"></i>
                            </button>
                            <button id="helpBtn" class="btn-help" title="Ajuda">
                                <i class="fas fa-question-circle"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php else: ?>
                    <?php if ($is_modern): ?>
                    <div class="fornc-kpi-strip">
                        <div class="fornc-kpi-cell">
                            <div class="fornc-kpi-head"><i class="fas fa-truck fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Veículos</span></div>
                            <span class="val" id="giKpiVeiculos"><?php echo count($veiculos); ?></span>
                        </div>
                        <div class="fornc-kpi-cell">
                            <div class="fornc-kpi-head"><i class="fas fa-warehouse fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Disponíveis</span></div>
                            <span class="val" id="giKpiDisponiveis"><?php echo count($pneusDisponiveis); ?></span>
                        </div>
                        <div class="fornc-kpi-cell">
                            <div class="fornc-kpi-head"><i class="fas fa-truck-moving fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Em uso</span></div>
                            <span class="val" id="giKpiEmUso"><?php echo count($pneusEmUso); ?></span>
                        </div>
                        <div class="fornc-kpi-cell">
                            <div class="fornc-kpi-head"><i class="fas fa-screwdriver-wrench fornc-kpi-icon" aria-hidden="true"></i><span class="lbl">Manutenção</span></div>
                            <span class="val" id="giKpiManutencao"><?php echo count($pneusManutencao); ?></span>
                        </div>
                    </div>
                    <div class="fornc-toolbar gi-toolbar-actions">
                        <div class="fornc-btn-row">
                            <button type="button" class="fornc-btn fornc-btn--ghost" id="filterBtn" title="Filtros"><i class="fas fa-sliders-h"></i> Filtros</button>
                            <button type="button" class="fornc-btn fornc-btn--muted" id="exportBtn" title="Exportar"><i class="fas fa-file-export"></i> Exportar</button>
                            <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Estatísticas -->
                    <div class="dashboard-grid">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <i class="fas fa-truck kpi-card-icon" aria-hidden="true"></i>
                                <h3>Veículos</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value"><?php echo count($veiculos); ?></span>
                                    <span class="metric-subtitle">Total cadastrados</span>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-header">
                                <i class="fas fa-circle-dot kpi-card-icon" aria-hidden="true"></i>
                                <h3>Pneus Disponíveis</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value"><?php echo count($pneusDisponiveis); ?></span>
                                    <span class="metric-subtitle">Em estoque</span>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-header">
                                <i class="fas fa-gear kpi-card-icon" aria-hidden="true"></i>
                                <h3>Pneus em Uso</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value" id="pneus-em-uso-count"><?php echo count($pneusEmUso); ?></span>
                                    <span class="metric-subtitle">Instalados</span>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-header">
                                <i class="fas fa-screwdriver-wrench kpi-card-icon" aria-hidden="true"></i>
                                <h3>Em Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value"><?php echo count($pneusManutencao); ?></span>
                                    <span class="metric-subtitle">Em reparo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Conteúdo Principal -->
                    <div class="main-content-grid">
                        <!-- Seção de Veículos -->
                        <div class="vehicle-section">
                            <h2 class="gi-section-title"><i class="fas fa-truck"></i> Gestão de Pneus por Veículo</h2>
                            
                            <div id="area-modo-flexivel">
                                <div style="margin-bottom: 16px;">
                                    <select id="veiculoSelectorFlex" class="vehicle-selector">
                                        <option value="">Selecione um veículo</option>
                                        <?php foreach ($veiculos as $v): ?>
                                            <option value="<?= $v['id'] ?>"><?= $v['placa'] ?> - <?= $v['modelo'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="margin-bottom: 16px; display: flex; gap: 16px; flex-wrap: wrap;">
                                    <button class="btn btn-primary" onclick="adicionarEixo('caminhao')">Adicionar Eixo ao Caminhão</button>
                                    <button class="btn btn-primary" onclick="adicionarEixo('carreta')">Adicionar Eixo à Carreta</button>
                                    <button class="btn btn-secondary" onclick="salvarLayoutFlexivel()">Salvar Layout</button>
                                </div>
                                <div style="margin-bottom: 16px; display: flex; gap: 16px; flex-wrap: wrap;">
                                    <button class="btn btn-danger" onclick="excluirEixo('caminhao')">Excluir Eixo do Caminhão</button>
                                    <button class="btn btn-danger" onclick="excluirEixo('carreta')">Excluir Eixo da Carreta</button>
                                </div>
                                <div id="area-montagem-flex" style="position:relative; width:100%; min-height:520px; background:var(--bg-primary); border:1px dashed var(--border-color); border-radius:12px; overflow:auto; padding-bottom: 24px; display:flex;justify-content:center;align-items:flex-start;gap:64px;">
                                    <div class="bloco-veiculo-flex">
                                        <div id="componente-cavalo-wrapper" style="display:flex;flex-direction:column;align-items:center;width:340px;height:500px;">
                                            <div id="componente-cavalo"></div>
                                            <div style="margin-top:12px;color:#888;font-size:1.2em;font-weight:bold;">Cavalo</div>
                                        </div>
                                    </div>
                                    <div class="bloco-veiculo-flex">
                                        <div id="componente-carreta-wrapper" style="display:flex;flex-direction:column;align-items:center;width:340px;height:500px;">
                                            <div id="componente-carreta"></div>
                                            <div style="margin-top:12px;color:#888;font-size:1.2em;font-weight:bold;">Carreta</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="legend" style="margin-top: 18px;">
                                    <div class="legend-item">
                                        <div class="legend-color available"></div>
                                        <span>Disponível</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color occupied"></div>
                                        <span>Em Uso</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color alert"></div>
                                        <span>Alerta</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color maintenance"></div>
                                        <span>Manutenção</span>
                                    </div>
                                    <div class="legend-item">
                                        <div class="legend-color critical"></div>
                                        <span>Crítico</span>
                                    </div>
                                </div>
                                
                                <!-- Nova legenda de alertas inteligentes -->
                                <div class="legend-alertas">
                                    <div class="legend-alerta-item">
                                        <div class="legend-alerta-color critico"></div>
                                        <span>🚨 Crítico (Troca Urgente)</span>
                                    </div>
                                    <div class="legend-alerta-item">
                                        <div class="legend-alerta-color atencao"></div>
                                        <span>⚠️ Atenção (Planejar Troca)</span>
                                    </div>
                                    <div class="legend-alerta-item">
                                        <div class="legend-alerta-color pressao"></div>
                                        <span>🔧 Pressão/Calibração</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div class="sidebar-section">
                            <h3><i class="fas fa-history"></i> Histórico de Alocações</h3>
                            <div id="allocationHistory">
                                <p>Selecione um veículo para ver o histórico de alocações de pneus.</p>
                            </div>
                            <div id="pneusIaPanelMount" style="margin-top:20px"></div>
                            
                            <h3><i class="fas fa-chart-line"></i> Estatísticas do Veículo</h3>
                            <div id="vehicleStats">
                                <p>Selecione um veículo para ver as estatísticas.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Gráficos Interativos -->
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise de Pneus</h2>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Distribuição de Pneus por Status</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoPizza" width="400" height="320"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Tendência de Uso de Pneus (Últimos 6 Meses)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoBarras" width="500" height="320"></canvas>
                            </div>
                        </div>
                        <div class="analytics-card">
                            <div class="card-header">
                                <h3>Indicadores de Performance (KPIs)</h3>
                            </div>
                            <div class="card-body">
                                <canvas id="graficoKPIs" width="500" height="320"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Área de Estoque para Drop -->
    <div id="areaEstoque" class="area-estoque">
        <div>📦<br>Estoque</div>
    </div>
    
    <!-- Tooltip Avançado -->
    <div id="tooltipAvancado" class="tooltip-avancado"></div>
    
    <!-- Feedback de Drag -->
    <div id="dragFeedback" class="drag-feedback" style="display: none;"></div>
    
    <!-- JavaScript Files -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/ia_pneus.js"></script>
    <script src="../js/pneus_timeline.js?v=1.1"></script>
    <script src="../js/ia_pneus_avancado.js?v=1.1"></script>

    <script>
        // Escapa dados do servidor antes de injetar via innerHTML (previne XSS armazenado).
        function escapeHtml(value) {
            if (value === null || value === undefined) return '';
            return String(value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Toast não-bloqueante (substitui giToast()).
        function giToast(message, type) {
            type = type || 'info';
            var host = document.getElementById('giToastHost');
            if (!host) {
                host = document.createElement('div');
                host.id = 'giToastHost';
                host.style.cssText = 'position:fixed;top:18px;right:18px;z-index:99999;display:flex;flex-direction:column;gap:8px;max-width:380px;';
                document.body.appendChild(host);
            }
            var colors = { success: '#16a34a', error: '#dc2626', warning: '#f59e0b', info: '#2563eb' };
            var icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
            var el = document.createElement('div');
            el.style.cssText = 'display:flex;align-items:flex-start;gap:10px;background:#fff;border-left:4px solid ' + (colors[type] || colors.info) + ';box-shadow:0 6px 20px rgba(0,0,0,.15);border-radius:8px;padding:12px 14px;font-size:.9rem;color:#1f2937;opacity:0;transform:translateX(12px);transition:all .2s ease;';
            el.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '" style="color:' + (colors[type] || colors.info) + ';margin-top:2px;"></i><span style="flex:1;white-space:pre-line;"></span>';
            el.querySelector('span').textContent = message;
            host.appendChild(el);
            requestAnimationFrame(function () { el.style.opacity = '1'; el.style.transform = 'translateX(0)'; });
            setTimeout(function () { el.style.opacity = '0'; el.style.transform = 'translateX(12px)'; setTimeout(function () { el.remove(); }, 250); }, 4200);
        }

        // Modal informativo centralizado (substitui toast para leituras longas).
        function giInfoModal(title, content, opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                var ov = document.createElement('div');
                ov.className = 'modal active';
                ov.style.zIndex = '100000';
                var box = document.createElement('div');
                box.className = 'modal-content';
                box.style.maxWidth = opts.maxWidth || '520px';
                var footerHtml = '<div class="modal-footer">';
                if (opts.secondaryLabel) {
                    footerHtml += '<button type="button" class="btn-secondary" data-act="secondary"></button>';
                }
                footerHtml += '<button type="button" class="btn-primary" data-act="ok"></button></div>';
                box.innerHTML =
                    '<div class="modal-header"><h2></h2><span class="close-modal" data-act="cancel">&times;</span></div>' +
                    '<div class="modal-body gi-info-modal-body"></div>' +
                    footerHtml;
                box.querySelector('h2').textContent = title || 'Detalhes';
                var body = box.querySelector('.gi-info-modal-body');
                if (opts.html) {
                    body.innerHTML = content;
            } else {
                    body.style.whiteSpace = 'pre-line';
                    body.style.lineHeight = '1.55';
                    body.style.color = 'var(--text-primary)';
                    body.textContent = content;
                }
                box.querySelector('[data-act="ok"]').textContent = opts.okLabel || 'Fechar';
                if (opts.secondaryLabel) {
                    box.querySelector('[data-act="secondary"]').textContent = opts.secondaryLabel;
                }
                ov.appendChild(box);
                document.body.appendChild(ov);
                var done = function (v) { ov.remove(); resolve(v); };
                box.querySelectorAll('[data-act="cancel"]').forEach(function (el) { el.onclick = function () { done(null); }; });
                box.querySelector('[data-act="ok"]').onclick = function () { done('ok'); };
                if (opts.secondaryLabel) {
                    box.querySelector('[data-act="secondary"]').onclick = function () { done('secondary'); };
                }
                ov.addEventListener('click', function (e) { if (e.target === ov) done(null); });
            });
        }

        function buildPneuDetalhesHtml(pneu, slotId) {
            const analise = analisarPneuIA(pneu);
            const preditiva = analisePreditivaDesgaste(pneu);
            const statusIa = (analise.status || 'ok').toUpperCase();
            const statusColor = analise.status === 'critico' ? '#dc2626' : (analise.status === 'atencao' ? '#f59e0b' : '#16a34a');
            let html = '<div style="display:grid;gap:14px;">';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 16px;font-size:.92rem;">';
            html += '<div><strong>Pneu</strong><br>' + escapeHtml(pneu.numero_serie || '—') + '</div>';
            html += '<div><strong>Medida</strong><br>' + escapeHtml(pneu.medida || '—') + '</div>';
            html += '<div><strong>Marca / modelo</strong><br>' + escapeHtml((pneu.marca || '') + ' ' + (pneu.modelo || '')) + '</div>';
            html += '<div><strong>Posição</strong><br>' + escapeHtml(pneu.posicao_nome || slotId || '—') + '</div>';
            html += '<div><strong>Status</strong><br>' + escapeHtml(pneu.status_nome || 'N/A') + '</div>';
            html += '<div><strong>DOT</strong><br>' + escapeHtml(pneu.dot || 'N/A') + '</div>';
            html += '</div>';
            html += '<div style="border-top:1px solid var(--border-color,#e5e7eb);padding-top:12px;">';
            html += '<div style="font-weight:600;margin-bottom:8px;">Análise IA — <span style="color:' + statusColor + ';">' + escapeHtml(statusIa) + '</span></div>';
            html += '<div style="font-size:.9rem;line-height:1.55;">';
            html += '<div><strong>Prioridade:</strong> ' + escapeHtml(String(analise.prioridade || '—')) + '</div>';
            html += '<div><strong>KM restante (est.):</strong> ' + escapeHtml(String(preditiva.kmRestante ?? '—')) + '</div>';
            html += '<div><strong>Troca em (est.):</strong> ' + escapeHtml(String(preditiva.mesesRestante ?? '—')) + ' meses</div>';
            html += '<div style="margin-top:6px;"><strong>Recomendação:</strong> ' + escapeHtml(preditiva.recomendacao || '—') + '</div>';
            if (analise.alertas && analise.alertas.length) {
                html += '<div style="margin-top:8px;color:#b45309;"><strong>Alertas:</strong> ' + escapeHtml(analise.alertas.join('; ')) + '</div>';
            }
            if (analise.recomendacoes && analise.recomendacoes.length) {
                html += '<div style="margin-top:6px;"><strong>Ações sugeridas:</strong> ' + escapeHtml(analise.recomendacoes.join('; ')) + '</div>';
            }
            html += '</div></div>';
            html += '<p style="margin:0;font-size:.85rem;color:var(--text-secondary,#6b7280);">Para histórico, fotos, carcaça e garantias, use <strong>Ficha completa</strong>.</p>';
            html += '</div>';
            return html;
        }

        function mostrarDetalhesPneuModal(pneu, slotId) {
            if (!pneu) return;
            const opts = { html: true, okLabel: 'Fechar' };
            if (pneu.id && window.PneuTimeline) {
                opts.secondaryLabel = 'Ficha completa';
            }
            giInfoModal('Detalhes do pneu', buildPneuDetalhesHtml(pneu, slotId), opts).then(function (act) {
                if (act === 'secondary' && pneu.id && window.PneuTimeline) {
                    PneuTimeline.openFichaModal(pneu.id);
                }
            });
        }

        function mostrarHistoricoPneuModal(pneu) {
            if (!pneu) return;
            let html = '<div style="font-size:.92rem;line-height:1.6;">';
            html += '<p style="margin:0 0 12px;"><strong>Pneu:</strong> ' + escapeHtml(pneu.numero_serie || '—');
            if (pneu.marca || pneu.modelo) {
                html += ' — ' + escapeHtml((pneu.marca || '') + ' ' + (pneu.modelo || ''));
            }
            html += '</p>';
            html += '<div style="border-top:1px solid var(--border-color,#e5e7eb);padding-top:12px;">';
            html += buscarHistoricoPneu(pneu.id);
            html += '</div></div>';
            const opts = { html: true, okLabel: 'Fechar' };
            if (pneu.id && window.PneuTimeline) {
                opts.secondaryLabel = 'Ficha completa';
            }
            giInfoModal('Histórico de manutenção', html, opts).then(function (act) {
                if (act === 'secondary' && pneu.id && window.PneuTimeline) {
                    PneuTimeline.openFichaModal(pneu.id);
                }
            });
        }

        // Confirmação via modal (promise) — usa o modal padrão do sistema (tema-aware).
        function giConfirm(message, opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                var ov = document.createElement('div');
                ov.className = 'modal active';
                ov.style.zIndex = '100000';
                var box = document.createElement('div');
                box.className = 'modal-content';
                box.style.maxWidth = '430px';
                box.innerHTML =
                    '<div class="modal-header"><h2></h2><span class="close-modal" data-act="cancel">&times;</span></div>' +
                    '<div class="modal-body"><p style="margin:0;line-height:1.5;color:var(--text-primary);"></p></div>' +
                    '<div class="modal-footer"><button type="button" class="btn-secondary" data-act="cancel">Cancelar</button><button type="button" class="btn-primary" data-act="ok"></button></div>';
                box.querySelector('h2').textContent = opts.title || 'Confirmação';
                box.querySelector('.modal-body p').textContent = message;
                box.querySelector('[data-act="ok"]').textContent = opts.okLabel || 'Confirmar';
                ov.appendChild(box);
                document.body.appendChild(ov);
                var done = function (v) { ov.remove(); resolve(v); };
                box.querySelectorAll('[data-act="cancel"]').forEach(function (el) { el.onclick = function () { done(false); }; });
                box.querySelector('[data-act="ok"]').onclick = function () { done(true); };
                ov.addEventListener('click', function (e) { if (e.target === ov) done(false); });
            });
        }

        // Seletor em modal (lista clicável) — substitui prompt() de escolha por número.
        // options: [{ label, value, desc? }]. Resolve com o value escolhido, ou null se cancelar.
        function giChoose(title, options, opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                var ov = document.createElement('div');
                ov.className = 'modal active';
                ov.style.zIndex = '100000';
                var box = document.createElement('div');
                box.className = 'modal-content';
                box.style.maxWidth = '460px';
                box.innerHTML =
                    '<div class="modal-header"><h2></h2><span class="close-modal" data-act="cancel">&times;</span></div>' +
                    '<div class="modal-body" style="padding:8px 10px;"></div>';
                box.querySelector('h2').textContent = title;
                var list = box.querySelector('.modal-body');
                (options || []).forEach(function (o) {
                    var b = document.createElement('button');
                    b.type = 'button';
                    b.className = 'gi-choose-item';
                    b.innerHTML = '<span class="gi-choose-label"></span>' + (o.desc ? '<span class="gi-choose-desc"></span>' : '');
                    b.querySelector('.gi-choose-label').textContent = o.label;
                    if (o.desc) b.querySelector('.gi-choose-desc').textContent = o.desc;
                    b.onclick = function () { done(o.value); };
                    list.appendChild(b);
                });
                ov.appendChild(box);
                document.body.appendChild(ov);
                var done = function (v) { ov.remove(); resolve(v); };
                box.querySelector('[data-act="cancel"]').onclick = function () { done(null); };
                ov.addEventListener('click', function (e) { if (e.target === ov) done(null); });
            });
        }

        // Input em modal (texto) — substitui prompt() de texto livre.
        function giPrompt(title, defaultValue, opts) {
            opts = opts || {};
            return new Promise(function (resolve) {
                var ov = document.createElement('div');
                ov.className = 'modal active';
                ov.style.zIndex = '100000';
                var box = document.createElement('div');
                box.className = 'modal-content';
                box.style.maxWidth = '440px';
                box.innerHTML =
                    '<div class="modal-header"><h2></h2><span class="close-modal" data-act="cancel">&times;</span></div>' +
                    '<div class="modal-body"><input type="text" class="gi-prompt-input"></div>' +
                    '<div class="modal-footer"><button type="button" class="btn-secondary" data-act="cancel">Cancelar</button><button type="button" class="btn-primary" data-act="ok">OK</button></div>';
                box.querySelector('h2').textContent = title;
                var input = box.querySelector('input');
                input.value = defaultValue || '';
                if (opts.placeholder) input.placeholder = opts.placeholder;
                ov.appendChild(box);
                document.body.appendChild(ov);
                var done = function (v) { ov.remove(); resolve(v); };
                setTimeout(function () { input.focus(); }, 50);
                input.addEventListener('keydown', function (e) { if (e.key === 'Enter') done(input.value); if (e.key === 'Escape') done(null); });
                box.querySelectorAll('[data-act="cancel"]').forEach(function (el) { el.onclick = function () { done(null); }; });
                box.querySelector('[data-act="ok"]').onclick = function () { done(input.value); };
                ov.addEventListener('click', function (e) { if (e.target === ov) done(null); });
            });
        }

        const veiculosData = <?php echo json_encode($veiculos); ?>;
        let pneusDisponiveis = <?php echo json_encode($pneusDisponiveis); ?>;
        const pneusEmUso = <?php echo json_encode($pneusEmUso); ?>;
        const pneusManutencao = <?php echo json_encode($pneusManutencao); ?>;
        
        // Definir globalmente para compatibilidade
        window.pneusDisponiveis = pneusDisponiveis;
        
        let veiculoSelecionado = null;
        document.addEventListener('DOMContentLoaded', function() {
            
            if (typeof initTheme === 'function') initTheme();
            if (typeof initSidebar === 'function') initSidebar();
            if (typeof initNotifications === 'function') initNotifications();
            
            // Seletor de veículos modo flexível
            const selectorFlex = document.getElementById('veiculoSelectorFlex');
            if (selectorFlex) {
                selectorFlex.addEventListener('change', function() {
                    veiculoSelecionado = this.value;
                    if (veiculoSelecionado) {
                        carregarPneusDisponiveis().then(() => {
                            onSelecionarVeiculoFlexivel(veiculoSelecionado);
                            });
                    } else {
                        resetarFlexivel();
                    }
                });
            }
            
            // Inicializar gráficos após carregar Chart.js
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.onload = function() {
                setTimeout(inicializarGraficos, 500);
            };
            document.head.appendChild(script);

            if (typeof mountPneusIAPanel === 'function') {
                mountPneusIAPanel('pneusIaPanelMount');
            }
        });
        
        function carregarPneusDisponiveis() {
            
            const timestamp = new Date().getTime();
            
            return fetch(`../gestao_interativa/api/pneus_disponiveis.php?t=${timestamp}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                },
                credentials: 'same-origin' // Garantir que cookies de sessão sejam enviados
            })
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.pneus) {
                        // Atualizar a lista global de pneus disponíveis usando Object.assign
                        pneusDisponiveis.length = 0; // Limpar array existente
                        data.pneus.forEach(pneu => pneusDisponiveis.push(pneu)); // Adicionar novos pneus
                        
                        // Atualizar também a variável global
                        window.pneusDisponiveis = pneusDisponiveis;
                        
                        
                        // Verificar se a lista foi realmente atualizada
                        if (pneusDisponiveis.length > 0) {
                        }
                        
                        return pneusDisponiveis;
                    } else {
                        console.error('Erro ao carregar pneus disponíveis:', data.error);
                        throw new Error(data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar pneus disponíveis:', error);
                    throw error;
                })
                .finally(() => {
                });
        }

        // Renderização dos slots com click para alocação/desalocação
        function renderEixoFlex(qtdPneus, numero, tipo, idxEixo) {
            let pneusHTML = '';
            for (let i = 0; i < qtdPneus; i++) {
                const slotId = tipo + '-' + idxEixo + '-' + i;
                const pneu = pneusFlexAlocados[slotId];
                pneusHTML += `<div class='pneu-flex' data-ocupado='${!!pneu}' onclick="mostrarOpcoesPneuFlexivel('${escapeHtml(slotId)}')" title="${pneu ? escapeHtml(tooltipPneu(pneu)) : 'Clique para gerenciar seu pneu'}">
                    ${pneu ? `<span style='font-size:10px;'>${escapeHtml(pneu.numero_serie)}</span>` : ''}
                </div>`;
            }
            return `
                <div class='eixo-flex' data-pneus='${qtdPneus}'>
                    <div class='linha-eixo-flex'></div>
                    ${pneusHTML}
                    <div class='eixo-numero-flex'>${numero}</div>
                </div>
            `;
        }

        // Atualizar função de renderização para usar drag & drop
        function renderizarComponenteVeiculoFlexivel() {
            try {
                // Cavalo
                const cavalo = document.getElementById('componente-cavalo');
                if (cavalo) {
                    let htmlCavalo = `<div class='veiculo-flex'>
                        <div class='linha-central'></div>
                        ${eixosCaminhao.map((eixo, idx) => renderEixoFlexAvancado(eixo.pneus, idx+1, 'cavalo', idx)).join('')}
                    </div>`;
                    cavalo.innerHTML = htmlCavalo;
                }
                
                // Carreta
                const carreta = document.getElementById('componente-carreta');
                if (carreta) {
                    let htmlCarreta = `<div class='veiculo-flex'>
                        <div class='linha-central'></div>
                        ${eixosCarreta.map((eixo, idx) => renderEixoFlexAvancado(eixo.pneus, idx+1, 'carreta', idx)).join('')}
                    </div>`;
                    carreta.innerHTML = htmlCarreta;
                }
                
                // Inicializar drag & drop
                initDragAndDrop();
            } catch (error) {
                console.error('Erro ao renderizar componente veículo flexível:', error);
            }
        }

        function renderizarEixosFlexivel() {
            renderizarComponenteVeiculoFlexivel();
        }

        // Alocação/desalocação de pneus reais
        function mostrarOpcoesPneuFlexivel(slotId) {
            const pneu = pneusFlexAlocados[slotId];
            
            let opcoes = [];
            
            if (!pneu) {
                opcoes = ['Alocar Pneu'];
            } else {
                // Adicionar análise da IA se o pneu existe
                const analise = analisarPneuIA(pneu);
                if (analise.status === 'critico') {
                    opcoes = ['🚨 Análise IA (CRÍTICO)', 'Ficha completa', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                } else if (analise.status === 'atencao') {
                    opcoes = ['⚠️ Análise IA (ATENÇÃO)', 'Ficha completa', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                } else {
                    opcoes = ['✅ Análise IA (BOM)', 'Ficha completa', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                }
            }
            
            giChoose('Opções do pneu', opcoes.map(function (o) { return { label: o, value: o }; })).then(function (acao) {
                if (!acao) return;
            switch(acao) {
                case 'Alocar Pneu':
                    alocarPneuFlexivelPrompt(slotId);
                    break;
                case '🚨 Análise IA (CRÍTICO)':
                case '⚠️ Análise IA (ATENÇÃO)':
                case '✅ Análise IA (BOM)':
                    if (pneu) {
                        mostrarNotificacaoIA(pneu, slotId);
                    }
                    break;
                case 'Ficha completa':
                    if (pneu && pneu.id && window.PneuTimeline) {
                        PneuTimeline.openFichaModal(pneu.id);
                    }
                    break;
                case 'Ver Detalhes':
                    mostrarDetalhesPneuModal(pneu, slotId);
                    break;
                case 'Remover Pneu':
                    if (typeof solicitarRemocaoPneuFlexivel === 'function') {
                        solicitarRemocaoPneuFlexivel(slotId);
                    } else {
                        giConfirm('Remover pneu deste slot?', { okLabel: 'Remover' }).then(function (ok) {
                            if (ok) removerPneuFlexivel(slotId);
                        });
                    }
                    break;
                case 'Enviar para Manutenção':
                    if (pneu) {
                        const msg = analisarPneuIA(pneu).status === 'critico'
                            ? '🚨 Pneu em estado CRÍTICO. Confirma envio urgente para manutenção?'
                            : 'Enviar este pneu para manutenção? Ele será removido do veículo.';
                        giConfirm(msg, { okLabel: 'Enviar' }).then(function (ok) {
                            if (!ok) return;
                            giPrompt('Motivo ou observação (opcional):', '', { placeholder: 'Enviado para manutenção' }).then(function (obsInput) {
                                const obs = (obsInput && obsInput.trim()) ? obsInput.trim() : 'Enviado para manutenção';
                                if (typeof enviarManutencaoFlexivel === 'function') {
                                    enviarManutencaoFlexivel(slotId, obs);
                                }
                            });
                        });
                    }
                    break;
            }
            });
        }

        function alocarPneuFlexivelPrompt(slotId) {
            carregarPneusDisponiveis()
                .then(() => {
                    // Filtrar pneus já alocados em outros slots
                    const idsAlocados = Object.values(pneusFlexAlocados).map(p => p && p.id);
                    const pneusDisponiveisFiltrados = pneusDisponiveis.filter(p => !idsAlocados.includes(p.id));
                    
                    if (!pneusDisponiveisFiltrados || pneusDisponiveisFiltrados.length === 0) {
                        giToast('Não há pneus disponíveis no estoque!');
                        return;
                    }
                    
                    const opcoes = pneusDisponiveisFiltrados.map(function (pneu) {
                        return {
                            label: `${pneu.numero_serie} - ${pneu.marca} ${pneu.modelo}`,
                            desc: `Medida: ${pneu.medida || '—'}`,
                            value: pneu.id
                        };
                    });

                    giChoose('Selecione o pneu para alocar', opcoes).then(function (pneuId) {
                        if (pneuId === null || pneuId === undefined) return;
                    // Usar a nova API para alocar o pneu
                        alocarPneuFlexivel(slotId, pneuId);
                    });
                })
                .catch(error => {
                    console.error('Erro ao alocar pneu:', error);
                    giToast('Erro ao carregar pneus disponíveis: ' + error.message);
                });
        }

        // Função para atualizar o dashboard com pneus em uso
        function atualizarDashboardPneusEmUso() {
            const totalPneusFlexiveis = Object.keys(pneusFlexAlocados).length;
            
            const modernEl = document.getElementById('giKpiEmUso');
            if (modernEl) {
                modernEl.textContent = totalPneusFlexiveis;
            }

            const legacyEl = document.getElementById('pneus-em-uso-count');
            if (legacyEl) {
                legacyEl.textContent = totalPneusFlexiveis;
            }

            const dashboardCards = document.querySelectorAll('.dashboard-card');
            dashboardCards.forEach(card => {
                const header = card.querySelector('.card-header h3');
                if (header && header.textContent.includes('Pneus em Uso')) {
                    const metricValue = card.querySelector('.metric-value');
                    if (metricValue) {
                        metricValue.textContent = totalPneusFlexiveis;
                    }
                }
            });
        }

        function tooltipPneu(pneu) {
            let tooltip = `Série: ${pneu.numero_serie}\nMarca: ${pneu.marca}\nModelo: ${pneu.modelo}\nMedida: ${pneu.medida}`;
            
            // Adicionar posição se disponível
            if (pneu.posicao_nome) {
                tooltip += `\nPosição: ${pneu.posicao_nome}`;
            }
            
            return tooltip;
        }

        // Inicialização do modo flexível
        function resetarFlexivel() {
            eixosCaminhao = [];
            eixosCarreta = [];
            idEixo = 1;
            pneusFlexAlocados = {};
            renderizarEixosFlexivel();
            
            // Limpar histórico e estatísticas
            const historyDiv = document.getElementById('allocationHistory');
            const statsDiv = document.getElementById('vehicleStats');
            
            if (historyDiv) {
                historyDiv.innerHTML = '<p>Selecione um veículo para ver o histórico de alocações de pneus.</p>';
            }
            if (statsDiv) {
                statsDiv.innerHTML = '<p>Selecione um veículo para ver as estatísticas.</p>';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('area-modo-flexivel')) {
                resetarFlexivel();
            }
        });

        // ===== DRAG & DROP AVANÇADO =====
        let dragState = {
            isDragging: false,
            draggedElement: null,
            originalSlot: null,
            draggedPneu: null
        };

        // Inicializar sistema de drag & drop
        function initDragAndDrop() {
            try {
                const areaEstoque = document.getElementById('areaEstoque');
                
                // Event listeners para área de estoque (se existir)
                if (areaEstoque) {
                    areaEstoque.addEventListener('dragover', handleDragOver);
                    areaEstoque.addEventListener('drop', handleDropEstoque);
                    areaEstoque.addEventListener('dragenter', () => areaEstoque.classList.add('drop-active'));
                    areaEstoque.addEventListener('dragleave', () => areaEstoque.classList.remove('drop-active'));
                }
            } catch (error) {
                console.error('Erro ao inicializar drag & drop:', error);
            }
        }

        // Renderizar pneus com drag & drop
        function renderEixoFlexAvancado(qtdPneus, numero, tipo, idxEixo) {
            try {
                let pneusHTML = '';
                for (let i = 0; i < qtdPneus; i++) {
                    const slotId = tipo + '-' + idxEixo + '-' + i;
                    const pneu = pneusFlexAlocados[slotId];
                    const hasPneu = !!pneu;
                    
                    // Determinar classe de status para sombra
                    let statusClass = '';
                    let alertaIndicator = '';
                    
                    if (hasPneu) {
                        const kmAtual = pneu.quilometragem || 0;
                        const kmLimite = 80000;
                        const kmCritico = 100000;
                        
                        if (kmAtual > kmCritico) {
                            statusClass = 'pneu-critico';
                            alertaIndicator = '<div class="alerta-indicator critico"></div>';
                        } else if (kmAtual > (kmLimite * 0.8)) {
                            statusClass = 'pneu-alerta';
                            alertaIndicator = '<div class="alerta-indicator atencao"></div>';
                        } else if (kmAtual > 50000 && Math.random() > 0.7) {
                            statusClass = 'pneu-pressao';
                            alertaIndicator = '<div class="alerta-indicator pressao"></div>';
                        } else {
                            statusClass = 'pneu-bom';
                        }
                    }
                    
                    // Mostrar posição do pneu se disponível
                    let posicaoInfo = '';
                    if (hasPneu && pneu.posicao_nome) {
                        posicaoInfo = `<div style='font-size:8px;color:#666;margin-top:2px;'>${escapeHtml(pneu.posicao_nome)}</div>`;
                    }
                    
                    pneusHTML += `
                        <div class='pneu-flex ${hasPneu ? 'draggable' : 'drop-zone'} ${statusClass}' 
                             data-slot-id='${slotId}' 
                             data-tipo='${tipo}' 
                             data-eixo='${idxEixo}' 
                             data-posicao='${i}'
                             draggable='${hasPneu}'
                             onclick="mostrarOpcoesPneuFlexivel('${escapeHtml(slotId)}')"
                             onmouseenter='showTooltipAvancado(event, ${hasPneu ? escapeHtml(JSON.stringify(pneu)) : 'null'})'
                             onmouseleave='hideTooltipAvancado()'
                             ondragstart='handleDragStart(event, "${escapeHtml(slotId)}")'
                             ondragover='handleDragOver(event)'
                             ondrop='handleDrop(event, "${escapeHtml(slotId)}")'
                             ondragenter='handleDragEnter(event, "${escapeHtml(slotId)}")'
                             ondragleave='handleDragLeave(event, "${escapeHtml(slotId)}")'>
                            ${hasPneu ? `<span style='font-size:10px;'>${escapeHtml(pneu.numero_serie)}</span>` : ''}
                            ${posicaoInfo}
                            ${alertaIndicator}
                        </div>
                    `;
                }
                return `
                    <div class='eixo-flex' data-pneus='${qtdPneus}'>
                        <div class='linha-eixo-flex'></div>
                        ${pneusHTML}
                        <div class='eixo-numero-flex'>${numero}</div>
                    </div>
                `;
            } catch (error) {
                console.error('Erro ao renderizar eixo flexível avançado:', error);
                return `<div class='eixo-flex' data-pneus='${qtdPneus}'>
                    <div class='linha-eixo-flex'></div>
                    <div class='eixo-numero-flex'>${numero}</div>
                </div>`;
            }
        }

        // Event handlers para drag & drop
        function handleDragStart(event, slotId) {
            try {
                const pneu = pneusFlexAlocados[slotId];
                if (!pneu) return;
                
                dragState.isDragging = true;
                dragState.draggedElement = event.target;
                dragState.originalSlot = slotId;
                dragState.draggedPneu = pneu;
                
                event.target.classList.add('dragging');
                event.dataTransfer.setData('text/plain', slotId);
                event.dataTransfer.effectAllowed = 'move';
                
                showDragFeedback('Arrastando pneu...');
            } catch (error) {
                console.error('Erro ao processar drag start:', error);
            }
        }

        function handleDragOver(event) {
            try {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
            } catch (error) {
                console.error('Erro ao processar drag over:', error);
            }
        }

        function handleDragLeave(event, slotId) {
            try {
                const target = event.target.closest('.pneu-flex');
                if (!target) return;
                target.classList.remove('valid', 'invalid');
            } catch (error) {
                console.error('Erro ao processar drag leave:', error);
            }
        }

        function handleDrop(event, targetSlotId) {
            try {
                event.preventDefault();
                
                if (!dragState.isDragging) return;
                
                const target = event.target.closest('.pneu-flex');
                if (!target) return;
                
                if (!validateDrop(targetSlotId)) {
                    showDragFeedback('Posição incompatível!', 'error');
                    resetDragState();
                    target.classList.remove('valid', 'invalid');
                    return;
                }
                
                const fromSlot = dragState.originalSlot;
                const mover = typeof moverPneuFlexivel === 'function'
                    ? moverPneuFlexivel(fromSlot, targetSlotId)
                    : Promise.resolve({ success: false });

                mover.then(function (data) {
                    if (data && data.success) {
                        showDragFeedback('Pneu movido com sucesso!', 'success');
                    } else {
                        showDragFeedback('Não foi possível mover o pneu.', 'error');
                    }
                resetDragState();
                target.classList.remove('valid', 'invalid');
                });
            } catch (error) {
                console.error('Erro ao processar drop:', error);
                resetDragState();
            }
        }

        function handleDropEstoque(event) {
            try {
                event.preventDefault();
                
                if (!dragState.isDragging || !dragState.originalSlot) return;
                
                const slotOrigem = dragState.originalSlot;
                const mover = typeof removerPneuFlexivel === 'function'
                    ? removerPneuFlexivel(slotOrigem)
                    : Promise.resolve({ success: false });

                mover.then(function (data) {
                    if (data && data.success) {
                        showDragFeedback('Pneu retornado ao estoque!', 'success');
                        if (typeof carregarPneusDisponiveis === 'function') {
                            carregarPneusDisponiveis().catch(function () {});
                        }
                    } else {
                        showDragFeedback('Não foi possível remover o pneu do veículo.', 'error');
                    }
                resetDragState();
                const areaEstoque = document.getElementById('areaEstoque');
                if (areaEstoque) {
                    areaEstoque.classList.remove('drop-active');
                }
                });
            } catch (error) {
                console.error('Erro ao processar drop no estoque:', error);
                resetDragState();
            }
        }

        // Validação de drop
        function validateDrop(targetSlotId) {
            try {
                if (!dragState.draggedPneu) return false;
                
                // Verificar se o slot de destino está vazio
                if (pneusFlexAlocados[targetSlotId]) return false;
                
                // Aqui você pode adicionar mais validações específicas
                // Por exemplo: verificar compatibilidade de medidas, etc.
                
                return true;
            } catch (error) {
                console.error('Erro ao validar drop:', error);
                return false;
            }
        }

        // Mover pneu entre slots (persiste via API)
        function movePneu(fromSlotId, toSlotId) {
            try {
                if (!pneusFlexAlocados[fromSlotId]) return;
                if (typeof moverPneuFlexivel === 'function') {
                    moverPneuFlexivel(fromSlotId, toSlotId).then(function (data) {
                        if (data && data.success) {
                showDragFeedback('Pneu movido com sucesso!', 'success');
                        } else {
                            showDragFeedback('Não foi possível mover o pneu.', 'error');
                        }
                    });
                }
            } catch (error) {
                console.error('Erro ao mover pneu:', error);
                showDragFeedback('Erro ao mover pneu.', 'error');
            }
        }

        // Resetar estado de drag
        function resetDragState() {
            try {
                if (dragState.draggedElement) {
                    dragState.draggedElement.classList.remove('dragging');
                }
                
                dragState.isDragging = false;
                dragState.draggedElement = null;
                dragState.originalSlot = null;
                dragState.draggedPneu = null;
                
                hideDragFeedback();
            } catch (error) {
                console.error('Erro ao resetar estado de drag:', error);
            }
        }

        // Feedback visual
        function showDragFeedback(message, type = 'info') {
            try {
                const feedback = document.getElementById('dragFeedback');
                if (feedback) {
                    feedback.textContent = message;
                    feedback.style.display = 'block';
                    
                    // Cor baseada no tipo
                    if (type === 'success') {
                        feedback.style.background = 'rgba(39, 174, 96, 0.9)';
                    } else if (type === 'error') {
                        feedback.style.background = 'rgba(231, 76, 60, 0.9)';
                    } else {
                        feedback.style.background = 'rgba(52, 152, 219, 0.9)';
                    }
                    
                    setTimeout(() => hideDragFeedback(), 2000);
                }
            } catch (error) {
                console.error('Erro ao mostrar feedback de drag:', error);
            }
        }

        function hideDragFeedback() {
            try {
                const feedback = document.getElementById('dragFeedback');
                if (feedback) {
                    feedback.style.display = 'none';
                }
            } catch (error) {
                console.error('Erro ao esconder feedback de drag:', error);
            }
        }

        // ===== TOOLTIPS INTELIGENTES =====
        function showTooltipAvancado(event, pneu) {
            try {
                if (!pneu) {
                    // Se não há pneu, mostrar tooltip básico
                    const tooltip = document.getElementById('tooltipAvancado');
                    if (tooltip) {
                        tooltip.innerHTML = `
                            <div style="margin-bottom: 8px;">
                                <strong>Slot Vazio</strong>
                            </div>
                            <div style="font-size: 11px; line-height: 1.3;">
                                <strong>Status:</strong> Disponível<br>
                                <strong>Ação:</strong> Clique para alocar pneu
                            </div>
                        `;
                        
                        // Posicionar tooltip
                        const rect = event.target.getBoundingClientRect();
                        tooltip.style.left = rect.right + 10 + 'px';
                        tooltip.style.top = rect.top + 'px';
                        tooltip.classList.add('show');
                    }
                    return;
                }
                
                const tooltip = document.getElementById('tooltipAvancado');
                if (!tooltip) return;
                
                // Calcular custo acumulado (exemplo)
                const custoAcumulado = calcularCustoAcumulado(pneu);
                const proximaManutencao = calcularProximaManutencao(pneu);
                const historico = buscarHistoricoPneu(pneu.id);
                
                tooltip.innerHTML = `
                    <div style="margin-bottom: 8px;">
                        <strong>${escapeHtml(pneu.numero_serie)}</strong>
                        <span class="status-indicator status-${escapeHtml(getStatusClass(pneu.status_nome))}"></span>
                    </div>
                    <div style="font-size: 11px; line-height: 1.3;">
                        <strong>Marca:</strong> ${escapeHtml(pneu.marca)}<br>
                        <strong>Modelo:</strong> ${escapeHtml(pneu.modelo)}<br>
                        <strong>Medida:</strong> ${escapeHtml(pneu.medida)}<br>
                        <strong>Posição:</strong> ${escapeHtml(pneu.posicao_nome || 'Não identificada')}<br>
                        <strong>DOT:</strong> ${escapeHtml(pneu.dot || 'N/A')}<br>
                        <strong>KM Atual:</strong> ${escapeHtml(pneu.quilometragem || 'N/A')} km<br>
                        <strong>Próxima Manutenção:</strong> ${escapeHtml(proximaManutencao)}<br>
                        <strong>Custo Acumulado:</strong> R$ ${custoAcumulado}<br>
                        <hr style="margin: 4px 0; border-color: #555;">
                        <strong>Últimas Alocações:</strong><br>
                        ${historico}
                    </div>
                `;
                
                // Posicionar tooltip
                const rect = event.target.getBoundingClientRect();
                tooltip.style.left = rect.right + 10 + 'px';
                tooltip.style.top = rect.top + 'px';
                tooltip.classList.add('show');
            } catch (error) {
                console.error('Erro ao mostrar tooltip avançado:', error);
            }
        }

        function hideTooltipAvancado() {
            try {
                const tooltip = document.getElementById('tooltipAvancado');
                if (tooltip) {
                    tooltip.classList.remove('show');
                }
            } catch (error) {
                console.error('Erro ao esconder tooltip avançado:', error);
            }
        }

        // Funções auxiliares para tooltips
        function calcularCustoAcumulado(pneu) {
            try {
                // Exemplo de cálculo - você pode integrar com dados reais
                return (Math.random() * 1000 + 500).toFixed(2);
            } catch (error) {
                console.error('Erro ao calcular custo acumulado:', error);
                return '0.00';
            }
        }

        function calcularProximaManutencao(pneu) {
            try {
                // Exemplo de cálculo baseado em quilometragem
                const kmAtual = pneu.quilometragem || 0;
                const kmProxima = kmAtual + 50000;
                return `${kmProxima.toLocaleString()} km`;
            } catch (error) {
                console.error('Erro ao calcular próxima manutenção:', error);
                return 'N/A';
            }
        }

        function buscarHistoricoPneu(pneuId) {
            try {
                // Exemplo de histórico - você pode integrar com dados reais
                return `
                    15/01/2024 - Pos. 1 (Caminhão)<br>
                    10/12/2023 - Pos. 3 (Carreta)<br>
                    05/11/2023 - Estoque
                `;
            } catch (error) {
                console.error('Erro ao buscar histórico do pneu:', error);
                return 'N/A';
            }
        }

        function getStatusClass(status) {
            try {
                if (!status) return 'bom';
                if (status.includes('bom') || status.includes('novo')) return 'bom';
                if (status.includes('gasto')) return 'gasto';
                if (status.includes('critico') || status.includes('furado')) return 'critico';
                return 'bom';
            } catch (error) {
                console.error('Erro ao obter classe de status:', error);
                return 'bom';
            }
        }

        function handleDragEnter(event, slotId) {
            try {
                event.preventDefault();
                const target = event.target.closest('.pneu-flex');
                if (!target) return;
                
                // Validar se o drop é válido
                const isValid = validateDrop(slotId);
                target.classList.remove('valid', 'invalid');
                target.classList.add(isValid ? 'valid' : 'invalid');
            } catch (error) {
                console.error('Erro ao processar drag enter:', error);
            }
        }

        // ===== IA - ANÁLISE INTELIGENTE DE PNEUS =====
        
        // Analisar estado do pneu e gerar recomendações
        function analisarPneuIA(pneu) {
            try {
                const analise = {
                    status: 'bom',
                    alertas: [],
                    recomendacoes: [],
                    prioridade: 'baixa'
                };
                
                // Análise de quilometragem
                const kmAtual = pneu.quilometragem || 0;
                const kmLimite = 80000; // Limite recomendado
                const kmCritico = 100000; // Limite crítico
                
                if (kmAtual > kmCritico) {
                    analise.status = 'critico';
                    analise.alertas.push('🚨 Pneu com quilometragem CRÍTICA!');
                    analise.recomendacoes.push('Troca URGENTE recomendada');
                    analise.prioridade = 'alta';
                } else if (kmAtual > kmLimite) {
                    analise.status = 'atencao';
                    analise.alertas.push('⚠️ Pneu próximo do limite de quilometragem');
                    analise.recomendacoes.push('Planejar troca nas próximas semanas');
                    analise.prioridade = 'media';
                }
                
                // Análise de idade (baseada no DOT)
                if (pneu.dot) {
                    const anoDOT = parseInt(pneu.dot.substring(2, 4)) + 2000;
                    const idade = new Date().getFullYear() - anoDOT;
                    
                    if (idade > 6) {
                        analise.alertas.push('📅 Pneu com mais de 6 anos - verificar integridade');
                        analise.recomendacoes.push('Considerar troca por idade');
                        if (analise.prioridade === 'baixa') analise.prioridade = 'media';
                    }
                }
                
                // Análise de status
                const status = pneu.status_nome || '';
                if (status.includes('critico') || status.includes('furado')) {
                    analise.status = 'critico';
                    analise.alertas.push('🔴 Pneu em estado CRÍTICO!');
                    analise.recomendacoes.push('Troca IMEDIATA necessária');
                    analise.prioridade = 'alta';
                } else if (status.includes('gasto')) {
                    analise.status = 'atencao';
                    analise.alertas.push('🟡 Pneu gasto - monitorar desgaste');
                    analise.recomendacoes.push('Verificar sulcos e planejar troca');
                    analise.prioridade = 'media';
                }
                
                // Análise de custo-benefício
                const custoAcumulado = calcularCustoAcumulado(pneu);
                if (custoAcumulado > 2000) {
                    analise.recomendacoes.push('💰 Alto custo acumulado - avaliar recapagem');
                }
                
                return analise;
            } catch (error) {
                console.error('Erro na análise IA do pneu:', error);
                return { status: 'bom', alertas: [], recomendacoes: [], prioridade: 'baixa' };
            }
        }
        
        // Gerar notificação inteligente
        function gerarNotificacaoIA(pneu, slotId) {
            try {
                const analise = analisarPneuIA(pneu);
                const notificacao = {
                    titulo: '',
                    mensagem: '',
                    tipo: 'info',
                    acoes: []
                };
                
                if (analise.status === 'critico') {
                    notificacao.titulo = '🚨 ALERTA CRÍTICO - Pneu ' + pneu.numero_serie;
                    notificacao.tipo = 'critico';
                    notificacao.mensagem = analise.alertas.join('\n') + '\n\n' + analise.recomendacoes.join('\n');
                    notificacao.acoes = ['Trocar Imediatamente', 'Agendar Manutenção', 'Verificar Estoque'];
                } else if (analise.status === 'atencao') {
                    notificacao.titulo = '⚠️ ATENÇÃO - Pneu ' + pneu.numero_serie;
                    notificacao.tipo = 'atencao';
                    notificacao.mensagem = analise.alertas.join('\n') + '\n\n' + analise.recomendacoes.join('\n');
                    notificacao.acoes = ['Planejar Troca', 'Monitorar Desgaste', 'Verificar Histórico'];
                } else {
                    notificacao.titulo = '✅ Pneu ' + pneu.numero_serie + ' - Estado Bom';
                    notificacao.tipo = 'sucesso';
                    notificacao.mensagem = 'Pneu em bom estado. Continue monitorando o desgaste regularmente.';
                    notificacao.acoes = ['Ver Detalhes', 'Histórico de Manutenção'];
                }
                
                return notificacao;
            } catch (error) {
                console.error('Erro ao gerar notificação IA:', error);
                return { titulo: 'Erro', mensagem: 'Erro ao analisar pneu', tipo: 'erro', acoes: [] };
            }
        }
        
        // Mostrar notificação IA
        function mostrarNotificacaoIA(pneu, slotId) {
            try {
                const notificacao = gerarNotificacaoIA(pneu, slotId);
                
                // Criar modal de notificação
                const modal = document.createElement('div');
                modal.className = 'modal-notificacao-ia';
                modal.innerHTML = `
                    <div class="modal-content-ia ${notificacao.tipo}">
                        <div class="modal-header-ia">
                            <h3>${escapeHtml(notificacao.titulo)}</h3>
                            <span class="close-ia" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                        </div>
                        <div class="modal-body-ia">
                            <p>${escapeHtml(notificacao.mensagem)}</p>
                            <div class="acoes-ia">
                                ${notificacao.acoes.map(acao => `<button onclick="executarAcaoIA('${acao}', '${slotId}')">${acao}</button>`).join('')}
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Auto-remover após 10 segundos se não for crítica
                if (notificacao.tipo !== 'critico') {
                    setTimeout(() => {
                        if (modal.parentElement) {
                            modal.remove();
                        }
                    }, 10000);
                }
            } catch (error) {
                console.error('Erro ao mostrar notificação IA:', error);
            }
        }
        
        // Executar ação da IA
        function executarAcaoIA(acao, slotId) {
            try {
                const pneu = pneusFlexAlocados[slotId];
                
                switch(acao) {
                    case 'Trocar Imediatamente':
                        giToast('Ação: Trocar pneu imediatamente\n\nPróximos passos:\n1. Remover pneu do veículo\n2. Verificar estoque de reposição\n3. Alocar novo pneu\n4. Registrar troca no sistema');
                        break;
                    case 'Agendar Manutenção':
                        giToast('Ação: Agendar manutenção\n\nPróximos passos:\n1. Verificar disponibilidade da oficina\n2. Agendar data e horário\n3. Preparar pneu de reposição\n4. Notificar motorista');
                        break;
                    case 'Verificar Estoque':
                        giToast('Verificando estoque de pneus compatíveis...');
                        // Aqui você pode integrar com a API de estoque
                        break;
                    case 'Planejar Troca':
                        giToast('Ação: Planejar troca\n\nRecomendações:\n1. Monitorar desgaste semanalmente\n2. Preparar pneu de reposição\n3. Agendar troca para próxima manutenção\n4. Documentar planejamento');
                        break;
                    case 'Monitorar Desgaste':
                        giToast('Ação: Monitorar desgaste\n\nChecklist:\n1. Verificar sulcos (mínimo 1.6mm)\n2. Observar desgaste irregular\n3. Medir pressão regularmente\n4. Registrar medições');
                        break;
                    case 'Verificar Histórico':
                        mostrarHistoricoPneuModal(pneu);
                        break;
                    case 'Ver Detalhes':
                        mostrarDetalhesPneuModal(pneu, slotId);
                        break;
                    case 'Histórico de Manutenção':
                        mostrarHistoricoPneuModal(pneu);
                        break;
                }
                
                // Remover modal
                const modal = document.querySelector('.modal-notificacao-ia');
                if (modal) modal.remove();
            } catch (error) {
                console.error('Erro ao executar ação IA:', error);
            }
        }
        
        // Análise preditiva de desgaste
        function analisePreditivaDesgaste(pneu) {
            try {
                const kmAtual = pneu.quilometragem || 0;
                const kmPorMes = 5000; // Estimativa
                const kmRestante = 80000 - kmAtual;
                const mesesRestante = Math.floor(kmRestante / kmPorMes);
                
                return {
                    kmRestante: kmRestante,
                    mesesRestante: mesesRestante,
                    recomendacao: mesesRestante <= 3 ? 'Troca Imediata' : 
                                 mesesRestante <= 6 ? 'Planejar Troca' : 'Monitorar'
                };
            } catch (error) {
                console.error('Erro na análise preditiva:', error);
                return { kmRestante: 0, mesesRestante: 0, recomendacao: 'Erro' };
            }
        }
        
        // ===== GRÁFICOS INTERATIVOS =====
        function inicializarGraficos() {
            try {
                // Verificar se Chart.js está carregado
                if (typeof Chart === 'undefined') {
                    setTimeout(inicializarGraficos, 1000);
                    return;
                }
                
                // Gráfico de pizza - Distribuição de pneus por status
                const canvasPizza = document.getElementById('graficoPizza');
                if (canvasPizza) {
                    const ctxPizza = canvasPizza.getContext('2d');
                    
                    // Usar as variáveis globais que já estão definidas
                    const pneusDisponiveisCount = pneusDisponiveis.length;
                    const pneusEmUsoCount = pneusEmUso.length;
                    const pneusManutencaoCount = pneusManutencao.length;
                    
                    // Pneus críticos = pneus em uso com status crítico (pode ser baseado em data de instalação ou outro critério)
                    // Por enquanto, vamos usar 20% dos pneus em uso como críticos para demonstração
                    const pneusCriticosCount = Math.round(pneusEmUsoCount * 0.2);
                    
                    new Chart(ctxPizza, {
                        type: 'doughnut',
                        data: {
                            labels: ['Disponíveis', 'Em Uso', 'Manutenção', 'Críticos'],
                            datasets: [{
                                data: [
                                    pneusDisponiveisCount,
                                    pneusEmUsoCount,
                                    pneusManutencaoCount,
                                    pneusCriticosCount
                                ],
                                backgroundColor: [
                                    '#10B981', // Verde
                                    '#3B82F6', // Azul
                                    '#F59E0B', // Amarelo
                                    '#EF4444'  // Vermelho
                                ],
                                borderWidth: 2,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Distribuição de Pneus por Status',
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                                    font: { size: 16, weight: 'bold' }
                                }
                            }
                        }
                    });
                }
                
                // Gráfico de barras - Tendência de uso ao longo do tempo
                const canvasBarras = document.getElementById('graficoBarras');
                if (canvasBarras) {
                    const ctxBarras = canvasBarras.getContext('2d');
                    new Chart(ctxBarras, {
                        type: 'bar',
                        data: {
                            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Pneus Alocados',
                                data: [12, 15, 18, 14, 20, 22],
                                backgroundColor: '#3B82F6',
                                borderColor: '#2563EB',
                                borderWidth: 1
                            }, {
                                label: 'Pneus Trocados',
                                data: [3, 5, 4, 6, 8, 7],
                                backgroundColor: '#EF4444',
                                borderColor: '#DC2626',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Tendência de Uso de Pneus (Últimos 6 Meses)',
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                                    font: { size: 16, weight: 'bold' }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                                    },
                                    grid: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                                    },
                                    grid: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Gráfico de linha - KPIs de Performance
                const canvasKPIs = document.getElementById('graficoKPIs');
                if (canvasKPIs) {
                    const ctxKPIs = canvasKPIs.getContext('2d');
                    new Chart(ctxKPIs, {
                        type: 'line',
                        data: {
                            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                            datasets: [{
                                label: 'Eficiência (%)',
                                data: [85, 88, 92, 89, 94, 96],
                                borderColor: '#10B981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                tension: 0.4,
                                fill: true
                            }, {
                                label: 'Custo por KM (R$)',
                                data: [0.15, 0.14, 0.13, 0.12, 0.11, 0.10],
                                borderColor: '#F59E0B',
                                backgroundColor: 'rgba(245, 158, 11, 0.1)',
                                tension: 0.4,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                                    }
                                },
                                title: {
                                    display: true,
                                    text: 'Indicadores de Performance (KPIs)',
                                    color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary'),
                                    font: { size: 16, weight: 'bold' }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                                    },
                                    grid: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                                    }
                                },
                                x: {
                                    ticks: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                                    },
                                    grid: {
                                        color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                                    }
                                }
                            }
                        }
                    });
                }
                
            } catch (error) {
                console.error('Erro ao inicializar gráficos:', error);
            }
        }
        
        // Atualizar gráficos quando dados mudarem
        function atualizarGraficos() {
            try {
                // Remover gráficos existentes
                const canvasPizza = document.getElementById('graficoPizza');
                const canvasBarras = document.getElementById('graficoBarras');
                const canvasKPIs = document.getElementById('graficoKPIs');
                
                if (canvasPizza) canvasPizza.style.display = 'none';
                if (canvasBarras) canvasBarras.style.display = 'none';
                if (canvasKPIs) canvasKPIs.style.display = 'none';
                
                // Recriar gráficos
                setTimeout(() => {
                    if (canvasPizza) canvasPizza.style.display = 'block';
                    if (canvasBarras) canvasBarras.style.display = 'block';
                    if (canvasKPIs) canvasKPIs.style.display = 'block';
                    inicializarGraficos();
                }, 100);
            } catch (error) {
                console.error('Erro ao atualizar gráficos:', error);
            }
        }

        // Função para carregar histórico no modo flexível
        function carregarHistoricoFlexivel(veiculoId) {
            fetch(`../gestao_interativa/api/historico_alocacoes.php?veiculo_id=${veiculoId}`, {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    const historyDiv = document.getElementById('allocationHistory');
                    
                    if (data.success && data.historico && data.historico.length > 0) {
                        let html = '<div style="max-height: 200px; overflow-y: auto;">';
                        data.historico.forEach(item => {
                            const acao = item.data_remocao ? 'Remoção' : 'Alocação';
                            const data = item.data_remocao || item.data_instalacao;
                            html += `
                                <div style="padding: 8px; border-bottom: 1px solid #ddd; font-size: 0.9em;">
                                    <strong>${data}</strong><br>
                                    ${acao}: ${item.numero_serie} (Pos. ${item.posicao})<br>
                                    <span class="status-badge status-${item.status_nome ? item.status_nome.toLowerCase().replace(' ', '-') : 'bom'}">${item.status_nome || 'Bom'}</span>
                                </div>
                            `;
                        });
                        html += '</div>';
                        historyDiv.innerHTML = html;
                    } else {
                        historyDiv.innerHTML = '<p>Nenhum histórico encontrado para este veículo.</p>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar histórico flexível:', error);
                    const historyDiv = document.getElementById('allocationHistory');
                    if (historyDiv) {
                        historyDiv.innerHTML = '<p>Erro ao carregar histórico.</p>';
                    }
                });
        }

        // Função para carregar estatísticas no modo flexível
        function carregarEstatisticasFlexivel(veiculoId) {
            fetch(`../gestao_interativa/api/estatisticas_veiculo.php?veiculo_id=${veiculoId}`, {
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    const statsDiv = document.getElementById('vehicleStats');
                    
                    if (data.success && data.estatisticas) {
                        const stats = data.estatisticas;
                        statsDiv.innerHTML = `
                            <div style="font-size: 0.9em;">
                                <p><strong>Pneus Ativos:</strong> ${stats.pneusAtivos}</p>
                                <p><strong>Em Manutenção:</strong> ${stats.pneusManutencao}</p>
                                <p><strong>Descartados:</strong> ${stats.pneusDescartados}</p>
                                <p><strong>KM Médio:</strong> ${stats.quilometragemMedia.toLocaleString()} km</p>
                                <p><strong>Total de Alocações:</strong> ${stats.totalAlocacoes}</p>
                            </div>
                        `;
                    } else {
                        statsDiv.innerHTML = '<p>Erro ao carregar estatísticas.</p>';
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar estatísticas flexível:', error);
                    const statsDiv = document.getElementById('vehicleStats');
                    if (statsDiv) {
                        statsDiv.innerHTML = '<p>Erro ao carregar estatísticas.</p>';
                    }
                });
        }

        // Função de fallback para sistema antigo (JSON)
        function carregarLayoutFallback(veiculoId) {
            fetch('../gestao_interativa/api/layout_flexivel.php?veiculo_id=' + veiculoId, {
                credentials: 'same-origin'
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.layout) {
                        eixosCaminhao = data.layout.eixosCaminhao || [];
                        eixosCarreta = data.layout.eixosCarreta || [];
                        idEixo = data.layout.idEixo || 1;
                        pneusFlexAlocados = data.layout.pneusFlexAlocados || {};
                    } else {
                        eixosCaminhao = [];
                        eixosCarreta = [];
                        idEixo = 1;
                        pneusFlexAlocados = {};
                    }
                    renderizarEixosFlexivel();
                    atualizarDashboardPneusEmUso();
                });
        }
        
        // Controle de Modais
        document.addEventListener('DOMContentLoaded', function() {
            // Modal de Filtros
            const filterBtn = document.getElementById('filterBtn');
            const filterModal = document.getElementById('filterModal');
            const filterClose = filterModal?.querySelector('.close-modal');
            
            if (filterBtn && filterModal) {
                filterBtn.addEventListener('click', function() {
                    filterModal.style.display = 'flex';
                });
            }
            
            if (filterClose) {
                filterClose.addEventListener('click', function() {
                    filterModal.style.display = 'none';
                });
            }
            
            // Fechar modal de filtros ao clicar fora
            window.addEventListener('click', function(event) {
                if (event.target === filterModal) {
                    filterModal.style.display = 'none';
                }
            });
            
            // Botão de Exportar
            const exportBtn = document.getElementById('exportBtn');
            if (exportBtn) {
                exportBtn.addEventListener('click', function() {
                    exportarDados();
                });
            }
        });
        
        // Função para aplicar filtros
        function aplicarFiltros() {
            const status = document.getElementById('filterStatus')?.value || '';
            const marca = document.getElementById('filterMarca')?.value || '';
            const modelo = document.getElementById('filterModelo')?.value || '';
            const numeroSerie = document.getElementById('filterNumeroSerie')?.value || '';
            const veiculo = document.getElementById('filterVeiculo')?.value || '';
            
            // Aplicar filtros nos pneus disponíveis
            if (window.pneusDisponiveis) {
                let pneusFiltrados = window.pneusDisponiveis.filter(pneu => {
                    if (status && status !== 'disponivel') return false;
                    if (marca && !pneu.marca?.toLowerCase().includes(marca.toLowerCase())) return false;
                    if (modelo && !pneu.modelo?.toLowerCase().includes(modelo.toLowerCase())) return false;
                    if (numeroSerie && !pneu.numero_serie?.includes(numeroSerie)) return false;
                    return true;
                });
                
                // Atualizar área de estoque ou lista de pneus disponíveis
                giToast(`Filtros aplicados! ${pneusFiltrados.length} pneu(s) encontrado(s).`);
            }
            
            // Se houver veículo selecionado, recarregar
            const veiculoSelecionado = document.getElementById('veiculoSelectorFlex')?.value;
            if (veiculoSelecionado && veiculo === veiculoSelecionado) {
                onSelecionarVeiculoFlexivel(veiculoSelecionado);
            }
            
            // Fechar modal
            document.getElementById('filterModal').style.display = 'none';
        }
        
        // Função para limpar filtros
        function limparFiltros() {
            document.getElementById('filterStatus').value = '';
            document.getElementById('filterMarca').value = '';
            document.getElementById('filterModelo').value = '';
            document.getElementById('filterNumeroSerie').value = '';
            document.getElementById('filterVeiculo').value = '';
            
            // Recarregar dados sem filtros
            if (window.carregarPneusDisponiveis) {
                carregarPneusDisponiveis().then(() => {
                    const veiculoSelecionado = document.getElementById('veiculoSelectorFlex')?.value;
                    if (veiculoSelecionado) {
                        onSelecionarVeiculoFlexivel(veiculoSelecionado);
                    }
                });
            }
            
            document.getElementById('filterModal').style.display = 'none';
        }
        
        // Função para exportar dados
        function exportarDados() {
            const veiculoSelecionado = document.getElementById('veiculoSelectorFlex')?.value;
            
            if (!veiculoSelecionado) {
                giToast('Por favor, selecione um veículo primeiro.');
                return;
            }
            
            // Criar dados para exportação
            const dados = {
                veiculo: veiculoSelecionado,
                data: new Date().toLocaleDateString('pt-BR'),
                pneus: window.pneusEmUso || [],
                pneusDisponiveis: window.pneusDisponiveis || []
            };
            
            // Converter para CSV
            let csv = 'Gestão de Pneus - Relatório\n';
            csv += `Data: ${dados.data}\n`;
            csv += `Veículo: ${veiculoSelecionado}\n\n`;
            csv += 'Pneus em Uso\n';
            csv += 'Número de Série,Marca,Modelo,Status,Posição\n';
            
            if (dados.pneus.length > 0) {
                dados.pneus.forEach(pneu => {
                    csv += `${pneu.numero_serie || ''},${pneu.marca || ''},${pneu.modelo || ''},${pneu.status_nome || ''},${pneu.posicao || ''}\n`;
                });
            } else {
                csv += 'Nenhum pneu em uso\n';
            }
            
            csv += '\nPneus Disponíveis\n';
            csv += 'Número de Série,Marca,Modelo,Status\n';
            
            if (dados.pneusDisponiveis.length > 0) {
                dados.pneusDisponiveis.forEach(pneu => {
                    csv += `${pneu.numero_serie || ''},${pneu.marca || ''},${pneu.modelo || ''},${pneu.status_nome || ''}\n`;
                });
            } else {
                csv += 'Nenhum pneu disponível\n';
            }
            
            // Download do arquivo
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', `relatorio_pneus_${new Date().getTime()}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            giToast('Relatório exportado com sucesso!');
        }
    </script>
    
    <!-- Novo sistema de eixos com banco de dados -->
    <script src="../js/gestao_interativa_eixos.js?v=2.1"></script>
    
    <!-- Modal de Filtros -->
    <div class="modal" id="filterModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2><i class="fas fa-filter"></i> Filtros de Pneus</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="filterStatus">Status:</label>
                    <select id="filterStatus" class="form-control">
                        <option value="">Todos</option>
                        <option value="disponivel">Disponível</option>
                        <option value="em_uso">Em Uso</option>
                        <option value="manutencao">Em Manutenção</option>
                        <option value="critico">Crítico</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filterMarca">Marca:</label>
                    <input type="text" id="filterMarca" class="form-control" placeholder="Ex: Michelin, Bridgestone...">
                </div>
                <div class="form-group">
                    <label for="filterModelo">Modelo:</label>
                    <input type="text" id="filterModelo" class="form-control" placeholder="Ex: XZA2, R268...">
                </div>
                <div class="form-group">
                    <label for="filterNumeroSerie">Número de Série:</label>
                    <input type="text" id="filterNumeroSerie" class="form-control" placeholder="Buscar por número de série">
                </div>
                <div class="form-group">
                    <label for="filterVeiculo">Veículo:</label>
                    <select id="filterVeiculo" class="form-control">
                        <option value="">Todos</option>
                        <?php foreach ($veiculos as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= $v['placa'] ?> - <?= $v['modelo'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="aplicarFiltros()" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i> Aplicar Filtros
                    </button>
                    <button onclick="limparFiltros()" class="btn btn-secondary" style="flex: 1;">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Ajuda -->
    <?php $sf_help_key = 'gestao_interativa'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 