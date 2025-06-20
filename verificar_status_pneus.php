<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

echo "<h2>Verificando status dos pneus...</h2>";

try {
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>1. Status cadastrados na tabela status_pneus:</h3>";
    
    $stmt = $pdo->query("SELECT * FROM status_pneus ORDER BY id");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($statuses)) {
        echo "❌ Nenhum status encontrado! Inserindo status padrão...<br>";
        
        $statuses = [
            [1, 'furado', 'Pneu furado ou com danos graves', '#dc3545'],
            [2, 'disponivel', 'Pneu disponível para uso', '#28a745'],
            [3, 'descartado', 'Pneu descartado/irrecuperável', '#6c757d'],
            [4, 'gasto', 'Pneu gasto mas ainda utilizável', '#ffc107'],
            [5, 'novo', 'Pneu novo ou em excelente estado', '#17a2b8']
        ];
        
        foreach ($statuses as $status) {
            $stmt = $pdo->prepare("INSERT INTO status_pneus (id, nome, descricao, cor) VALUES (?, ?, ?, ?)");
            $stmt->execute($status);
        }
        echo "✓ Status padrão inseridos<br>";
    } else {
        echo "✓ Status encontrados:<br>";
        foreach ($statuses as $status) {
            echo "• ID {$status['id']}: {$status['nome']} - {$status['descricao']}<br>";
        }
    }
    
    echo "<h3>2. Verificando pneus disponíveis...</h3>";
    
    // Contar pneus por status
    $stmt = $pdo->query("SELECT p.status_id, sp.nome as status_nome, COUNT(*) as total 
                        FROM pneus p 
                        LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                        GROUP BY p.status_id, sp.nome 
                        ORDER BY p.status_id");
    $pneus_por_status = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Pneus por status:<br>";
    foreach ($pneus_por_status as $status) {
        echo "• {$status['status_nome']} (ID: {$status['status_id']}): {$status['total']} pneus<br>";
    }
    
    echo "<h3>3. Verificando pneus instalados...</h3>";
    
    // Verificar pneus instalados
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM instalacoes_pneus WHERE data_remocao IS NULL");
    $pneus_instalados = $stmt->fetchColumn();
    echo "Pneus atualmente instalados: $pneus_instalados<br>";
    
    echo "<h3>4. Pneus disponíveis para alocação:</h3>";
    
    // Pneus disponíveis (não instalados e com status adequado)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total 
                          FROM pneus p 
                          WHERE p.empresa_id = ? 
                          AND p.status_id IN (2, 5)
                          AND p.id NOT IN (
                              SELECT pneu_id 
                              FROM instalacoes_pneus 
                              WHERE data_remocao IS NULL
                          )");
    $stmt->execute([1]); // empresa_id = 1
    $pneus_disponiveis = $stmt->fetchColumn();
    
    echo "Pneus disponíveis para alocação: $pneus_disponiveis<br>";
    
    if ($pneus_disponiveis > 0) {
        echo "<h3>5. Lista de pneus disponíveis:</h3>";
        
        $stmt = $pdo->prepare("SELECT p.*, sp.nome as status_nome 
                              FROM pneus p 
                              LEFT JOIN status_pneus sp ON p.status_id = sp.id 
                              WHERE p.empresa_id = ? 
                              AND p.status_id IN (2, 5)
                              AND p.id NOT IN (
                                  SELECT pneu_id 
                                  FROM instalacoes_pneus 
                                  WHERE data_remocao IS NULL
                              )
                              ORDER BY p.numero_serie");
        $stmt->execute([1]);
        $pneus = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Número Série</th><th>Marca</th><th>Modelo</th><th>Status</th></tr>";
        foreach ($pneus as $pneu) {
            echo "<tr>";
            echo "<td>{$pneu['id']}</td>";
            echo "<td>{$pneu['numero_serie']}</td>";
            echo "<td>{$pneu['marca']}</td>";
            echo "<td>{$pneu['modelo']}</td>";
            echo "<td>{$pneu['status_nome']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<h3>❌ Nenhum pneu disponível encontrado!</h3>";
        echo "<p>Possíveis causas:</p>";
        echo "<ul>";
        echo "<li>Todos os pneus estão instalados em veículos</li>";
        echo "<li>Os pneus não têm status_id 2 (disponível) ou 5 (novo)</li>";
        echo "<li>Problema na consulta SQL</li>";
        echo "</ul>";
    }
    
    echo "<h3>6. Verificando tabela estoque_pneus...</h3>";
    
    // Verificar se a tabela estoque_pneus existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'estoque_pneus'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Tabela estoque_pneus existe<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM estoque_pneus");
        $total_estoque = $stmt->fetchColumn();
        echo "Total de registros no estoque: $total_estoque<br>";
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM estoque_pneus WHERE disponivel = 1");
        $disponiveis_estoque = $stmt->fetchColumn();
        echo "Pneus marcados como disponíveis no estoque: $disponiveis_estoque<br>";
    } else {
        echo "❌ Tabela estoque_pneus não existe<br>";
    }
    
} catch (Exception $e) {
    echo "<h3>❌ Erro durante a verificação:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}
?> 