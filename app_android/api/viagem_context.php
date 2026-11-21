<?php
/**
 * Contexto do Modo Viagem: POIs (postos/pedágios históricos), cercas e destino.
 * GET ?rota_id=X
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    api_error('Método não permitido.', 405);
}

$rota_id = (int) ($_GET['rota_id'] ?? 0);
if ($rota_id < 1) {
    api_error('Informe rota_id.');
}

try {
    $conn = getConnection();

    $stmt = $conn->prepare('
        SELECT r.id, r.cidade_destino_id, c2.nome AS cidade_destino_nome
        FROM rotas r
        LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
        WHERE r.id = :id AND r.empresa_id = :e AND r.motorista_id = :m
        LIMIT 1
    ');
    $stmt->execute([':id' => $rota_id, ':e' => $empresa_id, ':m' => $motorista_id]);
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rota) {
        api_error('Rota não encontrada.', 404);
    }

    $destino_lat = null;
    $destino_lng = null;

    // Postos conhecidos: abastecimentos com GPS da empresa (últimos 12 meses).
    $postos = [];
    try {
        $sp = $conn->prepare('
            SELECT posto AS nome, AVG(latitude) AS lat, AVG(longitude) AS lng, COUNT(*) AS vezes
            FROM abastecimentos
            WHERE empresa_id = :e AND latitude IS NOT NULL AND longitude IS NOT NULL
              AND posto IS NOT NULL AND TRIM(posto) <> ""
              AND data_abastecimento >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY ROUND(latitude, 3), ROUND(longitude, 3), posto
            HAVING vezes >= 1
            ORDER BY vezes DESC
            LIMIT 80
        ');
        $sp->execute([':e' => $empresa_id]);
        foreach ($sp->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $postos[] = [
                'nome' => $row['nome'],
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
            ];
        }
    } catch (PDOException $e) {
        // latitude em abastecimentos pode não existir em instalações antigas
    }

    // Pedágios históricos com GPS.
    $pedagios = [];
    try {
        $st = $conn->prepare('
            SELECT praca AS nome, AVG(latitude) AS lat, AVG(longitude) AS lng, COUNT(*) AS vezes
            FROM pedagios_viagem
            WHERE empresa_id = :e AND motorista_id = :m
              AND latitude IS NOT NULL AND longitude IS NOT NULL
              AND data_hora >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY ROUND(latitude, 3), ROUND(longitude, 3), COALESCE(praca, "")
            ORDER BY vezes DESC
            LIMIT 60
        ');
        $st->execute([':e' => $empresa_id, ':m' => $motorista_id]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pedagios[] = [
                'nome' => $row['nome'] ?: 'Pedágio',
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
            ];
        }
    } catch (PDOException $e) {
    }

    // Cercas eletrônicas ativas.
    $cercas = [];
    try {
        $sc = $conn->prepare('
            SELECT id, nome, latitude AS lat, longitude AS lng, raio_metros
            FROM gps_cercas
            WHERE empresa_id = :e AND ativo = 1
            ORDER BY nome
            LIMIT 50
        ');
        $sc->execute([':e' => $empresa_id]);
        foreach ($sc->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cercas[] = [
                'id' => (int) $row['id'],
                'nome' => $row['nome'],
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lng'],
                'raio_metros' => (int) $row['raio_metros'],
            ];
        }
    } catch (PDOException $e) {
    }

    api_success([
        'rota_id' => $rota_id,
        'destino' => $rota['cidade_destino_nome'],
        'destino_lat' => $destino_lat,
        'destino_lng' => $destino_lng,
        'postos' => $postos,
        'pedagios' => $pedagios,
        'cercas' => $cercas,
    ]);
} catch (PDOException $e) {
    error_log('viagem_context.php: ' . $e->getMessage());
    api_error('Erro ao carregar contexto da viagem.', 500);
}
