<?php
// API de eixos veículos - versão corrigida
ob_start();
header('Content-Type: application/json');

// Incluir configurações principais
require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/functions.php';
require_once dirname(__DIR__, 2) . '/includes/pneu_movimentacoes_helper.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/api_json.php';
require_once dirname(__DIR__, 2) . '/includes/permissions.php';
require_once dirname(__DIR__, 2) . '/includes/pneus_helpers.php';

/**
 * Resolve slot_id (ex.: cavalo-0-1) para eixo_veiculo_id e posicao_slot.
 */
function eixos_resolve_slot_veiculo(PDO $conn, int $veiculo_id, int $empresa_id, string $slot_id): ?array
{
    $parts = explode('-', $slot_id, 3);
    if (count($parts) !== 3) {
        return null;
    }
    [$tipoSlot, $idxEixo, $posSlot] = $parts;
    $tipo_veiculo = ($tipoSlot === 'cavalo') ? 'caminhao' : (($tipoSlot === 'carreta') ? 'carreta' : null);
    if ($tipo_veiculo === null) {
        return null;
    }
    $offset = (int) $idxEixo;
    $stmt = $conn->prepare(
        'SELECT id, quantidade_pneus FROM eixos_veiculos
         WHERE veiculo_id = ? AND empresa_id = ? AND tipo_veiculo = ?
         ORDER BY numero_eixo ASC
         LIMIT 1 OFFSET ' . $offset
    );
    $stmt->execute([$veiculo_id, $empresa_id, $tipo_veiculo]);
    $eixo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$eixo) {
        return null;
    }
    $pos = (int) $posSlot;
    if ($pos < 0 || $pos >= (int) $eixo['quantidade_pneus']) {
        return null;
    }
    return [
        'eixo_id' => (int) $eixo['id'],
        'posicao_slot' => $pos,
    ];
}

function eixos_alocacao_posicao_id_exists(PDO $conn): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    $stmt = $conn->prepare("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
    $stmt->execute();
    $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    return $exists;
}

// Configurar sessão
configure_session();

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug: Verificar dados da sessão (apenas em DEBUG_MODE)
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    error_log('API Eixos - Session ID: ' . session_id());
}

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

if (!can_access_tire_management()) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Sem permissão para gestão de pneus']);
    exit;
}

