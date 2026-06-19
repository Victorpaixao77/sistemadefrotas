<?php

require_once __DIR__ . '/../includes/lucratividade_api_base.php';



try {

    $conn = getConnection();

    $rotaPeriod = lucra_rota_period_sql('r');



    // Classificação por distância (evita JOIN/collation com empresa_clientes)

    $sql = "

    SELECT

        CASE

            WHEN COALESCE(r.distancia_km, 0) <= 100 THEN 'Frete Local'

            WHEN COALESCE(r.distancia_km, 0) <= 500 THEN 'Frete Regional'

            ELSE 'Frete Interestadual'

        END AS tipo_frete,

        COALESCE(SUM(r.frete), 0) AS total_frete,

        COALESCE(SUM(r.comissao), 0) AS total_comissao,

        COUNT(*) AS total_rotas

    FROM rotas r

    WHERE r.empresa_id = ?

      AND {$rotaPeriod}

    GROUP BY

        CASE

            WHEN COALESCE(r.distancia_km, 0) <= 100 THEN 'Frete Local'

            WHEN COALESCE(r.distancia_km, 0) <= 500 THEN 'Frete Regional'

            ELSE 'Frete Interestadual'

        END

    ORDER BY total_frete DESC

    ";



    $stmt = $conn->prepare($sql);

    $stmt->execute([$empresa_id, $mes, $ano]);

    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];



    $labels = [];

    $margens = [];

    foreach ($resultados as $row) {

        $total_frete = (float) ($row['total_frete'] ?? 0);

        if ($total_frete <= 0) {

            continue;

        }

        $lucro_bruto = $total_frete - (float) ($row['total_comissao'] ?? 0);

        $labels[] = (string) $row['tipo_frete'];

        $margens[] = round(($lucro_bruto / $total_frete) * 100, 1);

    }



    echo json_encode([

        'success' => true,

        'labels' => $labels,

        'margens' => $margens,

        'empty' => empty($labels),

    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    error_log('margem_tipo_frete: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Não foi possível carregar margens.', 'labels' => [], 'margens' => []], JSON_UNESCAPED_UNICODE);

}

