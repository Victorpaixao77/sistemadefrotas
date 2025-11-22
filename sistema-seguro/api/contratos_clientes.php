<?php
/**
 * API para gerenciar contratos de clientes
 * CRUD completo para seguro_contratos_clientes
 */

// Iniciar output buffering para capturar qualquer output indesejado
ob_start();

// Desabilitar exibição de erros para não quebrar JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config/database.php';
require_once '../config/auth.php';

// Limpar qualquer output anterior
ob_clean();

// Garantir que o header seja enviado antes de qualquer output
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticação
verificarLogin();
$usuario = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

try {
    $pdo = getDB();
    
    // Verificar se a tabela existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'seguro_contratos_clientes'");
    $tabelaExiste = $stmt->fetch();
    
    if (!$tabelaExiste) {
        // Tabela não existe, retornar array vazio em vez de erro
        echo json_encode([
            'sucesso' => true,
            'contratos' => [],
            'aviso' => 'Tabela de contratos não existe. Execute criar_tabela_contratos.php'
        ]);
        exit;
    }
    
    $metodo = $_SERVER['REQUEST_METHOD'];
    
    switch ($metodo) {
        case 'GET':
            // Listar contratos de um cliente
            if (isset($_GET['cliente_id'])) {
                $cliente_id = intval($_GET['cliente_id']);
                
                $sql = "SELECT * FROM seguro_contratos_clientes 
                        WHERE cliente_id = :cliente_id 
                          AND empresa_id = :empresa_id
                          AND ativo = 'sim'
                        ORDER BY matricula ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'cliente_id' => $cliente_id,
                    'empresa_id' => $empresa_id
                ]);
                
                $contratos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'sucesso' => true,
                    'contratos' => $contratos
                ]);
                
            } elseif (isset($_GET['id'])) {
                // Buscar contrato específico
                $id = intval($_GET['id']);
                
                $sql = "SELECT * FROM seguro_contratos_clientes 
                        WHERE id = :id AND empresa_id = :empresa_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'id' => $id,
                    'empresa_id' => $empresa_id
                ]);
                
                $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($contrato) {
                    echo json_encode([
                        'sucesso' => true,
                        'contrato' => $contrato
                    ]);
                } else {
                    echo json_encode([
                        'sucesso' => false,
                        'erro' => 'Contrato não encontrado'
                    ]);
                }
            } else {
                echo json_encode([
                    'sucesso' => false,
                    'erro' => 'Parâmetro cliente_id ou id é obrigatório'
                ]);
            }
            break;
            
        case 'POST':
            // Criar novo contrato
            error_log("POST - Iniciando criação de contrato");
            
            $dados = json_decode(file_get_contents('php://input'), true);
            error_log("POST - Dados recebidos: " . json_encode($dados));
            
            $cliente_id = intval($dados['cliente_id'] ?? 0);
            $matricula = trim($dados['matricula'] ?? '');
            $placa = trim($dados['placa'] ?? '');
            $porcentagem = floatval($dados['porcentagem_recorrencia'] ?? 0);
            $dataInicio = $dados['data_inicio'] ?? null;
            $valor = isset($dados['valor']) && $dados['valor'] !== '' ? floatval($dados['valor']) : null;
            $situacao = $dados['situacao'] ?? 'aguardando_ativacao';
            $tipoOs = trim($dados['tipo_os'] ?? '');
            $envioWhatsapp = $dados['envio_whatsapp'] ?? 'nao';
            $envioEmail = $dados['envio_email'] ?? 'nao';
            $planilha = $dados['planilha'] ?? 'nao';
            $observacoes = trim($dados['observacoes'] ?? '');
            
            error_log("POST - Valores processados: cliente_id=$cliente_id, matricula=$matricula, placa=$placa, porcentagem=$porcentagem, data_inicio=$dataInicio, valor=$valor, situacao=$situacao, tipo_os=$tipoOs");
            
            // Validações
            if (empty($cliente_id)) {
                throw new Exception('Cliente ID é obrigatório');
            }
            
            if (empty($matricula)) {
                throw new Exception('Matrícula é obrigatória');
            }
            
            // Verificar se matrícula já existe para esta empresa
            $stmt = $pdo->prepare("SELECT id FROM seguro_contratos_clientes 
                                  WHERE matricula = ? AND empresa_id = ? AND ativo = 'sim'");
            $stmt->execute([$matricula, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('CONJUNTO já cadastrado para esta empresa');
            }
            
            error_log("POST - Validações OK, inserindo contrato");
            
            // Inserir contrato
            $sql = "INSERT INTO seguro_contratos_clientes 
                    (cliente_id, empresa_id, matricula, placa, porcentagem_recorrencia, data_inicio, valor, situacao, tipo_os, envio_whatsapp, envio_email, planilha, observacoes) 
                    VALUES (:cliente_id, :empresa_id, :matricula, :placa, :porcentagem, :data_inicio, :valor, :situacao, :tipo_os, :envio_whatsapp, :envio_email, :planilha, :observacoes)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'cliente_id' => $cliente_id,
                'empresa_id' => $empresa_id,
                'matricula' => $matricula,
                'placa' => $placa,
                'porcentagem' => $porcentagem,
                'data_inicio' => $dataInicio,
                'valor' => $valor,
                'situacao' => $situacao,
                'tipo_os' => $tipoOs ?: null,
                'envio_whatsapp' => $envioWhatsapp,
                'envio_email' => $envioEmail,
                'planilha' => $planilha,
                'observacoes' => $observacoes
            ]);
            
            $contrato_id = $pdo->lastInsertId();
            error_log("POST - Contrato criado com ID: $contrato_id");
            
            // Registrar log com os parâmetros corretos
            if (function_exists('registrarLog')) {
                registrarLog($empresa_id, $usuario['id'], 'criar', 'contratos', "CONJUNTO: $matricula para cliente ID: $cliente_id");
            }
            
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Contrato cadastrado com sucesso',
                'contrato_id' => $contrato_id
            ]);
            break;
            
        case 'PUT':
            // Atualizar contrato
            $dados = json_decode(file_get_contents('php://input'), true);
            
            $id = intval($dados['id'] ?? 0);
            $matricula = trim($dados['matricula'] ?? '');
            $placa = trim($dados['placa'] ?? '');
            $porcentagem = floatval($dados['porcentagem_recorrencia'] ?? 0);
            $dataInicio = $dados['data_inicio'] ?? null;
            $valor = isset($dados['valor']) && $dados['valor'] !== '' ? floatval($dados['valor']) : null;
            $situacao = $dados['situacao'] ?? 'aguardando_ativacao';
            $tipoOs = trim($dados['tipo_os'] ?? '');
            $envioWhatsapp = $dados['envio_whatsapp'] ?? 'nao';
            $envioEmail = $dados['envio_email'] ?? 'nao';
            $planilha = $dados['planilha'] ?? 'nao';
            $observacoes = trim($dados['observacoes'] ?? '');
            
            if (empty($id)) {
                throw new Exception('ID do contrato é obrigatório');
            }
            
            if (empty($matricula)) {
                throw new Exception('Matrícula é obrigatória');
            }
            
            // Verificar se matrícula já existe (exceto o próprio registro)
            $stmt = $pdo->prepare("SELECT id FROM seguro_contratos_clientes 
                                  WHERE matricula = ? AND empresa_id = ? AND id != ? AND ativo = 'sim'");
            $stmt->execute([$matricula, $empresa_id, $id]);
            
            if ($stmt->fetch()) {
                throw new Exception('Matrícula já cadastrada para outro contrato');
            }
            
            // Atualizar contrato
            $sql = "UPDATE seguro_contratos_clientes 
                    SET matricula = :matricula,
                        placa = :placa,
                        porcentagem_recorrencia = :porcentagem,
                        data_inicio = :data_inicio,
                        valor = :valor,
                        situacao = :situacao,
                        tipo_os = :tipo_os,
                        envio_whatsapp = :envio_whatsapp,
                        envio_email = :envio_email,
                        planilha = :planilha,
                        observacoes = :observacoes
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'matricula' => $matricula,
                'placa' => $placa,
                'porcentagem' => $porcentagem,
                'data_inicio' => $dataInicio,
                'valor' => $valor,
                'situacao' => $situacao,
                'tipo_os' => $tipoOs ?: null,
                'envio_whatsapp' => $envioWhatsapp,
                'envio_email' => $envioEmail,
                'planilha' => $planilha,
                'observacoes' => $observacoes,
                'id' => $id,
                'empresa_id' => $empresa_id
            ]);
            
            // Registrar log com os parâmetros corretos
            if (function_exists('registrarLog')) {
                registrarLog($empresa_id, $usuario['id'], 'editar', 'contratos', "CONJUNTO: $matricula (ID: $id)");
            }
            
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Contrato atualizado com sucesso'
            ]);
            break;
            
        case 'DELETE':
            // Excluir (desativar) contrato
            $dados = json_decode(file_get_contents('php://input'), true);
            $id = intval($dados['id'] ?? $_GET['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('ID do contrato é obrigatório');
            }
            
            // Soft delete
            $sql = "UPDATE seguro_contratos_clientes 
                    SET ativo = 'nao'
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'empresa_id' => $empresa_id
            ]);
            
            // Registrar log com os parâmetros corretos
            if (function_exists('registrarLog')) {
                registrarLog($empresa_id, $usuario['id'], 'excluir', 'contratos', "Contrato ID: $id");
            }
            
            echo json_encode([
                'sucesso' => true,
                'mensagem' => 'Contrato removido com sucesso'
            ]);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'erro' => 'Método não permitido'
            ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    $mensagem = "Erro no banco de dados: " . $e->getMessage();
    
    echo json_encode([
        'sucesso' => false,
        'erro' => $mensagem
    ]);
    
    error_log("ERRO PDO em contratos_clientes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'erro' => $e->getMessage()
    ]);
    
    error_log("Erro em contratos_clientes.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
}

// Limpar buffer e enviar resposta
ob_end_flush();
?>