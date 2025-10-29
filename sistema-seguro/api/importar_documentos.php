<?php
// Habilitar logs temporariamente para debug
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não exibir na tela
ini_set('log_errors', 1);
ini_set('error_log', '../logs/importacao_debug.log');

// Buffer de saída para capturar qualquer output não desejado
ob_start();

// Log de início
error_log("========== INÍCIO DA IMPORTAÇÃO ==========");
error_log("Hora: " . date('Y-m-d H:i:s'));

try {
    error_log("Carregando includes...");
    require_once '../config/database.php';
    error_log("✓ database.php carregado");
    
    require_once '../config/auth.php';
    error_log("✓ auth.php carregado");

    // Limpar qualquer output anterior
    ob_clean();
    
    header('Content-Type: application/json; charset=utf-8');
    error_log("✓ Headers definidos");
    
    // Verificar se está logado
    error_log("Verificando login...");
    verificarLogin();
    error_log("✓ Login verificado");
    
    // Obter empresa_id
    error_log("Obtendo empresa_id...");
    $empresa_id = obterEmpresaId();
    error_log("✓ Empresa ID: $empresa_id");
    
    // Obter conexão com o banco de dados
    error_log("Conectando ao banco...");
    $pdo = getDB();
    error_log("✓ Conexão estabelecida");
    
    // Receber JSON
    error_log("Lendo JSON de entrada...");
    $json = file_get_contents('php://input');
    error_log("JSON recebido: " . strlen($json) . " bytes");
    
    $data = json_decode($json, true);
    error_log("JSON decodificado: " . (is_array($data) ? 'OK' : 'FALHOU'));
    
    if (!isset($data['documentos']) || !is_array($data['documentos'])) {
        error_log("❌ Dados inválidos - esperado array de documentos");
        throw new Exception('Dados inválidos - esperado array de documentos');
    }
    
    $documentos = $data['documentos'];
    $nomeArquivo = $data['nome_arquivo'] ?? 'importacao_' . date('Ymd_His') . '.csv';
    $ehPrimeiroLote = $data['eh_primeiro_lote'] ?? true;
    $ehUltimoLote = $data['eh_ultimo_lote'] ?? true;
    $totalDocumentos = $data['total_documentos'] ?? count($documentos);
    
    error_log("Nome do arquivo: " . $nomeArquivo);
    error_log("Total de documentos recebidos: " . count($documentos));
    
    $importados = 0;
    $erros = [];
    $pulados = 0;
    $clientesCriados = 0;
    
    // NÃO usar transação global - processar documento por documento
    foreach ($documentos as $index => $doc) {
        try {
            // Iniciar transação para este documento
            $pdo->beginTransaction();
            // Buscar cliente pelo identificador
            $stmtCliente = $pdo->prepare("
                SELECT id 
                FROM seguro_clientes 
                WHERE identificador = ? 
                AND seguro_empresa_id = ?
                LIMIT 1
            ");
            $stmtCliente->execute([$doc['identificador'], $empresa_id]);
            $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
            
            if (!$cliente) {
                // Se não encontrar o cliente pelo identificador, buscar pelo nome do associado
                // Tentar pela coluna antiga (nome_razao_social) e nova (razao_social)
                $associado = $doc['associado'] ?? '';
                
                try {
                    $stmtCliente = $pdo->prepare("
                        SELECT id 
                        FROM seguro_clientes 
                        WHERE (razao_social = ? OR nome_fantasia = ? OR nome_razao_social = ? OR sigla_fantasia = ?)
                        AND seguro_empresa_id = ?
                        LIMIT 1
                    ");
                    $stmtCliente->execute([$associado, $associado, $associado, $associado, $empresa_id]);
                    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    // Se falhar, tentar só com as colunas antigas
                    try {
                        $stmtCliente = $pdo->prepare("
                            SELECT id 
                            FROM seguro_clientes 
                            WHERE (nome_razao_social = ? OR sigla_fantasia = ?)
                            AND seguro_empresa_id = ?
                            LIMIT 1
                        ");
                        $stmtCliente->execute([$associado, $associado, $empresa_id]);
                        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e2) {
                        // Ignorar erro, cliente não será encontrado
                        error_log("Erro ao buscar cliente por nome: " . $e2->getMessage());
                    }
                }
            }
            
            // Se não encontrou, marcar para quarentena
            $clienteNaoEncontrado = 'nao';
            if (!$cliente) {
                $cliente_id = null; // NULL = quarentena
                $clienteNaoEncontrado = 'sim';
                error_log("Cliente não encontrado para identificador: {$doc['identificador']}");
            } else {
                $cliente_id = $cliente['id'];
                error_log("Cliente encontrado: ID {$cliente_id}");
            }
            
            // Verificar se o documento já existe (verificar apenas por número e empresa)
            $stmtVerificar = $pdo->prepare("
                SELECT id 
                FROM seguro_financeiro 
                WHERE numero_documento = ? 
                AND seguro_empresa_id = ?
                LIMIT 1
            ");
            $stmtVerificar->execute([
                $doc['numero_documento'],
                $empresa_id
            ]);
            
            if ($stmtVerificar->fetch()) {
                // Documento já existe, pular silenciosamente
                $pdo->rollBack();
                $pulados++;
                continue;
            }
            
            // Buscar unidade da empresa
            $unidade = '';
            try {
                $stmtUnidade = $pdo->prepare("
                    SELECT unidade 
                    FROM seguro_empresa_clientes 
                    WHERE id = ?
                ");
                $stmtUnidade->execute([$empresa_id]);
                $unidadeEmpresa = $stmtUnidade->fetch(PDO::FETCH_ASSOC);
                $unidade = $unidadeEmpresa['unidade'] ?? '';
            } catch (Exception $e) {
                // Ignorar erro ao buscar unidade
            }
            
            // Inserir documento financeiro
            $stmtDoc = $pdo->prepare("
                INSERT INTO seguro_financeiro (
                    seguro_empresa_id,
                    cliente_id,
                    cliente_nao_encontrado,
                    identificador_original,
                    unidade,
                    identificador,
                    numero_documento,
                    associado,
                    classe,
                    data_emissao,
                    data_vencimento,
                    valor,
                    placa,
                    conjunto,
                    matricula,
                    status,
                    valor_pago,
                    data_baixa,
                    data_cadastro
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmtDoc->execute([
                $empresa_id,
                $cliente_id, // NULL se não encontrado
                $clienteNaoEncontrado, // 'sim' ou 'nao'
                $doc['identificador'] ?? '', // Guardar identificador original
                $unidade,
                $doc['identificador'] ?? '',
                $doc['numero_documento'] ?? '',
                $doc['associado'] ?? '',
                $doc['classe'] ?? '',
                $doc['data_emissao'] ?? null,
                $doc['data_vencimento'] ?? null,
                $doc['valor'] ?? 0,
                $doc['placa'] ?? '',
                $doc['conjunto'] ?? '',
                $doc['matricula'] ?? '',
                $doc['status'] ?? 'pendente',
                $doc['valor_pago'] ?? 0,
                $doc['data_baixa'] ?? null
            ]);
            
            // Contar quantos foram para quarentena
            if ($clienteNaoEncontrado === 'sim') {
                $clientesCriados++; // Reutilizar contador como "em quarentena"
            }
            
            // Commit da transação deste documento
            $pdo->commit();
            $importados++;
            
        } catch (Exception $e) {
            // Rollback apenas deste documento
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            $numDoc = $doc['numero_documento'] ?? 'N/A';
            $ident = $doc['identificador'] ?? 'N/A';
            $erros[] = "Doc #{$numDoc} (ID: {$ident}): " . $e->getMessage();
            
            // Log do erro mas continua processando
            error_log("Erro ao importar documento {$numDoc}: " . $e->getMessage());
        }
    }
    
    // Registrar log (com os 5 parâmetros corretos)
    try {
        $usuario = obterUsuarioLogado();
        registrarLog(
            $empresa_id,
            $usuario['id'] ?? 0,
            'importacao_documentos',
            'financeiro',
            "Importados: {$importados}, Pulados: {$pulados}, Em quarentena: {$clientesCriados}, Erros: " . count($erros)
        );
    } catch (Exception $e) {
        // Ignorar erro ao registrar log
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
    
    // Registrar no histórico de importações (apenas no último lote E se importou algo)
    if ($ehUltimoLote && $importados > 0) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'seguro_historico_importacoes'");
            if ($stmt->fetch()) {
                // Tabela existe, registrar
                $stmt = $pdo->prepare("
                    INSERT INTO seguro_historico_importacoes 
                    (empresa_id, usuario_id, nome_arquivo, total_registros, processados, total_erros, detalhes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                $usuario_id = obterUsuarioId();
                $totalErros = count($erros);
                $detalhesJson = json_encode([
                    'importados' => $importados,
                    'pulados' => $pulados,
                    'quarentena' => $clientesCriados,
                    'erros' => array_slice($erros, 0, 10) // Primeiros 10 erros
                ], JSON_UNESCAPED_UNICODE);
                
                $stmt->execute([
                    $empresa_id,
                    $usuario_id,
                    $nomeArquivo, // Nome real do arquivo
                    $totalDocumentos, // Total completo
                    $importados,
                    $totalErros,
                    $detalhesJson
                ]);
                
                error_log("✅ Importação registrada no histórico: {$nomeArquivo} ({$importados} documentos)");
            } else {
                // Log em arquivo se tabela não existe (apenas se importou algo)
                $logMsg = sprintf(
                    "[%s] [Empresa %d] Arquivo: %s | Total: %d | Processados: %d | Erros: %d",
                    date('Y-m-d H:i:s'),
                    $empresa_id,
                    $nomeArquivo,
                    $totalDocumentos,
                    $importados,
                    count($erros)
                );
                error_log($logMsg);
            }
        } catch (Exception $e) {
            error_log("Erro ao registrar histórico: " . $e->getMessage());
        }
    } else if ($ehUltimoLote && $importados == 0) {
        error_log("⚠️ Nenhum documento importado - histórico NÃO registrado");
    }
    
    // Limpar buffer
    ob_end_clean();
    
    // Retornar resultado
    echo json_encode([
        'success' => true,
        'importados' => $importados,
        'pulados' => $pulados,
        'emQuarentena' => $clientesCriados, // Renomeado para ficar mais claro
        'erros' => $erros,
        'total' => count($documentos),
        'mensagem' => "✅ {$importados} importados, {$pulados} duplicados pulados" . 
                      ($clientesCriados > 0 ? ", {$clientesCriados} em quarentena (cliente não encontrado)" : "") .
                      (count($erros) > 0 ? ", " . count($erros) . " com erro" : "")
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Limpar buffer
    ob_end_clean();
    
    // Log do erro crítico com detalhes
    error_log("❌ ERRO CRÍTICO NA IMPORTAÇÃO");
    error_log("Mensagem: " . $e->getMessage());
    error_log("Arquivo: " . $e->getFile());
    error_log("Linha: " . $e->getLine());
    error_log("Stack trace:");
    error_log($e->getTraceAsString());
    
    // Retornar erro em JSON
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'erro_detalhado' => [
            'mensagem' => $e->getMessage(),
            'arquivo' => basename($e->getFile()),
            'linha' => $e->getLine()
        ],
        'importados' => 0,
        'erros' => []
    ], JSON_UNESCAPED_UNICODE);
}

error_log("========== FIM DA IMPORTAÇÃO ==========\n");
?>

