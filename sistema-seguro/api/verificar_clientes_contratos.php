<?php
/**
 * API - Verificar Clientes e Contratos
 * Valida se cliente/contrato existe usando MATRÍCULA e CONJUNTO
 * Verifica também se documento já existe (duplicata)
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();
$empresa_id = obterEmpresaId();

// Receber dados
$dados = json_decode(file_get_contents('php://input'), true);

if (!isset($dados['documentos']) || !is_array($dados['documentos'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Dados inválidos'
    ]);
    exit;
}

try {
    $pdo = getDB();
    $resultado = [];
    
    foreach ($dados['documentos'] as $doc) {
        $matricula = $doc['matricula'] ?? '';
        $conjunto = $doc['conjunto'] ?? '';
        $numero_documento = $doc['numero_documento'] ?? '';
        $data_emissao = $doc['data_emissao'] ?? null;
        $data_vencimento = $doc['data_vencimento'] ?? null;
        
        $chave = "{$matricula}_{$conjunto}";
        
        $clienteExiste = false;
        $contratoExiste = false;
        $documentoDuplicado = false;
        $dadosCliente = null;
        
        // 1. Verificar se existe CONTRATO com esse CONJUNTO (matricula do contrato)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    cc.id as contrato_id,
                    cc.cliente_id,
                    c.codigo,
                    c.nome_razao_social,
                    c.matricula as matricula_cliente
                FROM seguro_contratos_clientes cc
                INNER JOIN seguro_clientes c ON cc.cliente_id = c.id
                WHERE cc.matricula = ?
                AND cc.empresa_id = ?
                AND cc.ativo = 'sim'
                LIMIT 1
            ");
            $stmt->execute([$conjunto, $empresa_id]);
            $contrato = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contrato) {
                $contratoExiste = true;
                $clienteExiste = true; // Se encontrou contrato, cliente existe
                $dadosCliente = [
                    'id' => $contrato['cliente_id'],
                    'codigo' => $contrato['codigo'],
                    'nome' => $contrato['nome_razao_social'],
                    'matricula' => $contrato['matricula_cliente'],
                    'encontrado_via' => 'contrato'
                ];
            }
        } catch (Exception $e) {
            // Tabela de contratos pode não existir ainda
            error_log("Erro ao verificar contrato: " . $e->getMessage());
        }
        
        // 2. Se não encontrou via contrato, buscar diretamente pelo cliente usando MATRÍCULA
        if (!$clienteExiste && !empty($matricula)) {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    codigo,
                    nome_razao_social,
                    matricula
                FROM seguro_clientes
                WHERE matricula = ?
                AND seguro_empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute([$matricula, $empresa_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($cliente) {
                $clienteExiste = true;
                $dadosCliente = [
                    'id' => $cliente['id'],
                    'codigo' => $cliente['codigo'],
                    'nome' => $cliente['nome_razao_social'],
                    'matricula' => $cliente['matricula'],
                    'encontrado_via' => 'matricula_cliente'
                ];
            }
        }
        
        // 3. Verificar se documento já existe (duplicata)
        if (!empty($numero_documento) && !empty($data_emissao) && !empty($data_vencimento)) {
            $stmt = $pdo->prepare("
                SELECT id 
                FROM seguro_financeiro
                WHERE numero_documento = ?
                AND data_emissao = ?
                AND data_vencimento = ?
                AND seguro_empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute([
                $numero_documento,
                $data_emissao,
                $data_vencimento,
                $empresa_id
            ]);
            
            if ($stmt->fetch()) {
                $documentoDuplicado = true;
            }
        }
        
        $resultado[$chave] = [
            'existe' => $clienteExiste,
            'contrato_existe' => $contratoExiste,
            'documento_duplicado' => $documentoDuplicado,
            'dados' => $dadosCliente
        ];
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $resultado
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao verificar clientes: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