// Libera o lock de sessão (só leitura daqui pra frente) para as chamadas não serializarem.
if (function_exists('session_write_close')) {
    session_write_close();
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
        
        // Buscar alocações ativas (falha aqui não deve esconder os eixos já configurados)
        $pneusFlexAlocados = [];
        try {
            $stmt = $conn->prepare("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
            $stmt->execute();
            $posicao_id_exists = $stmt->rowCount() > 0;

            if ($posicao_id_exists) {
                $sql = "SELECT apf.*, p.numero_serie, p.marca, p.modelo, p.medida, p.status_id, sp.nome as status_nome, pp.nome as posicao_nome
                        FROM alocacoes_pneus_flexiveis apf
                        INNER JOIN pneus p ON apf.pneu_id = p.id
                        LEFT JOIN status_pneus sp ON p.status_id = sp.id
                        LEFT JOIN posicoes_pneus pp ON apf.posicao_id = pp.id
                        INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                        WHERE ev.veiculo_id = ? AND apf.empresa_id = ? AND apf.ativo = 1";
            } else {
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
        } catch (Exception $alocEx) {
            error_log('layout_completo alocacoes veiculo ' . $veiculo_id . ': ' . $alocEx->getMessage());
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
        api_require_csrf_json();
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

                $pos_check_id = $posicao_id ? (int) $posicao_id : null;
                if (file_exists(__DIR__ . '/../../includes/pneus_mercado_helpers.php')) {
                    require_once __DIR__ . '/../../includes/pneus_mercado_helpers.php';
                    $val = pneus_validar_posicao_instalacao($conn, (int) $pneu_id, $pos_check_id, $empresa_id);
                    if (empty($val['ok'])) {
                        ob_clean();
                        echo json_encode(['success' => false, 'error' => $val['error'] ?? 'Posição incompatível com o pneu']);
                        exit;
                    }
                }
                
                $conn->beginTransaction();

                // Desativar alocações anteriores do pneu
                $stmt = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE pneu_id = ? AND ativo = 1");
                $stmt->execute([$pneu_id]);

                // Desativar pneu anteriormente alocado neste slot
                $stmt = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE slot_id = ? AND empresa_id = ? AND ativo = 1");
                $stmt->execute([$slot_id, $empresa_id]);
                
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
                
                // Atualizar status do pneu para "em uso" na página Pneus
                $stmt = $conn->prepare("SELECT id FROM status_pneus WHERE LOWER(TRIM(nome)) IN ('em uso', 'em_uso') LIMIT 1");
                $stmt->execute();
                $status_row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($status_row) {
                    $stmt = $conn->prepare("UPDATE pneus SET status_id = ? WHERE id = ? AND empresa_id = ?");
                    $stmt->execute([$status_row['id'], $pneu_id, $empresa_id]);
                }
                
                // Histórico único: instalação no veículo
                $stmt = $conn->prepare("SELECT veiculo_id FROM eixos_veiculos WHERE id = ? AND empresa_id = ?");
                $stmt->execute([$eixo_id, $empresa_id]);
                $eixo_row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($eixo_row) {
                    $veiculo_id_inst = (int) $eixo_row['veiculo_id'];
                    $km_odometro = pneus_get_veiculo_km($conn, $veiculo_id_inst, $empresa_id);
                    if ($km_odometro !== null) {
                        $stmt = $conn->prepare(
                            'UPDATE pneus SET km_instalacao = CASE WHEN km_instalacao IS NULL OR km_instalacao = 0 THEN ? ELSE km_instalacao END,
                             updated_at = NOW() WHERE id = ? AND empresa_id = ?'
                        );
                        $stmt->execute([$km_odometro, $pneu_id, $empresa_id]);
                        if (pneus_column_exists($conn, 'pneus', 'km_rodado_total')) {
                            $stmt = $conn->prepare('SELECT km_instalacao FROM pneus WHERE id = ?');
                            $stmt->execute([$pneu_id]);
                            $ki = (int) ($stmt->fetchColumn() ?: 0);
                            $kr = max(0, $km_odometro - $ki);
                            $stmt = $conn->prepare('UPDATE pneus SET km_rodado_total = ? WHERE id = ? AND empresa_id = ?');
                            $stmt->execute([$kr, $pneu_id, $empresa_id]);
                        }
                    }
                    pneu_movimentacao_inserir($conn, [
                        'empresa_id'  => $empresa_id,
                        'pneu_id'     => (int) $pneu_id,
                        'tipo'        => 'instalacao',
                        'veiculo_id'  => $veiculo_id_inst,
                        'eixo_id'     => (int) $eixo_id,
                        'posicao_id'  => $posicao_id ? (int) $posicao_id : null,
                        'km_odometro' => $km_odometro,
                    ]);
                }

                $conn->commit();

                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu alocado com sucesso']);
                break;
                
            case 'remover_pneu':
                $slot_id = $input['slot_id'] ?? null;
                $motivo_remocao_id = isset($input['motivo_remocao_id']) ? (int) $input['motivo_remocao_id'] : null;
                
                if (!$slot_id) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'slot_id obrigatório']);
                    exit;
                }
                
                // Obter pneu_id e eixo antes de desativar para movimentação e status
                $stmt = $conn->prepare("SELECT apf.pneu_id, apf.eixo_veiculo_id FROM alocacoes_pneus_flexiveis apf WHERE apf.slot_id = ? AND apf.empresa_id = ? AND apf.ativo = 1 LIMIT 1");
                $stmt->execute([$slot_id, $empresa_id]);
                $aloc = $stmt->fetch(PDO::FETCH_ASSOC);
                $pneu_id_removido = $aloc ? (int)$aloc['pneu_id'] : null;
                $eixo_veiculo_id = $aloc ? (int)$aloc['eixo_veiculo_id'] : null;

                $conn->beginTransaction();

                // Desativar alocação
                $stmt = $conn->prepare("UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW() WHERE slot_id = ? AND empresa_id = ? AND ativo = 1");
                $stmt->execute([$slot_id, $empresa_id]);
                
                // Atualizar status do pneu para "usado" na página Pneus (quando removido do veículo)
                if ($pneu_id_removido) {
                    $stmt = $conn->prepare("SELECT id FROM status_pneus WHERE LOWER(TRIM(nome)) = 'usado' LIMIT 1");
                    $stmt->execute();
                    $status_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($status_row) {
                        $stmt = $conn->prepare("UPDATE pneus SET status_id = ? WHERE id = ? AND empresa_id = ?");
                        $stmt->execute([$status_row['id'], $pneu_id_removido, $empresa_id]);
                    }
                    // Histórico único: remoção do veículo
                    if ($eixo_veiculo_id) {
                        $stmt = $conn->prepare("SELECT veiculo_id FROM eixos_veiculos WHERE id = ? AND empresa_id = ?");
                        $stmt->execute([$eixo_veiculo_id, $empresa_id]);
                        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($ev) {
                            $mov = [
                                'empresa_id' => $empresa_id,
                                'pneu_id'    => $pneu_id_removido,
                                'tipo'       => 'remocao',
                                'veiculo_id' => (int) $ev['veiculo_id'],
                                'eixo_id'    => $eixo_veiculo_id,
                            ];
                            if ($motivo_remocao_id > 0) {
                                $mov['motivo_remocao_id'] = $motivo_remocao_id;
                            }
                            pneu_movimentacao_inserir($conn, $mov);
                            if (file_exists(__DIR__ . '/../../includes/pneus_mercado_helpers.php')) {
                                require_once __DIR__ . '/../../includes/pneus_mercado_helpers.php';
                                pneus_auditoria_registrar($conn, [
                                    'empresa_id' => $empresa_id,
                                    'pneu_id' => $pneu_id_removido,
                                    'acao' => 'remocao_veiculo',
                                    'dados' => ['slot_id' => $slot_id, 'motivo_remocao_id' => $motivo_remocao_id],
                                ]);
                            }
                        }
                    }
                }

                $conn->commit();

                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu removido com sucesso']);
                break;

            case 'mover_pneu':
                $from_slot = $input['from_slot_id'] ?? null;
                $to_slot = $input['to_slot_id'] ?? null;

                if (!$from_slot || !$to_slot || $from_slot === $to_slot) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Slots de origem e destino inválidos']);
                    exit;
                }

                $stmt = $conn->prepare(
                    'SELECT apf.pneu_id, apf.posicao_id, ev.veiculo_id
                     FROM alocacoes_pneus_flexiveis apf
                     INNER JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                     WHERE apf.slot_id = ? AND apf.empresa_id = ? AND apf.ativo = 1
                     LIMIT 1'
                );
                $stmt->execute([$from_slot, $empresa_id]);
                $origem = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$origem) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Nenhum pneu alocado no slot de origem']);
                    exit;
                }

                $stmt = $conn->prepare(
                    'SELECT id FROM alocacoes_pneus_flexiveis WHERE slot_id = ? AND empresa_id = ? AND ativo = 1 LIMIT 1'
                );
                $stmt->execute([$to_slot, $empresa_id]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Slot de destino já está ocupado']);
                    exit;
                }

                $veiculo_id_move = (int) $origem['veiculo_id'];
                $target = eixos_resolve_slot_veiculo($conn, $veiculo_id_move, $empresa_id, $to_slot);
                if (!$target) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Slot de destino inválido para este veículo']);
                    exit;
                }

                $pneu_id_move = (int) $origem['pneu_id'];
                $posicao_id_move = isset($origem['posicao_id']) ? (int) $origem['posicao_id'] : null;
                if ($posicao_id_move <= 0) {
                    $posicao_id_move = null;
                }

                $conn->beginTransaction();

                $stmt = $conn->prepare(
                    'UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW()
                     WHERE slot_id = ? AND empresa_id = ? AND ativo = 1'
                );
                $stmt->execute([$from_slot, $empresa_id]);

                if (eixos_alocacao_posicao_id_exists($conn)) {
                    $stmt = $conn->prepare(
                        'INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, posicao_id, slot_id, empresa_id)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $target['eixo_id'],
                        $pneu_id_move,
                        $target['posicao_slot'],
                        $posicao_id_move,
                        $to_slot,
                        $empresa_id,
                    ]);
                } else {
                    $stmt = $conn->prepare(
                        'INSERT INTO alocacoes_pneus_flexiveis (eixo_veiculo_id, pneu_id, posicao_slot, slot_id, empresa_id)
                         VALUES (?, ?, ?, ?, ?)'
                    );
                    $stmt->execute([
                        $target['eixo_id'],
                        $pneu_id_move,
                        $target['posicao_slot'],
                        $to_slot,
                        $empresa_id,
                    ]);
                }

                pneu_movimentacao_inserir($conn, [
                    'empresa_id' => $empresa_id,
                    'pneu_id' => $pneu_id_move,
                    'tipo' => 'deslocamento',
                    'veiculo_id' => $veiculo_id_move,
                    'eixo_id' => $target['eixo_id'],
                    'posicao_id' => $posicao_id_move,
                    'observacoes' => 'Movido de ' . $from_slot . ' para ' . $to_slot,
                ]);

                $conn->commit();

                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu movido com sucesso']);
                break;

            case 'enviar_manutencao':
                $slot_id = $input['slot_id'] ?? null;
                $observacoes = trim((string) ($input['observacoes'] ?? 'Enviado para manutenção'));

                if (!$slot_id) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'slot_id obrigatório']);
                    exit;
                }

                $stmt = $conn->prepare(
                    'SELECT apf.pneu_id, apf.eixo_veiculo_id, apf.posicao_id
                     FROM alocacoes_pneus_flexiveis apf
                     WHERE apf.slot_id = ? AND apf.empresa_id = ? AND apf.ativo = 1
                     LIMIT 1'
                );
                $stmt->execute([$slot_id, $empresa_id]);
                $aloc = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$aloc) {
                    ob_clean();
                    echo json_encode(['success' => false, 'error' => 'Nenhum pneu alocado neste slot']);
                    exit;
                }

                $pneu_id_manut = (int) $aloc['pneu_id'];
                $eixo_veiculo_id = (int) $aloc['eixo_veiculo_id'];
                $posicao_id_manut = isset($aloc['posicao_id']) ? (int) $aloc['posicao_id'] : null;
                if ($posicao_id_manut <= 0) {
                    $posicao_id_manut = null;
                }

                $stmt = $conn->prepare('SELECT veiculo_id FROM eixos_veiculos WHERE id = ? AND empresa_id = ?');
                $stmt->execute([$eixo_veiculo_id, $empresa_id]);
                $ev = $stmt->fetch(PDO::FETCH_ASSOC);
                $veiculo_id_manut = $ev ? (int) $ev['veiculo_id'] : null;

                $conn->beginTransaction();

                $stmt = $conn->prepare(
                    'UPDATE alocacoes_pneus_flexiveis SET ativo = 0, data_remocao = NOW()
                     WHERE slot_id = ? AND empresa_id = ? AND ativo = 1'
                );
                $stmt->execute([$slot_id, $empresa_id]);

                $statusManut = pneus_status_id_manutencao($conn) ?? pneus_status_id_por_nome($conn, 'furado');
                if ($statusManut) {
                    pneus_sync_status_estoque($conn, $pneu_id_manut, $empresa_id, $statusManut, 0);
                }

                if ($veiculo_id_manut) {
                    pneu_movimentacao_inserir($conn, [
                        'empresa_id' => $empresa_id,
                        'pneu_id' => $pneu_id_manut,
                        'tipo' => 'manutencao',
                        'veiculo_id' => $veiculo_id_manut,
                        'eixo_id' => $eixo_veiculo_id,
                        'posicao_id' => $posicao_id_manut,
                        'observacoes' => $observacoes,
                    ]);
                }

                $conn->commit();

                ob_clean();
                echo json_encode(['success' => true, 'message' => 'Pneu enviado para manutenção']);
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
    if (isset($conn) && $conn->inTransaction()) {
        $conn->rollBack();
    }
    ob_clean();
    error_log("Erro na API de eixos: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar a operação de eixos.']);
}
?>
