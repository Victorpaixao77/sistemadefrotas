<?php
/**
 * API - Dashboard - Atividades Recentes
 * Retorna logs de atividades recentes do sistema
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$empresa_id = obterEmpresaId();

try {
    // Buscar logs recentes da tabela seguro_logs
    $stmt = $db->prepare("
        SELECT 
            l.id,
            DATE_FORMAT(l.data_hora, '%d/%m/%Y %H:%i') as data_formatada,
            l.data_hora,
            l.acao,
            l.tabela,
            l.descricao,
            u.nome as usuario
        FROM seguro_logs l
        LEFT JOIN seguro_usuarios u ON l.usuario_id = u.id
        WHERE l.seguro_empresa_id = ?
        ORDER BY l.data_hora DESC
        LIMIT 10
    ");
    $stmt->execute([$empresa_id]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $atividades = [];
    
    foreach ($logs as $log) {
        // Mapear tipo de ação
        $tipoAcao = 'default';
        if (in_array($log['acao'], ['criar', 'cadastrar', 'novo'])) {
            $tipoAcao = 'criar';
        } elseif (in_array($log['acao'], ['editar', 'atualizar'])) {
            $tipoAcao = 'editar';
        } elseif (in_array($log['acao'], ['deletar', 'excluir'])) {
            $tipoAcao = 'deletar';
        } elseif ($log['acao'] === 'login') {
            $tipoAcao = 'login';
        } elseif ($log['acao'] === 'logout') {
            $tipoAcao = 'logout';
        }
        
        $atividades[] = [
            'data_formatada' => $log['data_formatada'],
            'usuario' => $log['usuario'] ?? 'Sistema',
            'acao' => ucfirst($log['acao']),
            'descricao' => $log['descricao'],
            'tipo_acao' => $tipoAcao,
            'tabela' => $log['tabela']
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'atividades' => $atividades,
        'total' => count($atividades)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar atividades: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar atividades'
    ], JSON_UNESCAPED_UNICODE);
}
?>

