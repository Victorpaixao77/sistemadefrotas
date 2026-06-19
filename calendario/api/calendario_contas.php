<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $sql = "SELECT cp.id, cp.descricao, cp.valor, cp.data_vencimento, cp.fornecedor,
                   DATEDIFF(cp.data_vencimento, CURRENT_DATE) AS dias_para_vencimento
            FROM contas_pagar cp
            WHERE cp.empresa_id = :empresa_id
            AND cp.data_vencimento IS NOT NULL
            AND cp.data_vencimento >= CURRENT_DATE
            AND (cp.status_id IS NULL OR cp.status_id = 1 OR cp.status_id = 4)
            ORDER BY cp.data_vencimento ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $row['dias_para_vencimento'];
        $fornecedor = $row['fornecedor'] ?: 'Fornecedor não informado';
        $valor = number_format((float) $row['valor'], 2, ',', '.');
        $base = [
            'category' => 'Contas',
            'conta_id' => (int) $row['id'],
            'fornecedor' => $fornecedor,
            'valor' => $row['valor'],
            'dias_para_vencimento' => $dias,
            'source' => 'automatic',
        ];

        if ($dias === 0) {
            $events[] = [
                'id' => 'conta_' . $row['id'] . '_hoje',
                'title' => 'Conta VENCE HOJE: ' . $row['descricao'],
                'start' => $row['data_vencimento'],
                'end' => $row['data_vencimento'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'editable' => false,
                'extendedProps' => array_merge($base, [
                    'priority' => 'critical',
                    'description' => 'Conta "' . $row['descricao'] . '" vence hoje. R$ ' . $valor,
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
            'id' => 'conta_' . $row['id'] . '_' . $dias,
            'title' => 'Conta Vence em ' . $label . ': ' . $row['descricao'],
            'start' => $row['data_vencimento'],
            'end' => $row['data_vencimento'],
            'allDay' => true,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'editable' => false,
            'extendedProps' => array_merge($base, [
                'priority' => $priority,
                'description' => 'Conta de ' . $fornecedor . ' em ' . $dias . ' dias. R$ ' . $valor,
            ]),
        ];
    }

    echo json_encode($events);
} catch (Throwable $e) {
    calendario_api_fail('calendario_contas', $e);
}
