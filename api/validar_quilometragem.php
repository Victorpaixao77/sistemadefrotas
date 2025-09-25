<?php
/**
 * API para Validação de Quilometragem
 * 
 * Validações implementadas:
 * 1. KM Saída da rota >= quilometragem atual do veículo
 * 2. Quilometragem do abastecimento > KM Saída da rota
 */

require_once '../includes/db_connect.php';
session_start();

header('Content-Type: application/json');

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'validar_km_saida_rota':
            validarKmSaidaRota($conn, $empresa_id);
            break;
            
        case 'validar_km_abastecimento':
            validarKmAbastecimento($conn, $empresa_id);
            break;
            
        case 'obter_km_atual_veiculo':
            obterKmAtualVeiculo($conn, $empresa_id);
            break;
            
        case 'obter_km_saida_rota':
            obterKmSaidaRota($conn, $empresa_id);
            break;
            
        case 'obter_abastecimentos_rota':
            obterAbastecimentosRota($conn, $empresa_id);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Ação não especificada']);
    }
    
} catch (Exception $e) {
    error_log("Erro na validação de quilometragem: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Validar KM Saída da rota
 * Regra: KM Saída >= quilometragem atual do veículo
 */
function validarKmSaidaRota($conn, $empresa_id) {
    $veiculo_id = $_POST['veiculo_id'] ?? '';
    $km_saida = $_POST['km_saida'] ?? '';
    
    if (empty($veiculo_id) || empty($km_saida)) {
        echo json_encode(['success' => false, 'error' => 'Parâmetros obrigatórios não fornecidos']);
        return;
    }
    
    // Obter quilometragem atual do veículo
    $stmt = $conn->prepare('SELECT km_atual FROM veiculos WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$veiculo_id, $empresa_id]);
    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$veiculo) {
        echo json_encode(['success' => false, 'error' => 'Veículo não encontrado']);
        return;
    }
    
    $km_atual_veiculo = (float) $veiculo['km_atual'];
    $km_saida_float = (float) $km_saida;
    
    // Verificar se é a primeira rota do veículo
    $stmt = $conn->prepare('SELECT COUNT(*) as total FROM rotas WHERE veiculo_id = ? AND empresa_id = ?');
    $stmt->execute([$veiculo_id, $empresa_id]);
    $primeira_rota = $stmt->fetch(PDO::FETCH_ASSOC)['total'] == 0;
    
    $validacao = [
        'success' => true,
        'km_atual_veiculo' => $km_atual_veiculo,
        'km_saida' => $km_saida_float,
        'primeira_rota' => $primeira_rota,
        'valido' => true,
        'mensagem' => ''
    ];
    
    if ($primeira_rota) {
        // Primeira rota: KM Saída deve ser >= quilometragem atual do veículo
        if ($km_saida_float < $km_atual_veiculo) {
            $validacao['valido'] = false;
            $validacao['mensagem'] = "KM Saída ({$km_saida_float}) deve ser maior ou igual à quilometragem atual do veículo ({$km_atual_veiculo})";
        } else {
            $validacao['mensagem'] = "Validação OK: Primeira rota do veículo";
        }
    } else {
        // Rotas subsequentes: KM Saída deve ser >= KM Chegada da última rota
        $stmt = $conn->prepare('SELECT km_chegada FROM rotas WHERE veiculo_id = ? AND empresa_id = ? ORDER BY data_saida DESC LIMIT 1');
        $stmt->execute([$veiculo_id, $empresa_id]);
        $ultima_rota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ultima_rota) {
            $km_chegada_ultima = (float) $ultima_rota['km_chegada'];
            $validacao['km_chegada_ultima_rota'] = $km_chegada_ultima;
            
            if ($km_saida_float < $km_chegada_ultima) {
                $validacao['valido'] = false;
                $validacao['mensagem'] = "KM Saída ({$km_saida_float}) deve ser maior ou igual ao KM Chegada da última rota ({$km_chegada_ultima})";
            } else {
                $validacao['mensagem'] = "Validação OK: Rota subsequente";
            }
        }
    }
    
    echo json_encode($validacao);
}

/**
 * Validar quilometragem do abastecimento
 * Regras:
 * 1. Quilometragem do abastecimento > KM Saída da rota
 * 2. Se já existem abastecimentos para a rota, quilometragem > quilometragem do último abastecimento
 */
function validarKmAbastecimento($conn, $empresa_id) {
    $rota_id = $_POST['rota_id'] ?? '';
    $km_abastecimento = $_POST['km_abastecimento'] ?? '';
    $abastecimento_id = $_POST['abastecimento_id'] ?? null; // Para edição
    
    if (empty($rota_id) || empty($km_abastecimento)) {
        echo json_encode(['success' => false, 'error' => 'Parâmetros obrigatórios não fornecidos']);
        return;
    }
    
    // Obter KM Saída da rota
    $stmt = $conn->prepare('SELECT km_saida, veiculo_id FROM rotas WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$rota_id, $empresa_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rota) {
        echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
        return;
    }
    
    $km_saida_rota = (float) $rota['km_saida'];
    $km_abastecimento_float = (float) $km_abastecimento;
    
    $validacao = [
        'success' => true,
        'km_saida_rota' => $km_saida_rota,
        'km_abastecimento' => $km_abastecimento_float,
        'valido' => true,
        'mensagem' => '',
        'km_ultimo_abastecimento' => null
    ];
    
    // Verificar se quilometragem é maior que KM Saída da rota
    if ($km_abastecimento_float <= $km_saida_rota) {
        $validacao['valido'] = false;
        $validacao['mensagem'] = "Quilometragem do abastecimento ({$km_abastecimento_float}) deve ser maior que o KM Saída da rota ({$km_saida_rota})";
        echo json_encode($validacao);
        return;
    }
    
    // Verificar se já existem abastecimentos para esta rota
    $sql_ultimo_abastecimento = 'SELECT km_atual FROM abastecimentos 
                                WHERE rota_id = ? AND empresa_id = ?';
    $params = [$rota_id, $empresa_id];
    
    // Se estiver editando, excluir o próprio abastecimento da verificação
    if ($abastecimento_id) {
        $sql_ultimo_abastecimento .= ' AND id != ?';
        $params[] = $abastecimento_id;
    }
    
    $sql_ultimo_abastecimento .= ' ORDER BY km_atual DESC LIMIT 1';
    
    $stmt = $conn->prepare($sql_ultimo_abastecimento);
    $stmt->execute($params);
    $ultimo_abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ultimo_abastecimento) {
        $km_ultimo_abastecimento = (float) $ultimo_abastecimento['km_atual'];
        $validacao['km_ultimo_abastecimento'] = $km_ultimo_abastecimento;
        
        // Verificar se quilometragem é maior que a do último abastecimento
        if ($km_abastecimento_float <= $km_ultimo_abastecimento) {
            $validacao['valido'] = false;
            $validacao['mensagem'] = "Quilometragem do abastecimento ({$km_abastecimento_float}) deve ser maior que a quilometragem do último abastecimento ({$km_ultimo_abastecimento})";
            echo json_encode($validacao);
            return;
        }
        
        $validacao['mensagem'] = "Validação OK: Quilometragem válida (maior que KM Saída e último abastecimento)";
    } else {
        $validacao['mensagem'] = "Validação OK: Primeiro abastecimento da rota";
    }
    
    echo json_encode($validacao);
}

