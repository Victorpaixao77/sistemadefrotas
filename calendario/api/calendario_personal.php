<?php
require_once __DIR__ . '/calendario_api_base.php';

$empresa_id = calendario_api_require_get();

try {
    $conn = getConnection();
    $sql = "SELECT ce.id, ce.titulo AS title, COALESCE(cc.nome, 'Personalizado') AS category,
                   ce.data_inicio AS start, ce.data_fim AS end, ce.descricao AS description,
                   ce.cor AS backgroundColor, ce.cor AS borderColor
            FROM calendario_eventos ce
            LEFT JOIN categorias_calendario cc ON ce.categoria_id = cc.id
            WHERE ce.empresa_id = :empresa_id
            AND (ce.origem_tipo = 'personalizado' OR ce.origem_tipo IS NULL)
            ORDER BY ce.data_inicio DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $formatted = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $evento) {
        $cat = $evento['category'] === 'manutencao' ? 'Manutenção' : $evento['category'];
        $formatted[] = [
            'id' => (int) $evento['id'],
            'title' => $evento['title'],
            'start' => $evento['start'],
            'end' => $evento['end'],
            'backgroundColor' => $evento['backgroundColor'],
            'borderColor' => $evento['borderColor'],
            'editable' => true,
            'extendedProps' => [
                'category' => $cat,
                'source' => 'manual',
                'description' => $evento['description'],
            ],
        ];
    }
    echo json_encode($formatted);
} catch (Throwable $e) {
    calendario_api_fail('calendario_personal', $e);
}
