<?php
/**
 * API - Obter Distribuição por Classes
 * Retorna distribuição de documentos financeiros por classe
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

verificarLogin();

try {
    $db = getDB();
    $empresa_id = obterEmpresaId();
    
    // Buscar distribuição por classe
    $stmt = $db->prepare("
        SELECT 
            COALESCE(classe, 'Sem Classe') as classe,
            COUNT(*) as quantidade,
            SUM(valor_pago) as total
        FROM seguro_financeiro
        WHERE seguro_empresa_id = ?
          AND valor_pago > 0
          AND data_baixa IS NOT NULL
        GROUP BY classe
        ORDER BY total DESC
        LIMIT 10
    ");
    $stmt->execute([$empresa_id]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'sucesso' => true,
        'classes' => $classes
    ]);
    
} catch (Exception $e) {
    error_log("Erro ao buscar distribuição por classes: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados',
        'classes' => []
    ]);
}
?>

