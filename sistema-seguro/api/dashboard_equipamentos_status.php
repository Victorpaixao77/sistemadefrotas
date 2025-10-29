<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter empresa_id
$empresa_id = obterEmpresaId();

try {
    $db = getDB();
    
    // Buscar equipamentos agrupados por status
    $sql = "
        SELECT 
            COALESCE(situacao, 'ativo') as situacao,
            COUNT(*) as total
        FROM seguro_equipamentos
        WHERE seguro_empresa_id = ?
        GROUP BY situacao
        ORDER BY 
            CASE situacao
                WHEN 'ativo' THEN 1
                WHEN 'inativo' THEN 2
                WHEN 'manutencao' THEN 3
                WHEN 'substituido' THEN 4
                ELSE 5
            END
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$empresa_id]);
    
    $equipamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Se não houver equipamentos, retornar array vazio
    if (empty($equipamentos)) {
        $equipamentos = [
            ['situacao' => 'ativo', 'total' => 0],
            ['situacao' => 'inativo', 'total' => 0],
            ['situacao' => 'manutencao', 'total' => 0],
            ['situacao' => 'substituido', 'total' => 0]
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'dados' => $equipamentos
    ]);
    
} catch (PDOException $e) {
    error_log("Erro ao buscar equipamentos: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar dados de equipamentos',
        'dados' => []
    ]);
} catch (Exception $e) {
    error_log("Erro geral: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao processar requisição',
        'dados' => []
    ]);
}
?>
