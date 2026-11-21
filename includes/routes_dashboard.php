<?php
/**
 * Dashboard de rotas (KPIs + gráficos) — compartilhado entre API e SSR.
 */

if (!function_exists('routes_normalize_month_year')) {
    function routes_normalize_month_year($month, $year): array
    {
        $month = (int) $month;
        $year = (int) $year;
        if ($month < 1 || $month > 12) {
            $month = (int) date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        return [$month, $year];
    }
}

if (!function_exists('routes_build_kpi_historico')) {
    function routes_build_kpi_historico(PDO $conn, int $empresa_id, int $month, int $year): array
    {
        $refDate = sprintf('%04d-%02d-01', $year, $month);
        $histIni = date('Y-m-d', strtotime($refDate . ' -5 months'));
        $histFim = date('Y-m-d', strtotime($refDate . ' +1 month'));
        $sql_kpi_hist = "SELECT
                DATE_FORMAT(r.data_rota, '%Y-%m') AS mes,
                COUNT(*) AS total_rotas,
                SUM(CASE WHEN r.no_prazo IS NOT NULL THEN 1 ELSE 0 END) AS rotas_concluidas,
                COALESCE(SUM(r.distancia_km), 0) AS distancia_total,
                COALESCE(SUM(r.frete), 0) AS frete_total,
                SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) AS rotas_no_prazo,
                SUM(CASE WHEN r.no_prazo = 0 THEN 1 ELSE 0 END) AS rotas_atrasadas,
                COALESCE(AVG(r.eficiencia_viagem), 0) AS media_eficiencia,
                COALESCE(AVG(r.percentual_vazio), 0) AS percentual_vazio
            FROM rotas r
            WHERE r.empresa_id = :empresa_id
              AND r.data_rota >= :hist_ini
              AND r.data_rota < :hist_fim
            GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m')
            ORDER BY mes";
        $stmt_kpi_hist = $conn->prepare($sql_kpi_hist);
        $stmt_kpi_hist->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_kpi_hist->bindValue(':hist_ini', $histIni);
        $stmt_kpi_hist->bindValue(':hist_fim', $histFim);
        $stmt_kpi_hist->execute();
        $kpi_hist_rows = $stmt_kpi_hist->fetchAll(PDO::FETCH_ASSOC);
        $kpi_hist_map = [];
        foreach ($kpi_hist_rows as $row) {
            $kpi_hist_map[(string) $row['mes']] = $row;
        }
        $kpi_historico = [];
        for ($i = 5; $i >= 0; $i--) {
            $mesKey = date('Y-m', strtotime($refDate . " -$i months"));
            $row = $kpi_hist_map[$mesKey] ?? null;
            $kpi_historico[] = [
                'mes' => $mesKey,
                'total_rotas' => (int) ($row['total_rotas'] ?? 0),
                'rotas_concluidas' => (int) ($row['rotas_concluidas'] ?? 0),
                'distancia_total' => (float) ($row['distancia_total'] ?? 0),
                'frete_total' => (float) ($row['frete_total'] ?? 0),
                'rotas_no_prazo' => (int) ($row['rotas_no_prazo'] ?? 0),
                'rotas_atrasadas' => (int) ($row['rotas_atrasadas'] ?? 0),
                'media_eficiencia' => (float) ($row['media_eficiencia'] ?? 0),
                'percentual_vazio' => (float) ($row['percentual_vazio'] ?? 0),
            ];
        }
        return $kpi_historico;
    }
}

if (!function_exists('routes_fetch_dashboard_kpis')) {
    /** KPIs do mês (1 query agregada + histórico para sparklines). */
    function routes_fetch_dashboard_kpis(PDO $conn, int $empresa_id, $month = null, $year = null): array
    {
        [$month, $year] = routes_normalize_month_year($month, $year);
        $date_condition = 'AND MONTH(r.data_rota) = :month AND YEAR(r.data_rota) = :year';

        $sql = "SELECT
            COUNT(*) AS total_rotas,
            SUM(CASE WHEN r.no_prazo IS NOT NULL THEN 1 ELSE 0 END) AS rotas_concluidas,
            COALESCE(SUM(r.distancia_km), 0) AS distancia_total,
            COALESCE(SUM(r.frete), 0) AS frete_total,
            SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) AS rotas_no_prazo,
            SUM(CASE WHEN r.no_prazo = 0 THEN 1 ELSE 0 END) AS rotas_atrasadas,
            COALESCE(AVG(CASE WHEN r.eficiencia_viagem IS NOT NULL THEN r.eficiencia_viagem END), 0) AS media_eficiencia,
            COALESCE(AVG(CASE WHEN r.percentual_vazio IS NOT NULL THEN r.percentual_vazio END), 0) AS percentual_vazio
        FROM rotas r
        WHERE r.empresa_id = :empresa_id $date_condition";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_rotas' => (int) ($row['total_rotas'] ?? 0),
            'rotas_concluidas' => (int) ($row['rotas_concluidas'] ?? 0),
            'distancia_total' => (float) ($row['distancia_total'] ?? 0),
            'frete_total' => (float) ($row['frete_total'] ?? 0),
            'rotas_no_prazo' => (int) ($row['rotas_no_prazo'] ?? 0),
            'rotas_atrasadas' => (int) ($row['rotas_atrasadas'] ?? 0),
            'media_eficiencia' => (float) ($row['media_eficiencia'] ?? 0),
            'percentual_vazio' => (float) ($row['percentual_vazio'] ?? 0),
            'kpi_historico' => routes_build_kpi_historico($conn, $empresa_id, $month, $year),
            'month' => $month,
            'year' => $year,
        ];
    }
}

