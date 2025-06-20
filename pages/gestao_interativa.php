<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

require_authentication();

$page_title = "Gestão Interativa de Pneus";

try {
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->prepare("SELECT * FROM veiculos WHERE empresa_id = ?");
    $stmt->execute([$_SESSION['empresa_id']]);
    $veiculos = $stmt->fetchAll();
    
    $pneusDisponiveis = [];
    $pneusEmUso = [];
    $pneusManutencao = [];
    
    try {
        // Buscar pneus disponíveis (status_id 2 = disponível, 5 = novo/bom)
        // Pneus que não estão atualmente instalados em nenhum veículo
        $stmt = $pdo->prepare("SELECT p.*, sp.nome as status_nome 
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              WHERE p.empresa_id = ? AND p.status_id IN (2, 5)
                              AND p.id NOT IN (
                                  SELECT pneu_id 
                                  FROM instalacoes_pneus 
                                  WHERE data_remocao IS NULL
                              )");
        $stmt->execute([$_SESSION['empresa_id']]);
        $pneusDisponiveis = $stmt->fetchAll();
        
        error_log("Pneus disponíveis encontrados: " . count($pneusDisponiveis));
        
        // Buscar pneus em uso (status_id 5 = em uso)
        // Pneus que estão atualmente instalados
        $stmt = $pdo->prepare("SELECT p.*, sp.nome as status_nome, ip.veiculo_id, ip.posicao
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              INNER JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                              WHERE p.empresa_id = ? AND p.status_id = 5
                              AND ip.data_remocao IS NULL");
        $stmt->execute([$_SESSION['empresa_id']]);
        $pneusEmUso = $stmt->fetchAll();
        
        error_log("Pneus em uso encontrados: " . count($pneusEmUso));
        
        // Buscar pneus em manutenção (status_id 1 = furado/manutenção)
        $stmt = $pdo->prepare("SELECT p.*, sp.nome as status_nome 
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              WHERE p.empresa_id = ? AND p.status_id = 1");
        $stmt->execute([$_SESSION['empresa_id']]);
        $pneusManutencao = $stmt->fetchAll();
        
        error_log("Pneus em manutenção encontrados: " . count($pneusManutencao));
    } catch (PDOException $e) {
        error_log("Erro ao buscar pneus: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Erro na conexão com banco: " . $e->getMessage());
    $error = "Erro ao conectar com o banco de dados: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
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
        
        .vehicle-section {
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
        
        @keyframes pulse-critico {
            0% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4), 0 2px 4px rgba(231, 76, 60, 0.2); }
            50% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.6), 0 2px 4px rgba(231, 76, 60, 0.4); }
            100% { box-shadow: 0 4px 12px rgba(231, 76, 60, 0.4), 0 2px 4px rgba(231, 76, 60, 0.2); }
        }
        
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
        
        /* Layout da seção principal */
        .main-content-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
            margin-top: 20px;
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
            height: 500px;
            display: flex;
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
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error; ?>
                    </div>
                <?php else: ?>
                    <!-- Estatísticas -->
                    <div class="dashboard-grid">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-truck"></i> Veículos</h3>
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
                                <h3><i class="fas fa-circle"></i> Pneus Disponíveis</h3>
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
                                <h3><i class="fas fa-cog"></i> Pneus em Uso</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value"><?php echo count($pneusEmUso); ?></span>
                                    <span class="metric-subtitle">Instalados</span>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h3><i class="fas fa-tools"></i> Em Manutenção</h3>
                            </div>
                            <div class="card-body">
                                <div class="metric">
                                    <span class="metric-value"><?php echo count($pneusManutencao); ?></span>
                                    <span class="metric-subtitle">Em reparo</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Conteúdo Principal -->
                    <div class="main-content-grid">
                        <!-- Seção de Veículos -->
                        <div class="vehicle-section">
                            <h2><i class="fas fa-truck"></i> Gestão de Pneus por Veículo</h2>
                            
                            <ul class="nav nav-tabs" id="pneusTab" style="margin-bottom: 20px;">
                                <li class="nav-item">
                                    <a class="nav-link active" id="tab-padrao" href="#" onclick="mostrarModoPadrao(event)">Modo Padrão</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="tab-flexivel" href="#" onclick="mostrarModoFlexivel(event)">Modo Flexível</a>
                                </li>
                            </ul>

                            <div id="area-modo-padrao">
                                <select id="vehicleSelector" class="vehicle-selector">
                                    <option value="">Selecione um veículo...</option>
                                    <?php foreach ($veiculos as $veiculo): ?>
                                        <option value="<?php echo $veiculo['id']; ?>" 
                                                data-tipo="<?php echo $veiculo['tipo_veiculo'] ?? 'truck'; ?>"
                                                data-placa="<?php echo $veiculo['placa']; ?>"
                                                data-modelo="<?php echo $veiculo['modelo']; ?>">
                                            <?php echo $veiculo['placa']; ?> - <?php echo $veiculo['modelo']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <div id="vehicleInfo" class="vehicle-info" style="display: none;">
                                    <div class="vehicle-image" id="vehicleImage">
                                        🚛
                                    </div>
                                    <div class="vehicle-details">
                                        <div class="vehicle-type" id="vehicleType">Tipo do Veículo</div>
                                        <div class="vehicle-plate" id="vehiclePlate">Placa</div>
                                    </div>
                                </div>
                                
                                                                <!-- Legenda -->
                                                                <div class="legend">
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
                                
                                <!-- Grid de Pneus -->
                                <div class="tire-grid-container">
                                    <div id="tireGrid" class="tire-grid">
                                        <!-- Grid será gerado dinamicamente baseado no tipo de veículo -->
                                    </div>
                                </div>
                            </div>

                            <div id="area-modo-flexivel" style="display:none;">
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
                                    <button class="btn btn-secondary" onclick="voltarModoPadrao()">Voltar ao Padrão</button>
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
                            </div>
                        </div>
                        
                        <!-- Sidebar -->
                        <div class="sidebar-section">
                            <h3><i class="fas fa-history"></i> Histórico de Alocações</h3>
                            <div id="allocationHistory">
                                <p>Selecione um veículo para ver o histórico de alocações de pneus.</p>
                            </div>
                            
                            <h3><i class="fas fa-chart-line"></i> Estatísticas do Veículo</h3>
                            <div id="vehicleStats">
                                <p>Selecione um veículo para ver as estatísticas.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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
    
    <!-- Scripts do sistema -->
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/header.js"></script>

    <script>
        const veiculosData = <?php echo json_encode($veiculos); ?>;
        let pneusDisponiveis = <?php echo json_encode($pneusDisponiveis); ?>;
        const pneusEmUso = <?php echo json_encode($pneusEmUso); ?>;
        const pneusManutencao = <?php echo json_encode($pneusManutencao); ?>;
        
        let veiculoSelecionado = null;
        let veiculoAtual = null;
        
        // Configurações de layout por tipo de veículo
        const vehicleLayouts = {
            'truck': {
                type: 'truck-6x2',
                positions: [
                    '1 - Dianteira Esquerda', '2 - Dianteira Direita',
                    '3 - Traseira Esquerda 1', '4 - Traseira Direita 1',
                    '5 - Traseira Esquerda 2', '6 - Traseira Direita 2'
                ],
                image: '🚛'
            },
            'truck-6x4': {
                type: 'truck-6x4',
                positions: [
                    '1 - Dianteira Esquerda', '2 - Dianteira Direita',
                    '3 - Traseira Esquerda 1', '4 - Traseira Direita 1',
                    '5 - Traseira Esquerda 2', '6 - Traseira Direita 2'
                ],
                image: '🚛'
            },
            'trailer': {
                type: 'trailer',
                positions: [
                    '1 - Esquerda Dianteira', '2 - Direita Dianteira',
                    '3 - Esquerda Traseira', '4 - Direita Traseira'
                ],
                image: '🚛'
            },
            'tractor': {
                type: 'tractor',
                positions: [
                    '1 - Dianteira Esquerda', '2 - Dianteira Direita',
                    '3 - Traseira Esquerda 1', '4 - Traseira Direita 1',
                    '5 - Traseira Esquerda 2', '6 - Traseira Direita 2'
                ],
                image: '🚛'
            }
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Página Gestão Interativa carregada com sucesso!');
            
            if (typeof initTheme === 'function') initTheme();
            if (typeof initSidebar === 'function') initSidebar();
            if (typeof initNotifications === 'function') initNotifications();
            
            const vehicleSelector = document.getElementById('vehicleSelector');
            if (vehicleSelector) {
                vehicleSelector.addEventListener('change', function() {
                    const veiculoId = this.value;
                    if (veiculoId) {
                        console.log('Veículo selecionado:', veiculoId);
                        carregarPneusVeiculo(veiculoId);
                    } else {
                        limparGridPneus();
                    }
                });
            }

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
        });
        
        function carregarPneusVeiculo(veiculoId) {
            console.log('Carregando pneus para o veículo:', veiculoId);
            veiculoSelecionado = veiculoId;
            
            // Buscar dados do veículo
            const veiculo = veiculosData.find(v => v.id == veiculoId);
            if (veiculo) {
                veiculoAtual = veiculo;
                mostrarInfoVeiculo(veiculo);
                gerarGridPneus(veiculo);
            }
            
            // Buscar pneus realmente alocados a este veículo
            buscarPneusAlocados(veiculoId);
            
            carregarHistorico(veiculoId);
            carregarEstatisticas(veiculoId);
        }
        
        function mostrarInfoVeiculo(veiculo) {
            const vehicleInfo = document.getElementById('vehicleInfo');
            const vehicleImage = document.getElementById('vehicleImage');
            const vehicleType = document.getElementById('vehicleType');
            const vehiclePlate = document.getElementById('vehiclePlate');
            
            const tipo = veiculo.tipo_veiculo || 'truck';
            const layout = vehicleLayouts[tipo] || vehicleLayouts['truck'];
            
            vehicleImage.innerHTML = layout.image;
            vehicleType.textContent = `${veiculo.modelo} (${tipo.toUpperCase()})`;
            vehiclePlate.textContent = veiculo.placa;
            
            vehicleInfo.style.display = 'flex';
        }
        
        function gerarGridPneus(veiculo) {
            const tireGrid = document.getElementById('tireGrid');
            const tipo = veiculo.tipo_veiculo || 'truck';
            const layout = vehicleLayouts[tipo] || vehicleLayouts['truck'];
            
            // Limpar grid anterior
            tireGrid.innerHTML = '';
            tireGrid.className = `tire-grid ${layout.type}`;
            
            // Gerar slots baseado no layout
            layout.positions.forEach((posicao, index) => {
                const slot = document.createElement('div');
                slot.className = 'tire-slot';
                slot.dataset.position = index + 1;
                slot.dataset.posicaoNome = posicao;
                
                slot.innerHTML = `
                    <div class="tire-position">${posicao}</div>
                    <div class="tire-info">Vazio</div>
                    <div class="tire-details">Clique para gerenciar pneu</div>
                `;
                
                slot.addEventListener('click', function() {
                    const position = this.dataset.position;
                    console.log('Slot clicado:', position, '-', posicao);
                    if (veiculoSelecionado) {
                        mostrarOpcoesPneu(position, this);
                    }
                });
                
                tireGrid.appendChild(slot);
            });
        }
        
        function buscarPneusAlocados(veiculoId) {
            fetch(`../gestao_interativa/api/pneus_veiculo.php?veiculo_id=${veiculoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.pneus_alocados) {
                        console.log('Pneus alocados encontrados:', data.pneus_alocados);
                        
                        data.pneus_alocados.forEach(pneu => {
                            const posicao = pneu.posicao;
                            const slot = document.querySelector(`[data-position="${posicao}"]`);
                            
                            if (slot) {
                                slot.classList.add('occupied');
                                slot.querySelector('.tire-info').textContent = `${pneu.numero_serie}\n${pneu.marca}`;
                                
                                // Adicionar informações detalhadas para hover
                                slot.querySelector('.tire-details').innerHTML = `
                                    <strong>${pneu.numero_serie}</strong><br>
                                    Marca: ${pneu.marca}<br>
                                    Modelo: ${pneu.modelo}<br>
                                    Medida: ${pneu.medida}<br>
                                    Status: ${pneu.status}<br>
                                    KM: ${pneu.quilometragem || 'N/A'}
                                `;
                                
                                slot.dataset.pneuId = pneu.pneu_id;
                                slot.dataset.pneuSerie = pneu.numero_serie;
                                slot.dataset.instalacaoId = pneu.id;
                            }
                        });
                    } else {
                        console.log('Nenhum pneu alocado encontrado para este veículo');
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar pneus alocados:', error);
                });
        }
        
        function limparGridPneus() {
            veiculoSelecionado = null;
            veiculoAtual = null;
            
            document.getElementById('vehicleInfo').style.display = 'none';
            document.getElementById('tireGrid').innerHTML = '<div class="loading">Selecione um veículo para gerenciar os pneus</div>';
            
            document.getElementById('allocationHistory').innerHTML = '<p>Selecione um veículo para ver o histórico de alocações de pneus.</p>';
            document.getElementById('vehicleStats').innerHTML = '<p>Selecione um veículo para ver as estatísticas.</p>';
        }
        
        function mostrarOpcoesPneu(position, slot) {
            const isOccupied = slot.classList.contains('occupied');
            const isMaintenance = slot.classList.contains('maintenance');
            const isCritical = slot.classList.contains('critical');
            
            let opcoes = [];
            
            if (!isOccupied && !isMaintenance && !isCritical) {
                opcoes = ['Alocar Pneu'];
            } else if (isOccupied) {
                opcoes = ['Ver Detalhes', 'Enviar para Manutenção', 'Retornar ao Estoque'];
            } else if (isMaintenance) {
                opcoes = ['Ver Detalhes', 'Retornar ao Estoque', 'Descartar Pneu'];
            } else if (isCritical) {
                opcoes = ['Ver Detalhes'];
            }
            
            if (opcoes.length === 0) {
                alert('Nenhuma ação disponível para este pneu.');
                return;
            }
            
            const opcao = prompt(`Opções para posição ${position}:\n\n${opcoes.join('\n')}\n\nDigite o número da opção (1-${opcoes.length}):`);
            const index = parseInt(opcao) - 1;
            
            if (isNaN(index) || index < 0 || index >= opcoes.length) {
                console.log('Opção inválida');
                return;
            }
            
            const acao = opcoes[index];
            
            switch(acao) {
                case 'Alocar Pneu':
                    alocarPneu(position, slot);
                    break;
                case 'Ver Detalhes':
                    verDetalhesPneu(slot);
                    break;
                case 'Enviar para Manutenção':
                    enviarManutencao(position, slot);
                    break;
                case 'Retornar ao Estoque':
                    retornarEstoque(position, slot);
                    break;
                case 'Descartar Pneu':
                    descartarPneu(position, slot);
                    break;
                default:
                    console.log('Ação não reconhecida');
            }
        }
        
        function verDetalhesPneu(slot) {
            const pneuId = slot.dataset.pneuId;
            const pneuSerie = slot.dataset.pneuSerie;
            
            if (!pneuId) {
                alert('Nenhum pneu encontrado nesta posição!');
                return;
            }
            
            // Buscar detalhes completos do pneu
            fetch(`../gestao_interativa/api/pneu_detalhes.php?pneu_id=${pneuId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const pneu = data.pneu;
                        alert(`Detalhes do Pneu:\n\n` +
                              `Número de Série: ${pneu.numero_serie}\n` +
                              `Marca: ${pneu.marca}\n` +
                              `Modelo: ${pneu.modelo}\n` +
                              `Medida: ${pneu.medida}\n` +
                              `Status: ${pneu.status}\n` +
                              `Quilometragem: ${pneu.quilometragem || 'N/A'} km\n` +
                              `Data de Instalação: ${pneu.data_instalacao || 'N/A'}\n` +
                              `Última Manutenção: ${pneu.ultima_manutencao || 'N/A'}`);
                    } else {
                        alert('Erro ao buscar detalhes do pneu: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao buscar detalhes do pneu');
                });
        }
        
        function alocarPneu(position = null, slot = null) {
            if (!veiculoSelecionado) {
                alert('Selecione um veículo primeiro!');
                return;
            }
            
            console.log('Pneus disponíveis:', pneusDisponiveis);
            
            if (pneusDisponiveis.length === 0) {
                alert('Não há pneus disponíveis no estoque!\n\nVerifique se existem pneus com status "disponível" ou "novo" que não estejam instalados em outros veículos.');
                return;
            }
            
            // Mostrar lista de pneus disponíveis para seleção
            let opcoes = 'Pneus disponíveis:\n\n';
            pneusDisponiveis.forEach((pneu, index) => {
                opcoes += `${index + 1}. ${pneu.numero_serie} - ${pneu.marca} ${pneu.modelo} (${pneu.medida})\n`;
            });
            
            const escolha = prompt(opcoes + '\nDigite o número do pneu que deseja alocar:');
            const index = parseInt(escolha) - 1;
            
            if (isNaN(index) || index < 0 || index >= pneusDisponiveis.length) {
                alert('Seleção inválida!');
                return;
            }
            
            const pneuSelecionado = pneusDisponiveis[index];
            console.log('Alocando pneu:', pneuSelecionado, 'para posição:', position);
            
            const dados = {
                veiculo_id: veiculoSelecionado,
                pneu_id: pneuSelecionado.id,
                posicao: position,
                acao: 'alocar'
            };
            
            console.log('Dados para API:', dados);
            
            fetch('../gestao_interativa/api/teste_alocacao.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dados)
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', e);
                    throw new Error('Resposta inválida da API: ' + text);
                }
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    if (slot) {
                        slot.classList.add('occupied');
                        slot.querySelector('.tire-info').textContent = `${pneuSelecionado.numero_serie}\n${pneuSelecionado.marca}`;
                        slot.dataset.pneuId = pneuSelecionado.id;
                        slot.dataset.pneuSerie = pneuSelecionado.numero_serie;
                        
                        // Atualizar detalhes do hover
                        slot.querySelector('.tire-details').innerHTML = `
                            <strong>${pneuSelecionado.numero_serie}</strong><br>
                            Marca: ${pneuSelecionado.marca}<br>
                            Modelo: ${pneuSelecionado.modelo}<br>
                            Medida: ${pneuSelecionado.medida}<br>
                            Status: Em Uso
                        `;
                    }
                    
                    // Remover pneu da lista de disponíveis
                    pneusDisponiveis.splice(index, 1);
                    
                    alert(`Pneu ${pneuSelecionado.numero_serie} alocado com sucesso na posição ${position || 'selecionada'}!`);
                    carregarHistorico(veiculoSelecionado);
                } else {
                    alert('Erro ao alocar pneu: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Erro completo:', error);
                alert('Erro ao salvar alocação do pneu: ' + error.message);
            });
        }
        
        function enviarManutencao(position = null, slot = null) {
            if (!veiculoSelecionado) {
                alert('Selecione um veículo primeiro!');
                return;
            }
            
            const pneuId = slot ? slot.dataset.pneuId : null;
            if (!pneuId) {
                alert('Nenhum pneu encontrado nesta posição!');
                return;
            }
            
            if (confirm('Tem certeza que deseja enviar este pneu para manutenção?')) {
                const dados = {
                    veiculo_id: veiculoSelecionado,
                    pneu_id: pneuId,
                    posicao: position,
                    acao: 'manutencao'
                };
                
                fetch('../gestao_interativa/api/teste_alocacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(dados)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (slot) {
                            slot.classList.remove('occupied');
                            slot.classList.add('maintenance');
                            slot.querySelector('.tire-info').textContent = 'Manutenção';
                            slot.querySelector('.tire-details').innerHTML = 'Pneu em manutenção';
                            delete slot.dataset.pneuId;
                            delete slot.dataset.pneuSerie;
                        }
                        alert(`Pneu enviado para manutenção na posição ${position || 'selecionada'}!`);
                        
                        // Recarregar lista de pneus disponíveis
                        carregarPneusDisponiveis();
                    } else {
                        alert('Erro ao enviar pneu para manutenção: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao salvar alteração do pneu');
                });
            }
        }
        
        function retornarEstoque(position = null, slot = null) {
            if (!veiculoSelecionado) {
                alert('Selecione um veículo primeiro!');
                return;
            }
            
            const pneuId = slot ? slot.dataset.pneuId : null;
            if (!pneuId) {
                alert('Nenhum pneu encontrado nesta posição!');
                return;
            }
            
            if (confirm('Tem certeza que deseja retornar este pneu ao estoque?')) {
                const dados = {
                    veiculo_id: veiculoSelecionado,
                    pneu_id: pneuId,
                    posicao: position,
                    acao: 'remover'
                };
                
                fetch('../gestao_interativa/api/teste_alocacao.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(dados)
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Resposta da API (remover):', data);
                    if (data.success) {
                        if (slot) {
                            slot.classList.remove('occupied', 'maintenance', 'critical');
                            slot.querySelector('.tire-info').textContent = 'Vazio';
                            slot.querySelector('.tire-details').innerHTML = 'Clique para gerenciar pneu';
                            delete slot.dataset.pneuId;
                            delete slot.dataset.pneuSerie;
                        }
                        alert(`Pneu retornado ao estoque da posição ${position || 'selecionada'}!`);
                        carregarHistorico(veiculoSelecionado);
                        
                        // Recarregar lista de pneus disponíveis em tempo real
                        console.log('Recarregando pneus disponíveis em tempo real...');
                        carregarPneusDisponiveis()
                            .then(() => {
                                console.log('Lista atualizada com sucesso. Pneus disponíveis:', pneusDisponiveis.length);
                            })
                            .catch(error => {
                                console.error('Erro ao atualizar lista:', error);
                            });
                    } else {
                        alert('Erro ao retornar pneu ao estoque: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Erro completo (remover):', error);
                    alert('Erro ao salvar alteração do pneu');
                });
            }
        }
        
        function descartarPneu(position = null, slot = null) {
            if (!veiculoSelecionado) {
                alert('Selecione um veículo primeiro!');
                return;
            }
            
            if (confirm('Tem certeza que deseja descartar este pneu?')) {
                if (slot) {
                    slot.classList.remove('occupied', 'maintenance');
                    slot.classList.add('critical');
                    slot.querySelector('.tire-info').textContent = 'Descartado';
                    slot.querySelector('.tire-details').innerHTML = 'Pneu descartado';
                }
                
                alert(`Pneu descartado da posição ${position || 'selecionada'}!`);
                carregarHistorico(veiculoSelecionado);
            }
        }
        
        function carregarHistorico(veiculoId) {
            fetch(`../gestao_interativa/api/historico_alocacoes.php?veiculo_id=${veiculoId}`)
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
                                    <span class="status-badge status-${item.status_nome.toLowerCase().replace(' ', '-')}">${item.status_nome}</span>
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
                    console.error('Erro ao carregar histórico:', error);
                    document.getElementById('allocationHistory').innerHTML = '<p>Erro ao carregar histórico.</p>';
                });
        }
        
        function carregarEstatisticas(veiculoId) {
            const stats = {
                pneusAtivos: 4,
                pneusManutencao: 1,
                pneusDescartados: 0,
                quilometragemMedia: 45000
            };
            
            const statsDiv = document.getElementById('vehicleStats');
            statsDiv.innerHTML = `
                <div style="font-size: 0.9em;">
                    <p><strong>Pneus Ativos:</strong> ${stats.pneusAtivos}</p>
                    <p><strong>Em Manutenção:</strong> ${stats.pneusManutencao}</p>
                    <p><strong>Descartados:</strong> ${stats.pneusDescartados}</p>
                    <p><strong>KM Médio:</strong> ${stats.quilometragemMedia.toLocaleString()} km</p>
                </div>
            `;
        }
        
        function carregarPneusDisponiveis() {
            console.log('=== INICIANDO CARREGAMENTO DE PNEUS DISPONÍVEIS ===');
            
            const timestamp = new Date().getTime();
            
            return fetch(`../gestao_interativa/api/pneus_disponiveis.php?empresa_id=1&t=${timestamp}`, {
                method: 'GET',
                headers: {
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da API pneus_disponiveis:', data);
                    if (data.success && data.pneus) {
                        // Atualizar a lista global de pneus disponíveis usando Object.assign
                        pneusDisponiveis.length = 0; // Limpar array existente
                        data.pneus.forEach(pneu => pneusDisponiveis.push(pneu)); // Adicionar novos pneus
                        console.log('Lista de pneus disponíveis atualizada:', pneusDisponiveis);
                        console.log('Quantidade de pneus disponíveis:', pneusDisponiveis.length);
                        
                        // Verificar se a lista foi realmente atualizada
                        if (pneusDisponiveis.length > 0) {
                            console.log('Primeiro pneu disponível:', pneusDisponiveis[0]);
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
                    console.log('=== FIM CARREGAMENTO DE PNEUS DISPONÍVEIS ===');
                });
        }

        // Alternância entre modos
        function mostrarModoPadrao(e) {
            e.preventDefault();
            document.getElementById('area-modo-padrao').style.display = '';
            document.getElementById('area-modo-flexivel').style.display = 'none';
            document.getElementById('tab-padrao').classList.add('active');
            document.getElementById('tab-flexivel').classList.remove('active');
        }
        function mostrarModoFlexivel(e) {
            e.preventDefault();
            document.getElementById('area-modo-padrao').style.display = 'none';
            document.getElementById('area-modo-flexivel').style.display = '';
            document.getElementById('tab-padrao').classList.remove('active');
            document.getElementById('tab-flexivel').classList.add('active');
        }
        function voltarModoPadrao() {
            mostrarModoPadrao({preventDefault:()=>{}});
        }

        // Estado dos eixos e pneus alocados no modo flexível
        let eixosCaminhao = [];
        let eixosCarreta = [];
        let idEixo = 1;
        let pneusFlexAlocados = {}; // { slotId: pneuObj }

        // Ao selecionar veículo, carregar layout e pneus alocados
        function onSelecionarVeiculoFlexivel(veiculoId) {
            fetch('../gestao_interativa/api/layout_flexivel.php?veiculo_id=' + veiculoId)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.layout) {
                        eixosCaminhao = data.layout.eixosCaminhao || [];
                        eixosCarreta = data.layout.eixosCarreta || [];
                        idEixo = data.layout.idEixo || 1;
                        // Reconectar pneus alocados aos dados atuais
                        const salvos = data.layout.pneusFlexAlocados || {};
                        pneusFlexAlocados = {};
                        for (const slotId in salvos) {
                            const pneuSalvo = salvos[slotId];
                            // Tenta encontrar o pneu pelo ID na lista global (pode estar em uso, então busca em todas as listas)
                            let pneuAtual = null;
                            if (window.pneusDisponiveis) {
                                pneuAtual = window.pneusDisponiveis.find(p => p.id == pneuSalvo.id);
                            }
                            // Se não encontrar, mantém o snapshot salvo
                            pneusFlexAlocados[slotId] = pneuAtual || pneuSalvo;
                        }
                    } else {
                        eixosCaminhao = [];
                        eixosCarreta = [];
                        idEixo = 1;
                        pneusFlexAlocados = {};
                    }
                    renderizarEixosFlexivel();
                });
        }

        // Renderização dos slots com click para alocação/desalocação
        function renderEixoFlex(qtdPneus, numero, tipo, idxEixo) {
            let pneusHTML = '';
            for (let i = 0; i < qtdPneus; i++) {
                const slotId = tipo + '-' + idxEixo + '-' + i;
                const pneu = pneusFlexAlocados[slotId];
                pneusHTML += `<div class='pneu-flex' data-ocupado='${!!pneu}' onclick="mostrarOpcoesPneuFlexivel('${slotId}')" title="${pneu ? tooltipPneu(pneu) : 'Clique para gerenciar seu pneu'}">
                    ${pneu ? `<span style='font-size:10px;'>${pneu.numero_serie}</span>` : ''}
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
                    opcoes = ['🚨 Análise IA (CRÍTICO)', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                } else if (analise.status === 'atencao') {
                    opcoes = ['⚠️ Análise IA (ATENÇÃO)', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                } else {
                    opcoes = ['✅ Análise IA (BOM)', 'Ver Detalhes', 'Remover Pneu', 'Enviar para Manutenção'];
                }
            }
            
            const opcao = prompt(`Opções para o slot ${slotId}:\n\n${opcoes.map((o,i)=>`${i+1}. ${o}`).join('\n')}\n\nDigite o número da opção (1-${opcoes.length}):`);
            const index = parseInt(opcao) - 1;
            
            if (isNaN(index) || index < 0 || index >= opcoes.length) {
                return;
            }
            
            const acao = opcoes[index];
            
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
                case 'Ver Detalhes':
                    if (pneu) {
                        const analise = analisarPneuIA(pneu);
                        const preditiva = analisePreditivaDesgaste(pneu);
                        const detalhes = `
                            Pneu: ${pneu.numero_serie}
                            Marca: ${pneu.marca}
                            Modelo: ${pneu.modelo}
                            Medida: ${pneu.medida}
                            Status: ${pneu.status_nome || 'N/A'}
                            KM Atual: ${pneu.quilometragem || 'N/A'}
                            DOT: ${pneu.dot || 'N/A'}
                            
                            === ANÁLISE IA ===
                            Status: ${analise.status.toUpperCase()}
                            Prioridade: ${analise.prioridade}
                            KM Restante: ${preditiva.kmRestante}
                            Meses Restante: ${preditiva.mesesRestante}
                            Recomendação: ${preditiva.recomendacao}
                            
                            Alertas: ${analise.alertas.join(', ')}
                            Recomendações: ${analise.recomendacoes.join(', ')}
                        `;
                        alert(detalhes);
                    }
                    break;
                case 'Remover Pneu':
                    if (confirm('Remover pneu deste slot?')) {
                        delete pneusFlexAlocados[slotId];
                        renderizarEixosFlexivel();
                        salvarLayoutFlexivel(true);
                    }
                    break;
                case 'Enviar para Manutenção':
                    if (pneu) {
                        const analise = analisarPneuIA(pneu);
                        if (analise.status === 'critico') {
                            alert('🚨 PNEU CRÍTICO!\n\nAção: Enviar para manutenção URGENTE\n\nPróximos passos:\n1. Remover pneu do veículo\n2. Registrar motivo da manutenção\n3. Enviar para oficina credenciada\n4. Alocar pneu de reposição');
                        } else {
                            alert('Funcionalidade de manutenção será implementada em breve.');
                        }
                    }
                    break;
            }
        }

        function alocarPneuFlexivelPrompt(slotId) {
            carregarPneusDisponiveis()
                .then(() => {
                    // Filtrar pneus já alocados em outros slots
                    const idsAlocados = Object.values(pneusFlexAlocados).map(p => p && p.id);
                    const pneusDisponiveisFiltrados = pneusDisponiveis.filter(p => !idsAlocados.includes(p.id));
                    
                    if (!pneusDisponiveisFiltrados || pneusDisponiveisFiltrados.length === 0) {
                        alert('Não há pneus disponíveis no estoque!');
                        return;
                    }
                    
                    let opcoes = 'Pneus disponíveis:\n\n';
                    pneusDisponiveisFiltrados.forEach((pneu, index) => {
                        opcoes += `${index + 1}. ${pneu.numero_serie} - ${pneu.marca} ${pneu.modelo} (${pneu.medida})\n`;
                    });
                    
                    const escolha = prompt(opcoes + '\nDigite o número do pneu que deseja alocar:');
                    const index = parseInt(escolha) - 1;
                    
                    if (isNaN(index) || index < 0 || index >= pneusDisponiveisFiltrados.length) {
                        alert('Seleção inválida!');
                        return;
                    }
                    
                    const pneuSelecionado = pneusDisponiveisFiltrados[index];
                    pneusFlexAlocados[slotId] = pneuSelecionado;
                    
                    renderizarEixosFlexivel();
                    salvarLayoutFlexivel(true);
                })
                .catch(error => {
                    console.error('Erro ao alocar pneu:', error);
                    alert('Erro ao carregar pneus disponíveis: ' + error.message);
                });
        }

        function tooltipPneu(pneu) {
            return `Série: ${pneu.numero_serie}\nMarca: ${pneu.marca}\nModelo: ${pneu.modelo}\nMedida: ${pneu.medida}`;
        }

        // Inicialização do modo flexível
        function resetarFlexivel() {
            eixosCaminhao = [];
            eixosCarreta = [];
            idEixo = 1;
            pneusFlexAlocados = {};
            renderizarEixosFlexivel();
        }
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('area-modo-flexivel')) {
                resetarFlexivel();
            }
        });

        function adicionarEixo(tipo) {
            let qtd = prompt('Quantos pneus por eixo? (1 = rodado simples/2 pneus, 2 = rodado duplo/4 pneus)', '1');
            qtd = parseInt(qtd);
            if (isNaN(qtd) || (qtd !== 1 && qtd !== 2)) {
                alert('Escolha 1 (simples) ou 2 (duplo)');
                return;
            }
            const novoEixo = {
                id: idEixo++,
                pneus: qtd === 1 ? 2 : 4
            };
            if (tipo === 'caminhao') {
                eixosCaminhao.push(novoEixo);
            } else {
                eixosCarreta.push(novoEixo);
            }
            renderizarEixosFlexivel();
            salvarLayoutFlexivel(true);
        }

        function excluirEixo(tipo) {
            const eixos = tipo === 'caminhao' ? eixosCaminhao : eixosCarreta;
            
            if (eixos.length === 0) {
                alert(`Não há eixos para excluir no ${tipo === 'caminhao' ? 'caminhão' : 'carreta'}.`);
                return;
            }
            
            // Verificar quais eixos têm pneus alocados
            const eixosComPneus = [];
            const eixosSemPneus = [];
            
            eixos.forEach((eixo, idx) => {
                let temPneus = false;
                const qtdPneus = eixo.pneus;
                
                // Verificar se algum pneu está alocado neste eixo
                for (let i = 0; i < qtdPneus; i++) {
                    const slotId = tipo === 'caminhao' ? `cavalo-${idx}-${i}` : `carreta-${idx}-${i}`;
                    if (pneusFlexAlocados[slotId]) {
                        temPneus = true;
                        break;
                    }
                }
                
                if (temPneus) {
                    eixosComPneus.push(idx + 1);
                } else {
                    eixosSemPneus.push(idx + 1);
                }
            });
            
            if (eixosSemPneus.length === 0) {
                alert(`Todos os eixos do ${tipo === 'caminhao' ? 'caminhão' : 'carreta'} têm pneus alocados. Remova os pneus primeiro.`);
                return;
            }
            
            // Mostrar opções de eixos que podem ser excluídos
            let opcoes = `Eixos disponíveis para exclusão:\n\n`;
            eixosSemPneus.forEach(numero => {
                opcoes += `${numero}. Eixo ${numero}\n`;
            });
            
            if (eixosComPneus.length > 0) {
                opcoes += `\nEixos com pneus (não podem ser excluídos): ${eixosComPneus.join(', ')}`;
            }
            
            const escolha = prompt(opcoes + '\n\nDigite o número do eixo que deseja excluir:');
            const index = parseInt(escolha) - 1;
            
            if (isNaN(index) || index < 0 || !eixosSemPneus.includes(index + 1)) {
                alert('Seleção inválida!');
                return;
            }
            
            if (confirm(`Tem certeza que deseja excluir o Eixo ${index + 1}?`)) {
                if (tipo === 'caminhao') {
                    eixosCaminhao.splice(index, 1);
                } else {
                    eixosCarreta.splice(index, 1);
                }
                renderizarEixosFlexivel();
                salvarLayoutFlexivel(true);
                alert(`Eixo ${index + 1} excluído com sucesso!`);
            }
        }

        function salvarLayoutFlexivel(auto = false) {
            const veiculoId = veiculoSelecionado;
            if (!veiculoId) {
                if (!auto) alert('Selecione um veículo primeiro!');
                return;
            }
            const layout = {
                eixosCaminhao,
                eixosCarreta,
                idEixo,
                pneusFlexAlocados
            };
            fetch('../gestao_interativa/api/layout_flexivel.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ veiculo_id: veiculoId, layout })
            })
                .then(r => r.json())
                .then(data => {
                    if (!auto) {
                        if (data.success) {
                            alert('Layout salvo com sucesso!');
                        } else {
                            alert('Erro ao salvar layout: ' + data.error);
                        }
                    }
                });
        }

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
                    if (hasPneu) {
                        const status = pneu.status_nome || '';
                        if (status.includes('critico') || status.includes('furado') || status.includes('gasto')) {
                            statusClass = 'pneu-critico';
                        } else {
                            statusClass = 'pneu-bom';
                        }
                    }
                    
                    pneusHTML += `
                        <div class='pneu-flex ${hasPneu ? 'draggable' : 'drop-zone'} ${statusClass}' 
                             data-slot-id='${slotId}' 
                             data-tipo='${tipo}' 
                             data-eixo='${idxEixo}' 
                             data-posicao='${i}'
                             draggable='${hasPneu}'
                             onclick="mostrarOpcoesPneuFlexivel('${slotId}')"
                             onmouseenter='showTooltipAvancado(event, ${hasPneu ? JSON.stringify(pneu) : 'null'})'
                             onmouseleave='hideTooltipAvancado()'
                             ondragstart='handleDragStart(event, "${slotId}")'
                             ondragover='handleDragOver(event)'
                             ondrop='handleDrop(event, "${slotId}")'
                             ondragenter='handleDragEnter(event, "${slotId}")'
                             ondragleave='handleDragLeave(event, "${slotId}")'>
                            ${hasPneu ? `<span style='font-size:10px;'>${pneu.numero_serie}</span>` : ''}
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
                
                // Validar drop
                if (!validateDrop(targetSlotId)) {
                    showDragFeedback('Posição incompatível!', 'error');
                    return;
                }
                
                // Executar movimento
                movePneu(dragState.originalSlot, targetSlotId);
                
                // Limpar estado
                resetDragState();
                target.classList.remove('valid', 'invalid');
            } catch (error) {
                console.error('Erro ao processar drop:', error);
            }
        }

        function handleDropEstoque(event) {
            try {
                event.preventDefault();
                
                if (!dragState.isDragging) return;
                
                // Remover pneu do slot original
                delete pneusFlexAlocados[dragState.originalSlot];
                
                showDragFeedback('Pneu movido para estoque!', 'success');
                
                // Atualizar interface
                renderizarEixosFlexivel();
                salvarLayoutFlexivel(true);
                
                // Limpar estado
                resetDragState();
                
                const areaEstoque = document.getElementById('areaEstoque');
                if (areaEstoque) {
                    areaEstoque.classList.remove('drop-active');
                }
            } catch (error) {
                console.error('Erro ao processar drop no estoque:', error);
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

        // Mover pneu entre slots
        function movePneu(fromSlotId, toSlotId) {
            try {
                const pneu = pneusFlexAlocados[fromSlotId];
                if (!pneu) return;
                
                // Remover do slot original
                delete pneusFlexAlocados[fromSlotId];
                
                // Adicionar ao novo slot
                pneusFlexAlocados[toSlotId] = pneu;
                
                showDragFeedback('Pneu movido com sucesso!', 'success');
                
                // Atualizar interface
                renderizarEixosFlexivel();
                salvarLayoutFlexivel(true);
            } catch (error) {
                console.error('Erro ao mover pneu:', error);
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
                        <strong>${pneu.numero_serie}</strong>
                        <span class="status-indicator status-${getStatusClass(pneu.status_nome)}"></span>
                    </div>
                    <div style="font-size: 11px; line-height: 1.3;">
                        <strong>Marca:</strong> ${pneu.marca}<br>
                        <strong>Modelo:</strong> ${pneu.modelo}<br>
                        <strong>Medida:</strong> ${pneu.medida}<br>
                        <strong>DOT:</strong> ${pneu.dot || 'N/A'}<br>
                        <strong>KM Atual:</strong> ${pneu.quilometragem || 'N/A'} km<br>
                        <strong>Próxima Manutenção:</strong> ${proximaManutencao}<br>
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
                            <h3>${notificacao.titulo}</h3>
                            <span class="close-ia" onclick="this.parentElement.parentElement.parentElement.remove()">&times;</span>
                        </div>
                        <div class="modal-body-ia">
                            <p>${notificacao.mensagem}</p>
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
                        alert('Ação: Trocar pneu imediatamente\n\nPróximos passos:\n1. Remover pneu do veículo\n2. Verificar estoque de reposição\n3. Alocar novo pneu\n4. Registrar troca no sistema');
                        break;
                    case 'Agendar Manutenção':
                        alert('Ação: Agendar manutenção\n\nPróximos passos:\n1. Verificar disponibilidade da oficina\n2. Agendar data e horário\n3. Preparar pneu de reposição\n4. Notificar motorista');
                        break;
                    case 'Verificar Estoque':
                        alert('Verificando estoque de pneus compatíveis...');
                        // Aqui você pode integrar com a API de estoque
                        break;
                    case 'Planejar Troca':
                        alert('Ação: Planejar troca\n\nRecomendações:\n1. Monitorar desgaste semanalmente\n2. Preparar pneu de reposição\n3. Agendar troca para próxima manutenção\n4. Documentar planejamento');
                        break;
                    case 'Monitorar Desgaste':
                        alert('Ação: Monitorar desgaste\n\nChecklist:\n1. Verificar sulcos (mínimo 1.6mm)\n2. Observar desgaste irregular\n3. Medir pressão regularmente\n4. Registrar medições');
                        break;
                    case 'Verificar Histórico':
                        alert('Histórico do pneu:\n\n' + buscarHistoricoPneu(pneu.id));
                        break;
                    case 'Ver Detalhes':
                        if (pneu) {
                            const detalhes = `
                                Pneu: ${pneu.numero_serie}
                                Marca: ${pneu.marca}
                                Modelo: ${pneu.modelo}
                                Medida: ${pneu.medida}
                                Status: ${pneu.status_nome || 'N/A'}
                                KM: ${pneu.quilometragem || 'N/A'}
                                DOT: ${pneu.dot || 'N/A'}
                            `;
                            alert(detalhes);
                        }
                        break;
                    case 'Histórico de Manutenção':
                        alert('Histórico de manutenção:\n\n' + buscarHistoricoPneu(pneu.id));
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
    </script>
</body>
</html> 