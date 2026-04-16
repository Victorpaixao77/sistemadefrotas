<?php
/**
 * KPIs e score simplificado para o mapa da frota (inteligência no servidor).
 * Depende de gps_ultima_posicao, gps_logs e gps_alertas_operacionais.
 */

if (!function_exists('gps_inteligencia_kpis_mapa')) {
    /**
     * @return array{
     *   veiculos_sinal_recente: int,
     *   veiculos_sem_sinal_30m: int,
     *   pontos_gps_24h: int,
     *   alertas_24h: int,
     *   alertas_por_tipo: array<string, int>
     * }
     */
    function gps_inteligencia_kpis_mapa(PDO $conn, int $empresa_id): array
    {
        $out = [
            'veiculos_sinal_recente' => 0,
            'veiculos_sem_sinal_30m' => 0,
            'pontos_gps_24h' => 0,
            'alertas_24h' => 0,
            'alertas_por_tipo' => [],
        ];
        if ($empresa_id <= 0) {
            return $out;
        }

        try {
            $st = $conn->prepare('
                SELECT
                    SUM(CASE WHEN data_hora >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) THEN 1 ELSE 0 END) AS ok15,
                    SUM(CASE WHEN data_hora < DATE_SUB(NOW(), INTERVAL 30 MINUTE) THEN 1 ELSE 0 END) AS stale30
                FROM gps_ultima_posicao
                WHERE empresa_id = :eid
            ');
            $st->execute([':eid' => $empresa_id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $out['veiculos_sinal_recente'] = (int) ($row['ok15'] ?? 0);
                $out['veiculos_sem_sinal_30m'] = (int) ($row['stale30'] ?? 0);
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'gps_ultima_posicao') === false) {
                error_log('gps_inteligencia_kpis ultima: ' . $e->getMessage());
            }
        }

        try {
            $st = $conn->prepare('
                SELECT COUNT(*) FROM gps_logs
                WHERE empresa_id = :eid AND data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ');
            $st->execute([':eid' => $empresa_id]);
            $out['pontos_gps_24h'] = (int) $st->fetchColumn();
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'gps_logs') === false) {
                error_log('gps_inteligencia_kpis logs: ' . $e->getMessage());
            }
        }

        try {
            $st = $conn->prepare('
                SELECT tipo, COUNT(*) AS c
                FROM gps_alertas_operacionais
                WHERE empresa_id = :eid AND data_hora >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY tipo
            ');
            $st->execute([':eid' => $empresa_id]);
            $porTipo = [];
            $total = 0;
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $c = (int) $r['c'];
                $porTipo[(string) $r['tipo']] = $c;
                $total += $c;
            }
            $out['alertas_por_tipo'] = $porTipo;
            $out['alertas_24h'] = $total;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'gps_alertas_operacionais') === false) {
                error_log('gps_inteligencia_kpis alertas: ' . $e->getMessage());
            }
        }

        return $out;
    }
}

if (!function_exists('gps_inteligencia_score_motoristas')) {
    /**
     * Score 0–100 nos últimos N dias com base em alertas operacionais (heurística simples).
     *
     * @return list<array{motorista_id: int, nome: string, score: int, alertas: int, detalhe: string}>
     */
    function gps_inteligencia_score_motoristas(PDO $conn, int $empresa_id, int $dias = 7, int $limite = 12): array
    {
        if ($empresa_id <= 0) {
            return [];
        }
        $limite = max(1, min(30, $limite));
        $dias = max(1, min(30, $dias));

        try {
            $st = $conn->prepare('
                SELECT a.motorista_id,
                       COALESCE(m.nome, CONCAT(\'ID \', a.motorista_id)) AS nome,
                       SUM(CASE WHEN a.tipo = \'velocidade_alta\' THEN 1 ELSE 0 END) AS n_vel,
                       SUM(CASE WHEN a.tipo = \'velocidade_impossivel\' THEN 1 ELSE 0 END) AS n_vel_imp,
                       SUM(CASE WHEN a.tipo = \'salto_suspeito\' THEN 1 ELSE 0 END) AS n_salto,
                       SUM(CASE WHEN a.tipo = \'gps_mock\' THEN 1 ELSE 0 END) AS n_mock,
                       SUM(CASE WHEN a.tipo IN (\'bateria_baixa\', \'bateria_critica\') THEN 1 ELSE 0 END) AS n_bat,
                       SUM(CASE WHEN a.tipo = \'perda_sinal_gps\' THEN 1 ELSE 0 END) AS n_sinal,
                       SUM(CASE WHEN a.tipo = \'ignicao_parado\' THEN 1 ELSE 0 END) AS n_ign,
                       COUNT(*) AS total
                FROM gps_alertas_operacionais a
                LEFT JOIN motoristas m ON m.id = a.motorista_id AND m.empresa_id = a.empresa_id
                WHERE a.empresa_id = :eid
                  AND a.data_hora >= DATE_SUB(NOW(), INTERVAL ' . (int) $dias . ' DAY)
                GROUP BY a.motorista_id, m.nome
                HAVING total > 0
                ORDER BY total DESC
                LIMIT ' . (int) $limite . '
            ');
            $st->execute([':eid' => $empresa_id]);
            $rows = [];
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                $pen = 0;
                $pen += (int) $r['n_vel'] * 4;
                $pen += (int) $r['n_vel_imp'] * 10;
                $pen += (int) $r['n_salto'] * 5;
                $pen += (int) $r['n_mock'] * 15;
                $pen += (int) $r['n_bat'] * 1;
                $pen += (int) $r['n_sinal'] * 2;
                $pen += (int) $r['n_ign'] * 3;
                $score = max(0, 100 - min(95, $pen));
                $rows[] = [
                    'motorista_id' => (int) $r['motorista_id'],
                    'nome' => (string) $r['nome'],
                    'score' => $score,
                    'alertas' => (int) $r['total'],
                    'detalhe' => 'V:' . (int) $r['n_vel'] . ' Mock:' . (int) $r['n_mock'] . ' …',
                ];
            }

            return $rows;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'gps_alertas_operacionais') === false) {
                error_log('gps_inteligencia_score: ' . $e->getMessage());
            }

            return [];
        }
    }
}
