<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

try {
    $pdo = getConnection();
    $sql = "SELECT id, id_cavalo, id_carreta FROM veiculos";
    $stmt = $pdo->query($sql);
    $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalEixosCorrigidos = 0;
    foreach ($veiculos as $veiculo) {
        $veiculo_id = $veiculo['id'];
        $id_cavalo = $veiculo['id_cavalo'];
        $id_carreta = $veiculo['id_carreta'];

        // Buscar dados do cavalo
        $sqlCavalo = "SELECT eixos, tracao FROM tipos_cavalos WHERE id = ?";
        $stmtCavalo = $pdo->prepare($sqlCavalo);
        $stmtCavalo->execute([$id_cavalo]);
        $cavalo = $stmtCavalo->fetch(PDO::FETCH_ASSOC);

        // Buscar dados da carreta
        $sqlCarreta = "SELECT nro_eixos FROM tipos_carretas WHERE id = ?";
        $stmtCarreta = $pdo->prepare($sqlCarreta);
        $stmtCarreta->execute([$id_carreta]);
        $carreta = $stmtCarreta->fetch(PDO::FETCH_ASSOC);

        if (!$cavalo || !$carreta) {
            continue;
        }

        // Apagar eixos antigos
        $pdo->prepare("DELETE FROM eixos WHERE veiculo_id = ?")->execute([$veiculo_id]);

        $eixos = [];
        // Dianteiro
        $eixos[] = ['posicao' => 'dianteiro', 'quantidade_pneus' => 2];
        // Tração
        $nro_tracoes = (int) filter_var($cavalo['tracao'], FILTER_SANITIZE_NUMBER_INT);
        for ($i = 1; $i <= $nro_tracoes; $i++) {
            $eixos[] = ['posicao' => "tracao_$i", 'quantidade_pneus' => 4];
        }
        // Apoio
        if ($cavalo['eixos'] >= 3) {
            $eixos[] = ['posicao' => 'apoio', 'quantidade_pneus' => 2];
        }
        // Carreta
        for ($i = 1; $i <= $carreta['nro_eixos']; $i++) {
            $eixos[] = ['posicao' => "carreta_traseiro_$i", 'quantidade_pneus' => 4]; // sempre 4!
        }
        // Inserir eixos
        $sqlInsert = "INSERT INTO eixos (veiculo_id, posicao, quantidade_pneus) VALUES (?, ?, ?)";
        $stmtInsert = $pdo->prepare($sqlInsert);
        foreach ($eixos as $eixo) {
            $stmtInsert->execute([$veiculo_id, $eixo['posicao'], $eixo['quantidade_pneus']]);
            $totalEixosCorrigidos++;
        }
    }

    // Corrigir eixos de carreta já existentes com quantidade_pneus diferente de 4
    $sqlUpdate = "UPDATE eixos SET quantidade_pneus = 4 WHERE posicao LIKE 'carreta_traseiro%' AND quantidade_pneus != 4";
    $pdo->exec($sqlUpdate);

    echo "Eixos atualizados com sucesso. Total de eixos inseridos/corrigidos: $totalEixosCorrigidos";
} catch (Exception $e) {
    echo "Erro ao atualizar eixos: " . $e->getMessage();
} 