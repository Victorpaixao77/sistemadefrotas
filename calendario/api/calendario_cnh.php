<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $sql = "SELECT m.id, m.nome, COALESCE(m.cnh, 'N/A') AS cnh_numero, m.data_validade_cnh,
                   DATEDIFF(m.data_validade_cnh, CURRENT_DATE) AS dias_para_vencimento
            FROM motoristas m
            WHERE m.empresa_id = :empresa_id
            AND m.data_validade_cnh IS NOT NULL
            AND m.data_validade_cnh >= CURRENT_DATE
            ORDER BY m.data_validade_cnh ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $row['dias_para_vencimento'];
        $base = [
            'category' => 'CNH',
            'description' => 'CNH do motorista ' . $row['nome'] . ' (Número: ' . $row['cnh_numero'] . ').',
            'motorista_id' => (int) $row['id'],
            'cnh_numero' => $row['cnh_numero'],
            'dias_para_vencimento' => $dias,
            'source' => 'automatic',
        ];

        if ($dias === 0) {
            $events[] = [
                'id' => 'cnh_' . $row['id'] . '_hoje',
                'title' => 'CNH VENCE HOJE: ' . $row['nome'],
                'start' => $row['data_validade_cnh'],
                'end' => $row['data_validade_cnh'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'editable' => false,
                'extendedProps' => array_merge($base, ['priority' => 'critical', 'description' => 'ATENÇÃO: CNH vence hoje!']),
            ];
            continue;
        }

        if ($dias <= 30) {
            $color = '#ef4444';
            $label = '30 dias';
            $priority = 'high';
        } elseif ($dias <= 60) {
            $color = '#f59e0b';
            $label = '60 dias';
            $priority = 'medium';
        } elseif ($dias <= 90) {
            $color = '#3b82f6';
            $label = '90 dias';
            $priority = 'low';
        } else {
            continue;
        }

        $events[] = [
            'id' => 'cnh_' . $row['id'] . '_' . $dias,
            'title' => 'CNH Vence em ' . $label . ': ' . $row['nome'],
            'start' => $row['data_validade_cnh'],
            'end' => $row['data_validade_cnh'],
            'allDay' => true,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'editable' => false,
            'extendedProps' => array_merge($base, [
                'priority' => $priority,
                'description' => $base['description'] . ' Vence em ' . $dias . ' dias.',
            ]),
        ];
    }

    echo json_encode($events);
} catch (Throwable $e) {
    calendario_api_fail('calendario_cnh', $e);
}
