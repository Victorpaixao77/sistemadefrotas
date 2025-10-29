<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter empresa_id
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar total de atendimentos
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM seguro_atendimentos
        WHERE seguro_empresa_id = ?
    ");
    $stmt->execute([$empresa_id]);
    $total_atendimentos = $stmt->fetchColumn();
    
    // Buscar atendimentos fechados (resolvidos ou fechados)
    $stmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM seguro_atendimentos
        WHERE seguro_empresa_id = ?
          AND status IN ('resolvido', 'fechado')
    ");
    $stmt->execute([$empresa_id]);
    $atendimentos_fechados = $stmt->fetchColumn();
    
    // Calcular taxa de resolução
    $taxa_resolucao = 0;
    if ($total_atendimentos > 0) {
        $taxa_resolucao = ($atendimentos_fechados / $total_atendimentos) * 100;
    }
    
    echo json_encode([
        'sucesso' => true,
        'taxa_resolucao' => round($taxa_resolucao, 1),
        'total_atendimentos' => $total_atendimentos,
        'atendimentos_fechados' => $atendimentos_fechados
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar taxa de resolução: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados',
        'taxa_resolucao' => 0,
        'total_atendimentos' => 0,
        'atendimentos_fechados' => 0
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição',
        'taxa_resolucao' => 0,
        'total_atendimentos' => 0,
        'atendimentos_fechados' => 0
    ]);
}
?>
