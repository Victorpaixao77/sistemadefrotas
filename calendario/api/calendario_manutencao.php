<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $events = [];

    $sql = "SELECT m.id, m.veiculo_id, v.placa, v.modelo, v.marca, v.ano, m.data_manutencao,
                   DATEDIFF(m.data_manutencao, CURRENT_DATE) AS dias_para_manutencao
            FROM manutencoes m
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id
            AND m.data_manutencao IS NOT NULL
            AND m.data_manutencao >= CURRENT_DATE
            ORDER BY m.data_manutencao ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $row['dias_para_manutencao'];
        $veiculo = trim($row['placa'] . ' (' . $row['marca'] . ' ' . $row['modelo'] . ')');
        $base = [
            'category' => 'Manutenção',
            'manutencao_id' => (int) $row['id'],
            'veiculo_id' => (int) $row['veiculo_id'],
            'placa' => $row['placa'],
            'dias_para_manutencao' => $dias,
            'source' => 'automatic',
        ];

        if ($dias === 0) {
            $events[] = [
                'id' => 'manutencao_' . $row['id'] . '_hoje',
                'title' => 'MANUTENÇÃO HOJE: ' . $row['placa'],
                'start' => $row['data_manutencao'],
                'end' => $row['data_manutencao'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'editable' => false,
                'extendedProps' => array_merge($base, [
                    'priority' => 'critical',
                    'description' => 'Manutenção agendada hoje — ' . $veiculo,
                ]),
            ];
            continue;
        }

        if ($dias <= 7) {
            $color = '#ef4444';
            $label = '7 dias';
            $priority = 'high';
        } elseif ($dias <= 15) {
            $color = '#f59e0b';
            $label = '15 dias';
            $priority = 'medium';
        } elseif ($dias <= 30) {
            $color = '#3b82f6';
            $label = '30 dias';
            $priority = 'low';
        } else {
            continue;
        }

        $events[] = [
            'id' => 'manutencao_' . $row['id'] . '_' . $dias,
            'title' => 'Manutenção em ' . $label . ': ' . $row['placa'],
            'start' => $row['data_manutencao'],
            'end' => $row['data_manutencao'],
            'allDay' => true,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'editable' => false,
            'extendedProps' => array_merge($base, [
                'priority' => $priority,
                'description' => 'Manutenção em ' . $dias . ' dias — ' . $veiculo,
            ]),
        ];
    }

    try {
        $r = $conn->query("SHOW TABLES LIKE 'planos_manutencao'");
        if ($r && $r->rowCount() > 0) {
            $sqlPl = "SELECT p.id, p.veiculo_id,
                             DATE_ADD(p.ultima_data, INTERVAL p.intervalo_dias DAY) AS proxima_data,
                             v.placa, cm.nome AS componente_nome, tm.nome AS tipo_nome
                      FROM planos_manutencao p
                      JOIN veiculos v ON v.id = p.veiculo_id AND v.empresa_id = p.empresa_id
                      JOIN componentes_manutencao cm ON cm.id = p.componente_id
                      JOIN tipos_manutencao tm ON tm.id = p.tipo_manutencao_id
                      WHERE p.empresa_id = :empresa_id AND p.ativo = 1
                      AND p.intervalo_dias IS NOT NULL AND p.ultima_data IS NOT NULL
                      AND DATE_ADD(p.ultima_data, INTERVAL p.intervalo_dias DAY) >= CURRENT_DATE
                      AND DATE_ADD(p.ultima_data, INTERVAL p.intervalo_dias DAY) <= DATE_ADD(CURRENT_DATE, INTERVAL 90 DAY)";
            $stPl = $conn->prepare($sqlPl);
            $stPl->execute([':empresa_id' => $empresa_id]);
            while ($row = $stPl->fetch(PDO::FETCH_ASSOC)) {
                $proxima = $row['proxima_data'];
                $dias = (int) floor((strtotime($proxima) - time()) / 86400);
                $color = $dias <= 7 ? '#ef4444' : ($dias <= 15 ? '#f59e0b' : '#3b82f6');
                $events[] = [
                    'id' => 'plano_' . $row['id'],
                    'title' => 'Plano: ' . $row['placa'] . ' — ' . $row['componente_nome'],
                    'start' => $proxima,
                    'end' => $proxima,
                    'allDay' => true,
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'editable' => false,
                    'extendedProps' => [
                        'category' => 'Manutenção',
                        'description' => 'Preventiva: ' . $row['componente_nome'] . ' (' . $row['tipo_nome'] . ')',
                        'veiculo_id' => (int) $row['veiculo_id'],
                        'placa' => $row['placa'],
                        'source' => 'automatic',
                        'priority' => $dias <= 7 ? 'high' : ($dias <= 15 ? 'medium' : 'low'),
                    ],
                ];
            }
        }
    } catch (Throwable $e) {
        // planos opcionais
    }

    echo json_encode($events);
} catch (Throwable $e) {
    calendario_api_fail('calendario_manutencao', $e);
}
