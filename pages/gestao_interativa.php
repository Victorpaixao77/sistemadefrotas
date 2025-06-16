<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Set page title
$page_title = "Gestão Interativa";

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Função para buscar dados dos pneus
function getPneusData($conn, $empresa_id, $veiculo_id = null) {
    try {
        // Verificar se as tabelas existem
        $tables = ['pneus', 'veiculos', 'status_pneus', 'posicoes_pneus', 'eixos', 'eixo_pneus'];
        foreach ($tables as $table) {
            $stmt = $conn->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() == 0) {
                throw new Exception("Tabela $table não encontrada");
            }
        }

        // Buscar estrutura dos eixos do veículo
        $eixos_sql = "SELECT e.*, 
                            COUNT(ep.id) as pneus_alocados,
                            GROUP_CONCAT(DISTINCT ep.posicao_id) as posicoes_ocupadas
                     FROM eixos e 
                     LEFT JOIN eixo_pneus ep ON e.id = ep.eixo_id AND ep.status = 'alocado'
                     WHERE e.veiculo_id = :veiculo_id 
                     GROUP BY e.id
                     ORDER BY e.posicao_id";
        $eixos_stmt = $conn->prepare($eixos_sql);
        $eixos_stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $eixos_stmt->execute();
        $eixos = $eixos_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar pneus alocados no veículo
        $pneus_sql = "SELECT 
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
                p.status_id,
                        e.id as eixo_id,
                        e.posicao_id as eixo_posicao,
                        ep.posicao_id as pneu_posicao,
                        ep.data_alocacao,
                        ep.km_alocacao,
                        ep.km_desalocacao,
                        ep.status as alocacao_status,
                        ep.observacoes as alocacao_obs
            FROM pneus p
                    INNER JOIN eixo_pneus ep ON p.id = ep.pneu_id
                    INNER JOIN eixos e ON ep.eixo_id = e.id
                    LEFT JOIN status_pneus sp ON p.status_id = sp.id
                    WHERE e.veiculo_id = :veiculo_id
                    AND ep.status = 'alocado'
                    ORDER BY e.posicao_id, ep.posicao_id";
        $pneus_stmt = $conn->prepare($pneus_sql);
        $pneus_stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $pneus_stmt->execute();
        $pneus = $pneus_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar histórico de alocações
        $historico_sql = "SELECT 
                            p.id as pneu_id,
                            p.numero_serie,
                            e.id as eixo_id,
                            e.posicao_id as eixo_posicao,
                            ep.posicao_id as pneu_posicao,
                            ep.data_alocacao,
                            ep.km_alocacao,
                            ep.data_desalocacao,
                            ep.km_desalocacao,
                            ep.status as alocacao_status,
                            ep.observacoes
                        FROM eixo_pneus ep
                        INNER JOIN pneus p ON ep.pneu_id = p.id
                        INNER JOIN eixos e ON ep.eixo_id = e.id
                        WHERE e.veiculo_id = :veiculo_id
                        ORDER BY ep.data_alocacao DESC, ep.data_desalocacao DESC";
        $historico_stmt = $conn->prepare($historico_sql);
        $historico_stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
        $historico_stmt->execute();
        $historico = $historico_stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'eixos' => $eixos,
            'pneus' => $pneus,
            'historico' => $historico
        ];
    } catch (Exception $e) {
        error_log("Erro ao buscar dados dos pneus: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("Erro ao buscar dados dos pneus: " . $e->getMessage());
    }
}

// Função para buscar veículos
function getVeiculos($conn, $empresa_id) {
    try {
        $sql = "SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :empresa_id ORDER BY placa";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Erro ao buscar veículos: " . $e->getMessage());
        throw new Exception("Erro ao buscar veículos");
    }
}

