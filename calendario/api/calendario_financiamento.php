<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $sql = "SELECT pf.id, pf.numero_parcela, pf.valor, pf.data_vencimento, pf.financiamento_id,
                   f.veiculo_id, v.placa,
                   DATEDIFF(pf.data_vencimento, CURRENT_DATE) AS dias_para_vencimento
            FROM parcelas_financiamento pf
            JOIN financiamentos f ON pf.financiamento_id = f.id
            JOIN veiculos v ON f.veiculo_id = v.id
            WHERE pf.empresa_id = :empresa_id
            AND pf.data_vencimento IS NOT NULL
            AND pf.data_vencimento >= CURRENT_DATE
            AND (pf.status_id IS NULL OR pf.status_id = 1)
            ORDER BY pf.data_vencimento ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dias = (int) $row['dias_para_vencimento'];
        $valor = number_format((float) $row['valor'], 2, ',', '.');
        $base = [
            'category' => 'Financiamento',
            'parcela_id' => (int) $row['id'],
            'financiamento_id' => (int) $row['financiamento_id'],
            'veiculo_id' => (int) $row['veiculo_id'],
            'placa' => $row['placa'],
            'numero_parcela' => $row['numero_parcela'],
            'valor' => $row['valor'],
            'dias_para_vencimento' => $dias,
            'source' => 'automatic',
        ];

        if ($dias === 0) {
            $events[] = [
                'id' => 'fin_' . $row['id'] . '_hoje',
                'title' => 'Parcela VENCE HOJE: ' . $row['placa'],
                'start' => $row['data_vencimento'],
                'end' => $row['data_vencimento'],
                'allDay' => true,
                'backgroundColor' => '#dc2626',
                'borderColor' => '#dc2626',
                'editable' => false,
                'extendedProps' => array_merge($base, [
                    'priority' => 'critical',
                    'description' => 'Parcela ' . $row['numero_parcela'] . ' vence hoje. R$ ' . $valor,
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
            'id' => 'fin_' . $row['id'] . '_' . $dias,
            'title' => 'Parcela Vence em ' . $label . ': ' . $row['placa'],
            'start' => $row['data_vencimento'],
            'end' => $row['data_vencimento'],
            'allDay' => true,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'editable' => false,
            'extendedProps' => array_merge($base, [
                'priority' => $priority,
                'description' => 'Parcela ' . $row['numero_parcela'] . ' em ' . $dias . ' dias. R$ ' . $valor,
            ]),
        ];
    }

    echo json_encode($events);
} catch (Throwable $e) {
    calendario_api_fail('calendario_financiamento', $e);
}