if (!function_exists('getDashboardData')) {
    function getDashboardData($conn, $empresa_id, $month = null, $year = null)
    {
        try {
            [$month, $year] = routes_normalize_month_year($month, $year);
            $kpis = routes_fetch_dashboard_kpis($conn, $empresa_id, $month, $year);
            $date_condition = 'AND MONTH(r.data_rota) = :month AND YEAR(r.data_rota) = :year';

            $total_rotas = $kpis['total_rotas'];
            $rotas_concluidas = $kpis['rotas_concluidas'];
            $distancia_total = $kpis['distancia_total'];
            $frete_total = $kpis['frete_total'];
            $rotas_no_prazo = $kpis['rotas_no_prazo'];
            $rotas_atrasadas = $kpis['rotas_atrasadas'];
            $media_eficiencia = $kpis['media_eficiencia'];
            $percentual_vazio = $kpis['percentual_vazio'];
            $kpi_historico = $kpis['kpi_historico'];

            $sql_distancia_motorista = "SELECT m.nome, COALESCE(SUM(r.distancia_km), 0) as total
                                  FROM rotas r
                                  LEFT JOIN motoristas m ON r.motorista_id = m.id
                                  WHERE r.empresa_id = :empresa_id $date_condition
                                  GROUP BY m.id, m.nome
                                  ORDER BY total DESC
                                  LIMIT 5";
            $stmt_distancia_motorista = $conn->prepare($sql_distancia_motorista);
            $stmt_distancia_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_distancia_motorista->bindParam(':month', $month, PDO::PARAM_INT);
            $stmt_distancia_motorista->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt_distancia_motorista->execute();
            $distancia_motorista = $stmt_distancia_motorista->fetchAll(PDO::FETCH_ASSOC);

            $sql_eficiencia_motorista = "SELECT m.nome, COALESCE(AVG(r.eficiencia_viagem), 0) as media
                                   FROM rotas r
                                   LEFT JOIN motoristas m ON r.motorista_id = m.id
                                   WHERE r.empresa_id = :empresa_id $date_condition
                                   GROUP BY m.id, m.nome
                                   ORDER BY media DESC
                                   LIMIT 5";
            $stmt_eficiencia_motorista = $conn->prepare($sql_eficiencia_motorista);
            $stmt_eficiencia_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_eficiencia_motorista->bindParam(':month', $month, PDO::PARAM_INT);
            $stmt_eficiencia_motorista->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt_eficiencia_motorista->execute();
            $eficiencia_motorista = $stmt_eficiencia_motorista->fetchAll(PDO::FETCH_ASSOC);

            $sql_rotas_prazo = "SELECT m.nome,
                           COUNT(*) as total,
                           SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as no_prazo
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id $date_condition
                           GROUP BY m.id, m.nome
                           ORDER BY total DESC
                           LIMIT 5";
            $stmt_rotas_prazo = $conn->prepare($sql_rotas_prazo);
            $stmt_rotas_prazo->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_rotas_prazo->bindParam(':month', $month, PDO::PARAM_INT);
            $stmt_rotas_prazo->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt_rotas_prazo->execute();
            $rotas_prazo = $stmt_rotas_prazo->fetchAll(PDO::FETCH_ASSOC);

            $sql_frete_motorista = "SELECT m.nome, COALESCE(SUM(r.frete), 0) as total
                               FROM rotas r
                               LEFT JOIN motoristas m ON r.motorista_id = m.id
                               WHERE r.empresa_id = :empresa_id $date_condition
                               GROUP BY m.id, m.nome
                               ORDER BY total DESC
                               LIMIT 5";
            $stmt_frete_motorista = $conn->prepare($sql_frete_motorista);
            $stmt_frete_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_frete_motorista->bindParam(':month', $month, PDO::PARAM_INT);
            $stmt_frete_motorista->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt_frete_motorista->execute();
            $frete_motorista = $stmt_frete_motorista->fetchAll(PDO::FETCH_ASSOC);

            $sql_evolucao_km = "SELECT
                            DATE_FORMAT(r.data_rota, '%Y-%m') as mes,
                            m.nome,
                            SUM(r.distancia_km) as total
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id
                           AND r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m'), m.id, m.nome
                           ORDER BY mes, m.nome";
            $stmt_evolucao_km = $conn->prepare($sql_evolucao_km);
            $stmt_evolucao_km->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_evolucao_km->execute();
            $evolucao_km = $stmt_evolucao_km->fetchAll(PDO::FETCH_ASSOC);

            $sql_indicadores = "SELECT
                           m.nome,
                           AVG(r.eficiencia_viagem) as eficiencia,
                           AVG(r.percentual_vazio) as km_vazio,
                           SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as pontualidade,
                           COUNT(*) as total_rotas,
                           SUM(r.frete) as total_frete
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id $date_condition
                           GROUP BY m.id, m.nome
                           ORDER BY total_rotas DESC
                           LIMIT 5";
            $stmt_indicadores = $conn->prepare($sql_indicadores);
            $stmt_indicadores->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt_indicadores->bindParam(':month', $month, PDO::PARAM_INT);
            $stmt_indicadores->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt_indicadores->execute();
            $indicadores = $stmt_indicadores->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'total_rotas' => $total_rotas,
                'rotas_concluidas' => $rotas_concluidas,
                'distancia_total' => $distancia_total,
                'frete_total' => $frete_total,
                'rotas_no_prazo' => $rotas_no_prazo,
                'rotas_atrasadas' => $rotas_atrasadas,
                'media_eficiencia' => $media_eficiencia,
                'percentual_vazio' => $percentual_vazio,
                'kpi_historico' => $kpi_historico,
                'distancia_motorista' => [
                    'labels' => array_column($distancia_motorista, 'nome'),
                    'values' => array_column($distancia_motorista, 'total'),
                ],
                'eficiencia_motorista' => [
                    'labels' => array_column($eficiencia_motorista, 'nome'),
                    'values' => array_column($eficiencia_motorista, 'media'),
                ],
                'rotas_prazo' => [
                    'labels' => array_column($rotas_prazo, 'nome'),
                    'values' => array_column($rotas_prazo, 'no_prazo'),
                ],
                'frete_motorista' => [
                    'labels' => array_column($frete_motorista, 'nome'),
                    'values' => array_column($frete_motorista, 'total'),
                ],
                'evolucao_km' => [
                    'labels' => array_unique(array_column($evolucao_km, 'mes')),
                    'datasets' => [],
                ],
                'indicadores_motorista' => [
                    'labels' => ['Eficiência', 'KM Vazio', 'Pontualidade', 'Total Rotas', 'Frete'],
                    'datasets' => [],
                ],
            ];

            $motoristas = array_unique(array_column($evolucao_km, 'nome'));
            $meses = array_unique(array_column($evolucao_km, 'mes'));
            $cores = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];

            foreach ($motoristas as $index => $motorista) {
                $dados = [];
                foreach ($meses as $mes) {
                    $valor = 0;
                    foreach ($evolucao_km as $registro) {
                        if ($registro['nome'] === $motorista && $registro['mes'] === $mes) {
                            $valor = $registro['total'];
                            break;
                        }
                    }
                    $dados[] = $valor;
                }
                $response['evolucao_km']['datasets'][] = [
                    'label' => $motorista,
                    'data' => $dados,
                    'color' => $cores[$index % count($cores)],
                ];
            }

            foreach ($indicadores as $index => $motorista) {
                $response['indicadores_motorista']['datasets'][] = [
                    'label' => $motorista['nome'],
                    'data' => [
                        $motorista['eficiencia'],
                        $motorista['km_vazio'],
                        $motorista['pontualidade'],
                        $motorista['total_rotas'],
                        $motorista['total_frete'],
                    ],
                    'backgroundColor' => 'rgba(' . implode(',', sscanf($cores[$index % count($cores)], '#%02x%02x%02x')) . ',0.2)',
                    'borderColor' => $cores[$index % count($cores)],
                ];
            }

            return $response;
        } catch (PDOException $e) {
            error_log('Erro ao buscar dados do dashboard: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Erro ao buscar dados do dashboard: ',
            ];
        }
    }
}
