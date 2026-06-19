<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

try {
    $conn = getConnection();
    $tables = ['rotas', 'abastecimentos', 'manutencoes', 'despesas_fixas', 'despesas_viagem'];

    foreach ($tables as $table) {
        echo "\n========== TABELA: $table ==========\n";
        $result = $conn->query("SHOW INDEXES FROM $table");
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "Nenhum índice encontrado\n";
        } else {
            foreach ($rows as $row) {
                echo "Key: " . $row['Key_name'] . " | Coluna: " . $row['Column_name'] . "\n";
            }
        }
    }
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
