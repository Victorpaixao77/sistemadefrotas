<?php
/**
 * API Diário de Bordo - App Android Motoristas
 * GET: linha do tempo unificada das atividades do motorista (jornada, abastecimentos,
 *      ocorrências, entregas/POD, pânico, checklists), ordenada por data/hora.
 * Parâmetro opcional: dias (padrão 7), limite (padrão 80).
 */

require_once __DIR__ . '/../config.php';
require_motorista_token();

$motorista_id = get_motorista_id();
$empresa_id = get_empresa_id();

try {
    $conn = getConnection();
    $dias = min(60, max(1, (int) ($_GET['dias'] ?? 7)));
    $limite = min(200, max(1, (int) ($_GET['limite'] ?? 80)));

    // Cada SELECT devolve as mesmas colunas: tipo, titulo, descricao, data_hora.
    $sql = "
        SELECT 'jornada' AS tipo, tipo AS titulo, '' AS descricao, data_hora
          FROM jornada_motorista
         WHERE empresa_id = :e1 AND motorista_id = :m1
           AND data_hora >= DATE_SUB(NOW(), INTERVAL :d1 DAY)
        UNION ALL
        SELECT 'ocorrencia' AS tipo, tipo AS titulo, COALESCE(descricao, '') AS descricao, data_hora
          FROM ocorrencias_viagem
         WHERE empresa_id = :e2 AND motorista_id = :m2
           AND data_hora >= DATE_SUB(NOW(), INTERVAL :d2 DAY)
        UNION ALL
        SELECT 'entrega' AS tipo, 'Comprovante de entrega' AS titulo, COALESCE(recebedor_nome, '') AS descricao, data_hora
          FROM comprovantes_entrega
         WHERE empresa_id = :e3 AND motorista_id = :m3
           AND data_hora >= DATE_SUB(NOW(), INTERVAL :d3 DAY)
        UNION ALL
        SELECT 'panico' AS tipo, 'Pânico acionado' AS titulo, '' AS descricao, data_hora
          FROM panico_alertas
         WHERE empresa_id = :e4 AND motorista_id = :m4
           AND data_hora >= DATE_SUB(NOW(), INTERVAL :d4 DAY)
        UNION ALL
        SELECT 'checklist' AS tipo, 'Checklist de viagem' AS titulo, '' AS descricao, data_checklist AS data_hora
          FROM checklist_viagem
         WHERE empresa_id = :e5 AND motorista_id = :m5
           AND data_checklist >= DATE_SUB(NOW(), INTERVAL :d5 DAY)
        UNION ALL
        SELECT 'abastecimento' AS tipo, COALESCE(posto, 'Abastecimento') AS titulo,
               CONCAT('R$ ', FORMAT(COALESCE(valor_total, 0), 2)) AS descricao, data_abastecimento AS data_hora
          FROM abastecimentos
         WHERE empresa_id = :e6 AND motorista_id = :m6
           AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL :d6 DAY)
        UNION ALL
        SELECT 'pedagio' AS tipo, COALESCE(praca, 'Pedágio') AS titulo,
               CONCAT('R$ ', FORMAT(COALESCE(valor, 0), 2)) AS descricao, data_hora
          FROM pedagios_viagem
         WHERE empresa_id = :e7 AND motorista_id = :m7
           AND data_hora >= DATE_SUB(NOW(), INTERVAL :d7 DAY)
        ORDER BY data_hora DESC
        LIMIT " . $limite;

    $stmt = $conn->prepare($sql);
    $params = [];
    for ($i = 1; $i <= 7; $i++) {
        $params[":e$i"] = $empresa_id;
        $params[":m$i"] = $motorista_id;
        $params[":d$i"] = $dias;
    }
    $stmt->execute($params);

    api_success(['eventos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    error_log('diario.php: ' . $e->getMessage());
    api_error('Erro ao carregar o diário de bordo.', 500);
}
