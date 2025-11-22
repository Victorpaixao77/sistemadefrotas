<?php
require_once 'config/database.php';

try {
    $pdo = getDB();
    $columns = $pdo->query("SHOW COLUMNS FROM despesas_viagem LIKE 'arla'")->fetchAll();

    if (count($columns) === 0) {
        echo "A coluna 'arla' já foi renomeada anteriormente.\n";
    } else {
        echo "Renomeando coluna 'arla' para 'descarga' na tabela despesas_viagem...\n\n";

        $sql = file_get_contents(__DIR__ . '/database/renomear_arla_para_descarga_despesas_viagem.sql');

        if ($sql === false) {
            throw new Exception('Não foi possível ler o arquivo SQL.');
        }

        $pdo->exec($sql);

        echo "✅ Coluna renomeada com sucesso!\n";
    }
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