/**
 * Obter quilometragem atual do veículo
 */
function obterKmAtualVeiculo($conn, $empresa_id) {
    $veiculo_id = $_POST['veiculo_id'] ?? $_GET['veiculo_id'] ?? '';
    
    if (empty($veiculo_id)) {
        echo json_encode(['success' => false, 'error' => 'ID do veículo não fornecido']);
        return;
    }
    
    $stmt = $conn->prepare('SELECT km_atual FROM veiculos WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$veiculo_id, $empresa_id]);
    $veiculo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$veiculo) {
        echo json_encode(['success' => false, 'error' => 'Veículo não encontrado']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'km_atual' => (float) $veiculo['km_atual']
    ]);
}

/**
 * Obter KM Saída da rota
 */
function obterKmSaidaRota($conn, $empresa_id) {
    $rota_id = $_POST['rota_id'] ?? $_GET['rota_id'] ?? '';
    
    if (empty($rota_id)) {
        echo json_encode(['success' => false, 'error' => 'ID da rota não fornecido']);
        return;
    }
    
    $stmt = $conn->prepare('SELECT km_saida FROM rotas WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$rota_id, $empresa_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rota) {
        echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'km_saida' => (float) $rota['km_saida']
    ]);
}

/**
 * Obter informações de abastecimentos da rota
 */
function obterAbastecimentosRota($conn, $empresa_id) {
    $rota_id = $_POST['rota_id'] ?? $_GET['rota_id'] ?? '';
    
    if (empty($rota_id)) {
        echo json_encode(['success' => false, 'error' => 'ID da rota não fornecido']);
        return;
    }
    
    // Obter KM Saída da rota
    $stmt = $conn->prepare('SELECT km_saida FROM rotas WHERE id = ? AND empresa_id = ?');
    $stmt->execute([$rota_id, $empresa_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rota) {
        echo json_encode(['success' => false, 'error' => 'Rota não encontrada']);
        return;
    }
    
    // Obter abastecimentos da rota ordenados por quilometragem
    $stmt = $conn->prepare('SELECT id, km_atual, data_abastecimento, litros, valor_total 
                           FROM abastecimentos 
                           WHERE rota_id = ? AND empresa_id = ? 
                           ORDER BY km_atual ASC');
    $stmt->execute([$rota_id, $empresa_id]);
    $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $km_saida_rota = (float) $rota['km_saida'];
    $km_ultimo_abastecimento = null;
    $total_abastecimentos = count($abastecimentos);
    
    if ($total_abastecimentos > 0) {
        $km_ultimo_abastecimento = (float) $abastecimentos[$total_abastecimentos - 1]['km_atual'];
    }
    
    echo json_encode([
        'success' => true,
        'km_saida_rota' => $km_saida_rota,
        'km_ultimo_abastecimento' => $km_ultimo_abastecimento,
        'total_abastecimentos' => $total_abastecimentos,
        'abastecimentos' => $abastecimentos
    ]);
}