try {
    $conn = getConnection();
    $pneusData = getPneusData($conn, $empresa_id);
    $veiculos = getVeiculos($conn, $empresa_id);
} catch (Exception $e) {
    $error = $e->getMessage();
    $veiculos = [];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <style>
        .gestao-pneus-container {
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .veiculo-selector {
            margin-bottom: 20px;
        }
        
        .veiculo-selector select {
            padding: 8px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            width: 200px;
        }
        
        #componente-veiculo-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 30px;
            transition: transform 0.6s ease;
            transform-origin: center center;
        }
        #componente-veiculo-wrapper.rotacionado {
            transform: rotate(90deg);
        }
        
        .veiculo {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            min-width: 220px;
            min-height: 350px;
            justify-content: center;
            height: 100%;
        }
        .veiculo-centralizado {
            min-height: 400px;
        }
        .linha-central {
            position: absolute;
            left: 50%;
            top: 0;
            width: 8px;
            height: 100%;
            background: black;
            border-radius: 4px;
            transform: translateX(-50%);
            z-index: 0;
        }
        .eixos-wrapper,
        .drop-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .eixo {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 24px 0;
            position: relative;
            z-index: 1;
            gap: 0;
        }
        
        .linha-eixo {
            position: absolute;
            left: 50%;
            top: 50%;
            height: 8px;
            background: #000;
            border-radius: 4px;
            transform: translate(-50%, -50%);
            z-index: 0;
        }
        
        .eixo .pneu {
            margin: 0 2px;
            z-index: 1;
        }
        
        .pneu {
            width: 30px;
            height: 60px;
            background: radial-gradient(#333, #111);
            border-radius: 10px;
            cursor: pointer;
            transition: transform 0.2s, background 0.2s, box-shadow 0.3s;
            box-shadow: 0 4px 8px rgba(0,0,0,0.6);
            position: relative;
            z-index: 1;
        }
        
        .pneu:hover {
            transform: scale(1.1);
            background: radial-gradient(#555, #222);
            z-index: 2;
        }
        
        .pneu.bom { box-shadow: 0 0 5px 2px green; }
        .pneu.gasto { box-shadow: 0 0 5px 2px orange; }
        .pneu.furado { box-shadow: 0 0 5px 2px red; }
        
        .pneu.alerta::after {
            content: "⚠️";
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 14px;
        }
        
        .tooltip {
            position: absolute;
            bottom: -60px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(51, 51, 51, 0.95);
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 12px;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 9999;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            max-width: 200px;
            text-align: center;
        }
        
        .pneu:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .pneu.rodizio {
            border: 2px dashed yellow;
        }
        
        .pneu.vazio {
            background: radial-gradient(#666, #444);
            opacity: 0.5;
        }
        
        .view-controls {
            margin-bottom: 20px;
            display: flex;
            flex-direction: row;
            gap: 10px;
            justify-content: center;
            align-items: center;
            flex-wrap: nowrap;
        }
        
        .view-controls button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            background: var(--primary-color);
            color: white;
            cursor: pointer;
            transition: background 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            min-width: fit-content;
        }
        
        .view-controls button:hover {
            background: var(--primary-color-dark);
        }
        
        .view-controls button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .view-controls button i {
            font-size: 14px;
        }
        
        /* Espaço central para separar os lados do eixo */
        .espaco-central {
            width: 20px;
            min-width: 20px;
            height: 1px;
            display: inline-block;
        }
        
        .composicao-veiculo {
            display: flex;
            flex-direction: row;
            align-items: flex-start;
            justify-content: center;
            gap: 60px;
            width: 100%;
            margin: 0 auto;
        }
        .cavalo, .carreta {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .label-cavalo, .label-carreta {
            margin-top: 10px;
            font-weight: bold;
            color: #333;
        }
        .legenda-pneus {
            position: fixed;
            top: 160px;
            right: 40px;
            margin-left: auto;
            align-self: flex-start;
            font-size: 15px;
            z-index: 100;
            display: none;
        }
        .legenda-pneus.ativa {
            display: block;
        }
        .legenda-pneus div {
            display: flex;
            align-items: center;
            margin-bottom: 7px;
            gap: 8px;
        }
        .legenda-pneus .pneu.legenda {
            width: 22px;
            height: 22px;
            border-radius: 6px;
            display: inline-block;
            margin-right: 6px;
            background: radial-gradient(#333, #111);
            box-shadow: 0 0 5px 2px #ccc;
            position: relative;
        }
        .legenda-pneus .pneu.bom { box-shadow: 0 0 5px 2px green; }
        .legenda-pneus .pneu.gasto { box-shadow: 0 0 5px 2px orange; }
        .legenda-pneus .pneu.furado { box-shadow: 0 0 5px 2px red; }
        .legenda-pneus .pneu.rodizio { border: 2px dashed yellow; }
        .legenda-pneus .pneu.alerta .icone-alerta {
            position: absolute;
            top: -8px;
            right: -8px;
            font-size: 14px;
        }
        .gestao-pneus-flex {
            display: flex;
            flex-direction: row;
            align-items: center;
            justify-content: center;
            gap: 40px;
            width: 100%;
            margin-top: 30px;
            position: relative;
        }
        .painel-eixos-disponiveis {
            position: fixed;
            top: 340px;
            right: 40px;
            margin-top: 0;
            background: transparent !important;
            border: 1.5px solid #ddd;
            border-radius: 10px;
            box-shadow: none;
            padding: 18px 16px 12px 16px;
            z-index: 200;
            min-width: 110px;
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 18px;
        }
        .painel-eixos-disponiveis.ativa {
            display: flex;
        }
        .painel-eixos-disponiveis .pneu {
            box-shadow: 0 0 5px 2px #bbb !important;
            border: none !important;
        }
        .painel-eixos-disponiveis .pneu.bom,
        .painel-eixos-disponiveis .pneu.gasto,
        .painel-eixos-disponiveis .pneu.furado,
        .painel-eixos-disponiveis .pneu.rodizio {
            box-shadow: 0 0 5px 2px #bbb !important;
            border: none !important;
        }
        .eixo-drag {
            cursor: grab;
            user-select: none;
            opacity: 1;
            border-radius: 8px;
            background:rgba(245, 245, 245, 0);
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            padding: 10px 18px 8px 18px;
            display: flex;
            flex-direction: column;
            align-items: center;
            transition: box-shadow 0.2s, opacity 0.2s, background 0.2s;
        }
        .eixo-drag:active {
            opacity: 0.7;
            background: #e0e0e0;
        }
        .eixo-drag .pneu {
            margin: 0 2px;
        }
        .drop-area {
            min-height: 60px;
            min-width: 220px;
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            margin: 18px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background:rgba(250, 251, 252, 0);
            transition: border-color 0.2s, background 0.2s;
        }
        .drop-area.over {
            border-color: #007bff;
            background: #eaf4ff;
        }
        .btn-salvar-eixos, .btn-reset-eixos, .btn-undo-eixos {
            min-width: 120px;
            padding: 10px 0;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            margin: 0 4px;
        }
        .btn-salvar-eixos {
            background: #6c757d;
            color: #fff;
        }
        .btn-salvar-eixos:hover {
            background: #495057;
        }
        .btn-reset-eixos {
            background: #dee2e6;
            color: #333;
        }
        .btn-reset-eixos:hover {
            background: #adb5bd;
            color: #222;
        }
        .btn-undo-eixos {
            background: #f8f9fa;
            color: #333;
        }
        .btn-undo-eixos:hover {
            background: #ced4da;
            color: #222;
        }
        .posicao-indicator {
            position: relative;
            z-index: 2;
            background: #fff;
            color: #222;
            font-weight: bold;
            padding: 2px 10px;
            border-radius: 8px;
            margin-top: 8px;
            margin-bottom: -8px;
            box-shadow: 0 2px 6px #0001;
            font-size: 15px;
            display: inline-block;
        }
        .cabine {
            width: 100px;
            height: 80px;
            border: 4px solid black;
            border-radius: 20px;
            position: absolute;
            top: 35px;
            left: 50%;
            transform: translateX(-50%);
            background:rgba(248, 249, 250, 0);
            z-index: 0;
        }
        .cabine::before, .cabine::after {
            content: '';
            width: 20px;
            height: 10px;
            border: 4px solid black;
            border-radius: 5px;
            position: absolute;
            top: 30%;
            background:rgba(248, 249, 250, 0);
        }
        .cabine::before { left: -24px; }
        .cabine::after { right: -24px; }
        .carreta-wire {
            width: 134px;
            height: 460px;
            border: 4px solid black;
            position: absolute;
            left: 50%;
            top: 0;
            transform: translateX(-50%);
            background:rgba(248, 249, 250, 0);
            z-index: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
        .carreta-wire .eixo {
            width: 60px;
            height: 10px;
            border: 4px solid black;
            border-radius: 5px;
            position: absolute;
            left: -12px;
            background:rgba(248, 249, 250, 0);
        }
        .carreta-wire .eixo:first-child { top: 60px; }
        .carreta-wire .eixo:last-child { bottom: 60px; }
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: var(--bg-primary);
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 1.2em;
            color: var(--text-primary);
        }
        .modal-body {
            padding: 20px;
        }
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
            text-align: right;
        }
        .close-modal {
            color: var(--text-secondary);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .close-modal:hover {
            color: var(--text-primary);
        }
        .pneu-item-estoque {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-secondary);
        }
        .pneu-item-estoque:hover {
            background: var(--bg-tertiary);
        }
        .pneu-item-estoque.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        .btn-primary {
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .btn-primary:hover:not(:disabled) {
            background: var(--primary-color-dark);
        }
        .pneu-adicionado {
            box-shadow: 0 0 16px 6px #00ff7f, 0 0 0 4px #fff inset !important;
            animation: destaquePneuAdicionado 1.5s ease;
        }
        @keyframes destaquePneuAdicionado {
            0% { box-shadow: 0 0 24px 12px #00ff7f, 0 0 0 4px #fff inset; }
            60% { box-shadow: 0 0 16px 6px #00ff7f, 0 0 0 4px #fff inset; }
            100% { box-shadow: 0 0 5px 2px green, 0 0 0 0 #fff inset; }
        }
        .modal-tabs {
            display: flex;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .tab-button {
            padding: 8px 16px;
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--text-secondary);
        }
        .tab-button.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        .tab-content {
            margin-top: 16px;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .pneu-item-alocado {
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .pneu-item-alocado:hover {
            background-color: var(--hover-color);
        }
        .pneu-item-alocado {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pneu-info {
            flex: 1;
        }
        .pneu-actions {
            display: flex;
            gap: 8px;
        }
        .historico-pneus {
            margin-top: 20px;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .historico-pneus h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .eixo-info {
            display: flex;
            justify-content: space-between;
            padding: 5px;
            background: #f5f5f5;
            border-radius: 4px;
            margin-bottom: 5px;
        }
        .posicoes-pneus {
            display: flex;
            gap: 10px;
            padding: 10px;
        }
        .posicao-pneu {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .posicao-pneu.ocupada {
            background: #e3f2fd;
            border-color: #2196f3;
        }
        .posicao-pneu.livre {
            background: #f5f5f5;
            border-color: #ddd;
        }
        .posicao-pneu:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .pneu-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .pneu-info span {
            font-size: 0.9em;
            color: #666;
        }

        /* Estilos do Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px;
            border-radius: 5px;
            position: relative;
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 5px;
        }

        .close:hover {
            color: black;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-dark);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background-color: var(--bg-secondary);
            border-radius: var(--card-border-radius);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
            margin: auto;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--bg-tertiary);
        }

        .modal-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--text-primary);
        }

        .close-modal {
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }

        .close-modal:hover {
            color: var(--text-primary);
        }

        .modal-body {
            padding: 20px;
            background-color: var(--bg-secondary);
        }

        .modal-footer {
            padding: 20px;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background-color: var(--bg-tertiary);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--bg-primary);
            color: var(--text-primary);
        }

        .info-box {
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 1rem;
            margin-top: 0.5rem;
        }

        .info-box p {
            margin: 0.5rem 0;
            color: var(--text-primary);
        }

        .info-box strong {
            color: var(--text-secondary);
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-color-dark);
        }

        .btn-secondary {
            background-color: var(--bg-quaternary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: var(--bg-quinary);
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
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="gestao-pneus-container">
                    <div class="veiculo-selector">
                        <select id="veiculoSelect" onchange="carregarPneus(this.value)">
                            <option value="">Selecione um veículo</option>
                            <?php if (!empty($veiculos) && is_array($veiculos)): ?>
                                <?php foreach ($veiculos as $veiculo): ?>
                                    <option value="<?php echo $veiculo['id']; ?>">
                                        <?php echo htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="painel-eixos-disponiveis" id="painelEixos">
                        <div class="eixo-drag" draggable="true" data-qtd="2" title="Arraste para adicionar eixo simples">
                            <div style="display:flex;align-items:center;">
                                <div class="pneu bom"></div>
                                <div class="pneu bom"></div>
                            </div>
                            <div style="font-size:12px;text-align:center;">Eixo 2 pneus</div>
                        </div>
                        <div class="eixo-drag" draggable="true" data-qtd="4" title="Arraste para adicionar eixo duplo">
                            <div style="display:flex;align-items:center;">
                                <div class="pneu bom"></div>
                                <div class="pneu bom"></div>
                                <div class="pneu bom"></div>
                                <div class="pneu bom"></div>
                            </div>
                            <div style="font-size:12px;text-align:center;">Eixo 4 pneus</div>
                        </div>
                    </div>
                    <div class="gestao-pneus-flex">
                        <div id="componente-veiculo-wrapper">
                            <div id="componente-veiculo"></div>
                        </div>
                        <div class="legenda-pneus" id="legendaPneus">
                          <div><span class="pneu legenda bom"></span> Bom</div>
                          <div><span class="pneu legenda gasto"></span> Gasto/Alerta</div>
                          <div><span class="pneu legenda furado"></span> Furado/Ruim</div>
                          <div><span class="pneu legenda rodizio"></span> Rodízio Sugerido</div>
                          <div><span class="pneu legenda alerta"><span class="icone-alerta">⚠️</span></span> Alerta</div>
                        </div>
                    </div>
                </div>
                <div class="historico-pneus">
                    <h3>Histórico de Alocações</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Pneu</th>
                                <th>Eixo</th>
                                <th>Posição</th>
                                <th>Data Alocação</th>
                                <th>KM Alocação</th>
                                <th>Data Desalocação</th>
                                <th>KM Desalocação</th>
                                <th>Status</th>
                                <th>Observações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pneusData['historico'] as $registro): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($registro['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($registro['eixo_posicao']); ?></td>
                                <td><?php echo htmlspecialchars($registro['pneu_posicao']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($registro['data_alocacao'])); ?></td>
                                <td><?php echo number_format($registro['km_alocacao'], 0, ',', '.'); ?></td>
                                <td><?php echo $registro['data_desalocacao'] ? date('d/m/Y', strtotime($registro['data_desalocacao'])) : '-'; ?></td>
                                <td><?php echo $registro['km_desalocacao'] ? number_format($registro['km_desalocacao'], 0, ',', '.') : '-'; ?></td>
                                <td><?php echo htmlspecialchars($registro['alocacao_status']); ?></td>
                                <td><?php echo htmlspecialchars($registro['observacoes'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/header.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    
    <script>
        // Mapeamento manual de posicoes para cada eixo (exemplo, ajuste conforme necessário)
        const mapaEixoParaPosicoes = {
            'cavalo_eixo_1': [1, 2], // Dianteiro Esquerdo, Dianteiro Direito
            'cavalo_eixo_2': [6, 7], // Eixo 1 - Lado Esquerdo/Direito
            'cavalo_eixo_3': [8, 9], // Eixo 2 - Lado Esquerdo/Direito
            'cavalo_eixo_4': [12, 13], // Eixo 3 - Lado Esquerdo/Direito
            'cavalo_eixo_5': [14, 15], // Eixo 4 - Lado Esquerdo/Direito
            'carreta_eixo_1': [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
            'carreta_eixo_2': [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
            'carreta_eixo_3': [21, 22, 23, 24], // Pneu Externo Esquerdo, Pneu Externo Direito, Pneu Interno Esquerdo, Pneu Interno Direito
        };
        
        class VeiculoComponente {
            constructor(containerId) {
                this.container = document.getElementById(containerId);
                this.pneus = [];
                this.eixos = [];
                this.rodizioSugerido = [];
            }
            
            async carregarPneus(veiculoId) {
                try {
                    const response = await fetch(`../api/pneus_data.php?action=get_pneus&veiculo_id=${veiculoId}`);
                    const data = await response.json();
                    if (data.success) {
                        this.pneus = data.pneus || [];
                        this.eixos = data.eixos || [];
                        this.rodizioSugerido = this.pneus
                            .filter(p => p.rodizio === 1)
                            .map(p => p.posicao_id);

                        // Atualiza arrays temporários SEMPRE ao trocar de veículo
                        if (this.eixos && this.eixos.length > 0) {
                            // Separar eixos do cavalo e da carreta baseado na área onde foram adicionados
                            eixosCavaloMontados = this.eixos
                                .filter(e => e && e.posicao_id && e.posicao_id <= 10) // Cavalo: posições 1-10
                                .map(e => e.quantidade_pneus);
                            
                            eixosCarretaMontados = this.eixos
                                .filter(e => e && e.posicao_id && e.posicao_id > 10) // Carreta: posições 11+
                                .map(e => e.quantidade_pneus);
                        } else {
                            eixosCavaloMontados = [];
                            eixosCarretaMontados = [];
                        }

                        // Log para debug
                        console.log('Pneus carregados:', this.pneus);
                        console.log('Eixos carregados:', this.eixos);

                        this.render();
                        document.getElementById('legendaPneus').classList.add('ativa');
                        const painelEixos = document.getElementById('painelEixos');
                        painelEixos.classList.add('ativa');
                        renderEixosMontados(); // Atualiza visualmente
                        setupBtnSalvarEixos();
                        setupBtnResetEixos();
                    } else {
                        console.error('Erro ao carregar pneus:', data.error);
                        alert('Erro ao carregar pneus: ' + data.error);
                        document.getElementById('legendaPneus').classList.remove('ativa');
                        const painelEixos = document.getElementById('painelEixos');
                        painelEixos.classList.remove('ativa');
                    }
                } catch (error) {
                    console.error('Erro ao carregar pneus:', error);
                    alert('Erro ao carregar pneus. Verifique o console para mais detalhes.');
                    document.getElementById('legendaPneus').classList.remove('ativa');
                    const painelEixos = document.getElementById('painelEixos');
                    painelEixos.classList.remove('ativa');
                }
            }
            
            render() {
                // Sempre renderizar a área de montagem dos eixos
                this.container.innerHTML = `
                    <div class="composicao-veiculo">
                        <div class="cavalo" style="position:relative;">
                            <div class="cabine"></div>
                            <div class="veiculo veiculo-centralizado" id="veiculoCavalo">
                                <div class="linha-central"></div>
                                <div class="eixos-wrapper drop-area" id="dropCavalo"></div>
                            </div>
                            <div class="label-cavalo">Cavalo</div>
                        </div>
                        <div class="carreta" style="position:relative;">
                            <div class="carreta-wire">
                                <div class="eixo"></div>
                                <div class="eixo"></div>
                            </div>
                            <div class="veiculo veiculo-centralizado" id="veiculoCarreta">
                                <div class="linha-central"></div>
                                <div class="eixos-wrapper drop-area" id="dropCarreta"></div>
                            </div>
                            <div class="label-carreta">Carreta</div>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:center;gap:10px;margin-top:20px;">
                        <button class="btn-salvar-eixos" id="btnSalvarEixos">Salvar</button>
                        <button class="btn-undo-eixos" id="btnUndoEixos">Desfazer</button>
                        <button class="btn-reset-eixos" id="btnResetEixos">Resetar</button>
                    </div>
                `;
                // Após renderizar, atualizar as áreas de drop com os arrays temporários
                renderEixosMontados();
                setupDragDrop();
                setupBtnSalvarEixos();
                setupBtnResetEixos();
            }
            
            renderEixo(qtdPneus, posicao) {
                let pneusHTML = '';
                const posicoesDoEixo = mapaEixoParaPosicoes[posicao] || [];
                for (let i = 0; i < qtdPneus; i++) {
                    const posicaoId = posicoesDoEixo[i];
                    const pneu = this.pneus.find(p => p.posicao_id == posicaoId) || null;
                    pneusHTML += `<div style='display:flex;flex-direction:column;align-items:center;'>`;
                    pneusHTML += this.renderPneu(pneu, posicaoId, i < qtdPneus/2 ? 'esquerda' : 'direita');
                    pneusHTML += `</div>`;
                }
                pneusHTML += '<div class="espaco-central"></div>';
                return `
                    <div class="eixo" data-pneus="${qtdPneus}" data-posicao="${posicao || ''}" draggable="true">
                        <div class="linha-eixo"></div>
                        ${pneusHTML}
                    </div>`;
            }

            renderPneu(pneuData, posicao, lado) {
                if (!pneuData) {
                    return `<div class="pneu vazio" data-posicao_id="${posicao}" data-lado="${lado}"></div>`;
                }
                const alerta = pneuData.alerta === 1;
                const rodizio = this.rodizioSugerido.includes(pneuData.posicao_id);
                const status = pneuData.status || 'bom'; // Garante um status padrão
                return `
                    <div class="pneu ${status} ${alerta ? 'alerta' : ''} ${rodizio ? 'rodizio' : ''}" 
                         data-posicao_id="${pneuData.posicao_id}" data-lado="${lado}">
                        <div class="tooltip">
                            <strong>Pneu ${pneuData.posicao_nome}</strong><br>
                            Status: ${pneuData.status_nome}<br>
                            Marca: ${pneuData.marca}<br>
                            Modelo: ${pneuData.modelo}<br>
                            Sulco: ${pneuData.sulco_inicial} mm<br>
                            DOT: ${pneuData.dot}<br>
                            Última Recapagem: ${pneuData.data_ultima_recapagem || 'N/A'}
                        </div>
                    </div>`;
            }
        }
        
        // Inicializar componente
        const veiculo = new VeiculoComponente('componente-veiculo');
        
        // Função para carregar pneus do veículo selecionado
        function carregarPneus(veiculoId) {
            const painelEixos = document.getElementById('painelEixos');
            const cavalo = document.getElementById('cavalo');
            const carreta = document.getElementById('carreta');
            if (!veiculoId) {
                document.getElementById('legendaPneus').classList.remove('ativa');
                painelEixos.classList.remove('ativa');
                return;
            }
            painelEixos.classList.add('ativa');
            veiculo.carregarPneus(veiculoId);
            
            // Exibir visualização do cavalo e da carreta
            if (cavalo) cavalo.style.display = 'block';
            if (carreta) carreta.style.display = 'block';
        }

        // Estado temporário dos eixos montados
        let eixosCavaloMontados = [];
        let eixosCarretaMontados = [];
        let historicoAcoes = [];

        // Parâmetros visuais
        const PNEU_WIDTH = 30;
        const PNEU_MARGIN = 2;

        function renderEixosMontados() {
            const dropCavalo = document.getElementById('dropCavalo');
            const dropCarreta = document.getElementById('dropCarreta');
            if (!dropCavalo || !dropCarreta) return;

            // Calcular altura dinâmica do wireframe da carreta
            const alturaPneu = 60;
            const espacamentoEixo = 24;
            const margemTopo = 40;
            const numEixos = eixosCarretaMontados.length;
            const alturaMinima = 460;
            let alturaWire = alturaMinima;
            if (numEixos > 0) {
                alturaWire = margemTopo + (alturaPneu + espacamentoEixo) * (numEixos - 1) + alturaPneu;
                if (alturaWire < alturaMinima) alturaWire = alturaMinima;
            }
            const carretaWire = document.querySelector('.carreta-wire');
            if (carretaWire) {
                carretaWire.style.height = alturaWire + 'px';
            }

            // Função auxiliar para renderizar um pneu
            function renderizarPneu(eixoPneu) {
                console.log('Renderizando pneu:', eixoPneu);
                
                // Determinar a classe do pneu com base no status
                let pneuClass = 'pneu';
                if (eixoPneu.id) {
                    pneuClass += ' ' + (eixoPneu.status || 'bom');
                } else {
                    pneuClass += ' vazio';
                }
                
                // Gerar o HTML do pneu
                const html = `
                    <div class="${pneuClass}" 
                        data-eixo-pneu-id="${eixoPneu.id || ''}"
                        data-eixo-id="${eixoPneu.eixo_id}"
                        data-veiculo-id="${eixoPneu.veiculo_id}"
                        data-lado="${eixoPneu.lado}"
                        onclick="abrirModalAlocacao({
                            id: '${eixoPneu.id || ''}',
                            eixo_id: '${eixoPneu.eixo_id}',
                            veiculo_id: '${eixoPneu.veiculo_id}',
                            lado: '${eixoPneu.lado}'
                        })">
                        </div>
                `;
                
                console.log('HTML gerado:', html);
                return html;
            }

            // Renderizar eixos do cavalo
            dropCavalo.innerHTML = eixosCavaloMontados.map((qtd, idx) => {
                console.log('Renderizando eixo do cavalo:', idx + 1, 'quantidade:', qtd);
                let width, pneusHTML = '';
                const eixo = veiculo.eixos.find(e => e.posicao_id === idx + 1) || {
                    id: null,
                    veiculo_id: veiculo.id,
                    posicao_id: idx + 1,
                    quantidade_pneus: qtd
                };
                console.log('Drop Cavalo renderizado:', { eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, posicao_id: eixo.posicao_id });
                console.log('Eixo encontrado:', eixo);
                
                // Busca todos os registros de eixo_pneus para este eixo
                const pneusDoEixo = eixo.id ? veiculo.pneus.filter(p => p.eixo_id === eixo.id) : [];
                console.log('Pneus do eixo:', pneusDoEixo);

                if (qtd === 4) {
                    width = qtd * (PNEU_WIDTH + 2 * PNEU_MARGIN) - PNEU_MARGIN;
                    // Busca os pneus específicos para cada posição
                    const pneuEsquerda1 = pneusDoEixo[0] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuEsquerda2 = pneusDoEixo[1] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuDireita1 = pneusDoEixo[2] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };
                    const pneuDireita2 = pneusDoEixo[3] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };

                    pneusHTML += renderizarPneu(pneuEsquerda1);
                    pneusHTML += renderizarPneu(pneuEsquerda2);
                    pneusHTML += '<div class="espaco-central" style="width:40px;"></div>';
                    pneusHTML += renderizarPneu(pneuDireita1);
                    pneusHTML += renderizarPneu(pneuDireita2);
                } else if (qtd === 2) {
                    width = 108;
                    const pneuEsquerda = pneusDoEixo[0] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuDireita = pneusDoEixo[1] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };

                    pneusHTML += renderizarPneu(pneuEsquerda);
                    pneusHTML += '<div class="espaco-central" style="width:108px;"></div>';
                    pneusHTML += renderizarPneu(pneuDireita);
                } else {
                    width = qtd * (PNEU_WIDTH + 2 * PNEU_MARGIN) - PNEU_MARGIN;
                    for (let i = 0; i < qtd; i++) {
                        const pneu = pneusDoEixo[i] || { 
                            id: null, 
                            eixo_id: eixo.id, 
                            veiculo_id: eixo.veiculo_id, 
                            lado: i < qtd/2 ? 'esquerda' : 'direita' 
                        };
                        pneusHTML += renderizarPneu(pneu);
                    }
                }
                return `
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div class="eixo" data-pneus="${qtd}" data-idx="${idx}" draggable="true" style="position:relative;">
                            <div class="linha-eixo" style="width:${width}px;"></div>
                            ${pneusHTML}
                        </div>
                        <div class="posicao-indicator">${idx + 1}</div>
                    </div>
                `;
            }).join('');

            // Renderizar eixos da carreta
            dropCarreta.innerHTML = eixosCarretaMontados.map((qtd, idx) => {
                console.log('Renderizando eixo da carreta:', idx + 1, 'quantidade:', qtd);
                let width, pneusHTML = '';
                const eixo = veiculo.eixos.find(e => e.posicao_id === idx + 11) || {
                    id: null,
                    veiculo_id: veiculo.id,
                    posicao_id: idx + 11,
                    quantidade_pneus: qtd
                };
                console.log('Drop Carreta renderizado:', { eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, posicao_id: eixo.posicao_id });
                console.log('Eixo encontrado:', eixo);
                
                // Busca todos os registros de eixo_pneus para este eixo
                const pneusDoEixo = eixo.id ? veiculo.pneus.filter(p => p.eixo_id === eixo.id) : [];
                console.log('Pneus do eixo:', pneusDoEixo);

                if (qtd === 4) {
                    width = qtd * (PNEU_WIDTH + 2 * PNEU_MARGIN) - PNEU_MARGIN;
                    // Busca os pneus específicos para cada posição
                    const pneuEsquerda1 = pneusDoEixo[0] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuEsquerda2 = pneusDoEixo[1] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuDireita1 = pneusDoEixo[2] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };
                    const pneuDireita2 = pneusDoEixo[3] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };

                    pneusHTML += renderizarPneu(pneuEsquerda1);
                    pneusHTML += renderizarPneu(pneuEsquerda2);
                    pneusHTML += '<div class="espaco-central" style="width:40px;"></div>';
                    pneusHTML += renderizarPneu(pneuDireita1);
                    pneusHTML += renderizarPneu(pneuDireita2);
                } else if (qtd === 2) {
                    width = 108;
                    const pneuEsquerda = pneusDoEixo[0] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'esquerda' };
                    const pneuDireita = pneusDoEixo[1] || { id: null, eixo_id: eixo.id, veiculo_id: eixo.veiculo_id, lado: 'direita' };

                    pneusHTML += renderizarPneu(pneuEsquerda);
                    pneusHTML += '<div class="espaco-central" style="width:108px;"></div>';
                    pneusHTML += renderizarPneu(pneuDireita);
                } else {
                    width = qtd * (PNEU_WIDTH + 2 * PNEU_MARGIN) - PNEU_MARGIN;
                    for (let i = 0; i < qtd; i++) {
                        const pneu = pneusDoEixo[i] || { 
                            id: null, 
                            eixo_id: eixo.id, 
                            veiculo_id: eixo.veiculo_id, 
                            lado: i < qtd/2 ? 'esquerda' : 'direita' 
                        };
                        pneusHTML += renderizarPneu(pneu);
                    }
                }
                return `
                    <div style="display:flex;flex-direction:column;align-items:center;">
                        <div class="eixo" data-pneus="${qtd}" data-idx="${idx}" draggable="true" style="position:relative;">
                            <div class="linha-eixo" style="width:${width}px;"></div>
                            ${pneusHTML}
                        </div>
                        <div class="posicao-indicator">${idx + 1}</div>
                    </div>
                `;
            }).join('');

            setupDragDrop();
            setupBtnSalvarEixos();
            setupBtnResetEixos();
            setupBtnUndoEixos();
        }

        function setupDragDrop() {
            // Painel lateral: só adiciona ao arrastar
            document.querySelectorAll('.eixo-drag').forEach(el => {
                el.addEventListener('dragstart', function(e) {
                    e.dataTransfer.setData('qtd', this.getAttribute('data-qtd'));
                    e.dataTransfer.setData('source', 'painel');
                });
            });
            // Delegation para drop, dragover e dragleave nas áreas de drop
            const container = document.getElementById('componente-veiculo');
            if (!container._delegationSetup) {
                container.addEventListener('dragover', function(e) {
                    const dropArea = e.target.closest('.drop-area');
                    if (!dropArea) return;
                    e.preventDefault();
                    dropArea.classList.add('over');
                });
                container.addEventListener('dragleave', function(e) {
                    const dropArea = e.target.closest('.drop-area');
                    if (!dropArea) return;
                    dropArea.classList.remove('over');
                });
                container.addEventListener('drop', function(e) {
                    // Garante que o drop só ocorre na .drop-area, não em elementos internos
                    let dropArea = e.target;
                    while (dropArea && !dropArea.classList.contains('drop-area')) {
                        dropArea = dropArea.parentElement;
                    }
                    if (!dropArea) return;
                    e.preventDefault();
                    dropArea.classList.remove('over');
                    const qtd = parseInt(e.dataTransfer.getData('qtd'));
                    const source = e.dataTransfer.getData('source');
                    let arr, dropAreaId;
                    if (dropArea.id === 'dropCavalo') {
                        arr = eixosCavaloMontados;
                        dropAreaId = 'dropCavalo';
                    } else {
                        arr = eixosCarretaMontados;
                        dropAreaId = 'dropCarreta';
                    }
                    if (source === 'painel') {
                        arr.push(qtd);
                        historicoAcoes.push({ tipo: dropAreaId, idx: arr.length - 1 });
                    } else if (source === dropAreaId) {
                        // Reordenação interna
                        const fromIdx = parseInt(e.dataTransfer.getData('fromIdx'));
                        const toIdx = getDropIndex(e, dropArea);
                        if (fromIdx !== toIdx) {
                            const [moved] = arr.splice(fromIdx, 1);
                            arr.splice(toIdx, 0, moved);
                        }
                    }
                    renderEixosMontados();
                });
                container._delegationSetup = true;
            }
            // Eixos já montados: permitir drag para reordenar
            document.querySelectorAll('.drop-area .eixo').forEach(el => {
                el.setAttribute('draggable', 'true'); // Garante que só .eixo é arrastável
                el.addEventListener('dragstart', function(e) {
                    const parentId = this.parentElement.classList.contains('drop-area') ? this.parentElement.id : this.closest('.drop-area').id;
                    e.dataTransfer.setData('qtd', this.getAttribute('data-pneus'));
                    e.dataTransfer.setData('fromIdx', this.getAttribute('data-idx'));
                    e.dataTransfer.setData('source', parentId);
                });
            });
        }
        // Função utilitária para saber o índice de drop
        function getDropIndex(e, dropArea) {
            const rect = dropArea.getBoundingClientRect();
            const y = e.clientY - rect.top;
            const items = Array.from(dropArea.children);
            let idx = 0;
            let acc = 0;
            for (let i = 0; i < items.length; i++) {
                acc += items[i].offsetHeight;
                if (y < acc) {
                    idx = i;
                    break;
                }
                idx = i + 1;
            }
            return idx;
        }

        function setupBtnSalvarEixos() {
            var btnSalvar = document.getElementById('btnSalvarEixos');
            if (!btnSalvar) return;

            btnSalvar.onclick = async function() {
                const selectVeiculo = document.getElementById('veiculoSelect');
                const veiculoId = selectVeiculo ? selectVeiculo.value : null;
                
                    if (!veiculoId) {
                    alert('Selecione um veículo primeiro.');
                        return;
                    }

                    console.log('SALVAR EIXOS:', {
                    eixosCavaloMontados,
                    eixosCarretaMontados
                    });

                    try {
                        const resp = await fetch('../api/salvar_eixos.php', {
                            method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                            body: JSON.stringify({
                                veiculo_id: veiculoId,
                                eixos_cavalo: eixosCavaloMontados,
                                eixos_carreta: eixosCarretaMontados
                            })
                        });

                        const data = await resp.json();
                        if (data.success) {
                            alert('Configuração de eixos salva com sucesso!');
                        // Recarregar os dados do veículo para atualizar os IDs
                        await carregarVeiculo(veiculoId);
                        } else {
                        throw new Error(data.error || 'Erro ao salvar eixos');
                        }
                } catch (error) {
                    console.error('Erro ao salvar eixos:', error);
                        alert('Erro ao salvar configuração de eixos.');
                }
            };
        }

        function carregarVeiculo(veiculoId) {
            console.log('Carregando veículo:', veiculoId);
            fetch(`/sistema-frotas/api/veiculos.php?action=get&id=${veiculoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Atualizar os IDs dos eixos nos drops
                        if (data.eixos) {
                            data.eixos.forEach(eixo => {
                                const drop = document.querySelector(`.drop[data-eixo-id="${eixo.id}"]`);
                                if (drop) {
                                    drop.setAttribute('data-eixo-id', eixo.id);
                                    const eixoInfo = drop.querySelector('.eixo-info');
                                    if (eixoInfo) {
                                        eixoInfo.textContent = `Eixo: Eixo ${eixo.id}`;
                                    }
                                }
                            });
                        }
                        
                        // Atualizar a visualização
                        renderEixosMontados(data.eixos);
                    } else {
                        console.error('Erro ao carregar veículo:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar veículo:', error);
                });
        }

        function salvarAlocacaoPneu() {
            console.log('=== INICIANDO SALVAMENTO DE ALOCAÇÃO ===');
            
            // Obter os valores diretamente dos elementos
            const eixo_id = document.getElementById('eixo_id').value;
            const veiculo_id = document.getElementById('veiculo_id').value;
            const lado = document.getElementById('lado').value;
            const pneu_id = document.getElementById('pneu_id').value;
            const posicao_id = document.getElementById('posicao_id').value;

            console.log('Elementos encontrados:', {
                eixo_id_element: document.getElementById('eixo_id'),
                veiculo_id_element: document.getElementById('veiculo_id'),
                lado_element: document.getElementById('lado'),
                pneu_id_element: document.getElementById('pneu_id'),
                posicao_id_element: document.getElementById('posicao_id')
            });

            // Verificar se os elementos existem
            if (!document.getElementById('eixo_id') || !document.getElementById('veiculo_id') || 
                !document.getElementById('lado') || !document.getElementById('pneu_id') || 
                !document.getElementById('posicao_id')) {
                console.error('Elementos não encontrados no DOM');
                alert('Erro: Elementos do formulário não encontrados');
                            return;
                        }

            // Verificar se os valores são válidos
            if (typeof eixo_id === 'object') {
                console.error('eixo_id é um objeto:', eixo_id);
                alert('Erro: ID do eixo inválido');
                        return;
                    }

            if (!eixo_id || !veiculo_id || !lado || !pneu_id || !posicao_id) {
                console.error('Dados incompletos:', {
                    eixo_id: eixo_id || 'faltando',
                    veiculo_id: veiculo_id || 'faltando',
                    lado: lado || 'faltando',
                    pneu_id: pneu_id || 'faltando',
                    posicao_id: posicao_id || 'faltando'
                });
                alert('Por favor, preencha todos os campos.');
                return;
            }

            console.log('Valores obtidos:', {
                eixo_id,
                veiculo_id,
                lado,
                pneu_id,
                posicao_id
            });

            // Criar FormData manualmente
            const formData = new FormData();
            formData.append('eixo_id', eixo_id);
            formData.append('veiculo_id', veiculo_id);
            formData.append('lado', lado);
            formData.append('pneu_id', pneu_id);
            formData.append('posicao_id', posicao_id);

            console.log('Enviando requisição para alocar pneu...');
            console.log('URL da requisição:', '../api/alocar_pneu.php');
            console.log('Dados a serem enviados:');
            for (let pair of formData.entries()) {
                console.log(pair[0] + ': ' + pair[1]);
            }

            fetch('../api/alocar_pneu.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Status da resposta:', response.status);
                console.log('Headers da resposta:', Object.fromEntries(response.headers.entries()));
                return response.text().then(text => {
                    console.log('Resposta bruta:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Erro ao parsear JSON:', e);
                        throw new Error('Erro ao processar resposta do servidor: ' + text);
                    }
                });
            })
            .then(data => {
                console.log('Dados processados:', data);
                if (data.success) {
                    console.log('Alocação realizada com sucesso');
                    alert(data.message);
                    fecharModalAlocacao();
                    // Recarregar a página ou atualizar a visualização
                    location.reload();
                } else {
                    console.error('Erro retornado pela API:', data.message || data.error);
                    throw new Error(data.message || data.error);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro ao alocar pneu: ' + error.message);
            });
        }

        // ... existing code ...
        // Função para lidar com o drop do pneu
        async function handleDrop(e) {
            e.preventDefault();
            const pneuId = e.dataTransfer.getData('text/plain');
            const dropArea = e.target.closest('.pneu');
            
            if (!dropArea) return;
            
            const eixoPneuId = dropArea.dataset.eixo_pneu_id;
            const eixoId = dropArea.dataset.eixo_id;
            const veiculoId = dropArea.dataset.veiculo_id;
            const lado = dropArea.dataset.lado;
            
            if (!eixoPneuId || !eixoId || !veiculoId) {
                console.error('Dados do drop inválidos:', { eixoPneuId, eixoId, veiculoId });
                return;
            }

            try {
                const response = await fetch('api/alocar_pneu.php', {
                        method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                        body: JSON.stringify({
                        pneu_id: pneuId,
                        eixo_pneu_id: eixoPneuId,
                        eixo_id: eixoId,
                        veiculo_id: veiculoId,
                        lado: lado
                        })
                    });

                const data = await response.json();
                    if (data.success) {
                    // Atualizar a interface
                    dropArea.innerHTML = `
                        <div class="pneu-info">
                            <div class="pneu-serie">${data.pneu.numero_serie}</div>
                            <div class="pneu-status">bom</div>
                        </div>
                        <div class="tooltip">
                            <strong>Pneu ${data.pneu.posicao_nome}</strong><br>
                            Status: ${data.pneu.status_nome}<br>
                            Marca: ${data.pneu.marca}<br>
                            Modelo: ${data.pneu.modelo}<br>
                            Sulco: ${data.pneu.sulco_inicial} mm<br>
                            DOT: ${data.pneu.dot}<br>
                            Última Recapagem: ${data.pneu.data_ultima_recapagem || 'N/A'}
                        </div>
                    `;
                    dropArea.classList.remove('vazio');
                    dropArea.classList.add('bom');
                    dropArea.style.backgroundColor = 'var(--success-color)';
                    dropArea.style.color = 'white';
                    dropArea.dataset.pneuId = pneuId;
                    
                    // Atualizar a lista de pneus disponíveis
                    const pneuElement = document.querySelector(`.pneu-item[data-id="${pneuId}"]`);
                    if (pneuElement) {
                        pneuElement.remove();
                    }
                        } else {
                    throw new Error(data.message || 'Erro ao alocar pneu');
                }
            } catch (error) {
                console.error('Erro ao alocar pneu:', error);
                alert(error.message);
            }
        }
        // ... existing code ...

        // ... existing code ...
        // Função para renderizar um pneu
        function renderizarPneu(pneu) {
            if (!pneu) {
                return `
                    <div class="pneu vazio" 
                         data-eixo-pneu-id=""
                         data-eixo-id=""
                         data-veiculo-id=""
                         data-lado="">
                    </div>
                `;
            }
            
            const classePneu = pneu.status === 'ativo' ? 'pneu ativo' : 'pneu inativo';
            return `
                <div class="${classePneu}" 
                     data-eixo-pneu-id="${pneu.id || ''}"
                     data-eixo-id="${pneu.eixo_id}"
                     data-veiculo-id="${pneu.veiculo_id}"
                     data-lado="${pneu.lado}">
                    <div class="pneu-info">
                        <span class="pneu-numero">${pneu.numero}</span>
                        <span class="pneu-marca">${pneu.marca}</span>
                    </div>
                </div>
            `;
        }

        // Função para abrir o modal de alocação
        function abrirModalAlocacao(dados) {
            console.log('=== INICIANDO ABERTURA DO MODAL ===');
            console.log('Dados recebidos:', dados);

            // Criar o modal
            const modalElements = criarModal();
            console.log('Elementos do modal criados:', modalElements);

            if (!modalElements) {
                console.error('Erro ao criar elementos do modal');
                        return;
                    }

            // Preencher os campos hidden
            modalElements.eixoId.value = dados.eixo_id;
            modalElements.veiculoId.value = dados.veiculo_id;
            modalElements.lado.value = dados.lado;

            // Preencher informações do local
            modalElements.infoEixo.textContent = `Eixo ${dados.eixo_id}`;
            modalElements.infoLado.textContent = dados.lado;

            // Carregar pneus disponíveis
            carregarPneusDisponiveis(modalElements.pneuId)
                .then(() => {
                    console.log('Pneus carregados com sucesso');
                })
                .catch(error => {
                    console.error('Erro ao carregar pneus:', error);
                    alert('Erro ao carregar pneus disponíveis');
                });

            // Mostrar o modal
            const modal = document.getElementById('modalAlocacao');
            if (modal) {
                modal.style.display = 'block';
            }
        }

        // ... existing code ...
        // Função para fechar o modal de alocação de pneu
        function fecharModalAlocacao() {
            const modal = document.getElementById('modalAlocacao');
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        }

        // Certifique-se de adicionar os event listeners após criar o modal
        function adicionarListenersFechamentoModal() {
            const modal = document.getElementById('modalAlocacao');
            if (!modal) return;

            // Botão X
            const btnClose = modal.querySelector('.close-modal');
            if (btnClose) {
                btnClose.onclick = fecharModalAlocacao;
            }

            // Botão Cancelar
            const btnCancelar = modal.querySelector('.btn-cancelar-alocacao');
            if (btnCancelar) {
                btnCancelar.onclick = fecharModalAlocacao;
            }

            // Clique fora do modal-content
            modal.onclick = function(event) {
                if (event.target === modal) {
                    fecharModalAlocacao();
                }
            };

            // ESC fecha o modal
            document.addEventListener('keydown', function escListener(e) {
                if (e.key === 'Escape' && modal.classList.contains('active')) {
                    fecharModalAlocacao();
                }
            });
        }

        // Certifique-se de chamar adicionarListenersFechamentoModal() após criar/abrir o modal
        // Exemplo: dentro de abrirModalAlocacao()
        // adicionarListenersFechamentoModal();
        // ... existing code ...

        // Função para carregar pneus disponíveis
        async function carregarPneusDisponiveis(select) {
            console.log('Carregando pneus disponíveis...');
            try {
                const response = await fetch('../api/pneus_disponiveis.php');
                console.log('Resposta da API:', response);
                
                if (!response.ok) {
                    throw new Error(`Erro na requisição: ${response.status}`);
                }
                
                const data = await response.json();
                console.log('Dados recebidos:', data);
                
                if (!data.success) {
                    throw new Error(data.message || 'Erro ao carregar pneus disponíveis');
                }
                
                console.log('Iniciando preenchimento do select...');
                // Limpa o select mantendo apenas a primeira opção
                select.innerHTML = '<option value="">Selecione um pneu...</option>';
                
                // Adiciona as opções de pneus
                if (Array.isArray(data.pneus)) {
                    data.pneus.forEach(pneu => {
                        const option = document.createElement('option');
                        option.value = pneu.id;
                        option.textContent = `${pneu.numero_serie} - ${pneu.marca} ${pneu.modelo} (${pneu.status})`;
                        select.appendChild(option);
                    });
                    console.log('Select preenchido com sucesso');
                        } else {
                    console.error('Dados de pneus inválidos:', data);
                    throw new Error('Formato de dados inválido');
                }
                
                return true;
            } catch (error) {
                console.error('Erro ao carregar pneus disponíveis:', error);
                throw error;
            }
        }

        // Event listener para clique em pneus vazios
        document.addEventListener('click', function(e) {
            console.log('=== CLIQUE DETECTADO ===');
            console.log('Elemento clicado:', e.target);
            
            const drop = e.target.closest('.drop');
            console.log('Drop encontrado:', !!drop);
            
            if (drop) {
                console.log('Dataset do drop:', drop.dataset);
                const dados = {
                    id: drop.dataset.eixoPneuId || '',
                    eixo_id: drop.dataset.eixo_id,
                    veiculo_id: drop.dataset.veiculo_id,
                    lado: drop.dataset.lado,
                    posicao: drop.dataset.posicao
                };
                console.log('Dados extraídos do drop:', dados);
                abrirModalAlocacao(dados);
            }
        });

        function renderizarEixo(eixo) {
            console.log('Renderizando eixo:', eixo);
            const eixoHtml = `
                <div class="eixo" data-eixo-id="${eixo.id}">
                    <div class="eixo-info">
                        <span class="eixo-numero">Eixo ${eixo.numero}</span>
                        <span class="eixo-tipo">${eixo.tipo}</span>
                    </div>
                    <div class="pneus-container">
                        <div class="pneu-drop" data-eixo-id="${eixo.id}" data-veiculo-id="${eixo.veiculo_id}" data-lado="esquerda">
                            ${renderizarPneu(eixo.pneus.find(p => p.lado === 'esquerda'))}
                        </div>
                        <div class="pneu-drop" data-eixo-id="${eixo.id}" data-veiculo-id="${eixo.veiculo_id}" data-lado="direita">
                            ${renderizarPneu(eixo.pneus.find(p => p.lado === 'direita'))}
                        </div>
                    </div>
                </div>
            `;
            
            const eixoElement = document.createElement('div');
            eixoElement.innerHTML = eixoHtml;
            const eixoContainer = eixoElement.firstElementChild;
            
            // Adicionar event listeners para os drops vazios
            const drops = eixoContainer.querySelectorAll('.pneu-drop');
            drops.forEach(drop => {
                const pneuVazio = drop.querySelector('.pneu.vazio');
                if (pneuVazio) {
                    pneuVazio.addEventListener('click', function() {
                        const dados = {
                            id: '',
                            eixo_id: drop.dataset.eixoId,
                            veiculo_id: drop.dataset.veiculoId,
                            lado: drop.dataset.lado
                        };
                        abrirModalAlocacao(dados);
                    });
                }
            });
            
            return eixoContainer;
        }

        function criarModal() {
            console.log('Criando modal...');
            const modalHTML = `
                <div id="modalAlocacao" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2>Alocar Pneu</h2>
                            <span class="close-modal">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="formAlocacao" class="form-alocacao">
                                <input type="hidden" name="eixo_id" id="eixo_id">
                                <input type="hidden" name="veiculo_id" id="veiculo_id">
                                <input type="hidden" name="lado" id="lado">
                                
                                <div class="form-group">
                                    <label for="pneu_id">Selecione o Pneu:</label>
                                    <select name="pneu_id" id="pneu_id" class="form-control" required>
                                        <option value="">Selecione um pneu...</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="posicao_id">Selecione a Posição:</label>
                                    <select id="posicao_id" name="posicao_id" class="form-control" required>
                                        <option value="">Selecione uma posição...</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Informações do Local:</label>
                                    <div class="info-box">
                                        <p><strong>Eixo:</strong> <span id="infoEixo"></span></p>
                                        <p><strong>Lado:</strong> <span id="infoLado"></span></p>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="fecharModalAlocacao()">Cancelar</button>
                            <button type="button" class="btn btn-primary" onclick="salvarAlocacaoPneu()">Alocar Pneu</button>
                        </div>
                    </div>
                </div>
            `;

            // Remove o modal existente se houver
            const modalExistente = document.getElementById('modalAlocacao');
            if (modalExistente) {
                modalExistente.remove();
            }
            
            // Adiciona o novo modal
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            console.log('Modal HTML inserido no DOM');

            // Verifica se os elementos foram criados corretamente
            const modal = document.getElementById('modalAlocacao');
            const form = document.getElementById('formAlocacao');
            const eixoId = document.getElementById('eixo_id');
            const veiculoId = document.getElementById('veiculo_id');
            const lado = document.getElementById('lado');
            const pneuId = document.getElementById('pneu_id');
            const posicaoId = document.getElementById('posicao_id');
            const infoEixo = document.getElementById('infoEixo');
            const infoLado = document.getElementById('infoLado');
            
            console.log('Verificação dos elementos:');
            console.log('- Modal:', !!modal);
            console.log('- Form:', !!form);
            console.log('- Eixo ID:', !!eixoId);
            console.log('- Veículo ID:', !!veiculoId);
            console.log('- Lado:', !!lado);
            console.log('- Pneu ID:', !!pneuId);
            console.log('- Posição ID:', !!posicaoId);
            console.log('- Info Eixo:', !!infoEixo);
            console.log('- Info Lado:', !!infoLado);

            // Carregar posições disponíveis
            console.log('Chamando carregarPosicoes...');
            carregarPosicoes();

            // Adiciona event listeners
            const closeButton = modal.querySelector('.close-modal');
            if (closeButton) {
                closeButton.addEventListener('click', fecharModalAlocacao);
            }
            
            // Adiciona event listener para fechar ao clicar fora do modal
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    fecharModalAlocacao();
                }
            });

            // Adiciona event listener para fechar com a tecla ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && modal.style.display === 'block') {
                    fecharModalAlocacao();
                }
            });

            // Retorna os elementos do modal
            return {
                modal,
                form,
                eixoId,
                veiculoId,
                lado,
                pneuId,
                posicaoId,
                infoEixo,
                infoLado
            };
        }

        function inicializarAplicacao() {
            console.log('=== INICIALIZANDO APLICAÇÃO ===');
            
            // Inicializar event listeners
            const selectVeiculo = document.getElementById('veiculoSelect');
            if (selectVeiculo) {
                console.log('Adicionando event listener ao select de veículos...');
                selectVeiculo.addEventListener('change', function() {
                    const veiculoId = this.value;
                    if (veiculoId) {
                        carregarVeiculo(veiculoId);
                    }
                });
            }

            // Adicionar event listener para o botão de salvar eixos
            const btnSalvarEixos = document.getElementById('btnSalvarEixos');
            if (btnSalvarEixos) {
                console.log('Adicionando event listener ao botão de salvar eixos...');
                btnSalvarEixos.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Botão salvar eixos clicado');
                    salvarEixo();
                });
            }
        }

        // Inicializar a aplicação quando o DOM estiver pronto
        document.addEventListener('DOMContentLoaded', inicializarAplicacao);

        function carregarPosicoes() {
            console.log('Iniciando carregamento de posições...');
            fetch('../api/get_posicoes.php')  // Corrigindo o caminho da API
                .then(response => {
                    console.log('Resposta recebida do servidor:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('Dados das posições recebidos:', data);
                    const select = document.getElementById('posicao_id');
                    console.log('Elemento select encontrado:', select);
                    
                    if (!select) {
                        console.error('Elemento select não encontrado!');
                        return;
                    }
                    
                    select.innerHTML = '<option value="">Selecione uma posição...</option>';
                    console.log('Opção padrão adicionada');
                    
                    if (!Array.isArray(data)) {
                        console.error('Dados recebidos não são um array:', data);
                        return;
                    }

                    data.forEach(posicao => {
                        console.log('Adicionando posição:', posicao);
                        const option = document.createElement('option');
                        option.value = posicao.id;
                        option.textContent = posicao.nome;
                        select.appendChild(option);
                    });
                    console.log('Todas as posições foram adicionadas');
                })
                .catch(error => {
                    console.error('Erro ao carregar posições:', error);
                    alert('Erro ao carregar posições disponíveis');
                });
        }

        function salvarEixo() {
            console.log('Iniciando salvamento de eixos...');
            const veiculoId = document.getElementById('veiculoSelect').value;
            if (!veiculoId) {
                alert('Selecione um veículo primeiro!');
                        return;
                    }
                    
            // Coletar dados dos eixos
            const eixos_cavalo = [];
            const eixos_carreta = [];
            
            // Coletar eixos do cavalo
            document.querySelectorAll('#componente-veiculo .eixo').forEach((eixo, index) => {
                const qtd = eixo.querySelectorAll('.pneu').length;
                if (qtd > 0) {
                    eixos_cavalo[index] = qtd;
                }
            });
            
            // Coletar eixos da carreta
            document.querySelectorAll('#componente-carreta .eixo').forEach((eixo, index) => {
                const qtd = eixo.querySelectorAll('.pneu').length;
                if (qtd > 0) {
                    eixos_carreta[index] = qtd;
                }
            });

            const data = {
                veiculo_id: veiculoId,
                eixos_cavalo: eixos_cavalo,
                eixos_carreta: eixos_carreta
            };

            console.log('Dados a serem enviados:', data);

            // Desabilitar o botão durante o salvamento
            const btnSalvarEixos = document.getElementById('btnSalvarEixos');
            if (btnSalvarEixos) {
                btnSalvarEixos.disabled = true;
                btnSalvarEixos.textContent = 'Salvando...';
            }

            fetch('../api/salvar_eixos.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Resposta do servidor:', data);
                if (data.success) {
                    alert('Configuração de eixos salva com sucesso!');
                    location.reload();
                } else {
                    alert('Erro ao salvar eixos: ' + data.message);
                    // Reabilitar o botão em caso de erro
                    if (btnSalvarEixos) {
                        btnSalvarEixos.disabled = false;
                        btnSalvarEixos.textContent = 'Salvar';
                    }
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao salvar eixos');
                // Reabilitar o botão em caso de erro
                if (btnSalvarEixos) {
                    btnSalvarEixos.disabled = false;
                    btnSalvarEixos.textContent = 'Salvar';
                }
            });
        }

        // ... existing code ...
        function setupBtnResetEixos() {
            var btnReset = document.getElementById('btnResetEixos');
            if (btnReset && !btnReset._listenerSetup) {
                btnReset.addEventListener('click', async function() {
                    const select = document.getElementById('veiculoSelect');
                    const veiculoId = select ? select.value : null;
                    if (!veiculoId) return;
                    // Verifica se há pneus alocados
                    try {
                        const resp = await fetch(`../api/pneus_data.php?action=get_pneus&veiculo_id=${veiculoId}`);
                        const data = await resp.json();
                        if (data.pneus && data.pneus.some(p => p.veiculo_id == veiculoId)) {
                            alert('Não é possível resetar enquanto houver pneus alocados. Desloque todos os pneus antes de resetar.');
                            return;
                        }
                    } catch (err) {
                        alert('Erro ao verificar pneus alocados.');
                        return;
                    }
                    // Se não houver pneus alocados, permite resetar
                    eixosCavaloMontados = [];
                    eixosCarretaMontados = [];
                    renderEixosMontados();
                    setupBtnSalvarEixos();
                    setupBtnResetEixos();
                });
                btnReset._listenerSetup = true;
            }
        }

        function setupBtnUndoEixos() {
            var btnUndo = document.getElementById('btnUndoEixos');
            if (btnUndo && !btnUndo._listenerSetup) {
                btnUndo.addEventListener('click', function() {
                    if (historicoAcoes.length === 0) return;
                    const last = historicoAcoes.pop();
                    if (last.tipo === 'dropCavalo') {
                        eixosCavaloMontados.splice(last.idx, 1);
                    } else if (last.tipo === 'dropCarreta') {
                        eixosCarretaMontados.splice(last.idx, 1);
                    }
                    renderEixosMontados();
                    setupBtnSalvarEixos();
                    setupBtnResetEixos();
                });
                btnUndo._listenerSetup = true;
            }
        }

        // Função para fechar o modal de alocação de pneu
        function fecharModalAlocacao() {
            const modal = document.getElementById('modalAlocacao');
            if (modal) {
                modal.classList.remove('active');
                modal.style.display = 'none';
            }
        }
        // ... existing code ...
    </script>

    <!-- Modal de Seleção de Pneu -->
    <div id="modalSelecionarPneu" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Selecionar Pneu do Estoque</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-tabs">
                    <button class="tab-button active" data-tab="disponiveis">Pneus Disponíveis</button>
                    <button class="tab-button" data-tab="alocados">Pneus Alocados</button>
                </div>
                <div class="tab-content">
                    <div id="tab-disponiveis" class="tab-pane active">
                        <label for="selectPosicaoPneu" style="font-weight:bold;">Escolha a posição:</label>
                        <select id="selectPosicaoPneu" style="width:100%;margin-bottom:16px;"></select>
                        <div id="listaPneusEstoque" style="max-height:250px;overflow-y:auto;margin-bottom:16px;"></div>
                    </div>
                    <div id="tab-alocados" class="tab-pane">
                        <div id="listaPneusAlocados" style="max-height:250px;overflow-y:auto;margin-bottom:16px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="btnConfirmarAlocacaoPneu" class="btn-primary" disabled>Alocar Pneu</button>
                <button id="btnDesalocarPneu" class="btn-secondary" style="display:none;">Desalocar Pneu</button>
            </div>
        </div>
    </div>

    <!-- Modal de Deslocamento -->
    <div id="modalDeslocarPneu" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Gestão de Pneus</h2>
                <span class="close-modal" id="closeModalDeslocarPneu">&times;</span>
            </div>
            <div class="modal-body" id="infoDeslocarPneu"></div>
            <div class="modal-footer">
                <button id="btnConfirmarDeslocarPneu" class="btn-primary">Deslocar/Voltar para o Estoque</button>
            </div>
        </div>
    </div>

    <!-- Modal de Alocação de Pneu -->
    <div id="modalAlocacao" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>Alocar Pneu</h2>
            <form id="formAlocacao">
                <div class="form-group">
                    <label for="pneu_id">Selecione o Pneu:</label>
                    <select id="pneu_id" name="pneu_id" required>
                        <option value="">Selecione um pneu</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="posicao_id">Selecione a Posição:</label>
                    <select id="posicao_id" name="posicao_id" required>
                        <option value="">Selecione uma posição...</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Alocar</button>
                    <button type="button" class="btn btn-secondary" onclick="fecharModalAlocacao()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html> 