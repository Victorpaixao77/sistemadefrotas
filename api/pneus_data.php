<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in
require_authentication();

// Get action from request
$action = $_GET['action'] ?? '';

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'get_pneus':
            $veiculo_id = isset($_GET['veiculo_id']) ? intval($_GET['veiculo_id']) : 0;
            
            if (!$veiculo_id) {
                throw new Exception('ID do veículo não fornecido');
            }
            
            // Buscar dados dos pneus
            $sql = "SELECT 
                    ep.id as alocacao_id,
                    ep.eixo_id,
                    ep.veiculo_id,
                    ep.posicao_id,
                    ep.status as alocacao_status,
                    ep.data_alocacao,
                    ep.km_alocacao,
                    p.id as pneu_id,
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
                    v.placa,
                    v.modelo as modelo_veiculo,
                    sp.nome as status_nome,
                    pp.nome as posicao_nome,
                    CASE 
                        WHEN p.status_id = 5 THEN 'bom'
                        WHEN p.status_id = 4 THEN 'gasto'
                        WHEN p.status_id = 1 THEN 'furado'
                        WHEN p.status_id = 2 THEN 'reserva'
                        WHEN p.status_id = 3 THEN 'descartado'
                        ELSE 'gasto'
                    END as status,
                    CASE 
                        WHEN p.sulco_inicial <= 2.0 THEN 1
                        ELSE 0
                    END as alerta,
                    CASE 
                        WHEN p.data_ultima_recapagem IS NULL OR DATEDIFF(CURRENT_DATE, p.data_ultima_recapagem) > 90 THEN 1
                        ELSE 0
                    END as rodizio
                    FROM eixo_pneus ep
                    LEFT JOIN pneus p ON ep.pneu_id = p.id
                    INNER JOIN eixos e ON ep.eixo_id = e.id
                    LEFT JOIN veiculos v ON v.id = e.veiculo_id
                    LEFT JOIN status_pneus sp ON sp.id = p.status_id
                    LEFT JOIN posicoes_pneus pp ON pp.id = ep.posicao_id
                    WHERE e.veiculo_id = :veiculo_id
                    AND p.empresa_id = :empresa_id
                    ORDER BY ep.id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar dados dos eixos
            $eixos_sql = "SELECT * FROM eixos WHERE veiculo_id = :veiculo_id ORDER BY id";
            $eixos_stmt = $conn->prepare($eixos_sql);
            $eixos_stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
            $eixos_stmt->execute();
            $eixos = $eixos_stmt->fetchAll(PDO::FETCH_ASSOC);
            
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
            }
            
            echo json_encode([
                'success' => true,
                'pneus' => $pneus,
                'eixos' => $eixos
            ]);
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
} catch (Exception $e) {
    error_log("Erro na API de pneus: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} 