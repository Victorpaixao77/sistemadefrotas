<?php
/**
 * API - Baixar Arquivo de Importação
 * Permite fazer download do CSV importado
 */

require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    $id = $_GET['id'] ?? 0;
    $nomeArquivo = $_GET['nome_arquivo'] ?? 'importacao.csv';
    
    // Buscar dados da importação
    $stmt = $db->query("SHOW TABLES LIKE 'seguro_historico_importacoes'");
    $tabelaExiste = $stmt->fetch();
    
    if ($tabelaExiste && $id > 0) {
        // Buscar do banco
        $stmt = $db->prepare("
            SELECT * 
            FROM seguro_historico_importacoes 
            WHERE id = ? AND empresa_id = ?
        ");
        $stmt->execute([$id, $empresa_id]);
        $importacao = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($importacao) {
            $detalhes = json_decode($importacao['detalhes'], true);
            
            // Gerar CSV com os dados
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            echo "\xEF\xBB\xBF"; // BOM UTF-8
            
            $output = fopen('php://output', 'w');
            
            // Cabeçalho
            fputcsv($output, [
                'IDENTIFICADOR',
                'NÚMERO DOC',
                'ASSOCIADO',
                'VALOR',
                'DATA VENCIMENTO',
                'DATA BAIXA',
                'VALOR PAGO',
                'STATUS'
            ], ';');
            
            // Se tiver dados no detalhes, usar
            // Caso contrário, gerar informação resumida
            if ($detalhes) {
                fputcsv($output, [
                    'RESUMO DA IMPORTAÇÃO',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                ], ';');
                
                fputcsv($output, [
                    'Total de registros',
                    $importacao['total_registros'],
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                ], ';');
                
                fputcsv($output, [
                    'Processados',
                    $importacao['processados'],
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                ], ';');
                
                fputcsv($output, [
                    'Erros',
                    $importacao['total_erros'],
                    '',
                    '',
                    '',
                    '',
                    '',
                    ''
                ], ';');
                
                if (isset($detalhes['erros']) && is_array($detalhes['erros'])) {
                    fputcsv($output, [''], ';');
                    fputcsv($output, ['ERROS ENCONTRADOS'], ';');
                    foreach ($detalhes['erros'] as $erro) {
                        fputcsv($output, [$erro], ';');
                    }
                }
            }
            
            fclose($output);
            exit;
        }
    }
    
    // Se não encontrou no banco ou não tem tabela, gerar CSV genérico
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nomeArquivo . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    
    $output = fopen('php://output', 'w');
    
    fputcsv($output, [
        'IDENTIFICADOR',
        'NÚMERO DOC',
        'ASSOCIADO',
        'VALOR',
        'DATA VENCIMENTO',
        'DATA BAIXA',
        'VALOR PAGO',
        'STATUS'
    ], ';');
    
    fputcsv($output, [
        'Arquivo de importação não disponível',
        'Use a opção "Importar Retorno" para importar novamente',
        '',
        '',
        '',
        '',
        '',
        ''
    ], ';');
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    error_log("Erro ao baixar importação: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo "Erro ao baixar arquivo: " . $e->getMessage();
    exit;
}
?>

