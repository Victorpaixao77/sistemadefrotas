<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $sql = "SELECT m.id, m.tipo_infracao, m.valor, m.vencimento, m.veiculo_id,
                   COALESCE(v.placa, 'N/A') AS placa,
                   DATEDIFF(m.vencimento, CURRENT_DATE) AS dias_para_vencimento
            FROM multas m
            LEFT JOIN veiculos v ON m.veiculo_id = v.id
            WHERE m.empresa_id = :empresa_id
            AND m.vencimento IS NOT NULL
            AND m.vencimento >= CURRENT_DATE
            AND (m.status_pagamento IS NULL OR m.status_pagamento != 'pago')
            ORDER BY m.vencimento ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $row['dias_para_vencimento'];
        $valor = number_format((float) $row['valor'], 2, ',', '.');
        $base = [
            'category' => 'Multas',
            'multa_id' => (int) $row['id'],
            'veiculo_id' => (int) $row['veiculo_id'],
            'placa' => $row['placa'],
            'valor' => $row['valor'],
            'dias_para_vencimento' => $dias,
            'source' => 'automatic',
        ];

        if ($dias === 0) {
            $events[] = [
                'id' => 'multa_' . $row['id'] . '_hoje',
                'title' => 'Multa VENCE HOJE: ' . $row['placa'],
                'start' => $row['vencimento'],
                'end' => $row['vencimento'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'editable' => false,
                'extendedProps' => array_merge($base, [
                    'priority' => 'critical',
                    'description' => 'Multa (' . $row['tipo_infracao'] . ') vence hoje. R$ ' . $valor,
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
            'id' => 'multa_' . $row['id'] . '_' . $dias,
            'title' => 'Multa Vence em ' . $label . ': ' . $row['placa'],
            'start' => $row['vencimento'],
            'end' => $row['vencimento'],
            'allDay' => true,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'editable' => false,
            'extendedProps' => array_merge($base, [
                'priority' => $priority,
                'description' => 'Multa (' . $row['tipo_infracao'] . ') em ' . $dias . ' dias. R$ ' . $valor,
            ]),
        ];
    }

    echo json_encode($events);
} catch (Throwable $e) {
    calendario_api_fail('calendario_multas', $e);
}
