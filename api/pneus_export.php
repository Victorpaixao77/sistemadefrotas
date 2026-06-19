<?php
/**
 * Exportação CSV (compatível Excel) — módulo de pneus.
 * GET ?tipo=estoque|movimentacoes|custos|sulcos
 */
require_once __DIR__ . '/../includes/pneus_api_base.php';
require_once __DIR__ . '/../includes/pneus_repository.php';

$conn = getConnection();
$empresa_id = pneus_empresa_id();
$tipo = $_GET['tipo'] ?? 'estoque';

function pneus_export_csv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

try {
    switch ($tipo) {
        case 'estoque':
            $st = $conn->prepare(
                'SELECT p.numero_serie, p.marca, p.modelo, p.medida, sp.nome AS status,
                        p.sulco_inicial, p.valor_compra, p.km_instalacao, p.numero_recapagens, p.data_entrada
                 FROM pneus p LEFT JOIN status_pneus sp ON sp.id = p.status_id
                 WHERE p.empresa_id = ? ORDER BY p.numero_serie'
            );
            $st->execute([$empresa_id]);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[] = array_values($r);
            }
            pneus_export_csv('estoque_pneus.csv', ['Série', 'Marca', 'Modelo', 'Medida', 'Status', 'Sulco mm', 'Valor compra', 'KM', 'Recapagens', 'Entrada'], $rows);
            break;

        case 'movimentacoes':
            $st = $conn->prepare(
                'SELECT p.numero_serie, pm.tipo, pm.data_movimentacao, v.placa, pm.km_odometro, pm.km_rodado, pm.sulco_mm, pm.custo, pm.observacoes
                 FROM pneu_movimentacoes pm
                 INNER JOIN pneus p ON p.id = pm.pneu_id AND p.empresa_id = pm.empresa_id
                 LEFT JOIN veiculos v ON v.id = pm.veiculo_id
                 WHERE pm.empresa_id = ?
                 ORDER BY pm.data_movimentacao DESC LIMIT 5000'
            );
            $st->execute([$empresa_id]);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $rows[] = array_values($r);
            }
            pneus_export_csv('movimentacoes_pneus.csv', ['Série', 'Tipo', 'Data', 'Placa', 'KM odômetro', 'KM rodado', 'Sulco', 'Custo', 'Obs'], $rows);
            break;

        case 'custos':
            $st = $conn->prepare(
                'SELECT p.numero_serie, p.marca, p.valor_compra,
                        (SELECT COALESCE(SUM(custo),0) FROM pneu_manutencao m WHERE m.pneu_id = p.id) AS manut,
                        (SELECT COALESCE(SUM(custo),0) FROM pneu_movimentacoes pm WHERE pm.pneu_id = p.id AND pm.tipo = \'recapagem\') AS recap
                 FROM pneus p WHERE p.empresa_id = ? ORDER BY p.numero_serie'
            );
            $st->execute([$empresa_id]);
            $rows = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $total = (float) ($r['valor_compra'] ?? 0) + (float) ($r['manut'] ?? 0) + (float) ($r['recap'] ?? 0);
                $rows[] = [$r['numero_serie'], $r['marca'], $r['valor_compra'], $r['manut'], $r['recap'], $total];
            }
            pneus_export_csv('custos_pneus.csv', ['Série', 'Marca', 'Compra', 'Manutenção', 'Recapagem', 'Total vida'], $rows);
            break;

        case 'sulcos':
            try {
                $st = $conn->prepare(
                    'SELECT p.numero_serie, ms.data_medicao, ms.sulco_mm, ms.km_veiculo, v.placa, ms.observacoes
                     FROM pneu_medicoes_sulco ms
                     INNER JOIN pneus p ON p.id = ms.pneu_id
                     LEFT JOIN veiculos v ON v.id = ms.veiculo_id
                     WHERE ms.empresa_id = ? ORDER BY ms.data_medicao DESC LIMIT 3000'
                );
                $st->execute([$empresa_id]);
                $rows = [];
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $rows[] = array_values($r);
                }
                pneus_export_csv('sulcos_pneus.csv', ['Série', 'Data', 'Sulco mm', 'KM', 'Placa', 'Obs'], $rows);
            } catch (Throwable $e) {
                http_response_code(404);
                echo 'Tabela de medições não encontrada. Execute sql/pneus_profissional_v1.sql';
            }
            break;

        case 'marcas':
            $data = pneus_ranking_marcas($conn, $empresa_id, 50);
            $rows = array_map(static function ($r) {
                return [$r['marca'], $r['total_pneus'], $r['km_medio'], $r['valor_medio_compra']];
            }, $data);
            pneus_export_csv('ranking_marcas.csv', ['Marca', 'Total pneus', 'KM médio', 'Valor médio compra'], $rows);
            break;

        default:
            http_response_code(400);
            echo 'tipo inválido';
    }
} catch (Throwable $e) {
    error_log('pneus_export: ' . $e->getMessage());
    http_response_code(500);
    echo 'Erro na exportação';
}
