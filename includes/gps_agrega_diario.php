<?php
/**
 * Agrega gps_logs de um dia por veículo em gps_logs_resumido e remove o bruto desse dia.
 * Chamado pelo cron (ex.: 02:00) para D-1.
 */

require_once __DIR__ . '/gps_geofence.php';

if (!function_exists('gps_agrega_dia')) {
    /**
     * @return array{linhas_resumo: int, pontos_removidos: int}
     */
    function gps_agrega_dia(PDO $conn, string $dataYmd): array
    {
        $dataYmd = preg_replace('/[^0-9\-]/', '', $dataYmd);
        if ($dataYmd === '' || strlen($dataYmd) !== 10) {
            throw new InvalidArgumentException('Data inválida (use Y-m-d).');
        }

        $st = $conn->prepare('
            SELECT empresa_id, veiculo_id, latitude, longitude, velocidade, data_hora
            FROM gps_logs
            WHERE DATE(data_hora) = :d
            ORDER BY empresa_id, veiculo_id, data_hora ASC, id ASC
        ');
        $st->execute([':d' => $dataYmd]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['linhas_resumo' => 0, 'pontos_removidos' => 0];
        }

        $grupos = [];
        foreach ($rows as $r) {
            $eid = (int) $r['empresa_id'];
            $vid = (int) $r['veiculo_id'];
            $key = $eid . ':' . $vid;
            if (!isset($grupos[$key])) {
                $grupos[$key] = ['empresa_id' => $eid, 'veiculo_id' => $vid, 'pontos' => []];
            }
            $grupos[$key]['pontos'][] = $r;
        }

        $velMovimento = (float) (getenv('SF_GPS_VELOCIDADE_MOVIMENTO_KMH') ?: 3);
        $ins = $conn->prepare('
            INSERT INTO gps_logs_resumido
            (empresa_id, veiculo_id, data, total_pontos, distancia_km, tempo_movimento_seg, tempo_parado_seg)
            VALUES (:eid, :vid, :data, :tp, :dkm, :tmov, :tpar)
            ON DUPLICATE KEY UPDATE
                total_pontos = VALUES(total_pontos),
                distancia_km = VALUES(distancia_km),
                tempo_movimento_seg = VALUES(tempo_movimento_seg),
                tempo_parado_seg = VALUES(tempo_parado_seg)
        ');

        $linhas = 0;
        foreach ($grupos as $g) {
            $pts = $g['pontos'];
            $n = count($pts);
            if ($n === 0) {
                continue;
            }

            $distM = 0.0;
            $movSeg = 0;
            $parSeg = 0;

            for ($i = 1; $i < $n; $i++) {
                $a = $pts[$i - 1];
                $b = $pts[$i];
                $lat1 = (float) $a['latitude'];
                $lon1 = (float) $a['longitude'];
                $lat2 = (float) $b['latitude'];
                $lon2 = (float) $b['longitude'];
                $segM = gps_haversine_metros($lat1, $lon1, $lat2, $lon2);
                $distM += $segM;

                $t1 = strtotime((string) $a['data_hora']) ?: 0;
                $t2 = strtotime((string) $b['data_hora']) ?: $t1;
                $delta = max(0, $t2 - $t1);
                $vB = isset($b['velocidade']) && $b['velocidade'] !== null && $b['velocidade'] !== ''
                    ? (float) $b['velocidade']
                    : null;
                if ($vB !== null) {
                    $emMov = $vB >= $velMovimento;
                } else {
                    $emMov = $segM >= 15.0 && $delta > 0;
                }
                if ($emMov) {
                    $movSeg += $delta;
                } else {
                    $parSeg += $delta;
                }
            }

            $ins->execute([
                ':eid' => $g['empresa_id'],
                ':vid' => $g['veiculo_id'],
                ':data' => $dataYmd,
                ':tp' => $n,
                ':dkm' => round($distM / 1000, 2),
                ':tmov' => min($movSeg, 86400 * 2),
                ':tpar' => min($parSeg, 86400 * 2),
            ]);
            $linhas++;
        }

        $del = $conn->prepare('DELETE FROM gps_logs WHERE DATE(data_hora) = :d');
        $del->execute([':d' => $dataYmd]);
        $removidos = $del->rowCount();

        return ['linhas_resumo' => $linhas, 'pontos_removidos' => $removidos];
    }
}
