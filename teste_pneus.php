<?php
echo "<h2>Teste de Pneus Disponíveis</h2>";

try {
    $pdo = new PDO("mysql:host=localhost;port=3307;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h3>1. Total de pneus na empresa 1:</h3>";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pneus WHERE empresa_id = ?");
    $stmt->execute([1]);
    $total = $stmt->fetchColumn();
    echo "Total: $total pneus<br>";
    
    echo "<h3>2. Pneus por status:</h3>";
    $stmt = $pdo->query("SELECT p.status_id, COUNT(*) as total FROM pneus p WHERE p.empresa_id = 1 GROUP BY p.status_id ORDER BY p.status_id");
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuses as $status) {
        echo "Status ID {$status['status_id']}: {$status['total']} pneus<br>";
    }
    
    echo "<h3>3. Pneus instalados:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) FROM instalacoes_pneus WHERE data_remocao IS NULL");
    $instalados = $stmt->fetchColumn();
    echo "Instalados: $instalados pneus<br>";
    
    echo "<h3>4. Pneus disponíveis (consulta corrigida):</h3>";
    $sql = "SELECT p.* FROM pneus p 
            WHERE p.empresa_id = 1 
            AND p.status_id IN (2, 5)
            AND p.id NOT IN (
                SELECT pneu_id FROM instalacoes_pneus WHERE data_remocao IS NULL
            )";
    
    $stmt = $pdo->query($sql);
    $disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Disponíveis: " . count($disponiveis) . " pneus<br>";
    
    if (count($disponiveis) > 0) {
        echo "<h3>5. Lista de pneus disponíveis:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Série</th><th>Marca</th><th>Status ID</th></tr>";
        foreach ($disponiveis as $pneu) {
            echo "<tr>";
            echo "<td>{$pneu['id']}</td>";
            echo "<td>{$pneu['numero_serie']}</td>";
            echo "<td>{$pneu['marca']}</td>";
            echo "<td>{$pneu['status_id']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?> 