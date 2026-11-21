<?php
/**
 * Cron: atualiza veiculos.km_atual a partir do maior KM real já registrado
 * (rotas.km_chegada e abastecimentos.km_atual). O KM só aumenta — nunca regride.
 * Fecha o ciclo da manutenção preditiva com dado que já existe.
 *
 * Registrar (Windows Task Scheduler / cron), 1x por dia:
 *   C:\xampp\php\php.exe C:\xampp\htdocs\sistema-frotas\cron\atualizar_km_veiculos.php
 */

if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../includes/config.php';
    configure_session();
    session_start();
    if (empty($_SESSION['loggedin'])) {
        http_response_code(403);
        exit('Somente CLI.');
    }
}

require_once __DIR__ . '/../includes/db_connect.php';

/** Atualiza o km_atual dos veículos (opcionalmente de uma empresa). Retorna nº de linhas afetadas. */
function sf_atualizar_km_veiculos(PDO $conn, ?int $empresa_id = null): int
{
    $where = 'WHERE 1=1';
    $params = [];
    if ($empresa_id !== null) {
        $where = 'WHERE v.empresa_id = :e';
        $params[':e'] = $empresa_id;
    }
    $sql = "UPDATE veiculos v
        SET v.km_atual = GREATEST(
            COALESCE(v.km_atual, 0),
            COALESCE((SELECT MAX(r.km_chegada) FROM rotas r WHERE r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND r.km_chegada > 0), 0),
            COALESCE((SELECT MAX(a.km_atual) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = v.empresa_id AND a.km_atual > 0), 0)
        )
        $where";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

// Execução direta (CLI ou URL deste arquivo) roda o update global.
// Se este arquivo for apenas incluído por outro (API), o bloco não roda.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? __FILE__) === basename(__FILE__)) {
    try {
        $conn = getConnection();
        $n = sf_atualizar_km_veiculos($conn);
        $msg = "KM de veículos atualizado. Linhas afetadas: $n.";
        if (PHP_SAPI === 'cli') { echo $msg . PHP_EOL; }
        error_log($msg);
    } catch (Throwable $e) {
        error_log('cron atualizar_km_veiculos: ' . $e->getMessage());
        if (PHP_SAPI === 'cli') { echo 'Erro: ' . $e->getMessage() . PHP_EOL; }
        exit(1);
    }
}
