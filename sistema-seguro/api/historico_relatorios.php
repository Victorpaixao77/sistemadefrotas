<?php
/**
 * API - Histórico de Relatórios
 * Retorna os últimos relatórios gerados
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    $usuario_id = obterUsuarioId();
    
    // Buscar últimos 10 relatórios (simulado por enquanto)
    // Você pode criar uma tabela seguro_historico_relatorios para armazenar de verdade
    
    // Por enquanto, retornar array vazio ou dados de exemplo
    $historico = [];
    
    // Verificar se existe tabela de histórico
    $stmt = $db->query("SHOW TABLES LIKE 'seguro_historico_relatorios'");
    $tabelaExiste = $stmt->fetch();
    
    if ($tabelaExiste) {
        // Se a tabela existe, buscar dados reais
        $stmt = $db->prepare("
            SELECT 
                id,
                tipo_relatorio,
                data_geracao,
                periodo_inicio,
                periodo_fim,
                total_registros,
                formato
            FROM seguro_historico_relatorios
            WHERE seguro_empresa_id = ?
            ORDER BY data_geracao DESC
            LIMIT 10
        ");
        $stmt->execute([$empresa_id]);
        $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formatar datas
        foreach ($historico as &$item) {
            $item['data_geracao_formatada'] = date('d/m/Y H:i', strtotime($item['data_geracao']));
            $item['periodo_formatado'] = date('d/m/Y', strtotime($item['periodo_inicio'])) . ' a ' . date('d/m/Y', strtotime($item['periodo_fim']));
        }
    } else {
        // Se não existe, retornar array vazio
        $historico = [];
    }
    
    echo json_encode([
        'sucesso' => true,
        'historico' => $historico,
        'mensagem' => count($historico) > 0 ? 'Histórico carregado' : 'Nenhum relatório gerado ainda'
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar histórico de relatórios: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao carregar histórico',
        'historico' => []
    ]);
}
?>

