<?php
/**
 * API - Histórico de Importações
 * Retorna histórico de arquivos CSV importados
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $usuario_id = obterUsuarioId();
    
    $periodo = $_GET['periodo'] ?? 30;
    $filtro_status = $_GET['status'] ?? 'todos';
    
    // Verificar se existe tabela de histórico
    $stmt = $db->query("SHOW TABLES LIKE 'seguro_historico_importacoes'");
    $tabelaExiste = $stmt->fetch();
    
    $importacoes = [];
    
    if ($tabelaExiste) {
        // Buscar do banco
        $sql = "
            SELECT 
                hi.*,
                u.nome as usuario_nome,
                DATE_FORMAT(hi.data_hora, '%d/%m/%Y %H:%i') as data_hora_formatada
            FROM seguro_historico_importacoes hi
            LEFT JOIN seguro_usuarios u ON hi.usuario_id = u.id
            WHERE hi.empresa_id = ?
        ";
        
        // Filtro de período
        if ($periodo > 0) {
            $sql .= " AND hi.data_hora >= DATE_SUB(NOW(), INTERVAL {$periodo} DAY)";
        }
        
        // Filtro de status
        if ($filtro_status === 'sucesso') {
            $sql .= " AND hi.total_erros = 0";
        } elseif ($filtro_status === 'com_erros') {
            $sql .= " AND hi.total_erros > 0";
        }
        
        $sql .= " ORDER BY hi.data_hora DESC LIMIT 50";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$empresa_id]);
        $importacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else {
        // Se não existe tabela, retornar dados do log (se existir)
        $logFile = '../logs/importacao_debug.log';
        
        if (file_exists($logFile)) {
            $linhas = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $tempImportacoes = [];
            $importacaoAtual = null;
            
            foreach ($linhas as $linha) {
                // Início de importação
                if (strpos($linha, 'INÍCIO DA IMPORTAÇÃO') !== false) {
                    $importacaoAtual = [
                        'data_hora_formatada' => '',
                        'nome_arquivo' => 'importacao.csv',
                        'total_registros' => 0,
                        'processados' => 0,
                        'total_erros' => 0,
                        'usuario_nome' => 'Sistema',
                        'detalhes' => null
                    ];
                }
                
                // Extrair data/hora
                if ($importacaoAtual && preg_match('/Hora: (.+)/', $linha, $matches)) {
                    $importacaoAtual['data_hora_formatada'] = date('d/m/Y H:i', strtotime($matches[1]));
                }
                
                // Extrair total de documentos
                if ($importacaoAtual && preg_match('/Total de documentos recebidos: (\d+)/', $linha, $matches)) {
                    $importacaoAtual['total_registros'] = intval($matches[1]);
                }
                
                // Fim da importação - salvar
                if ($importacaoAtual && strpos($linha, 'FIM DA IMPORTAÇÃO') !== false) {
                    // Estimar processados (total - erros é uma aproximação)
                    $importacaoAtual['processados'] = $importacaoAtual['total_registros'];
                    $tempImportacoes[] = $importacaoAtual;
                    $importacaoAtual = null;
                }
            }
            
            // Pegar últimas N importações
            $importacoes = array_slice(array_reverse($tempImportacoes), 0, 50);
            
            // Aplicar filtros
            if ($filtro_status === 'sucesso') {
                $importacoes = array_filter($importacoes, fn($i) => $i['total_erros'] == 0);
            } elseif ($filtro_status === 'com_erros') {
                $importacoes = array_filter($importacoes, fn($i) => $i['total_erros'] > 0);
            }
            
            // Reindexar array
            $importacoes = array_values($importacoes);
        }
    }
    
    echo json_encode([
        'sucesso' => true,
        'importacoes' => $importacoes,
        'total' => count($importacoes),
        'mensagem' => count($importacoes) > 0 ? 'Histórico carregado' : 'Nenhuma importação encontrada'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar histórico de importações: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao carregar histórico',
        'importacoes' => []
    ]);
}
?>

