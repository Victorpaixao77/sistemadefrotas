<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/config.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$action = $_GET['action'] ?? 'status';

try {
    $conn = getConnection();
    
    switch ($action) {
        case 'status':
            // Verificar status da sincronização
            $status = getSyncStatus($conn, $empresa_id);
            echo json_encode(['success' => true, 'data' => $status]);
            break;
            
        case 'sync_multas':
            // Sincronizar multas manualmente
            $result = syncMultas($conn, $empresa_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'sync_cnh':
            // Sincronizar CNH manualmente
            $result = syncCNH($conn, $empresa_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'sync_all':
            // Sincronizar tudo
            $result = syncAll($conn, $empresa_id);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        case 'check_triggers':
            // Verificar se os triggers estão funcionando
            $result = checkTriggers($conn);
            echo json_encode(['success' => true, 'data' => $result]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Ação inválida']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Erro na API de sincronização: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}

function getSyncStatus($conn, $empresa_id) {
    $status = [];
    
    // Verificar se a tabela calendario_eventos existe
    try {
        $sql_check = "SHOW TABLES LIKE 'calendario_eventos'";
        $stmt = $conn->prepare($sql_check);
        $stmt->execute();
        $table_exists = $stmt->rowCount() > 0;
        
        if (!$table_exists) {
            $status['error'] = 'Tabela calendario_eventos não existe. Execute o setup primeiro.';
            return $status;
        }
        
        // Verificar se as colunas de origem existem
        $sql_columns = "SHOW COLUMNS FROM calendario_eventos LIKE 'origem_tipo'";
        $stmt = $conn->prepare($sql_columns);
        $stmt->execute();
        $origem_tipo_exists = $stmt->rowCount() > 0;
        
        if (!$origem_tipo_exists) {
            $status['error'] = 'Colunas de origem não existem. Execute o setup primeiro.';
            return $status;
        }
        
        // Verificar eventos de multas
        $sql_multas = "SELECT COUNT(*) as total FROM calendario_eventos 
                       WHERE origem_tipo = 'multa' AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql_multas);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status['eventos_multas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Verificar eventos de CNH
        $sql_cnh = "SELECT COUNT(*) as total FROM calendario_eventos 
                    WHERE origem_tipo = 'cnh' AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql_cnh);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status['eventos_cnh'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
    } catch (Exception $e) {
        $status['error'] = 'Erro ao verificar tabelas: ' . $e->getMessage();
        return $status;
    }
    
    // Verificar multas pendentes
    try {
        $sql_multas_pendentes = "SELECT COUNT(*) as total FROM multas 
                                WHERE empresa_id = :empresa_id 
                                AND vencimento IS NOT NULL 
                                AND status_pagamento != 'pago'";
        $stmt = $conn->prepare($sql_multas_pendentes);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status['multas_pendentes'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $status['multas_pendentes'] = 0;
        $status['error_multas'] = 'Erro ao verificar multas: ' . $e->getMessage();
    }
    
    // Verificar motoristas com CNH válida
    try {
        $sql_cnh_validas = "SELECT COUNT(*) as total FROM motoristas 
                            WHERE empresa_id = :empresa_id 
                            AND data_validade_cnh IS NOT NULL 
                            AND data_validade_cnh >= CURRENT_DATE";
        $stmt = $conn->prepare($sql_cnh_validas);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $status['cnh_validas'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        $status['cnh_validas'] = 0;
        $status['error_cnh'] = 'Erro ao verificar motoristas: ' . $e->getMessage();
    }
    
    // Verificar se há discrepâncias
    $status['multas_sincronizadas'] = $status['eventos_multas'] == $status['multas_pendentes'];
    $status['cnh_sincronizadas'] = $status['eventos_cnh'] > 0;
    
    return $status;
}

function syncMultas($conn, $empresa_id) {
    try {
        // Verificar se as colunas de origem existem
        $sql_columns = "SHOW COLUMNS FROM calendario_eventos LIKE 'origem_tipo'";
        $stmt = $conn->prepare($sql_columns);
        $stmt->execute();
        $origem_tipo_exists = $stmt->rowCount() > 0;
        
        if (!$origem_tipo_exists) {
            return [
                'error' => 'Colunas de origem não existem. Execute o setup primeiro.',
                'eventos_criados' => 0,
                'multas_processadas' => 0
            ];
        }
        
        // Primeiro, remover eventos antigos de multas
        $sql_delete = "DELETE FROM calendario_eventos 
                       WHERE origem_tipo = 'multa' AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql_delete);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        // Buscar categoria de multas
        $sql_categoria = "SELECT id FROM categorias_calendario 
                         WHERE nome = 'Multas' AND empresa_id = :empresa_id LIMIT 1";
        $stmt = $conn->prepare($sql_categoria);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoria) {
            // Criar categoria se não existir
            $sql_insert_categoria = "INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
                                    VALUES ('Multas', 'Vencimento de multas de trânsito', '#f59e0b', 'fas fa-exclamation-triangle', :empresa_id)";
            $stmt = $conn->prepare($sql_insert_categoria);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->execute();
            $categoria_id = $conn->lastInsertId();
        } else {
            $categoria_id = $categoria['id'];
        }
        
        // Buscar todas as multas pendentes
        $sql_multas = "SELECT id, vencimento, tipo_infracao, valor, veiculo_id, motorista_id
                       FROM multas 
                       WHERE empresa_id = :empresa_id 
                       AND vencimento IS NOT NULL 
                       AND status_pagamento != 'pago'";
        $stmt = $conn->prepare($sql_multas);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $multas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $eventos_criados = 0;
        
        foreach ($multas as $multa) {
            $dias_para_vencimento = (strtotime($multa['vencimento']) - time()) / (60 * 60 * 24);
            
            // Definir cor baseada na proximidade do vencimento
            if ($dias_para_vencimento <= 7) {
                $cor_evento = '#ef4444'; // Vermelho (crítico)
            } elseif ($dias_para_vencimento <= 15) {
                $cor_evento = '#f59e0b'; // Amarelo (médio)
            } else {
                $cor_evento = '#3b82f6'; // Azul (baixo)
            }
            
            // Criar título do evento
            $titulo_evento = 'Multa: ' . $multa['tipo_infracao'] . ' - R$ ' . $multa['valor'];
            
            // Inserir evento no calendário
            $sql_insert = "INSERT INTO calendario_eventos (
                titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                empresa_id, origem_tipo, origem_id, sincronizado
            ) VALUES (
                :titulo, :categoria_id, :data_inicio, :data_fim, :descricao, :cor,
                :empresa_id, 'multa', :origem_id, TRUE
            )";
            
            $stmt = $conn->prepare($sql_insert);
            $stmt->bindParam(':titulo', $titulo_evento);
            $stmt->bindParam(':categoria_id', $categoria_id);
            $stmt->bindParam(':data_inicio', $multa['vencimento']);
            $stmt->bindParam(':data_fim', $multa['vencimento']);
            $descricao = 'Multa de ' . $multa['tipo_infracao'] . ' - Veículo ID: ' . $multa['veiculo_id'] . ' - Motorista ID: ' . $multa['motorista_id'];
        $stmt->bindParam(':descricao', $descricao);
            $stmt->bindParam(':cor', $cor_evento);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':origem_id', $multa['id']);
            
            if ($stmt->execute()) {
                $eventos_criados++;
            }
        }
        
        return [
            'eventos_criados' => $eventos_criados,
            'multas_processadas' => count($multas)
        ];
        
    } catch (Exception $e) {
        return [
            'error' => 'Erro ao sincronizar multas: ' . $e->getMessage(),
            'eventos_criados' => 0,
            'multas_processadas' => 0
        ];
    }
}

function syncCNH($conn, $empresa_id) {
    try {
        // Verificar se as colunas de origem existem
        $sql_columns = "SHOW COLUMNS FROM calendario_eventos LIKE 'origem_tipo'";
        $stmt = $conn->prepare($sql_columns);
        $stmt->execute();
        $origem_tipo_exists = $stmt->rowCount() > 0;
        
        if (!$origem_tipo_exists) {
            return [
                'error' => 'Colunas de origem não existem. Execute o setup primeiro.',
                'eventos_criados' => 0,
                'motoristas_processados' => 0
            ];
        }
        
        // Primeiro, remover eventos antigos de CNH
        $sql_delete = "DELETE FROM calendario_eventos 
                       WHERE origem_tipo = 'cnh' AND empresa_id = :empresa_id";
        $stmt = $conn->prepare($sql_delete);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        // Buscar categoria de CNH
        $sql_categoria = "SELECT id FROM categorias_calendario 
                         WHERE nome = 'CNH' AND empresa_id = :empresa_id LIMIT 1";
        $stmt = $conn->prepare($sql_categoria);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$categoria) {
            // Criar categoria se não existir
            $sql_insert_categoria = "INSERT INTO categorias_calendario (nome, descricao, cor_padrao, icone, empresa_id) 
                                    VALUES ('CNH', 'Vencimento de Carteira Nacional de Habilitação', '#ef4444', 'fas fa-id-card', :empresa_id)";
            $stmt = $conn->prepare($sql_insert_categoria);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->execute();
            $categoria_id = $conn->lastInsertId();
        } else {
            $categoria_id = $categoria['id'];
        }
        
        // Buscar todos os motoristas com CNH válida
        $sql_motoristas = "SELECT id, nome, cnh, data_validade_cnh
                           FROM motoristas 
                           WHERE empresa_id = :empresa_id 
                           AND data_validade_cnh IS NOT NULL 
                           AND data_validade_cnh >= CURRENT_DATE";
        $stmt = $conn->prepare($sql_motoristas);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $eventos_criados = 0;
        
        foreach ($motoristas as $motorista) {
            $dias_para_vencimento = (strtotime($motorista['data_validade_cnh']) - time()) / (60 * 60 * 24);
            
            // Criar eventos para diferentes alertas
            if ($dias_para_vencimento <= 30) {
                // Vencimento em 30 dias ou menos - ALERTA VERMELHO
                $cor_evento = '#ef4444';
                $titulo_evento = 'CNH Vence em 30 dias: ' . $motorista['nome'];
                
                $sql_insert = "INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    :titulo, :categoria_id, :data_inicio, :data_fim, :descricao, :cor,
                    :empresa_id, 'cnh', :origem_id, TRUE
                )";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->bindParam(':titulo', $titulo_evento);
                $stmt->bindParam(':categoria_id', $categoria_id);
                $stmt->bindParam(':data_inicio', $motorista['data_validade_cnh']);
                $stmt->bindParam(':data_fim', $motorista['data_validade_cnh']);
                $descricao = 'CNH do motorista ' . $motorista['nome'] . ' (Número: ' . ($motorista['cnh'] ?? 'N/A') . ') vence em ' . round($dias_para_vencimento) . ' dias.';
                $stmt->bindParam(':descricao', $descricao);
                $stmt->bindParam(':cor', $cor_evento);
                $stmt->bindParam(':empresa_id', $empresa_id);
                $stmt->bindParam(':origem_id', $motorista['id']);
                
                if ($stmt->execute()) {
                    $eventos_criados++;
                }
            } elseif ($dias_para_vencimento <= 60) {
                // Vencimento em 60 dias ou menos - ALERTA AMARELO
                $cor_evento = '#f59e0b';
                $titulo_evento = 'CNH Vence em 60 dias: ' . $motorista['nome'];
                
                $sql_insert = "INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    :titulo, :categoria_id, :data_inicio, :data_fim, :descricao, :cor,
                    :empresa_id, 'cnh', :origem_id, TRUE
                )";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->bindParam(':titulo', $titulo_evento);
                $stmt->bindParam(':categoria_id', $categoria_id);
                $stmt->bindParam(':data_inicio', $motorista['data_validade_cnh']);
                $stmt->bindParam(':data_fim', $motorista['data_validade_cnh']);
                $descricao = 'CNH do motorista ' . $motorista['nome'] . ' (Número: ' . ($motorista['cnh'] ?? 'N/A') . ') vence em ' . round($dias_para_vencimento) . ' dias.';
                $stmt->bindParam(':descricao', $descricao);
                $stmt->bindParam(':cor', $cor_evento);
                $stmt->bindParam(':empresa_id', $empresa_id);
                $stmt->bindParam(':origem_id', $motorista['id']);
                
                if ($stmt->execute()) {
                    $eventos_criados++;
                }
            } elseif ($dias_para_vencimento <= 90) {
                // Vencimento em 90 dias ou menos - ALERTA AZUL
                $cor_evento = '#3b82f6';
                $titulo_evento = 'CNH Vence em 90 dias: ' . $motorista['nome'];
                
                $sql_insert = "INSERT INTO calendario_eventos (
                    titulo, categoria_id, data_inicio, data_fim, descricao, cor,
                    empresa_id, origem_tipo, origem_id, sincronizado
                ) VALUES (
                    :titulo, :categoria_id, :data_inicio, :data_fim, :descricao, :cor,
                    :empresa_id, 'cnh', :origem_id, TRUE
                )";
                
                $stmt = $conn->prepare($sql_insert);
                $stmt->bindParam(':titulo', $titulo_evento);
                $stmt->bindParam(':categoria_id', $categoria_id);
                $stmt->bindParam(':data_inicio', $motorista['data_validade_cnh']);
                $stmt->bindParam(':data_fim', $motorista['data_validade_cnh']);
                $descricao = 'CNH do motorista ' . $motorista['nome'] . ' (Número: ' . ($motorista['cnh'] ?? 'N/A') . ') vence em ' . round($dias_para_vencimento) . ' dias.';
                $stmt->bindParam(':descricao', $descricao);
                $stmt->bindParam(':cor', $cor_evento);
                $stmt->bindParam(':empresa_id', $empresa_id);
                $stmt->bindParam(':origem_id', $motorista['id']);
                
                if ($stmt->execute()) {
                    $eventos_criados++;
                }
            }
        }
        
        return [
            'eventos_criados' => $eventos_criados,
            'motoristas_processados' => count($motoristas)
        ];
        
    } catch (Exception $e) {
        return [
            'error' => 'Erro ao sincronizar CNH: ' . $e->getMessage(),
            'eventos_criados' => 0,
            'motoristas_processados' => 0
        ];
    }
}

function syncAll($conn, $empresa_id) {
    $result = [];
    
    // Sincronizar multas
    $result['multas'] = syncMultas($conn, $empresa_id);
    
    // Sincronizar CNH
    $result['cnh'] = syncCNH($conn, $empresa_id);
    
    // Status final
    $result['status'] = getSyncStatus($conn, $empresa_id);
    
    return $result;
}

function checkTriggers($conn) {
    $result = [];
    
    try {
        // Verificar se os triggers existem
        $sql = "SHOW TRIGGERS LIKE 'trigger_%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['triggers_encontrados'] = count($triggers);
        $result['triggers'] = $triggers;
        
        // Verificar se os procedimentos existem
        $sql = "SHOW PROCEDURE STATUS LIKE 'sincronizar_%'";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $procedures = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result['procedures_encontrados'] = count($procedures);
        $result['procedures'] = $procedures;
        
    } catch (Exception $e) {
        $result['error'] = 'Erro ao verificar triggers: ' . $e->getMessage();
        $result['triggers_encontrados'] = 0;
        $result['procedures_encontrados'] = 0;
        $result['triggers'] = [];
        $result['procedures'] = [];
    }
    
    return $result;
}
?>
