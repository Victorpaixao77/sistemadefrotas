<?php
/**
 * API - Gerenciar Atendimentos
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
        // Listar atendimentos
        
        if (isset($_GET['cliente_id'])) {
            // Atendimentos de um cliente específico
            $cliente_id = intval($_GET['cliente_id']);
            
            $stmt = $db->prepare("
                SELECT 
                    a.*,
                    a.titulo as assunto,
                    a.solucao as resposta,
                    COALESCE(c.razao_social, c.nome_razao_social) as cliente_nome,
                    u.nome as usuario_nome,
                    DATE_FORMAT(a.data_abertura, '%d/%m/%Y %H:%i') as data_abertura_fmt,
                    DATE_FORMAT(a.data_fechamento, '%d/%m/%Y %H:%i') as data_fechamento_fmt
                FROM seguro_atendimentos a
                LEFT JOIN seguro_clientes c ON a.seguro_cliente_id = c.id
                LEFT JOIN seguro_usuarios u ON a.usuario_id = u.id
                WHERE a.seguro_cliente_id = ? AND a.seguro_empresa_id = ?
                ORDER BY a.data_abertura DESC
            ");
            $stmt->execute([$cliente_id, $empresa_id]);
            $atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } else {
            // Todos os atendimentos da empresa com paginação
            $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
            $por_pagina = isset($_GET['por_pagina']) ? intval($_GET['por_pagina']) : 10;
            $offset = ($pagina - 1) * $por_pagina;
            
            // Contar total
            $stmtCount = $db->prepare("SELECT COUNT(*) FROM seguro_atendimentos WHERE seguro_empresa_id = ?");
            $stmtCount->execute([$empresa_id]);
            $total = $stmtCount->fetchColumn();
            
            $stmt = $db->prepare("
                SELECT 
                    a.*,
                    a.titulo as assunto,
                    a.solucao as resposta,
                    COALESCE(c.razao_social, c.nome_razao_social, 'Cliente não identificado') as cliente_nome,
                    u.nome as usuario_nome,
                    DATE_FORMAT(a.data_abertura, '%d/%m/%Y %H:%i') as data_abertura_fmt,
                    DATE_FORMAT(a.data_fechamento, '%d/%m/%Y %H:%i') as data_fechamento_fmt
                FROM seguro_atendimentos a
                LEFT JOIN seguro_clientes c ON a.seguro_cliente_id = c.id
                LEFT JOIN seguro_usuarios u ON a.usuario_id = u.id
                WHERE a.seguro_empresa_id = ?
                ORDER BY a.data_abertura DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$empresa_id, $por_pagina, $offset]);
            
            $atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_paginas = ceil($total / $por_pagina);
        }
        
        if (!isset($_GET['cliente_id'])) {
            echo json_encode([
                'success' => true,
                'atendimentos' => $atendimentos,
                'paginacao' => [
                    'pagina_atual' => $pagina,
                    'por_pagina' => $por_pagina,
                    'total' => $total,
                    'total_paginas' => $total_paginas,
                    'inicio' => $offset + 1,
                    'fim' => min($offset + $por_pagina, $total)
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => true,
                'atendimentos' => $atendimentos,
                'total' => count($atendimentos)
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } elseif ($method === 'POST') {
        // Criar novo atendimento
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // Gerar protocolo único
        $protocolo = 'AT-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $stmt = $db->prepare("
            INSERT INTO seguro_atendimentos (
                seguro_empresa_id,
                seguro_cliente_id,
                usuario_id,
                tipo,
                prioridade,
                titulo,
                descricao,
                status,
                protocolo,
                data_abertura
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'aberto', ?, NOW())
        ");
        
        $stmt->execute([
            $empresa_id,
            $data['cliente_id'] ?? null,
            $usuario['id'],
            $data['tipo'] ?? 'suporte',
            $data['prioridade'] ?? 'media',
            $data['assunto'] ?? '',
            $data['descricao'] ?? '',
            $protocolo
        ]);
        
        $atendimento_id = $db->lastInsertId();
        
        // Registrar log
        registrarLog(
            $empresa_id,
            $usuario['id'],
            'novo_atendimento',
            'atendimento',
            "Atendimento #{$atendimento_id} criado"
        );
        
        echo json_encode([
            'success' => true,
            'atendimento_id' => $atendimento_id,
            'mensagem' => 'Atendimento criado com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($method === 'PUT') {
        // Atualizar atendimento
        
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        $stmt = $db->prepare("
            UPDATE seguro_atendimentos 
            SET 
                tipo = ?,
                prioridade = ?,
                status = ?,
                solucao = ?,
                data_fechamento = IF(? IN ('fechado', 'resolvido'), NOW(), NULL)
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        
        $stmt->execute([
            $data['tipo'] ?? 'suporte',
            $data['prioridade'] ?? 'media',
            $data['status'],
            $data['resposta'] ?? '',
            $data['status'],
            $data['id'],
            $empresa_id
        ]);
        
        echo json_encode([
            'success' => true,
            'mensagem' => 'Atendimento atualizado!'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Erro em atendimentos API: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>


