<?php
// API de eixos veículos - versão corrigida
ob_start();
header('Content-Type: application/json');

// Incluir configurações principais
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';

// Configurar sessão
configure_session();

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Verificar dados da sessão
error_log("API Eixos - Session ID: " . session_id());
error_log("API Eixos - Session data: " . print_r($_SESSION, true));

// Verificar autenticação e empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

$empresa_id = intval($_SESSION['empresa_id']);

// Verificar se empresa_id é válido
if ($empresa_id <= 0) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'ID da empresa inválido']);
    exit;
}

$action = $_GET['action'] ?? '';
$veiculo_id = $_GET['veiculo_id'] ?? null;

try {
    $conn = getConnection();
    
    // Verificar se o veículo pertence à empresa
    if ($veiculo_id) {
        $stmt = $conn->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
        $stmt->execute([$veiculo_id, $empresa_id]);
        if (!$stmt->fetch()) {
            ob_clean();
            echo json_encode(['success' => false, 'error' => 'Veículo não encontrado ou não pertence à empresa']);
            exit;
        }
    }
    
    if ($action === 'layout_completo' && $veiculo_id) {
        // Buscar eixos
        $sql = "SELECT * FROM eixos_veiculos WHERE veiculo_id = ? AND empresa_id = ? ORDER BY tipo_veiculo, numero_eixo";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$veiculo_id, $empresa_id]);
        $eixos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar eixos por tipo
        $eixosCaminhao = [];
        $eixosCarreta = [];
        
        foreach ($eixos as $eixo) {
            $eixoData = [
                'id' => $eixo['id'],
                'pneus' => $eixo['quantidade_pneus']
            ];
            
            if ($eixo['tipo_veiculo'] === 'caminhao') {
                $eixosCaminhao[] = $eixoData;
            } else {
                $eixosCarreta[] = $eixoData;
            }
        }
        
        // Buscar alocações ativas
        // Primeiro verificar se a coluna posicao_id existe
        $stmt = $conn->prepare("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
        $stmt->execute();
        $posicao_id_exists = $stmt->rowCount() > 0;
        
        if ($posicao_id_exists) {
            // Query com posicao_id
            $sql = "SELECT apf.*, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome, pp.nome as posicao_nome
                    FROM alocacoes_pneus_flexiveis apf
                    INNER JOIN pneus p ON apf.pneu_id = p.id
                    LEFT JOIN status_pneus sp ON p.status_id = sp.id
                    LEFT JOIN posicoes_pneus pp ON apf.posicao_id = pp.id
                    INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                    WHERE ev.veiculo_id = ? AND apf.empresa_id = ? AND apf.ativo = 1";
        } else {
            // Query sem posicao_id
            $sql = "SELECT apf.*, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome
                    FROM alocacoes_pneus_flexiveis apf
                    INNER JOIN pneus p ON apf.pneu_id = p.id
                    LEFT JOIN status_pneus sp ON p.status_id = sp.id
                    INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                    WHERE ev.veiculo_id = ? AND apf.empresa_id = ? AND apf.ativo = 1";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$veiculo_id, $empresa_id]);
        $alocacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Organizar alocações por slot_id
        $pneusFlexAlocados = [];
        foreach ($alocacoes as $alocacao) {
            $pneusFlexAlocados[$alocacao['slot_id']] = [
                'id' => $alocacao['pneu_id'],
                'numero_serie' => $alocacao['numero_serie'],
                'marca' => $alocacao['marca'],
                'modelo' => $alocacao['modelo'],
                'medida' => $alocacao['medida'],
                'status_id' => $alocacao['status_id'],
                'status_nome' => $alocacao['status_nome'],
                'posicao_id' => $posicao_id_exists ? ($alocacao['posicao_id'] ?? null) : null,
                'posicao_nome' => $posicao_id_exists ? ($alocacao['posicao_nome'] ?? null) : null
            ];
        }
        
        // Calcular próximo ID de eixo
        $max_id = 0;
        if (count($eixosCaminhao) > 0) {
            $max_id = max(array_column($eixosCaminhao, 'id'));
        }
        if (count($eixosCarreta) > 0) {
            $max_id = max($max_id, max(array_column($eixosCarreta, 'id')));
        }
        $next_id = $max_id + 1;
        
        $layout = [
            'eixosCaminhao' => $eixosCaminhao,
            'eixosCarreta' => $eixosCarreta,
            'idEixo' => $next_id,
            'pneusFlexAlocados' => $pneusFlexAlocados
        ];
        
        ob_clean();
        echo json_encode(['success' => true, 'layout' => $layout]);
        
    } elseif ($action === 'list' && $veiculo_id) {
        // Listar eixos de um veículo específico
        $sql = "SELECT * FROM eixos_veiculos WHERE veiculo_id = ? AND empresa_id = ? ORDER BY tipo_veiculo, numero_eixo";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$veiculo_id, $empresa_id]);
        $eixos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ob_clean();
        echo json_encode(['success' => true, 'eixos' => $eixos]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Processar requisições POST
        $input = json_decode(file_get_contents('php://input'), true);
        $post_action = $action; // Usar o action da URL, não do corpo
        
        switch ($post_action) {
            case 'adicionar_eixo':
                $veiculo_id = $input['veiculo_id'] ?? null;
                $tipo_veiculo = $input['tipo_veiculo'] ?? null;
                $quantidade_pneus = $input['quantidade_pneus'] ?? 2;
                
                if (!$veiculo_id || !$tipo_veiculo) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não fornecidos']);
                    exit;
                }
                
                // Verificar se o veículo pertence à empresa
                $stmt = $conn->prepare("SELECT id FROM veiculos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$veiculo_id, $empresa_id]);
                if (!$stmt->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Veículo não encontrado']);
                    exit;
                }
                
                // Buscar próximo número de eixo
                $stmt = $conn->prepare("SELECT MAX(numero_eixo) as max_numero FROM eixos_veiculos WHERE veiculo_id = ? AND tipo_veiculo = ? AND empresa_id = ?");
                $stmt->execute([$veiculo_id, $tipo_veiculo, $empresa_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $numero_eixo = ($result['max_numero'] ?? 0) + 1;
                
                // Inserir novo eixo
                $stmt = $conn->prepare("INSERT INTO eixos_veiculos (veiculo_id, tipo_veiculo, numero_eixo, quantidade_pneus, empresa_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$veiculo_id, $tipo_veiculo, $numero_eixo, $quantidade_pneus, $empresa_id]);
                
                $eixo_id = $conn->lastInsertId();
                
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Eixo adicionado com sucesso',
                    'eixo' => [
                        'id' => $eixo_id,
                        'numero_eixo' => $numero_eixo,
                        'quantidade_pneus' => $quantidade_pneus
                    ]
                ]);
                break;
                
            case 'excluir_eixo':
                $eixo_id = $input['eixo_id'] ?? null;
                
                if (!$eixo_id) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'eixo_id obrigatório']);
                    exit;
                }
                
                // Verificar se o eixo pertence à empresa
                $stmt = $conn->prepare("SELECT id FROM eixos_veiculos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$eixo_id, $empresa_id]);
                if (!$stmt->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Eixo não encontrado']);
                    exit;
                }
                
                // Verificar se há pneus alocados
                $stmt = $conn->prepare("SELECT COUNT(*) as total FROM alocacoes_pneus_flexiveis WHERE eixo_veiculo_id = ? AND ativo = 1");
                $stmt->execute([$eixo_id]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['total'] > 0) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Não é possível excluir eixo com pneus alocados']);
                    exit;
                }
                
                // Excluir eixo
                $stmt = $conn->prepare("DELETE FROM eixos_veiculos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$eixo_id, $empresa_id]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Eixo excluído com sucesso']);
                break;
                
            case 'alocar_pneu':
                $eixo_id = $input['eixo_id'] ?? null;
                $pneu_id = $input['pneu_id'] ?? null;
                $slot_id = $input['slot_id'] ?? null;
                $posicao_slot = $input['posicao_slot'] ?? 0;
                $posicao_id = $input['posicao_id'] ?? null;
                
                if (!$eixo_id || !$pneu_id || !$slot_id) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Dados obrigatórios não fornecidos']);
                    exit;
                }
                
                // Verificar se o eixo pertence à empresa
                $stmt = $conn->prepare("SELECT id FROM eixos_veiculos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$eixo_id, $empresa_id]);
                if (!$stmt->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Eixo não encontrado']);
                    exit;
                }
                
                // Verificar se o pneu pertence à empresa
                $stmt = $conn->prepare("SELECT id FROM pneus WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$pneu_id, $empresa_id]);
                if (!$stmt->fetch()) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Pneu não encontrado']);
                    exit;
                }
                
                // Desativar alocações anteriores do pneu
                $stmt = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE pneu_id = ? AND ativo = 1");
                $stmt->execute([$pneu_id]);
                
                // Verificar se a coluna posicao_id existe
                $stmt = $conn->prepare("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
                $stmt->execute();
                $posicao_id_exists = $stmt->rowCount() > 0;
                
                if ($posicao_id_exists) {
                    // Inserir nova alocação com posicao_id
                    $stmt = $conn->prepare("INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, posicao_id, slot_id, empresa_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$eixo_id, $pneu_id, $posicao_slot, $posicao_id, $slot_id, $empresa_id]);
                } else {
                    // Inserir nova alocação sem posicao_id
                    $stmt = $conn->prepare("INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, slot_id, empresa_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$eixo_id, $pneu_id, $posicao_slot, $slot_id, $empresa_id]);
                }
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu alocado com sucesso']);
                break;
                
            case 'remover_pneu':
                $slot_id = $input['slot_id'] ?? null;
                
                if (!$slot_id) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'slot_id obrigatório']);
                    exit;
                }
                
                // Desativar alocação
                $stmt = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE slot_id = ? AND empresa_id = ? AND ativo = 1");
                $stmt->execute([$slot_id, $empresa_id]);
                
                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu removido com sucesso']);
                break;
                
            default:
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Ação não reconhecida']);
        }
        
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
    }
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro na API de eixos: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
