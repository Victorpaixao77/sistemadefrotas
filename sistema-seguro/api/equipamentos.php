<?php
/**
 * API - Gerenciar Equipamentos dos Clientes
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$empresa_id = obterEmpresaId();
$usuario = obterUsuarioLogado();

// Método da requisição
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        // Listar equipamentos
        
        if (isset($_GET['cliente_id'])) {
            // Equipamentos de um cliente específico
            $cliente_id = intval($_GET['cliente_id']);
            
            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    DATE_FORMAT(e.data_cadastro, '%d/%m/%Y') as data_cadastro_fmt,
                    DATE_FORMAT(e.data_instalacao, '%d/%m/%Y') as data_instalacao_fmt
                FROM seguro_equipamentos e
                WHERE e.seguro_cliente_id = ? AND e.seguro_empresa_id = ?
                ORDER BY e.data_cadastro DESC
            ");
            $stmt->execute([$cliente_id, $empresa_id]);
            
        } elseif (isset($_GET['id'])) {
            // Um equipamento específico
            $id = intval($_GET['id']);
            
            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    DATE_FORMAT(e.data_cadastro, '%d/%m/%Y') as data_cadastro_fmt,
                    DATE_FORMAT(e.data_instalacao, '%d/%m/%Y') as data_instalacao_fmt,
                    c.nome_razao_social as cliente_nome
                FROM seguro_equipamentos e
                LEFT JOIN seguro_clientes c ON e.seguro_cliente_id = c.id
                WHERE e.id = ? AND e.seguro_empresa_id = ?
            ");
            $stmt->execute([$id, $empresa_id]);
            
        } else {
            // Todos os equipamentos da empresa
            $stmt = $db->prepare("
                SELECT 
                    e.*,
                    DATE_FORMAT(e.data_cadastro, '%d/%m/%Y') as data_cadastro_fmt,
                    DATE_FORMAT(e.data_instalacao, '%d/%m/%Y') as data_instalacao_fmt,
                    c.nome_razao_social as cliente_nome
                FROM seguro_equipamentos e
                LEFT JOIN seguro_clientes c ON e.seguro_cliente_id = c.id
                WHERE e.seguro_empresa_id = ?
                ORDER BY e.data_cadastro DESC
                LIMIT 100
            ");
            $stmt->execute([$empresa_id]);
        }
        
        $equipamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'sucesso' => true,
            'equipamentos' => $equipamentos,
            'total' => count($equipamentos)
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($method === 'POST') {
        // Criar novo equipamento
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $stmt = $db->prepare("
            INSERT INTO seguro_equipamentos (
                seguro_empresa_id,
                seguro_cliente_id,
                tipo,
                descricao,
                marca,
                modelo,
                numero_serie,
                data_instalacao,
                localizacao,
                situacao,
                observacoes,
                data_cadastro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $empresa_id,
            $data['cliente_id'] ?? null,
            $data['tipo'] ?? '',
            $data['descricao'] ?? '',
            $data['marca'] ?? null,
            $data['modelo'] ?? null,
            $data['numero_serie'] ?? null,
            $data['data_instalacao'] ?? null,
            $data['localizacao'] ?? null,
            $data['situacao'] ?? 'ativo',
            $data['observacoes'] ?? null
        ]);
        
        $equipamento_id = $db->lastInsertId();
        
        echo json_encode([
            'sucesso' => true,
            'equipamento_id' => $equipamento_id,
            'mensagem' => 'Equipamento cadastrado com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($method === 'PUT') {
        // Atualizar equipamento
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $stmt = $db->prepare("
            UPDATE seguro_equipamentos 
            SET 
                tipo = ?,
                descricao = ?,
                marca = ?,
                modelo = ?,
                numero_serie = ?,
                data_instalacao = ?,
                localizacao = ?,
                situacao = ?,
                observacoes = ?
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        
        $stmt->execute([
            $data['tipo'] ?? '',
            $data['descricao'] ?? '',
            $data['marca'] ?? null,
            $data['modelo'] ?? null,
            $data['numero_serie'] ?? null,
            $data['data_instalacao'] ?? null,
            $data['localizacao'] ?? null,
            $data['situacao'] ?? 'ativo',
            $data['observacoes'] ?? null,
            $data['id'],
            $empresa_id
        ]);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Equipamento atualizado com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($method === 'DELETE') {
        // Deletar equipamento
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $stmt = $db->prepare("
            DELETE FROM seguro_equipamentos 
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        
        $stmt->execute([
            $data['id'],
            $empresa_id
        ]);
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Equipamento excluído com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Erro em equipamentos API: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

