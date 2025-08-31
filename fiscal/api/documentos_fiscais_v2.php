<?php
/**
 * 📄 API Documentos Fiscais V2 - SISTEMA DE FROTA
 * 📋 Fluxo correto: Recebe NF-e do cliente, emite CT-e e MDF-e
 * 🏷️  Compatível com tabelas existentes (fiscal_*)
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
    $modelo = $tipo_documento === 'CTE' ? '57' : '58'; // CT-e ou MDF-e
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

// Função para validar CNPJ
function validarCNPJ($cnpj) {
    // Remove caracteres não numéricos
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    // Verifica se tem 14 dígitos
    if (strlen($cnpj) != 14) {
        return false;
    }
    
    // Verifica se todos os dígitos são iguais
    if (preg_match('/(\d)\1{13}/', $cnpj)) {
        return false;
    }
    
    // Validação do primeiro dígito verificador
    $soma = 0;
    $pesos = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 12; $i++) {
        $soma += $cnpj[$i] * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv1 = $resto < 2 ? 0 : 11 - $resto;
    
    if ($cnpj[12] != $dv1) {
        return false;
    }
    
    // Validação do segundo dígito verificador
    $soma = 0;
    $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    
    for ($i = 0; $i < 13; $i++) {
        $soma += $cnpj[$i] * $pesos[$i];
    }
    
    $resto = $soma % 11;
    $dv2 = $resto < 2 ? 0 : 11 - $resto;
    
    return $cnpj[13] == $dv2;
}

// Função para verificar duplicação de CT-e
function verificarDuplicacaoCTE($numero_cte, $serie_cte, $id_atual, $empresa_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total FROM fiscal_cte 
        WHERE numero_cte = ? AND serie_cte = ? AND empresa_id = ? AND id != ?
    ");
    $stmt->execute([$numero_cte, $serie_cte, $empresa_id, $id_atual]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado['total'] == 0;
}

// Função para simular envio para SEFAZ
function enviarParaSefaz($documento, $tipo) {
    // Simulação de envio para SEFAZ
    // Em produção, aqui seria feita a integração real
    
    // Simular tempo de processamento
    usleep(500000); // 0.5 segundos
    
    // Simular diferentes cenários
    $cenarios = [
        ['sucesso' => true, 'status' => 'autorizado', 'protocolo' => 'SEFAZ-' . date('Ymd') . '-' . rand(1000, 9999)],
        ['sucesso' => true, 'status' => 'autorizado', 'protocolo' => 'SEFAZ-' . date('Ymd') . '-' . rand(1000, 9999)],
        ['sucesso' => false, 'erro' => 'CNPJ do destinatário inválido'],
        ['sucesso' => false, 'erro' => 'Valor total não confere com itens'],
        ['sucesso' => true, 'status' => 'autorizado', 'protocolo' => 'SEFAZ-' . date('Ymd') . '-' . rand(1000, 9999)]
    ];
    
    $cenario = $cenarios[array_rand($cenarios)];
    
    if ($cenario['sucesso']) {
        return [
            'sucesso' => true,
            'status' => $cenario['status'],
            'protocolo' => $cenario['protocolo']
        ];
    } else {
        return [
            'sucesso' => false,
            'erro' => $cenario['erro']
        ];
    }
}

// Função para log de operações
function logOperacao($tipo_operacao, $descricao, $status = 'sucesso', $documento_id = null, $dados_entrada = null, $dados_saida = null) {
    global $conn, $empresa_id;
    
    try {
        // Se documento_id for null, usar 0 para operações sem documento específico
        $doc_id = $documento_id ?? 0;
        
        $stmt = $conn->prepare("
            INSERT INTO fiscal_logs (empresa_id, documento_tipo, documento_id, acao, status, mensagem, detalhes, usuario_id, ip_usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $documento_tipo = 'cte'; // Padrão para sistema de frota
        $usuario_id = $_SESSION['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        $stmt->execute([
            $empresa_id, $documento_tipo, $doc_id, $tipo_operacao, $status, $descricao,
            json_encode(['entrada' => $dados_entrada, 'saida' => $dados_saida]),
            $usuario_id, $ip
        ]);
    } catch (Exception $e) {
        // Se não conseguir logar, não falhar a operação principal
        error_log("Erro ao logar operação fiscal: " . $e->getMessage());
    }
}

// Processar requisição
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            // Listar documentos
            $tipo = $_GET['tipo'] ?? 'cte';
            $status = $_GET['status'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            // Mapear tipo para tabela
            $tabela = '';
            switch ($tipo) {
                case 'nfe':
                    $tabela = 'fiscal_nfe_clientes';
                    break;
                case 'cte':
                    $tabela = 'fiscal_cte';
                    break;
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    break;
                default:
                    throw new Exception('Tipo de documento inválido');
            }
            
            $sql = "SELECT * FROM $tabela WHERE empresa_id = ?";
            $params = [$empresa_id];
            
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
                FROM $tabela 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'documentos' => $documentos,
                'totais' => $totais,
                'tipo' => $tipo
            ]);
            
            logOperacao('listar', "Listou documentos $tipo", 'sucesso');
            break;
            
        case 'receber_nfe':
            // RECEBER NF-e do cliente (não emitir!)
            $numero_nfe = $_POST['numero_nfe'] ?? '';
            $serie_nfe = $_POST['serie_nfe'] ?? '';
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $cliente_remetente = $_POST['cliente_remetente'] ?? '';
            $cliente_destinatario = $_POST['cliente_destinatario'] ?? '';
            $valor_carga = $_POST['valor_carga'] ?? 0.00;
            $peso_carga = $_POST['peso_carga'] ?? 0.00;
            $volumes = $_POST['volumes'] ?? 0;
            
            if (!$numero_nfe || !$chave_acesso) {
                throw new Exception('Número da NF-e e chave de acesso são obrigatórios');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Inserir NF-e recebida do cliente
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_cnpj, valor_total, peso_carga, volumes,
                    status, tipo_operacao, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, date('Y-m-d'),
                $cliente_remetente, $_POST['cnpj_remetente'] ?? '', $valor_carga, $peso_carga, $volumes,
                'recebida', 'recebida', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida com sucesso!',
                'nfe_id' => $nfe_id,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe', "Recebeu NF-e #$numero_nfe do cliente", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_xml':
            // RECEBER NF-e via upload de XML (método recomendado)
            $xml_file = $_FILES['xml_file'] ?? null;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$xml_file || $xml_file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erro no upload do arquivo XML');
            }
            
            if (!in_array($xml_file['type'], ['text/xml', 'application/xml']) && 
                !str_ends_with(strtolower($xml_file['name']), '.xml')) {
                throw new Exception('Arquivo deve ser um XML válido');
            }
            
            // Validar tamanho (máximo 5MB)
            if ($xml_file['size'] > 5 * 1024 * 1024) {
                throw new Exception('Arquivo XML muito grande. Máximo: 5MB');
            }
            
            // Ler e validar XML
            $xml_content = file_get_contents($xml_file['tmp_name']);
            if (!$xml_content) {
                throw new Exception('Não foi possível ler o arquivo XML');
            }
            
            // Parsear XML (simplificado - em produção usar DOMDocument)
            $xml_data = simplexml_load_string($xml_content);
            if (!$xml_data) {
                throw new Exception('XML inválido ou malformado');
            }
            
            // Extrair dados da NF-e (namespace padrão NFe)
            $nfe_data = $xml_data->NFe ?? $xml_data->nfe ?? null;
            if (!$nfe_data) {
                throw new Exception('Estrutura XML não reconhecida como NF-e válida');
            }
            
            $inf_nfe = $nfe_data->infNFe ?? null;
            if (!$inf_nfe) {
                throw new Exception('Dados da NF-e não encontrados no XML');
            }
            
            // Extrair informações básicas
            $chave_acesso = (string)($inf_nfe->Id ?? '');
            $chave_acesso = str_replace('NFe', '', $chave_acesso); // Remove prefixo NFe
            
            if (strlen($chave_acesso) !== 44) {
                throw new Exception('Chave de acesso inválida no XML');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Extrair dados do XML
            $ide = $inf_nfe->ide ?? null;
            $emit = $inf_nfe->emit ?? null;
            $dest = $inf_nfe->dest ?? null;
            $total = $inf_nfe->total ?? null;
            $transp = $inf_nfe->transp ?? null;
            
            $numero_nfe = (string)($ide->nNF ?? '');
            $serie_nfe = (string)($ide->serie ?? '');
            $data_emissao = (string)($ide->dhEmi ?? '');
            $data_emissao = date('Y-m-d', strtotime($data_emissao));
            
            $emitente = (string)($emit->xNome ?? '');
            $cnpj_emitente = (string)($emit->CNPJ ?? '');
            $destinatario = (string)($dest->xNome ?? '');
            $cnpj_destinatario = (string)($dest->CNPJ ?? '');
            
            $valor_total = (float)($total->ICMSTot->vNF ?? 0);
            $peso_total = (float)($transp->vol->pesoB ?? 0);
            $volumes = (int)($transp->vol->qVol ?? 1);
            
            // Salvar arquivo XML
            $upload_dir = '../../uploads/nfe_xml/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $xml_filename = 'nfe_' . $chave_acesso . '_' . date('YmdHis') . '.xml';
            $xml_path = $upload_dir . $xml_filename;
            
            if (!move_uploaded_file($xml_file['tmp_name'], $xml_path)) {
                throw new Exception('Erro ao salvar arquivo XML');
            }
            
            // Inserir NF-e recebida
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_cnpj, cliente_destinatario, cnpj_destinatario,
                    valor_total, peso_carga, volumes, status, tipo_operacao, 
                    xml_path, observacoes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, $data_emissao,
                $emitente, $cnpj_emitente, $destinatario, $cnpj_destinatario,
                $valor_total, $peso_total, $volumes, 'recebida', 'recebida_xml',
                $xml_filename, $observacoes, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida via XML com sucesso!',
                'nfe_id' => $nfe_id,
                'chave_acesso' => $chave_acesso,
                'numero_nfe' => $numero_nfe,
                'emitente' => $emitente,
                'valor_total' => $valor_total,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe_xml', "Recebeu NF-e #$numero_nfe via XML", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_manual':
            // RECEBER NF-e via digitação manual (plano B)
            $numero_nfe = $_POST['numero_nfe'] ?? '';
            $serie_nfe = $_POST['serie_nfe'] ?? '';
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $cliente_remetente = $_POST['cliente_remetente'] ?? '';
            $cliente_destinatario = $_POST['cliente_destinatario'] ?? '';
            $valor_carga = $_POST['valor_carga'] ?? 0.00;
            $peso_carga = $_POST['peso_carga'] ?? 0.00;
            $volumes = $_POST['volumes'] ?? 0;
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$numero_nfe || !$chave_acesso || !$cliente_remetente || !$cliente_destinatario) {
                throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
            }
            
            // Validar formato da chave de acesso
            if (!preg_match('/^\d{44}$/', $chave_acesso)) {
                throw new Exception('Chave de acesso deve ter exatamente 44 dígitos numéricos');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Inserir NF-e recebida manualmente
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_destinatario, valor_total, peso_carga, volumes,
                    status, tipo_operacao, observacoes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_nfe, $serie_nfe, $chave_acesso, date('Y-m-d'),
                $cliente_remetente, $cliente_destinatario, $valor_carga, $peso_carga, $volumes,
                'recebida', 'recebida_manual', $observacoes, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e recebida manualmente com sucesso!',
                'nfe_id' => $nfe_id,
                'numero_nfe' => $numero_nfe,
                'chave_acesso' => $chave_acesso,
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe_manual', "Recebeu NF-e #$numero_nfe manualmente", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'receber_nfe_sefaz':
            // RECEBER NF-e via consulta automática na SEFAZ
            $chave_acesso = $_POST['chave_acesso'] ?? '';
            $validar_certificado = $_POST['validar_certificado'] ?? '1';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$chave_acesso) {
                throw new Exception('Chave de acesso é obrigatória');
            }
            
            // Validar formato da chave de acesso
            if (!preg_match('/^\d{44}$/', $chave_acesso)) {
                throw new Exception('Chave de acesso deve ter exatamente 44 dígitos numéricos');
            }
            
            // Verificar se NF-e já foi recebida
            $stmt = $conn->prepare("
                SELECT id FROM fiscal_nfe_clientes 
                WHERE chave_acesso = ? AND empresa_id = ?
            ");
            $stmt->execute([$chave_acesso, $empresa_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('NF-e já foi recebida anteriormente');
            }
            
            // Simular consulta na SEFAZ (em produção, integrar com webservice real)
            if ($validar_certificado === '1') {
                // Simular validação de certificado
                if (rand(1, 100) <= 5) { // 5% de chance de falha
                    throw new Exception('Certificado digital inválido ou expirado');
                }
            }
            
            // Consultar NF-e na SEFAZ
            $sefaz_data = consultarNFeSefaz($chave_acesso);
            
            if (!$sefaz_data) {
                throw new Exception('NF-e não encontrada na SEFAZ ou erro na consulta');
            }
            
            // Validar integridade dos dados SEFAZ
            if (!validarIntegridadeNFe($sefaz_data)) {
                throw new Exception('Dados da NF-e inconsistentes ou inválidos na SEFAZ');
            }
            
            // Inserir NF-e recebida via SEFAZ
            $stmt = $conn->prepare("
                INSERT INTO fiscal_nfe_clientes (
                    empresa_id, numero_nfe, serie_nfe, chave_acesso, data_emissao,
                    cliente_razao_social, cliente_destinatario, valor_total, peso_carga, volumes,
                    status, tipo_operacao, observacoes, protocolo_sefaz, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $sefaz_data['numero_nfe'], $sefaz_data['serie_nfe'], $chave_acesso, $sefaz_data['data_emissao'],
                $sefaz_data['emitente'], $sefaz_data['destinatario'], $sefaz_data['valor_total'], $sefaz_data['peso_total'], $sefaz_data['volumes'],
                'recebida', 'recebida_sefaz', $observacoes, $sefaz_data['protocolo'], date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $nfe_id = $conn->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => 'NF-e consultada na SEFAZ e recebida com sucesso!',
                'nfe_id' => $nfe_id,
                'chave_acesso' => $chave_acesso,
                'numero_nfe' => $sefaz_data['numero_nfe'],
                'emitente' => $sefaz_data['emitente'],
                'valor_total' => $sefaz_data['valor_total'],
                'protocolo' => $sefaz_data['protocolo'],
                'status' => 'recebida'
            ]);
            
            logOperacao('recebimento_nfe_sefaz', "Recebeu NF-e via SEFAZ: $chave_acesso", 'sucesso', $nfe_id, $_POST);
            break;
            
        case 'criar_cte':
            // CRIAR CT-e (Conhecimento de Transporte Eletrônico)
            $nfe_ids = $_POST['nfe_ids'] ?? [];
            $veiculo_id = $_POST['veiculo_id'] ?? null;
            $motorista_id = $_POST['motorista_id'] ?? null;
            $origem = $_POST['origem'] ?? '';
            $destino = $_POST['destino'] ?? '';
            $valor_frete = $_POST['valor_frete'] ?? 0.00;
            $peso_total = $_POST['peso_total'] ?? 0.00;
            $volumes_total = $_POST['volumes_total'] ?? 0;
            
            if (empty($nfe_ids)) {
                throw new Exception('É necessário selecionar pelo menos uma NF-e para transportar');
            }
            
            if (!$veiculo_id || !$motorista_id) {
                throw new Exception('Veículo e motorista são obrigatórios');
            }
            
            // Verificar se todas as NF-e estão recebidas
            $placeholders = str_repeat('?,', count($nfe_ids) - 1) . '?';
            $stmt = $conn->prepare("
                SELECT id, status FROM fiscal_nfe_clientes 
                WHERE id IN ($placeholders) AND empresa_id = ?
            ");
            $params = array_merge($nfe_ids, [$empresa_id]);
            $stmt->execute($params);
            $nfes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($nfes) != count($nfe_ids)) {
                throw new Exception('Algumas NF-e não foram encontradas');
            }
            
            foreach ($nfes as $nfe) {
                if ($nfe['status'] !== 'recebida') {
                    throw new Exception('Todas as NF-e devem estar com status "recebida"');
                }
            }
            
            // Criar CT-e
            $numero_cte = getProximoNumero('CTE', '1');
            $chave_acesso = gerarChaveAcesso('CTE', $numero_cte, '1');
            
            $stmt = $conn->prepare("
                INSERT INTO fiscal_cte (
                    empresa_id, numero_cte, serie_cte, chave_acesso, data_emissao,
                    natureza_operacao, valor_total, peso_carga, volumes_carga,
                    origem, destino, veiculo_id, motorista_id, status,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_cte, '1', $chave_acesso, date('Y-m-d'),
                'Transporte de mercadorias', $valor_frete, $peso_total, $volumes_total,
                $origem, $destino, $veiculo_id, $motorista_id, 'rascunho',
                date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $cte_id = $conn->lastInsertId();
            
            // Vincular NF-e ao CT-e
            foreach ($nfe_ids as $nfe_id) {
                $stmt = $conn->prepare("
                    UPDATE fiscal_nfe_clientes 
                    SET cte_id = ?, status = 'em_transporte', updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([$cte_id, date('Y-m-d H:i:s'), $nfe_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'CT-e criado com sucesso!',
                'cte_id' => $cte_id,
                'numero_cte' => $numero_cte,
                'status' => 'rascunho'
            ]);
            
            logOperacao('criacao_cte', "Criou CT-e #$numero_cte", 'sucesso', $cte_id, $_POST);
            break;
            
        case 'criar_mdfe':
            // CRIAR MDF-e (Manifesto Eletrônico de Documentos Fiscais)
            $cte_ids = $_POST['cte_ids'] ?? [];
            $veiculo_id = $_POST['veiculo_id'] ?? null;
            $motorista_id = $_POST['motorista_id'] ?? null;
            $rota_id = $_POST['rota_id'] ?? null;
            $data_viagem = $_POST['data_viagem'] ?? date('Y-m-d');
            
            if (empty($cte_ids)) {
                throw new Exception('É necessário selecionar pelo menos um CT-e para o manifesto');
            }
            
            if (!$veiculo_id || !$motorista_id) {
                throw new Exception('Veículo e motorista são obrigatórios');
            }
            
            // Capturar campos adicionais
            $uf_inicio = $_POST['uf_inicio'] ?? null;
            $uf_fim = $_POST['uf_fim'] ?? null;
            $municipio_carregamento = $_POST['municipio_carregamento'] ?? null;
            $municipio_descarregamento = $_POST['municipio_descarregamento'] ?? null;
            $tipo_viagem = $_POST['tipo_viagem'] ?? '1';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$uf_inicio || !$uf_fim) {
                throw new Exception('UF de início e fim são obrigatórias');
            }
            
            if (!$municipio_carregamento || !$municipio_descarregamento) {
                throw new Exception('Municípios de carregamento e descarregamento são obrigatórios');
            }
            
            // Verificar se todos os CT-e estão autorizados e calcular totais
            $placeholders = str_repeat('?,', count($cte_ids) - 1) . '?';
            $stmt = $conn->prepare("
                SELECT id, status, valor_total, peso_carga, volumes_carga 
                FROM fiscal_cte 
                WHERE id IN ($placeholders) AND empresa_id = ?
            ");
            $params = array_merge($cte_ids, [$empresa_id]);
            $stmt->execute($params);
            $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($ctes) != count($cte_ids)) {
                throw new Exception('Alguns CT-e não foram encontrados');
            }
            
            $peso_total = 0;
            $volumes_total = 0;
            $valor_total = 0;
            
            foreach ($ctes as $cte) {
                if ($cte['status'] !== 'autorizado') {
                    throw new Exception('Todos os CT-e devem estar autorizados para criar MDF-e');
                }
                $peso_total += floatval($cte['peso_carga']);
                $volumes_total += intval($cte['volumes_carga']);
                $valor_total += floatval($cte['valor_total']);
            }
            
            // Criar MDF-e
            $numero_mdfe = getProximoNumero('MDFE', '1');
            $chave_acesso = gerarChaveAcesso('MDFE', $numero_mdfe, '1');
            
            $stmt = $conn->prepare("
                INSERT INTO fiscal_mdfe (
                    empresa_id, numero_mdfe, serie_mdfe, chave_acesso, data_emissao,
                    tipo_transporte, veiculo_id, motorista_id, uf_inicio, uf_fim,
                    municipio_carregamento, municipio_descarregamento, tipo_viagem,
                    peso_total, volumes_total, valor_total, total_cte, observacoes,
                    status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $empresa_id, $numero_mdfe, '1', $chave_acesso, date('Y-m-d'),
                'rodoviario', $veiculo_id, $motorista_id, $uf_inicio, $uf_fim,
                $municipio_carregamento, $municipio_descarregamento, $tipo_viagem,
                $peso_total, $volumes_total, $valor_total, count($cte_ids), $observacoes,
                'rascunho', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')
            ]);
            
            $mdfe_id = $conn->lastInsertId();
            
            // Vincular CT-e ao MDF-e
            foreach ($cte_ids as $cte_id) {
                $stmt = $conn->prepare("
                    UPDATE fiscal_cte 
                    SET mdfe_id = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([$mdfe_id, date('Y-m-d H:i:s'), $cte_id]);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'MDF-e criado com sucesso!',
                'mdfe_id' => $mdfe_id,
                'numero_mdfe' => $numero_mdfe,
                'status' => 'rascunho',
                'peso_total' => $peso_total,
                'volumes_total' => $volumes_total,
                'valor_total' => $valor_total,
                'total_cte' => count($cte_ids)
            ]);
            
            logOperacao('criacao_mdfe', "Criou MDF-e #$numero_mdfe com " . count($cte_ids) . " CT-e", 'sucesso', $mdfe_id, $_POST);
            break;
            
        case 'get':
            // Obter documento específico
            $id = $_GET['id'] ?? null;
            $tipo = $_GET['tipo'] ?? 'cte';
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Mapear tipo para tabela
            $tabela = '';
            switch ($tipo) {
                case 'nfe':
                    $tabela = 'fiscal_nfe_clientes';
                    break;
                case 'cte':
                    $tabela = 'fiscal_cte';
                    break;
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    break;
                default:
                    throw new Exception('Tipo de documento inválido');
            }
            
            $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            echo json_encode([
                'success' => true,
                'documento' => $documento
            ]);
            
            logOperacao('consultar', "Consultou documento #$id", 'sucesso', $id);
            break;
            
        case 'update':
            // Atualizar documento existente
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo_documento'] ?? 'cte';
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            // Mapear tipo para tabela
            $tabela = '';
            $campos_update = [];
            $valores_update = [];
            
            switch ($tipo) {
                case 'cte':
                    $tabela = 'fiscal_cte';
                    $campos_update = [
                        'natureza_operacao' => $_POST['natureza_operacao'] ?? '',
                        'valor_total' => $_POST['valor_total'] ?? 0.00,
                        'peso_carga' => $_POST['peso_carga'] ?? 0.00,
                        'volumes_carga' => $_POST['volumes_carga'] ?? 0,
                        'origem' => $_POST['origem'] ?? '',
                        'destino' => $_POST['destino'] ?? '',
                        'status' => $_POST['status'] ?? 'rascunho',
                        'observacoes' => $_POST['observacoes'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    break;
                    
                case 'mdfe':
                    $tabela = 'fiscal_mdfe';
                    $campos_update = [
                        'tipo_transporte' => $_POST['tipo_transporte'] ?? 'rodoviario',
                        'data_viagem' => $_POST['data_viagem'] ?? date('Y-m-d'),
                        'status' => $_POST['status'] ?? 'rascunho',
                        'observacoes' => $_POST['observacoes'] ?? '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    break;
                    
                default:
                    throw new Exception('Tipo de documento inválido');
            }
            
            // Construir query de atualização
            $set_clause = [];
            $valores_update = [];
            
            foreach ($campos_update as $campo => $valor) {
                $set_clause[] = "$campo = ?";
                $valores_update[] = $valor;
            }
            
            // Adicionar valores para WHERE
            $valores_update[] = $empresa_id;
            $valores_update[] = $id;
            
            $sql = "UPDATE $tabela SET " . implode(', ', $set_clause) . " WHERE empresa_id = ? AND id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute($valores_update);
            
            if ($stmt->rowCount() > 0) {
                // Buscar documento atualizado
                $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ?");
                $stmt->execute([$id]);
                $documento = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst($tipo) . ' atualizado com sucesso!',
                    'documento' => $documento
                ]);
                
                logOperacao('atualizacao', "Atualizou documento $tipo #$id", 'sucesso', $id, $_POST);
            } else {
                throw new Exception('Nenhuma alteração foi feita ou documento não encontrado');
            }
            break;
            
        case 'totals':
            // Obter totais gerais
            $totais = [];
            
            // NF-e recebidas
            $stmt = $conn->prepare("
                SELECT 
                    'nfe' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'recebida' THEN 1 ELSE 0 END) as recebidas,
                    SUM(CASE WHEN status = 'em_transporte' THEN 1 ELSE 0 END) as em_transporte,
                    SUM(CASE WHEN status = 'entregue' THEN 1 ELSE 0 END) as entregues
                FROM fiscal_nfe_clientes 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['nfe'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // CT-e
            $stmt = $conn->prepare("
                SELECT 
                    'cte' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                FROM fiscal_cte 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['cte'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // MDF-e
            $stmt = $conn->prepare("
                SELECT 
                    'mdfe' as tipo,
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                    SUM(CASE WHEN status = 'autorizado' THEN 1 ELSE 0 END) as autorizados,
                    SUM(CASE WHEN status = 'rascunho' THEN 1 ELSE 0 END) as rascunhos
                FROM fiscal_mdfe 
                WHERE empresa_id = ?
            ");
            $stmt->execute([$empresa_id]);
            $totais['mdfe'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'totais' => $totais
            ]);
            
            logOperacao('consultar', "Consultou totais gerais", 'sucesso');
            break;
            
        case 'enviar_sefaz':
            // Enviar CT-e ou MDF-e para SEFAZ
            $id = $_POST['id'] ?? null;
            $tipo = $_POST['tipo_documento'] ?? 'cte';
            
            if (!$id) {
                throw new Exception('ID do documento não fornecido');
            }
            
            if ($tipo !== 'cte' && $tipo !== 'mdfe') {
                throw new Exception('Apenas CT-e e MDF-e podem ser enviados para SEFAZ');
            }
            
            // Buscar documento
            $tabela = $tipo === 'cte' ? 'fiscal_cte' : 'fiscal_mdfe';
            $stmt = $conn->prepare("SELECT * FROM $tabela WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            if ($documento['status'] !== 'rascunho' && $documento['status'] !== 'pendente') {
                throw new Exception('Apenas documentos com status rascunho ou pendente podem ser enviados para SEFAZ');
            }
            
            // Simular envio para SEFAZ
            $resultado_sefaz = enviarParaSefaz($documento, $tipo);
            
            if ($resultado_sefaz['sucesso']) {
                // Atualizar status do documento
                $stmt = $conn->prepare("
                    UPDATE $tabela 
                    SET status = ?, protocolo_autorizacao = ?, data_autorizacao = ?, updated_at = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $resultado_sefaz['status'],
                    $resultado_sefaz['protocolo'],
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                    $id
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => ucfirst(strtoupper($tipo)) . ' enviado para SEFAZ com sucesso!',
                    'status' => $resultado_sefaz['status'],
                    'protocolo' => $resultado_sefaz['protocolo']
                ]);
                
                $numero_doc = $tipo === 'cte' ? $documento['numero_cte'] : $documento['numero_mdfe'];
                logOperacao('envio_sefaz', "Enviou $tipo #$numero_doc para SEFAZ", 'sucesso', $id);
            } else {
                throw new Exception('Erro ao enviar para SEFAZ: ' . $resultado_sefaz['erro']);
            }
            break;
            
        case 'processar_evento':
            // PROCESSAR EVENTOS FISCAIS (CC-e, Cancelamento, Inutilização)
            $documento_id = $_POST['documento_id'] ?? null;
            $tipo_evento = $_POST['tipo_evento'] ?? null;
            $justificativa = $_POST['justificativa'] ?? '';
            $xml_evento = $_POST['xml_evento'] ?? null;
            
            if (!$documento_id || !$tipo_evento) {
                throw new Exception('ID do documento e tipo de evento são obrigatórios');
            }
            
            // Validar tipo de evento (conforme ENUM da tabela)
            $tipos_validos = ['cancelamento', 'encerramento', 'cce', 'inutilizacao', 'manifestacao'];
            if (!in_array($tipo_evento, $tipos_validos)) {
                throw new Exception('Tipo de evento inválido');
            }
            
            // Buscar documento
            $stmt = $conn->prepare("
                SELECT tipo_operacao, status, chave_acesso 
                FROM fiscal_nfe_clientes 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$documento_id, $empresa_id]);
            $documento = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$documento) {
                throw new Exception('Documento não encontrado');
            }
            
            // Validar se evento pode ser processado
            if (!validarEventoPermitido($documento, $tipo_evento)) {
                throw new Exception('Evento não permitido para o status atual do documento');
            }
            
            // Inserir evento na tabela correta
            $stmt = $conn->prepare("
                INSERT INTO fiscal_eventos_fiscais (
                    empresa_id, documento_tipo, documento_id, tipo_evento, 
                    justificativa, xml_evento, status, data_evento, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $documento_tipo = 'nfe'; // Por enquanto só NF-e, pode ser expandido
            $usuario_id = $_SESSION['user_id'] ?? null;
            
            $stmt->execute([
                $empresa_id, $documento_tipo, $documento_id, $tipo_evento,
                $justificativa, $xml_evento, 'pendente', date('Y-m-d H:i:s'), $usuario_id
            ]);
            
            $evento_id = $conn->lastInsertId();
            
            // Processar evento específico
            $resultado_evento = processarEventoEspecifico($evento_id, $tipo_evento, $documento_id, $justificativa);
            
            echo json_encode([
                'success' => true,
                'message' => 'Evento processado com sucesso!',
                'evento_id' => $evento_id,
                'tipo_evento' => $tipo_evento,
                'resultado' => $resultado_evento
            ]);
            
            logOperacao('evento_fiscal', "Processou evento $tipo_evento para documento #$documento_id", 'sucesso', $evento_id, $_POST);
            break;
            
        case 'listar_eventos':
            // LISTAR EVENTOS FISCAIS
            $documento_id = $_GET['documento_id'] ?? null;
            $tipo_evento = $_GET['tipo_evento'] ?? null;
            $limit = $_GET['limit'] ?? 50;
            
            $sql = "SELECT e.*, d.numero_nfe, d.chave_acesso 
                    FROM fiscal_eventos_fiscais e 
                    JOIN fiscal_nfe_clientes d ON e.documento_id = d.id 
                    WHERE e.empresa_id = ?";
            $params = [$empresa_id];
            
            if ($documento_id) {
                $sql .= " AND e.documento_id = ?";
                $params[] = $documento_id;
            }
            
            if ($tipo_evento) {
                $sql .= " AND e.tipo_evento = ?";
                $params[] = $tipo_evento;
            }
            
            $sql .= " ORDER BY e.data_evento DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'eventos' => $eventos
            ]);
            
            logOperacao('listar', "Listou eventos fiscais", 'sucesso');
            break;
            
        case 'acompanhar_viagem':
            // ACOMPANHAR VIAGEM COMPLETA
            $mdfe_id = $_GET['mdfe_id'] ?? null;
            
            if (!$mdfe_id) {
                throw new Exception('ID do MDF-e é obrigatório');
            }
            
            // Buscar MDF-e
            $stmt = $conn->prepare("
                SELECT * FROM fiscal_mdfe 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$mdfe_id, $empresa_id]);
            $mdfe = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$mdfe) {
                throw new Exception('MDF-e não encontrado');
            }
            
            // Buscar CT-e vinculados
            $stmt = $conn->prepare("
                SELECT * FROM fiscal_cte 
                WHERE mdfe_id = ? AND empresa_id = ?
                ORDER BY numero_cte
            ");
            $stmt->execute([$mdfe_id, $empresa_id]);
            $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Buscar NF-e vinculadas aos CT-e
            $nfes = [];
            foreach ($ctes as $cte) {
                if (!empty($cte['nfe_ids'])) {
                    $nfe_ids = json_decode($cte['nfe_ids'], true);
                    if (is_array($nfe_ids)) {
                        $placeholders = str_repeat('?,', count($nfe_ids) - 1) . '?';
                        $stmt = $conn->prepare("
                            SELECT * FROM fiscal_nfe_clientes 
                            WHERE id IN ($placeholders) AND empresa_id = ?
                        ");
                        $params = array_merge($nfe_ids, [$empresa_id]);
                        $stmt->execute($params);
                        $nfes_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $nfes = array_merge($nfes, $nfes_cte);
                    }
                }
            }
            
            // Calcular estatísticas da viagem
            $estatisticas = calcularEstatisticasViagem($mdfe, $ctes, $nfes);
            
            echo json_encode([
                'success' => true,
                'viagem' => [
                    'mdfe' => $mdfe,
                    'ctes' => $ctes,
                    'nfes' => $nfes,
                    'estatisticas' => $estatisticas
                ]
            ]);
            
            logOperacao('acompanhar', "Acompanhou viagem MDF-e #$mdfe_id", 'sucesso');
            break;
            
        case 'atualizar_status_viagem':
            // ATUALIZAR STATUS DA VIAGEM
            $mdfe_id = $_POST['mdfe_id'] ?? null;
            $novo_status = $_POST['status'] ?? null;
            $localizacao_atual = $_POST['localizacao_atual'] ?? '';
            $observacoes = $_POST['observacoes'] ?? '';
            
            if (!$mdfe_id || !$novo_status) {
                throw new Exception('ID do MDF-e e novo status são obrigatórios');
            }
            
            // Validar status
            $status_validos = ['rascunho', 'pendente', 'autorizado', 'em_viagem', 'entregue', 'encerrado', 'cancelado'];
            if (!in_array($novo_status, $status_validos)) {
                throw new Exception('Status inválido');
            }
            
            // Atualizar MDF-e
            $stmt = $conn->prepare("
                UPDATE fiscal_mdfe 
                SET status = ?, localizacao_atual = ?, observacoes = ?, updated_at = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmt->execute([$novo_status, $localizacao_atual, $observacoes, date('Y-m-d H:i:s'), $mdfe_id, $empresa_id]);
            
            if ($stmt->rowCount() > 0) {
                // Se a viagem foi finalizada, atualizar status das NF-e
                if (in_array($novo_status, ['entregue', 'encerrado'])) {
                    atualizarStatusNFesViagem($mdfe_id, 'entregue');
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Status da viagem atualizado com sucesso!',
                    'status' => $novo_status
                ]);
                
                logOperacao('atualizar_viagem', "Atualizou status da viagem MDF-e #$mdfe_id para $novo_status", 'sucesso', $mdfe_id);
            } else {
                throw new Exception('Nenhuma alteração foi feita');
            }
            break;
            
        case 'validar_consistencia':
            // VALIDAÇÕES AVANÇADAS E ALERTAS
            $tipo_validacao = $_GET['tipo'] ?? 'geral';
            $documento_id = $_GET['documento_id'] ?? null;
            
            $alertas = [];
            
            switch ($tipo_validacao) {
                case 'geral':
                    $alertas = validarConsistenciaGeral();
                    break;
                    
                case 'documento':
                    if (!$documento_id) {
                        throw new Exception('ID do documento é obrigatório para validação específica');
                    }
                    $alertas = validarConsistenciaDocumento($documento_id);
                    break;
                    
                case 'viagens':
                    $alertas = validarConsistenciaViagens();
                    break;
                    
                default:
                    throw new Exception('Tipo de validação inválido');
            }
            
            echo json_encode([
                'success' => true,
                'alertas' => $alertas,
                'total_alertas' => count($alertas),
                'tipo_validacao' => $tipo_validacao
            ]);
            
            logOperacao('validacao', "Executou validação de consistência ($tipo_validacao)", 'sucesso');
            break;
            
        case 'timeline_documento':
            // TIMELINE DE EVENTOS DO DOCUMENTO
            $documento_id = $_GET['documento_id'] ?? null;
            $tipo_documento = $_GET['tipo_documento'] ?? 'nfe';
            
            if (!$documento_id) {
                throw new Exception('ID do documento é obrigatório');
            }
            
            $timeline = gerarTimelineDocumento($documento_id, $tipo_documento);
            
            echo json_encode([
                'success' => true,
                'timeline' => $timeline,
                'total_eventos' => count($timeline)
            ]);
            
            logOperacao('timeline', "Gerou timeline do documento #$documento_id", 'sucesso');
            break;
            
        default:
            throw new Exception('Ação inválida');
    }
    
    // Função para consultar NF-e na SEFAZ (simulada)
    function consultarNFeSefaz($chave_acesso) {
        // Em produção, esta função faria uma consulta real na SEFAZ
        // usando o webservice de distribuição de documentos fiscais
        
        // Simular dados retornados pela SEFAZ
        $numero_nfe = rand(1000, 9999);
        $serie_nfe = rand(1, 9);
        $data_emissao = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
        
        // Simular dados do emitente e destinatário
        $emitentes = [
            'Empresa ABC Ltda',
            'Comercial XYZ S/A',
            'Indústria 123 Ltda',
            'Distribuidora Central',
            'Comércio Varejista'
        ];
        
        $destinatarios = [
            'Cliente Final',
            'Distribuidor Regional',
            'Loja de Varejo',
            'Consumidor Final',
            'Empresa Cliente'
        ];
        
        $emitente = $emitentes[array_rand($emitentes)];
        $destinatario = $destinatarios[array_rand($destinatarios)];
        
        // Simular valores
        $valor_total = round(rand(1000, 50000) / 100, 2);
        $peso_total = round(rand(100, 2000) / 10, 2);
        $volumes = rand(1, 20);
        
        // Simular protocolo SEFAZ
        $protocolo = 'SEFAZ' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        return [
            'numero_nfe' => $numero_nfe,
            'serie_nfe' => $serie_nfe,
            'data_emissao' => $data_emissao,
            'emitente' => $emitente,
            'destinatario' => $destinatario,
            'valor_total' => $valor_total,
            'peso_total' => $peso_total,
            'volumes' => $volumes,
            'protocolo' => $protocolo
        ];
    }
    
    /**
     * Validar integridade dos dados da NF-e
     */
    function validarIntegridadeNFe($dados) {
        // Validações básicas de integridade
        if (!isset($dados['numero_nfe']) || empty($dados['numero_nfe'])) {
            return false;
        }
        
        if (!isset($dados['valor_total']) || $dados['valor_total'] <= 0) {
            return false;
        }
        
        if (!isset($dados['emitente']) || empty($dados['emitente'])) {
            return false;
        }
        
        if (!isset($dados['data_emissao']) || empty($dados['data_emissao'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar se NF-e pode ser vinculada a CT-e
     */
    function verificarVinculacaoNFe($nfe_id, $empresa_id) {
        global $conn;
        
        // Verificar se NF-e existe e está em status adequado
        $stmt = $conn->prepare("
            SELECT status, chave_acesso 
            FROM fiscal_nfe_clientes 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$nfe_id, $empresa_id]);
        $nfe = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$nfe) {
            return ['valida' => false, 'erro' => 'NF-e não encontrada'];
        }
        
        // Verificar se já está vinculada a algum CT-e
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM fiscal_cte 
            WHERE JSON_CONTAINS(nfe_ids, ?) AND empresa_id = ?
        ");
        $stmt->execute([json_encode([$nfe_id]), $empresa_id]);
        $vinculada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vinculada['total'] > 0) {
            return ['valida' => false, 'erro' => 'NF-e já está vinculada a outro CT-e'];
        }
        
        // Verificar status
        $status_validos = ['recebida', 'validada', 'em_transporte'];
        if (!in_array($nfe['status'], $status_validos)) {
            return ['valida' => false, 'erro' => 'Status da NF-e não permite vinculação'];
        }
        
        return ['valida' => true, 'nfe' => $nfe];
    }
    
    /**
     * Validar se evento é permitido para o documento
     */
    function validarEventoPermitido($documento, $tipo_evento) {
        $status = $documento['status'];
        
        switch ($tipo_evento) {
            case 'cancelamento':
                // Cancelamento só é permitido para documentos autorizados/recebidos
                return in_array($status, ['recebida', 'validada', 'autorizada']);
                
            case 'cce':
                // Carta de Correção só é permitida para documentos autorizados
                return in_array($status, ['recebida', 'validada', 'autorizada']);
                
            case 'manifestacao':
                // Manifestação só é permitida para documentos recebidos
                return in_array($status, ['recebida', 'validada']);
                
            case 'inutilizacao':
                // Inutilização pode ser feita em qualquer status
                return true;
                
            case 'encerramento':
                // Encerramento para documentos em viagem
                return in_array($status, ['em_transporte', 'autorizada']);
                
            default:
                return false;
        }
    }
    
    /**
     * Processar evento específico
     */
    function processarEventoEspecifico($evento_id, $tipo_evento, $documento_id, $justificativa) {
        global $conn, $empresa_id;
        
        // Simular processamento do evento (em produção, enviaria para SEFAZ)
        $sucesso = rand(1, 100) <= 85; // 85% de chance de sucesso
        
        if ($sucesso) {
            // Simular protocolo SEFAZ
            $protocolo = 'EVE' . date('Ymd') . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
            
            // Atualizar evento como processado
            $stmt = $conn->prepare("
                UPDATE fiscal_eventos_fiscais 
                SET status = 'aceito', protocolo_evento = ?, data_processamento = ?
                WHERE id = ?
            ");
            $stmt->execute([$protocolo, date('Y-m-d H:i:s'), $evento_id]);
            
            // Atualizar status do documento se necessário
            if ($tipo_evento === 'cancelamento') {
                $stmt = $conn->prepare("
                    UPDATE fiscal_nfe_clientes 
                    SET status = 'cancelada', updated_at = ?
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmt->execute([date('Y-m-d H:i:s'), $documento_id, $empresa_id]);
            }
            
            return [
                'sucesso' => true,
                'protocolo' => $protocolo,
                'data_processamento' => date('Y-m-d H:i:s'),
                'mensagem' => "Evento $tipo_evento processado com sucesso"
            ];
        } else {
            // Simular erro
            $erro = 'Erro na validação do evento pela SEFAZ';
            
            // Atualizar evento como erro
            $stmt = $conn->prepare("
                UPDATE fiscal_eventos_fiscais 
                SET status = 'rejeitado', observacoes = ?
                WHERE id = ?
            ");
            $stmt->execute([$erro, $evento_id]);
            
            return [
                'sucesso' => false,
                'erro' => $erro,
                'data_processamento' => date('Y-m-d H:i:s'),
                'mensagem' => "Erro ao processar evento $tipo_evento"
            ];
        }
    }
    
    /**
     * Calcular estatísticas da viagem
     */
    function calcularEstatisticasViagem($mdfe, $ctes, $nfes) {
        $estatisticas = [
            'total_ctes' => count($ctes),
            'total_nfes' => count($nfes),
            'peso_total' => 0,
            'volumes_total' => 0,
            'valor_total_carga' => 0,
            'valor_total_frete' => 0,
            'distancia_estimada' => 0,
            'tempo_viagem' => null,
            'status_geral' => $mdfe['status']
        ];
        
        // Somar totais dos CT-e
        foreach ($ctes as $cte) {
            $estatisticas['peso_total'] += floatval($cte['peso_carga'] ?? 0);
            $estatisticas['volumes_total'] += intval($cte['volumes_carga'] ?? 0);
            $estatisticas['valor_total_frete'] += floatval($cte['valor_total'] ?? 0);
        }
        
        // Somar valores das NF-e
        foreach ($nfes as $nfe) {
            $estatisticas['valor_total_carga'] += floatval($nfe['valor_total'] ?? 0);
        }
        
        // Calcular distância estimada (simulado)
        $estatisticas['distancia_estimada'] = rand(100, 1500); // km
        
        // Calcular tempo de viagem se em trânsito
        if (in_array($mdfe['status'], ['em_viagem', 'autorizado'])) {
            $inicio_viagem = strtotime($mdfe['data_emissao']);
            $agora = time();
            $estatisticas['tempo_viagem'] = gmdate('H:i:s', $agora - $inicio_viagem);
        }
        
        return $estatisticas;
    }
    
    /**
     * Atualizar status das NF-e quando viagem é finalizada
     */
    function atualizarStatusNFesViagem($mdfe_id, $novo_status) {
        global $conn, $empresa_id;
        
        // Buscar CT-e do MDF-e
        $stmt = $conn->prepare("
            SELECT nfe_ids FROM fiscal_cte 
            WHERE mdfe_id = ? AND empresa_id = ?
        ");
        $stmt->execute([$mdfe_id, $empresa_id]);
        $ctes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $nfe_ids_total = [];
        foreach ($ctes as $cte) {
            if (!empty($cte['nfe_ids'])) {
                $nfe_ids = json_decode($cte['nfe_ids'], true);
                if (is_array($nfe_ids)) {
                    $nfe_ids_total = array_merge($nfe_ids_total, $nfe_ids);
                }
            }
        }
        
        // Atualizar status das NF-e
        if (!empty($nfe_ids_total)) {
            $nfe_ids_total = array_unique($nfe_ids_total);
            $placeholders = str_repeat('?,', count($nfe_ids_total) - 1) . '?';
            
            $stmt = $conn->prepare("
                UPDATE fiscal_nfe_clientes 
                SET status = ?, updated_at = ?
                WHERE id IN ($placeholders) AND empresa_id = ?
            ");
            
            $params = array_merge([$novo_status, date('Y-m-d H:i:s')], $nfe_ids_total, [$empresa_id]);
            $stmt->execute($params);
        }
    }
    
    /**
     * Gerar timeline de eventos do documento
     */
    function gerarTimelineDocumento($documento_id, $tipo_documento = 'nfe') {
        global $conn, $empresa_id;
        
        $timeline = [];
        
        // Buscar eventos do documento
        $stmt = $conn->prepare("
            SELECT 'evento' as tipo, tipo_evento as acao, data_evento as data, 
                   justificativa as descricao, status, protocolo_evento as protocolo
            FROM fiscal_eventos_fiscais 
            WHERE documento_id = ? AND documento_tipo = 'nfe'
            ORDER BY data_evento ASC
        ");
        $stmt->execute([$documento_id]);
        $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($eventos as $evento) {
            $timeline[] = [
                'tipo' => 'evento',
                'acao' => $evento['acao'],
                'data' => $evento['data'],
                'descricao' => $evento['descricao'],
                'status' => $evento['status'],
                'protocolo' => $evento['protocolo'],
                'icone' => getIconeEvento($evento['acao']),
                'cor' => getCorEvento($evento['status'])
            ];
        }
        
        // Buscar mudanças de status (logs)
        $stmt = $conn->prepare("
            SELECT 'log' as tipo, tipo_operacao as acao, created_at as data,
                   descricao, status
            FROM logs_fiscais 
            WHERE documento_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$documento_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($logs as $log) {
            $timeline[] = [
                'tipo' => 'log',
                'acao' => $log['acao'],
                'data' => $log['data'],
                'descricao' => $log['descricao'],
                'status' => $log['status'],
                'icone' => getIconeLog($log['acao']),
                'cor' => getCorLog($log['status'])
            ];
        }
        
        // Ordenar por data
        usort($timeline, function($a, $b) {
            return strtotime($a['data']) - strtotime($b['data']);
        });
        
        return $timeline;
    }
    
    /**
     * Obter ícone para evento
     */
    function getIconeEvento($tipo_evento) {
        $icones = [
            'cancelamento' => 'fas fa-ban',
            'carta_correcao' => 'fas fa-edit',
            'correcao' => 'fas fa-pen',
            'manifestacao' => 'fas fa-clipboard-check',
            'inutilizacao' => 'fas fa-trash'
        ];
        
        return $icones[$tipo_evento] ?? 'fas fa-calendar-alt';
    }
    
    /**
     * Obter cor para evento
     */
    function getCorEvento($status) {
        $cores = [
            'pendente' => 'warning',
            'aceito' => 'success',
            'rejeitado' => 'danger'
        ];
        
        return $cores[$status] ?? 'info';
    }
    
    /**
     * Obter ícone para log
     */
    function getIconeLog($tipo_operacao) {
        $icones = [
            'criacao' => 'fas fa-plus-circle',
            'atualizacao' => 'fas fa-edit',
            'envio_sefaz' => 'fas fa-paper-plane',
            'consulta' => 'fas fa-search',
            'validacao' => 'fas fa-check-circle'
        ];
        
        return $icones[$tipo_operacao] ?? 'fas fa-info-circle';
    }
    
    /**
     * Obter cor para log
     */
    function getCorLog($status) {
        $cores = [
            'sucesso' => 'success',
            'erro' => 'danger',
            'aviso' => 'warning'
        ];
        
        return $cores[$status] ?? 'info';
    }
    
    /**
     * Validar consistência geral do sistema
     */
    function validarConsistenciaGeral() {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // 1. NF-e sem CT-e há muito tempo
        $stmt = $conn->prepare("
            SELECT n.id, n.numero_nfe, n.data_emissao, n.status
            FROM fiscal_nfe_clientes n
            LEFT JOIN fiscal_cte c ON JSON_CONTAINS(c.nfe_ids, CAST(n.id AS JSON))
            WHERE n.empresa_id = ? 
                AND c.id IS NULL 
                AND n.status IN ('recebida', 'validada')
                AND DATEDIFF(CURDATE(), n.data_emissao) > 7
            ORDER BY n.data_emissao ASC
            LIMIT 10
        ");
        $stmt->execute([$empresa_id]);
        $nfes_sem_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($nfes_sem_cte as $nfe) {
            $dias = floor((time() - strtotime($nfe['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'nfe_pendente',
                'titulo' => 'NF-e sem CT-e há ' . $dias . ' dias',
                'descricao' => "NF-e {$nfe['numero_nfe']} está sem CT-e há {$dias} dias",
                'documento_id' => $nfe['id'],
                'acao_sugerida' => 'Criar CT-e para esta NF-e',
                'prioridade' => $dias > 15 ? 'alta' : 'media'
            ];
        }
        
        // 2. CT-e sem MDF-e autorizados
        $stmt = $conn->prepare("
            SELECT id, numero_cte, data_emissao, status
            FROM fiscal_cte
            WHERE empresa_id = ? 
                AND status = 'autorizado'
                AND (mdfe_id IS NULL OR mdfe_id = 0)
                AND DATEDIFF(CURDATE(), data_emissao) > 3
            ORDER BY data_emissao ASC
            LIMIT 10
        ");
        $stmt->execute([$empresa_id]);
        $ctes_sem_mdfe = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($ctes_sem_mdfe as $cte) {
            $dias = floor((time() - strtotime($cte['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'info',
                'categoria' => 'cte_pendente',
                'titulo' => 'CT-e autorizado sem MDF-e',
                'descricao' => "CT-e {$cte['numero_cte']} autorizado há {$dias} dias sem MDF-e",
                'documento_id' => $cte['id'],
                'acao_sugerida' => 'Incluir em MDF-e para viagem',
                'prioridade' => 'baixa'
            ];
        }
        
        // 3. MDF-e em viagem há muito tempo
        $stmt = $conn->prepare("
            SELECT id, numero_mdfe, data_emissao, status
            FROM fiscal_mdfe
            WHERE empresa_id = ? 
                AND status IN ('autorizado', 'em_viagem')
                AND DATEDIFF(CURDATE(), data_emissao) > 10
            ORDER BY data_emissao ASC
        ");
        $stmt->execute([$empresa_id]);
        $mdfe_viagem_longa = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($mdfe_viagem_longa as $mdfe) {
            $dias = floor((time() - strtotime($mdfe['data_emissao'])) / (60 * 60 * 24));
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'viagem_longa',
                'titulo' => 'Viagem em andamento há ' . $dias . ' dias',
                'descricao' => "MDF-e {$mdfe['numero_mdfe']} em viagem há {$dias} dias",
                'documento_id' => $mdfe['id'],
                'acao_sugerida' => 'Verificar status da viagem',
                'prioridade' => $dias > 20 ? 'alta' : 'media'
            ];
        }
        
        // 4. Eventos fiscais com erro
        $stmt = $conn->prepare("
            SELECT e.id, e.tipo_evento, e.data_evento, n.numero_nfe
            FROM fiscal_eventos_fiscais e
            JOIN fiscal_nfe_clientes n ON e.documento_id = n.id
            WHERE e.empresa_id = ? 
                AND e.status = 'rejeitado'
                AND DATEDIFF(CURDATE(), e.data_evento) <= 30
            ORDER BY e.data_evento DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $eventos_erro = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($eventos_erro as $evento) {
            $alertas[] = [
                'tipo' => 'danger',
                'categoria' => 'evento_erro',
                'titulo' => 'Evento fiscal com erro',
                'descricao' => "Evento {$evento['tipo_evento']} da NF-e {$evento['numero_nfe']} com erro",
                'documento_id' => $evento['id'],
                'acao_sugerida' => 'Reprocessar evento fiscal',
                'prioridade' => 'alta'
            ];
        }
        
        return $alertas;
    }
    
    /**
     * Validar consistência de um documento específico
     */
    function validarConsistenciaDocumento($documento_id) {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // Buscar documento
        $stmt = $conn->prepare("
            SELECT * FROM fiscal_nfe_clientes 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$documento_id, $empresa_id]);
        $documento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$documento) {
            return [['tipo' => 'danger', 'titulo' => 'Documento não encontrado', 'descricao' => 'O documento especificado não foi encontrado']];
        }
        
        // Validar chave de acesso
        if (strlen($documento['chave_acesso']) !== 44) {
            $alertas[] = [
                'tipo' => 'danger',
                'categoria' => 'chave_invalida',
                'titulo' => 'Chave de acesso inválida',
                'descricao' => 'A chave de acesso não possui 44 dígitos',
                'prioridade' => 'alta'
            ];
        }
        
        // Validar valores
        if ($documento['valor_total'] <= 0) {
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'valor_zerado',
                'titulo' => 'Valor total zerado',
                'descricao' => 'O valor total da NF-e está zerado ou negativo',
                'prioridade' => 'media'
            ];
        }
        
        // Verificar se está vinculada a CT-e
        $stmt = $conn->prepare("
            SELECT COUNT(*) as total 
            FROM fiscal_cte 
            WHERE JSON_CONTAINS(nfe_ids, ?) AND empresa_id = ?
        ");
        $stmt->execute([json_encode([$documento_id]), $empresa_id]);
        $vinculada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($vinculada['total'] == 0 && in_array($documento['status'], ['recebida', 'validada'])) {
            $dias = floor((time() - strtotime($documento['data_emissao'])) / (60 * 60 * 24));
            if ($dias > 3) {
                $alertas[] = [
                    'tipo' => 'info',
                    'categoria' => 'sem_cte',
                    'titulo' => 'NF-e não vinculada a CT-e',
                    'descricao' => "NF-e está há {$dias} dias sem vinculação a CT-e",
                    'prioridade' => 'baixa'
                ];
            }
        }
        
        return $alertas;
    }
    
    /**
     * Validar consistência das viagens
     */
    function validarConsistenciaViagens() {
        global $conn, $empresa_id;
        
        $alertas = [];
        
        // Viagens sem CT-e
        $stmt = $conn->prepare("
            SELECT m.id, m.numero_mdfe, m.data_emissao, COUNT(c.id) as total_ctes
            FROM fiscal_mdfe m
            LEFT JOIN fiscal_cte c ON c.mdfe_id = m.id
            WHERE m.empresa_id = ?
            GROUP BY m.id, m.numero_mdfe, m.data_emissao
            HAVING total_ctes = 0
            ORDER BY m.data_emissao DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $mdfe_sem_cte = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($mdfe_sem_cte as $mdfe) {
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'mdfe_vazio',
                'titulo' => 'MDF-e sem CT-e',
                'descricao' => "MDF-e {$mdfe['numero_mdfe']} não possui CT-e vinculados",
                'documento_id' => $mdfe['id'],
                'acao_sugerida' => 'Adicionar CT-e ao manifesto',
                'prioridade' => 'media'
            ];
        }
        
        // Viagens com discrepâncias de peso
        $stmt = $conn->prepare("
            SELECT m.id, m.numero_mdfe, m.peso_total as peso_mdfe, 
                   SUM(c.peso_carga) as peso_ctes
            FROM fiscal_mdfe m
            JOIN fiscal_cte c ON c.mdfe_id = m.id
            WHERE m.empresa_id = ?
            GROUP BY m.id, m.numero_mdfe, m.peso_total
            HAVING ABS(peso_mdfe - peso_ctes) > 100
            ORDER BY ABS(peso_mdfe - peso_ctes) DESC
            LIMIT 5
        ");
        $stmt->execute([$empresa_id]);
        $discrepancias_peso = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($discrepancias_peso as $disc) {
            $diferenca = abs($disc['peso_mdfe'] - $disc['peso_ctes']);
            $alertas[] = [
                'tipo' => 'warning',
                'categoria' => 'discrepancia_peso',
                'titulo' => 'Discrepância de peso na viagem',
                'descricao' => "MDF-e {$disc['numero_mdfe']} com diferença de {$diferenca}kg entre MDF-e e CT-e",
                'documento_id' => $disc['id'],
                'acao_sugerida' => 'Revisar pesos dos documentos',
                'prioridade' => 'media'
            ];
        }
        
        return $alertas;
    }
    
} catch (Exception $e) {
    // Não logar erros sem documento_id para evitar problemas
    if (strpos($e->getMessage(), 'Nenhuma alteração foi feita') === false) {
        logOperacao('erro', $e->getMessage(), 'erro', null);
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
