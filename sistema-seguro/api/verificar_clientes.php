<?php
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar se está logado
verificarLogin();

// Obter empresa_id
$empresa_id = obterEmpresaId();

// Obter conexão
$pdo = getDB();

try {
    // Receber lista de identificadores
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['identificadores']) || !is_array($data['identificadores'])) {
        throw new Exception('Dados inválidos');
    }
    
    $identificadores = $data['identificadores'];
    $resultado = [];
    
    foreach ($identificadores as $identificador) {
        // Buscar por identificador
        $stmt = $pdo->prepare("
            SELECT id, razao_social, nome_fantasia, codigo
            FROM seguro_clientes 
            WHERE identificador = ? AND seguro_empresa_id = ?
            LIMIT 1
        ");
        $stmt->execute([$identificador, $empresa_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cliente) {
            // Buscar por nome (coluna antiga)
            $stmt = $pdo->prepare("
                SELECT id, nome_razao_social as razao_social, sigla_fantasia as nome_fantasia, codigo
                FROM seguro_clientes 
                WHERE nome_razao_social LIKE ? AND seguro_empresa_id = ?
                LIMIT 1
            ");
            $stmt->execute(['%' . $identificador . '%', $empresa_id]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $resultado[$identificador] = [
            'existe' => $cliente ? true : false,
            'dados' => $cliente ?: null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'clientes' => $resultado
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

