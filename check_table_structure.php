<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=sistema_frotas", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if disponibilidades_motoristas exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'disponibilidades_motoristas'");
    $disponibilidadesMotoristas = $stmt->fetch();
    
    echo "Table disponibilidades_motoristas exists: " . ($disponibilidadesMotoristas ? "Yes" : "No") . "\n";
    
    // Check if disponibilidades exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'disponibilidades'");
    $disponibilidades = $stmt->fetch();
    
    echo "Table disponibilidades exists: " . ($disponibilidades ? "Yes" : "No") . "\n";
    
    if ($disponibilidades) {
        $stmt = $pdo->query("DESCRIBE disponibilidades");
        echo "\nDisponibilidades table structure:\n";
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            print_r($row);
        }
    }
    
    echo "\nMotoristas foreign keys:\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = 'sistema_frotas'
        AND TABLE_NAME = 'motoristas'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 