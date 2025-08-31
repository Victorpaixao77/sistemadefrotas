<?php
/**
 * 📄 API Documentos Fiscais
 * 📋 Operações reais para NF-e, CT-e e MDF-e
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar autenticação
if (!isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

// Função para obter próximo número de documento
function getProximoNumero($tipo_documento, $serie = '1') {
    global $conn, $empresa_id;
    
    $ano_atual = date('Y');
    
    // Verificar se já existe sequência para este ano
    $stmt = $conn->prepare("
        SELECT proximo_numero FROM sequencias_documentos 
        WHERE empresa_id = ? AND tipo_documento = ? AND serie = ? AND ano_exercicio = ?
    ");
    $stmt->execute([$empresa_id, $tipo_documento, $serie, $ano_atual]);
    $sequencia = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($sequencia) {
        // Atualizar próximo número
        $proximo = $sequencia['proximo_numero'];
        $stmt = $conn->prepare("
            UPDATE sequencias_documentos 
            SET proximo_numero = proximo_numero + 1, ultimo_numero = ? 
            WHERE empresa_id = ? AND tipo_documento = ? AND serie = ? AND ano_exercicio = ?
        ");
        $stmt->execute([$proximo, $empresa_id, $tipo_documento, $serie, $ano_atual]);
        
        return $proximo;
    } else {
        // Criar nova sequência
        $stmt = $conn->prepare("
            INSERT INTO sequencias_documentos (empresa_id, tipo_documento, serie, ultimo_numero, proximo_numero, ano_exercicio)
            VALUES (?, ?, ?, 1, 2, ?)
        ");
        $stmt->execute([$empresa_id, $tipo_documento, $serie, $ano_atual]);
        
        return 1;
    }
}

// Função para gerar chave de acesso (simulada)
function gerarChaveAcesso($tipo_documento, $numero, $serie) {
    $uf = '43'; // RS
    $ano = date('y');
    $mes = date('m');
    $cnpj = '00000000000191'; // CNPJ padrão
    $modelo = $tipo_documento === 'NFE' ? '55' : ($tipo_documento === 'CTE' ? '57' : '58');
    $serie_padrao = str_pad($serie, 3, '0', STR_PAD_LEFT);
    $numero_padrao = str_pad($numero, 9, '0', STR_PAD_LEFT);
    $codigo_aleatorio = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    $chave = $uf . $ano . $mes . $cnpj . $modelo . $serie_padrao . $numero_padrao . $codigo_aleatorio;
    
    // Calcular dígito verificador (simplificado)
    $soma = 0;
    $pesos = [4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2,9,8,7,6,5,4,3,2];
    
    for ($i = 0; $i < 42; $i++) {
        $soma += intval($chave[$i]) * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv = $resto < 2 ? 0 : 11 - $resto;
    
    return $chave . $dv;
}

// Função para log de operações
function logOperacao($tipo_operacao, $descricao, $status = 'sucesso', $documento_id = null, $dados_entrada = null, $dados_saida = null) {
    global $conn, $empresa_id;
    
    $stmt = $conn->prepare("
        INSERT INTO logs_fiscais (empresa_id, documento_id, tipo_operacao, descricao, status, dados_entrada, dados_saida, tempo_execucao, ip_origem, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $tempo = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt->execute([
        $empresa_id, $documento_id, $tipo_operacao, $descricao, $status,
        $dados_entrada ? json_encode($dados_entrada) : null,
        $dados_saida ? json_encode($dados_saida) : null,
        round($tempo, 3), $ip, $user_agent
    ]);
}

// Processar requisição
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Listar documentos
            $tipo = $_GET['tipo'] ?? 'NFE';
            $status = $_GET['status'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            $sql = "SELECT * FROM documentos_fiscais WHERE empresa_id = ? AND tipo_documento = ?";
            $params = [$empresa_id, $tipo];
            
            if ($status) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY data_emissao DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Contar totais
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                FROM documentos_fiscais 
                WHERE empresa_id = ? AND tipo_documento = ?
            ");
            $stmt->execute([$empresa_id, $tipo]);
            $totais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'documentos' => $documentos,
                'totais' => $totais,
                'tipo' => $tipo
            ]);
            
            logOperacao('listar', "Listou documentos $tipo", 'sucesso');
            break;
            
        case 'create':
            // Criar novo documento
            $tipo = $_POST['tipo_documento'] ?? 'NFE';
            $serie = $_POST['serie'] ?? '1';
            
            // Obter próximo número
            $numero = getProximoNumero($tipo, $serie);
            
            // Gerar chave de acesso
            $chave_acesso = gerarChaveAcesso($tipo, $numero, $serie);
            
            // Inserir documento
            $stmt = $conn->prepare("
                INSERT INTO documentos_fiscais (
                    empresa_id, tipo_documento, numero_documento, serie, chave_acesso,
                    natureza_operacao, tipo_operacao, destinatario_nome, destinatario_cnpj,
                    valor_total, ambiente
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $tipo, $numero, $serie, $chave_acesso,
                $_POST['natureza_operacao'] ?? 'Venda de mercadoria',
                $_POST['tipo_operacao'] ?? 'saida',
                $_POST['destinatario_nome'] ?? 'Cliente',
                $_POST['destinatario_cnpj'] ?? '00.000.000/0001-00',
                $_POST['valor_total'] ?? 0.00,
                $_POST['ambiente'] ?? 'homologacao'
            ]);
            
            $documento_id = $conn->lastInsertId();
            
            // Inserir itens se fornecidos
            if (isset($_POST['itens']) && is_array($_POST['itens'])) {
                foreach ($_POST['itens'] as $item) {
                    $stmt = $conn->prepare("
                        INSERT INTO itens_documentos (
                            documento_id, codigo_produto, descricao_produto, ncm, cfop,
                            unidade_comercial, quantidade_comercial, valor_unitario, valor_total
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $documento_id,
                        $item['codigo'] ?? '',
                        $item['descricao'] ?? 'Produto',
                        $item['ncm'] ?? '',
                        $item['cfop'] ?? '5102',
                        $item['unidade'] ?? 'UN',
                        $item['quantidade'] ?? 1,
                        $item['valor_unitario'] ?? 0.00,
                        $item['valor_total'] ?? 0.00
                    ]);
                }
            }
            
            // Buscar documento criado
            $stmt = $conn->prepare("SELECT * FROM documentos_fiscais WHERE id = ?");
            $stmt->execute([$documento_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'message' => ucfirst($tipo) . ' criado com sucesso!',
                'documento' => $documento
            ]);
            
            logOperacao('criar', "Criou documento $tipo #$numero", 'sucesso', $documento_id, $_POST);
            break;
            
        case 'get':
            // Obter documento específico
            $id = $_GET['id'] ?? null;
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            $stmt = $conn->prepare("
                SELECT d.*, GROUP_CONCAT(i.id) as itens_ids
                FROM documentos_fiscais d
                LEFT JOIN itens_documentos i ON d.id = i.documento_id
                WHERE d.id = ? AND d.empresa_id = ?
                GROUP BY d.id
            ");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            // Buscar itens
            $stmt = $conn->prepare("SELECT * FROM itens_documentos WHERE documento_id = ?");
            $stmt->execute([$id]);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $documento['itens'] = $itens;
            
            echo json_encode([
                'success' => true,
                'documento' => $documento
            ]);
            
            logOperacao('consultar', "Consultou documento #$id", 'sucesso', $id);
            break;
            
        case 'update':
            // Atualizar documento
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Verificar se documento existe e pertence à empresa
            $stmt = $conn->prepare("SELECT id FROM documentos_fiscais WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            
            if (!$stmt->fetch()) {
                throw new Exception('Documento não encontrado');
            }
            
            // Atualizar documento
            $stmt = $conn->prepare("
                UPDATE documentos_fiscais SET
                    natureza_operacao = ?,
                    tipo_operacao = ?,
                    destinatario_nome = ?,
                    destinatario_cnpj = ?,
                    destinatario_ie = ?,
                    destinatario_endereco = ?,
                    destinatario_cidade = ?,
                    destinatario_uf = ?,
                    destinatario_cep = ?,
                    valor_total = ?,
                    observacoes = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $stmt->execute([
                $_POST['natureza_operacao'] ?? 'Venda de mercadoria',
                $_POST['tipo_operacao'] ?? 'saida',
                $_POST['destinatario_nome'] ?? 'Cliente',
                $_POST['destinatario_cnpj'] ?? '00.000.000/0001-00',
                $_POST['destinatario_ie'] ?? '',
                $_POST['destinatario_endereco'] ?? '',
                $_POST['destinatario_cidade'] ?? '',
                $_POST['destinatario_uf'] ?? '',
                $_POST['destinatario_cep'] ?? '',
                $_POST['valor_total'] ?? 0.00,
                $_POST['observacoes'] ?? '',
                $id
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Documento atualizado com sucesso!'
            ]);
            
            logOperacao('atualizar', "Atualizou documento #$id", 'sucesso', $id, $_POST);
            break;
            
        case 'delete':
            // Excluir documento
            $id = $_POST['id'] ?? null;
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Verificar se documento existe e pertence à empresa
            $stmt = $conn->prepare("SELECT id, status FROM documentos_fiscais WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch();
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            if ($documento['status'] !== 'rascunho') {
                throw new Exception('Só é possível excluir documentos em rascunho');
            }
            
            // Excluir documento (itens serão excluídos automaticamente por CASCADE)
            $stmt = $conn->prepare("DELETE FROM documentos_fiscais WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Documento excluído com sucesso!'
            ]);
            
            logOperacao('excluir', "Excluiu documento #$id", 'sucesso', $id);
            break;
            
        case 'totals':
            // Obter totais gerais
            $stmt = $conn->prepare("
                SELECT 
                    tipo_documento,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos,
                    SUM(CASE WHEN status = 'cancelado' THEN 1 ELSE 0 END) as cancelados
                FROM documentos_fiscais 
                WHERE empresa_id = ?
                GROUP BY tipo_documento
            ");
            $stmt->execute([$empresa_id]);
            $totais = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'totais' => $totais
            ]);
            
            logOperacao('consultar', "Consultou totais gerais", 'sucesso');
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
} catch (Exception $e) {
    logOperacao('erro', $e->getMessage(), 'erro');
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
