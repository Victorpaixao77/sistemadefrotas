
<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/auto_reports.php';
require_once '../includes/csrf.php';
require_once '../includes/relatorios_helpers.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Verificar permissão para acessar relatórios avançados
require_permission('access_advanced_reports');

// Set page title
$page_title = "Relatórios";

$relatorio_flash_error = $_SESSION['relatorio_error'] ?? '';
$relatorio_flash_success = $_SESSION['relatorio_success'] ?? '';
unset($_SESSION['relatorio_error'], $_SESSION['relatorio_success']);

// Listas para os filtros opcionais (veículo / motorista) do modal.
$rel_veiculos = [];
$rel_motoristas = [];
try {
    $connFiltros = getConnection();
    $eidFiltros = (int) ($_SESSION['empresa_id'] ?? 0);
    if ($eidFiltros > 0) {
        $sv = $connFiltros->prepare('SELECT DISTINCT placa FROM veiculos WHERE empresa_id = :e AND placa IS NOT NULL AND placa <> "" ORDER BY placa');
        $sv->execute([':e' => $eidFiltros]);
        $rel_veiculos = $sv->fetchAll(PDO::FETCH_COLUMN);
        $sm = $connFiltros->prepare('SELECT DISTINCT nome FROM motoristas WHERE empresa_id = :e AND nome IS NOT NULL AND nome <> "" ORDER BY nome');
        $sm->execute([':e' => $eidFiltros]);
        $rel_motoristas = $sm->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (Throwable $e) {
    $rel_veiculos = [];
    $rel_motoristas = [];
}

// Função para gerar relatório em PDF
function generatePDF($html, $filename) {
    try {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Dependências não instaladas. Execute composer install na raiz do projeto.');
        }
        require_once $autoload;
        
        $tempDir = dirname(__DIR__) . '/tmp';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => $tempDir,
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9,
            'default_font' => 'dejavusans'
        ]);
        
        $mpdf->SetTitle($filename);
        $mpdf->SetAuthor('Sistema de Gestão de Frotas');
        $mpdf->SetCreator('Sistema de Gestão de Frotas');
        
        // Adicionar CSS para melhorar a aparência
        $css = '
            body { font-family: dejavusans; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th { background-color: #f5f5f5; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            h1 { color: #333; }
        ';
        $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
        $mpdf->WriteHTML($html);
        
        return $mpdf->Output($filename . '.pdf', 'D');
    } catch (Throwable $e) {
        error_log("ERRO ao gerar PDF $filename: " . $e->getMessage());
        throw new RuntimeException('Não foi possível gerar o PDF. Verifique se o Composer está instalado e tente novamente.');
    }
}

// Função para gerar relatório em Excel
function generateExcel($data, $filename) {
    try {
        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('Dependências não instaladas. Execute composer install na raiz do projeto.');
        }
        require_once $autoload;

        if (empty($data)) {
            $data = [['mensagem' => 'Nenhum registro encontrado para o período selecionado.']];
        }
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Adicionar cabeçalhos
        $headers = array_keys($data[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', ucfirst(str_replace('_', ' ', $header)));
            $col++;
        }
        
        // Adicionar dados
        $row = 2;
        foreach ($data as $rowData) {
            $col = 'A';
            foreach ($rowData as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Throwable $e) {
        error_log("ERRO ao gerar Excel $filename: " . $e->getMessage());
        throw new RuntimeException('Não foi possível gerar o Excel. Tente novamente.');
    }
}

// Função para gerar relatório em CSV
function generateCSV($data, $filename) {
    if (empty($data)) {
        $data = [['mensagem' => 'Nenhum registro encontrado para o período selecionado.']];
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment;filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    $out = fopen('php://output', 'w');
    // BOM UTF-8 para Excel abrir acentuação corretamente
    fwrite($out, "\xEF\xBB\xBF");
    // Cabeçalhos
    fputcsv($out, array_map(function ($h) { return ucfirst(str_replace('_', ' ', $h)); }, array_keys($data[0])), ';');
    foreach ($data as $row) {
        fputcsv($out, array_map(function ($v) { return $v ?? ''; }, $row), ';');
    }
    fclose($out);
    exit;
}

// Filtra linhas por veículo (placa) / motorista — só quando a coluna existe na linha.
function relatorio_filter_rows(array $data, string $fVeic, string $fMot) {
    if (($fVeic === '' && $fMot === '') || empty($data)) {
        return $data;
    }
    return array_values(array_filter($data, function ($row) use ($fVeic, $fMot) {
        if (!is_array($row)) { return true; }
        if ($fVeic !== '' && isset($row['placa'])) {
            if (stripos((string) $row['placa'], $fVeic) === false) { return false; }
        }
        if ($fMot !== '') {
            $motCol = isset($row['motorista']) ? 'motorista' : (isset($row['nome']) ? 'nome' : null);
            if ($motCol !== null && stripos((string) $row[$motCol], $fMot) === false) { return false; }
        }
        return true;
    }));
}

// Rótulo amigável para uma coluna (cabeçalho legível).
function relatorio_col_label($col) {
    return ucfirst(str_replace('_', ' ', (string) $col));
}

// Uma coluna é monetária? (para formatar totais em R$)
function relatorio_is_money_col($col) {
    $c = strtolower((string) $col);
    if (preg_match('/(km|litro|litros|qtd|quantidade|pontos|dias|percentual|idade|registros|excessos|velocidade)/', $c)) {
        return false;
    }
    return (bool) preg_match('/(valor|frete|comiss|lucro|custo|despesa|receita|gasto|margem|financiament|multa|pedagio|recebido|abastecimento)/', $c);
}

// Soma colunas numéricas dos dados brutos (antes da formatação em R$).
function relatorio_numeric_totals(array $rows) {
    $totals = [];
    $count = [];
    foreach ($rows as $row) {
        if (!is_array($row)) { continue; }
        foreach ($row as $k => $v) {
            if (is_numeric($v)) {
                $totals[$k] = ($totals[$k] ?? 0) + (float) $v;
                $count[$k] = ($count[$k] ?? 0) + 1;
            }
        }
    }
    // Só mantém colunas que foram numéricas em pelo menos uma linha.
    return $totals;
}

// Nome da empresa (cabeçalho dos PDFs) — a partir da sessão/banco, sem inventar.
function relatorio_empresa_nome() {
    static $nome = null;
    if ($nome !== null) { return $nome; }
    $nome = '';
    try {
        $empresa_id = relatorio_empresa_id();
        if ($empresa_id > 0) {
            $conn = getConnection();
            $stmt = $conn->prepare('SELECT COALESCE(NULLIF(nome_fantasia, ""), razao_social) AS nome FROM empresa_clientes WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $empresa_id]);
            $nome = (string) ($stmt->fetchColumn() ?: '');
        }
    } catch (Throwable $e) {
        $nome = '';
    }
    return $nome;
}

// Função para buscar dados do relatório
function getReportData($reportType, $month, $year) {
    try {
        $conn = getConnection();
        $data = [];
        $empresa_id = relatorio_empresa_id();
        if ($empresa_id < 1) {
            throw new InvalidArgumentException('Empresa não identificada na sessão.');
        }
        $year = (int) $year;
        $month = (int) $month;
        $rotaPeriod = relatorio_rota_period_sql('r');
        $rotaPeriodLit = relatorio_rota_period_literal($year, $month, 'r');
        $rotaPeriodRvLit = relatorio_rota_period_literal($year, $month, 'rv');
        
        switch ($reportType) {
            case 'rotas':
                $sql = "SELECT r.id, v.placa, v.modelo, m.nome as motorista,
                        COALESCE(r.data_rota, r.data_saida) AS data_viagem,
                        r.data_chegada,
                        CONCAT(COALESCE(r.estado_origem, ''), ' - ', COALESCE(co.nome, '')) AS origem,
                        CONCAT(COALESCE(r.estado_destino, ''), ' - ', COALESCE(cd.nome, '')) AS destino,
                        r.distancia_km, r.frete, r.comissao, r.km_vazio,
                        r.total_km, r.percentual_vazio, r.eficiencia_viagem,
                        r.peso_carga, r.descricao_carga, r.status
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id AND v.empresa_id = r.empresa_id
                        JOIN motoristas m ON m.id = r.motorista_id AND m.empresa_id = r.empresa_id
                        LEFT JOIN cidades co ON co.id = r.cidade_origem_id
                        LEFT JOIN cidades cd ON cd.id = r.cidade_destino_id
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        ORDER BY COALESCE(r.data_rota, r.data_saida) DESC";
                break;
                
            case 'despesas_viagem':
                $sql = "SELECT r.id AS id_viagem, 
                        CONCAT(COALESCE(r.estado_origem, ''), ' → ', COALESCE(r.estado_destino, '')) as rota,
                        SUM(dv.total_despviagem) AS total_despesas,
                        SUM(dv.descarga) as total_descarga,
                        SUM(dv.pedagios) as total_pedagios,
                        SUM(dv.caixinha) as total_caixinha,
                        SUM(dv.estacionamento) as total_estacionamento,
                        SUM(dv.lavagem) as total_lavagem,
                        SUM(dv.borracharia) as total_borracharia,
                        SUM(dv.eletrica_mecanica) as total_eletrica_mecanica,
                        SUM(dv.adiantamento) as total_adiantamento,
                        SUM(dv.outros) as total_outros,
                        COUNT(dv.id) as quantidade_despesas
                        FROM despesas_viagem dv
                        JOIN rotas r ON r.id = dv.rota_id
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        GROUP BY r.id, r.estado_origem, r.estado_destino
                        ORDER BY total_despesas DESC";
                break;
                
            case 'despesas_fixas':
                $sql = "SELECT df.*
                        FROM despesas_fixas df
                        WHERE df.empresa_id = :empresa_id
                        AND YEAR(df.data_pagamento) = :year 
                        AND MONTH(df.data_pagamento) = :month";
                break;
                
            case 'manutencoes':
                $sql = "SELECT m.*, v.placa, v.modelo
                        FROM manutencoes m
                        JOIN veiculos v ON v.id = m.veiculo_id
                        WHERE m.empresa_id = :empresa_id
                        AND YEAR(m.data_manutencao) = :year 
                        AND MONTH(m.data_manutencao) = :month
                        ORDER BY m.data_manutencao DESC";
                break;
                
            case 'abastecimentos':
                $sql = "SELECT v.placa, v.modelo, 
                        SUM(a.litros) as total_litros,
                        SUM(a.valor_total) as total_valor,
                        AVG(a.valor_litro) as media_valor_litro
                        FROM abastecimentos a
                        JOIN veiculos v ON v.id = a.veiculo_id
                        WHERE a.empresa_id = :empresa_id
                        AND YEAR(a.data_abastecimento) = :year 
                        AND MONTH(a.data_abastecimento) = :month
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY total_valor DESC";
                break;
                
            case 'lucro_viagem':
                $sql = "SELECT r.id, 
                        CONCAT(COALESCE(r.estado_origem, ''), ' - ', COALESCE(r.estado_destino, '')) as rota,
                        COALESCE(r.data_rota, r.data_saida) AS data_viagem,
                        v.placa,
                        r.frete, 
                        r.comissao,
                        (
                            SELECT COALESCE(SUM(total_despviagem), 0)
                            FROM despesas_viagem dv
                            WHERE dv.rota_id = r.id AND dv.empresa_id = r.empresa_id
                        ) AS total_despesas_viagem,
                        (
                            SELECT COALESCE(SUM(a.valor_total), 0)
                            FROM abastecimentos a
                            WHERE a.veiculo_id = r.veiculo_id
                              AND a.empresa_id = r.empresa_id
                              AND DATE(a.data_abastecimento) = DATE(COALESCE(r.data_rota, r.data_saida))
                        ) AS total_abastecimentos,
                        (r.frete - COALESCE(r.comissao, 0)) AS lucro_bruto,
                        (
                            r.frete - COALESCE(r.comissao, 0) - 
                            COALESCE((
                                SELECT SUM(total_despviagem)
                                FROM despesas_viagem dv
                                WHERE dv.rota_id = r.id AND dv.empresa_id = r.empresa_id
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total)
                                FROM abastecimentos a
                                WHERE a.veiculo_id = r.veiculo_id
                                  AND a.empresa_id = r.empresa_id
                                  AND DATE(a.data_abastecimento) = DATE(COALESCE(r.data_rota, r.data_saida))
                            ), 0)
                        ) AS lucro_liquido
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        ORDER BY COALESCE(r.data_rota, r.data_saida) DESC";
                break;
                
            case 'lucro_total':
                $sql = "SELECT 
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        COALESCE(SUM(r.comissao), 0) AS total_comissao,
                        (
                            SELECT COALESCE(SUM(dv.total_despviagem), 0)
                            FROM despesas_viagem dv
                            WHERE dv.empresa_id = {$empresa_id}
                            AND dv.rota_id IN (
                                SELECT id FROM rotas
                                WHERE empresa_id = {$empresa_id}
                                AND {$rotaPeriodLit}
                            )
                        ) AS total_despesas_viagem,
                        (
                            SELECT COALESCE(SUM(a.valor_total), 0)
                            FROM abastecimentos a
                            WHERE a.empresa_id = {$empresa_id}
                            AND YEAR(a.data_abastecimento) = " . (int) $year . "
                            AND MONTH(a.data_abastecimento) = " . (int) $month . "
                        ) AS total_abastecimentos,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) AS lucro_bruto,
                        (
                            COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) - 
                            COALESCE((
                                SELECT SUM(dv.total_despviagem)
                                FROM despesas_viagem dv
                                WHERE dv.empresa_id = {$empresa_id}
                                AND dv.rota_id IN (
                                    SELECT id FROM rotas
                                    WHERE empresa_id = {$empresa_id}
                                    AND {$rotaPeriodLit}
                                )
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total)
                                FROM abastecimentos a
                                WHERE a.empresa_id = {$empresa_id}
                                AND YEAR(a.data_abastecimento) = " . (int) $year . "
                                AND MONTH(a.data_abastecimento) = " . (int) $month . "
                            ), 0)
                        ) AS lucro_liquido
                        FROM rotas r
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}";
                break;
                
            case 'lucro_veiculo':
                // Lucro por veículo no mês: agrupa por veículo
                // Receitas: Total de fretes das rotas
                // Despesas: Comissões + Abastecimentos (incluindo ARLA) + Despesas de Viagem
                $sql = "SELECT 
                        v.id as veiculo_id,
                        v.placa,
                        v.modelo,
                        -- Receitas
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        -- Despesas
                        COALESCE(SUM(r.comissao), 0) AS total_comissao,
                        -- Abastecimentos (incluindo ARLA)
                        COALESCE((
                            SELECT SUM(a.valor_total + COALESCE(
                                CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                            ))
                            FROM abastecimentos a
                            WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                            AND YEAR(a.data_abastecimento) = " . intval($year) . "
                            AND MONTH(a.data_abastecimento) = " . intval($month) . "
                            AND a.status = 'aprovado'
                        ), 0) AS total_abastecimentos,
                        -- Despesas de viagem das rotas daquele veículo
                        COALESCE((
                            SELECT SUM(dv.total_despviagem)
                            FROM despesas_viagem dv
                            INNER JOIN rotas rv ON rv.id = dv.rota_id
                            WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                            AND {$rotaPeriodRvLit}
                        ), 0) AS total_despesas_viagem,
                        -- Quantidade de rotas
                        COUNT(DISTINCT r.id) AS quantidade_rotas,
                        -- Lucro Bruto (Frete - Comissão)
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) AS lucro_bruto,
                        -- Lucro Líquido (Frete - Comissão - Abastecimentos - Despesas Viagem)
                        (
                            COALESCE(SUM(r.frete), 0) - 
                            COALESCE(SUM(r.comissao), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total + COALESCE(
                                    CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                ))
                                FROM abastecimentos a
                                WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                AND a.status = 'aprovado'
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(dv.total_despviagem)
                                FROM despesas_viagem dv
                                INNER JOIN rotas rv ON rv.id = dv.rota_id
                                WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                                AND {$rotaPeriodRvLit}
                            ), 0)
                        ) AS lucro_liquido
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id
                            AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                            AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING COALESCE(SUM(r.frete), 0) > 0 OR 
                               COALESCE((
                                   SELECT SUM(a.valor_total + COALESCE(
                                       CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                   ))
                                   FROM abastecimentos a
                                   WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                   AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                   AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                   AND a.status = 'aprovado'
                               ), 0) > 0
                        ORDER BY lucro_liquido DESC";
                break;
                
            case 'lucro_completo_veiculo':
                // Lucro completo por veículo no mês incluindo TODOS os descontos
                // Receitas: Fretes
                // Despesas Operacionais: Comissões + Abastecimentos (com ARLA) + Despesas de Viagem
                // Despesas Fixas: Financiamentos + Multas + Despesas Fixas
                // Resultado Final: Lucro/Prejuízo após todos os descontos
                $sql = "SELECT 
                        v.id as veiculo_id,
                        v.placa,
                        v.modelo,
                        -- Receitas
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        -- Despesas Operacionais
                        COALESCE(SUM(r.comissao), 0) AS total_comissao,
                        -- Abastecimentos (incluindo ARLA)
                        COALESCE((
                            SELECT SUM(a.valor_total + COALESCE(
                                CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                            ))
                            FROM abastecimentos a
                            WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                            AND YEAR(a.data_abastecimento) = " . intval($year) . "
                            AND MONTH(a.data_abastecimento) = " . intval($month) . "
                            AND a.status = 'aprovado'
                        ), 0) AS total_abastecimentos,
                        -- Despesas de viagem das rotas daquele veículo
                        COALESCE((
                            SELECT SUM(dv.total_despviagem)
                            FROM despesas_viagem dv
                            INNER JOIN rotas rv ON rv.id = dv.rota_id
                            WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                            AND {$rotaPeriodRvLit}
                        ), 0) AS total_despesas_viagem,
                        -- Financiamentos (parcelas pagas no mês do veículo)
                        COALESCE((
                            SELECT SUM(pf.valor)
                            FROM parcelas_financiamento pf
                            INNER JOIN financiamentos f ON f.id = pf.financiamento_id
                            WHERE f.veiculo_id = v.id AND f.empresa_id = {$empresa_id}
                            AND pf.status_id = 2
                            AND YEAR(pf.data_pagamento) = " . intval($year) . "
                            AND MONTH(pf.data_pagamento) = " . intval($month) . "
                        ), 0) AS total_financiamentos,
                        -- Multas (multas pagas no mês do veículo)
                        COALESCE((
                            SELECT SUM(mu.valor)
                            FROM multas mu
                            WHERE mu.veiculo_id = v.id AND mu.empresa_id = {$empresa_id}
                            AND mu.status_pagamento = 'pago'
                            AND YEAR(mu.data_pagamento) = " . intval($year) . "
                            AND MONTH(mu.data_pagamento) = " . intval($month) . "
                        ), 0) AS total_multas,
                        -- Despesas Fixas (despesas fixas pagas no mês do veículo)
                        COALESCE((
                            SELECT SUM(df.valor)
                            FROM despesas_fixas df
                            WHERE df.veiculo_id = v.id AND df.empresa_id = {$empresa_id}
                            AND df.status_pagamento_id = 2
                            AND YEAR(df.data_pagamento) = " . intval($year) . "
                            AND MONTH(df.data_pagamento) = " . intval($month) . "
                        ), 0) AS total_despesas_fixas,
                        -- Quantidade de rotas
                        COUNT(DISTINCT r.id) AS quantidade_rotas,
                        -- Lucro Operacional (Frete - Comissão - Abastecimentos - Despesas Viagem)
                        (
                            COALESCE(SUM(r.frete), 0) - 
                            COALESCE(SUM(r.comissao), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total + COALESCE(
                                    CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                ))
                                FROM abastecimentos a
                                WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                AND a.status = 'aprovado'
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(dv.total_despviagem)
                                FROM despesas_viagem dv
                                INNER JOIN rotas rv ON rv.id = dv.rota_id
                                WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                                AND {$rotaPeriodRvLit}
                            ), 0)
                        ) AS lucro_operacional,
                        -- Lucro/Prejuízo Final (Lucro Operacional - Financiamentos - Multas - Despesas Fixas)
                        (
                            -- Lucro Operacional
                            (COALESCE(SUM(r.frete), 0) - 
                             COALESCE(SUM(r.comissao), 0) - 
                             COALESCE((
                                SELECT SUM(a.valor_total + COALESCE(
                                    CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                ))
                                FROM abastecimentos a
                                WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                AND a.status = 'aprovado'
                             ), 0) - 
                             COALESCE((
                                SELECT SUM(dv.total_despviagem)
                                FROM despesas_viagem dv
                                INNER JOIN rotas rv ON rv.id = dv.rota_id
                                WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                                AND {$rotaPeriodRvLit}
                             ), 0)) -
                            -- Menos Descontos Fixos
                            COALESCE((
                                SELECT SUM(pf.valor)
                                FROM parcelas_financiamento pf
                                INNER JOIN financiamentos f ON f.id = pf.financiamento_id
                                WHERE f.veiculo_id = v.id AND f.empresa_id = {$empresa_id}
                                AND pf.status_id = 2
                                AND YEAR(pf.data_pagamento) = " . intval($year) . "
                                AND MONTH(pf.data_pagamento) = " . intval($month) . "
                            ), 0) -
                            COALESCE((
                                SELECT SUM(mu.valor)
                                FROM multas mu
                                WHERE mu.veiculo_id = v.id AND mu.empresa_id = {$empresa_id}
                                AND mu.status_pagamento = 'pago'
                                AND YEAR(mu.data_pagamento) = " . intval($year) . "
                                AND MONTH(mu.data_pagamento) = " . intval($month) . "
                            ), 0) -
                            COALESCE((
                                SELECT SUM(df.valor)
                                FROM despesas_fixas df
                                WHERE df.veiculo_id = v.id AND df.empresa_id = {$empresa_id}
                                AND df.status_pagamento_id = 2
                                AND YEAR(df.data_pagamento) = " . intval($year) . "
                                AND MONTH(df.data_pagamento) = " . intval($month) . "
                            ), 0)
                        ) AS lucro_final,
                        -- Status (Lucro ou Prejuízo)
                        CASE 
                            WHEN (
                                (COALESCE(SUM(r.frete), 0) - 
                                 COALESCE(SUM(r.comissao), 0) - 
                                 COALESCE((
                                    SELECT SUM(a.valor_total + COALESCE(
                                        CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                    ))
                                    FROM abastecimentos a
                                    WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                    AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                    AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                    AND a.status = 'aprovado'
                                 ), 0) - 
                                 COALESCE((
                                    SELECT SUM(dv.total_despviagem)
                                    FROM despesas_viagem dv
                                    INNER JOIN rotas rv ON rv.id = dv.rota_id
                                    WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id}
                                    AND {$rotaPeriodRvLit}
                                 ), 0)) -
                                COALESCE((
                                    SELECT SUM(pf.valor)
                                    FROM parcelas_financiamento pf
                                    INNER JOIN financiamentos f ON f.id = pf.financiamento_id
                                    WHERE f.veiculo_id = v.id AND f.empresa_id = {$empresa_id}
                                    AND pf.status_id = 2
                                    AND YEAR(pf.data_pagamento) = " . intval($year) . "
                                    AND MONTH(pf.data_pagamento) = " . intval($month) . "
                                ), 0) -
                                COALESCE((
                                    SELECT SUM(mu.valor)
                                    FROM multas mu
                                    WHERE mu.veiculo_id = v.id AND mu.empresa_id = {$empresa_id}
                                    AND mu.status_pagamento = 'pago'
                                    AND YEAR(mu.data_pagamento) = " . intval($year) . "
                                    AND MONTH(mu.data_pagamento) = " . intval($month) . "
                                ), 0) -
                                COALESCE((
                                    SELECT SUM(df.valor)
                                    FROM despesas_fixas df
                                    WHERE df.veiculo_id = v.id AND df.empresa_id = {$empresa_id}
                                    AND df.status_pagamento_id = 2
                                    AND YEAR(df.data_pagamento) = " . intval($year) . "
                                    AND MONTH(df.data_pagamento) = " . intval($month) . "
                                ), 0)
                            ) >= 0 THEN 'Lucro'
                            ELSE 'Prejuízo'
                        END AS status_financeiro
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id
                            AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                            AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING COALESCE(SUM(r.frete), 0) > 0 OR 
                               COALESCE((
                                   SELECT SUM(a.valor_total + COALESCE(
                                       CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0
                                   ))
                                   FROM abastecimentos a
                                   WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id}
                                   AND YEAR(a.data_abastecimento) = " . intval($year) . "
                                   AND MONTH(a.data_abastecimento) = " . intval($month) . "
                                   AND a.status = 'aprovado'
                               ), 0) > 0 OR
                               COALESCE((
                                   SELECT SUM(pf.valor)
                                   FROM parcelas_financiamento pf
                                   INNER JOIN financiamentos f ON f.id = pf.financiamento_id
                                   WHERE f.veiculo_id = v.id AND f.empresa_id = {$empresa_id}
                                   AND pf.status_id = 2
                                   AND YEAR(pf.data_pagamento) = " . intval($year) . "
                                   AND MONTH(pf.data_pagamento) = " . intval($month) . "
                               ), 0) > 0 OR
                               COALESCE((
                                   SELECT SUM(mu.valor)
                                   FROM multas mu
                                   WHERE mu.veiculo_id = v.id AND mu.empresa_id = {$empresa_id}
                                   AND mu.status_pagamento = 'pago'
                                   AND YEAR(mu.data_pagamento) = " . intval($year) . "
                                   AND MONTH(mu.data_pagamento) = " . intval($month) . "
                               ), 0) > 0 OR
                               COALESCE((
                                   SELECT SUM(df.valor)
                                   FROM despesas_fixas df
                                   WHERE df.veiculo_id = v.id AND df.empresa_id = {$empresa_id}
                                   AND df.status_pagamento_id = 2
                                   AND YEAR(df.data_pagamento) = " . intval($year) . "
                                   AND MONTH(df.data_pagamento) = " . intval($month) . "
                               ), 0) > 0
                        ORDER BY lucro_final DESC";
                break;
                
            case 'pneus':
                $sql = "SELECT pm.*, v.placa, p.numero_serie, p.marca, p.modelo, p.medida,
                        tm.nome as tipo_manutencao
                        FROM pneu_manutencao pm
                        JOIN veiculos v ON v.id = pm.veiculo_id
                        JOIN pneus p ON p.id = pm.pneu_id
                        JOIN tipos_manutencao tm ON tm.id = pm.tipo_manutencao_id
                        WHERE v.empresa_id = :empresa_id
                        AND YEAR(pm.data_manutencao) = :year 
                        AND MONTH(pm.data_manutencao) = :month
                        ORDER BY pm.data_manutencao DESC";
                break;
                
            case 'vida_util_pneus':
                $sql = "SELECT 
                        p.numero_serie, p.marca, p.modelo, p.medida, p.km_instalacao,
                        p.dot, v.placa, v.modelo as veiculo_modelo,
                        CASE 
                            WHEN p.dot IS NULL OR LENGTH(p.dot) < 4 OR 
                                 CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) > 99 OR
                                 CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) < 0 THEN 0
                            ELSE GREATEST(0, YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED)))
                        END as idade_anos,
                        CASE 
                            WHEN p.km_instalacao > 100000 THEN 'Crítico'
                            WHEN p.km_instalacao > 80000 THEN 'Atenção'
                            WHEN p.km_instalacao > 60000 THEN 'Monitorar'
                            ELSE 'Bom'
                        END as status_desgaste,
                        (80000 - p.km_instalacao) as km_restante,
                        ROUND((p.km_instalacao / 80000) * 100, 2) as percentual_desgaste
                        FROM pneus p
                        LEFT JOIN pneus_alocacao pa ON p.id = pa.pneu_id AND pa.status = 'alocado'
                        LEFT JOIN veiculos v ON pa.veiculo_id = v.id
                        WHERE p.empresa_id = :empresa_id
                        ORDER BY p.km_instalacao DESC";
                break;
                
            case 'custos_veiculo':
                $sql = "SELECT 
                        v.placa, v.modelo,
                        COALESCE(SUM(a.valor_total), 0) as total_abastecimento,
                        COALESCE(SUM(m.valor), 0) as total_manutencao,
                        COALESCE(SUM(dv.total_despviagem), 0) as total_despesas_viagem,
                        COALESCE(SUM(df.valor), 0) as total_despesas_fixas,
                        (COALESCE(SUM(a.valor_total), 0) + COALESCE(SUM(m.valor), 0) + 
                         COALESCE(SUM(dv.total_despviagem), 0) + COALESCE(SUM(df.valor), 0)) as custo_total,
                        COALESCE(SUM(r.total_km), 0) as total_km_rodado,
                        CASE 
                            WHEN COALESCE(SUM(r.total_km), 0) > 0 
                            THEN ROUND((COALESCE(SUM(a.valor_total), 0) + COALESCE(SUM(m.valor), 0) + 
                                       COALESCE(SUM(dv.total_despviagem), 0) + COALESCE(SUM(df.valor), 0)) / 
                                      COALESCE(SUM(r.total_km), 1), 2)
                            ELSE 0 
                        END as custo_por_km
                        FROM veiculos v
                        LEFT JOIN abastecimentos a ON v.id = a.veiculo_id 
                            AND YEAR(a.data_abastecimento) = :year_abastecimento 
                            AND MONTH(a.data_abastecimento) = :month_abastecimento
                        LEFT JOIN manutencoes m ON v.id = m.veiculo_id 
                            AND YEAR(m.data_manutencao) = :year_manutencao 
                            AND MONTH(m.data_manutencao) = :month_manutencao
                        LEFT JOIN rotas r ON v.id = r.veiculo_id AND r.empresa_id = v.empresa_id
                            AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year_rota 
                            AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month_rota
                        LEFT JOIN despesas_viagem dv ON r.id = dv.rota_id
                        LEFT JOIN despesas_fixas df ON v.id = df.veiculo_id 
                            AND YEAR(df.data_pagamento) = :year_despesa 
                            AND MONTH(df.data_pagamento) = :month_despesa
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY custo_total DESC";
                break;
                
            case 'eficiencia_frota':
                $sql = "SELECT 
                        v.placa, v.modelo,
                        COUNT(r.id) as total_viagens,
                        COALESCE(SUM(r.distancia_km), 0) as total_km,
                        COALESCE(SUM(r.frete), 0) as total_frete,
                        COALESCE(SUM(r.km_vazio), 0) as total_km_vazio,
                        COALESCE(AVG(r.eficiencia_viagem), 0) as media_eficiencia,
                        COALESCE(SUM(a.litros), 0) as total_litros,
                        CASE 
                            WHEN COALESCE(SUM(r.distancia_km), 0) > 0 
                            THEN ROUND(COALESCE(SUM(r.distancia_km), 0) / COALESCE(SUM(a.litros), 1), 2)
                            ELSE 0 
                        END as km_por_litro,
                        CASE 
                            WHEN COALESCE(SUM(r.distancia_km), 0) > 0 
                            THEN ROUND((COALESCE(SUM(r.distancia_km), 0) - COALESCE(SUM(r.km_vazio), 0)) / 
                                      COALESCE(SUM(r.distancia_km), 1) * 100, 2)
                            ELSE 0 
                        END as percentual_ocupacao
                        FROM veiculos v
                        LEFT JOIN rotas r ON v.id = r.veiculo_id AND r.empresa_id = v.empresa_id
                            AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year_rota 
                            AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month_rota
                        LEFT JOIN abastecimentos a ON v.id = a.veiculo_id 
                            AND YEAR(a.data_abastecimento) = :year_abastecimento 
                            AND MONTH(a.data_abastecimento) = :month_abastecimento
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY media_eficiencia DESC";
                break;
                
            case 'historico_manutencoes':
                $sql = "SELECT 
                        m.id, m.data_manutencao, m.descricao, m.valor,
                        v.placa, v.modelo as veiculo_modelo,
                        tm.nome as tipo_manutencao,
                        sm.nome as status_manutencao,
                        cm.nome as componente,
                        DATEDIFF(m.data_conclusao, m.data_manutencao) as dias_duracao,
                        CASE 
                            WHEN m.valor > (SELECT AVG(m2.valor) FROM manutencoes m2 WHERE m2.empresa_id = {$empresa_id}) THEN 'Acima da Média'
                            WHEN m.valor < (SELECT AVG(m2.valor) FROM manutencoes m2 WHERE m2.empresa_id = {$empresa_id}) THEN 'Abaixo da Média'
                            ELSE 'Na Média'
                        END as comparacao_custo
                        FROM manutencoes m
                        JOIN veiculos v ON v.id = m.veiculo_id
                        JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                        JOIN status_manutencao sm ON sm.id = m.status_manutencao_id
                        LEFT JOIN componentes_manutencao cm ON cm.id = m.componente_id
                        WHERE v.empresa_id = :empresa_id
                        AND YEAR(m.data_manutencao) = :year 
                        AND MONTH(m.data_manutencao) = :month
                        ORDER BY m.data_manutencao DESC";
                break;
                
            case 'analise_preditiva':
                $sql = "SELECT 
                        v.placa, v.modelo,
                        p.numero_serie, p.marca, p.modelo as pneu_modelo,
                        p.km_instalacao, p.dot,
                        CASE 
                            WHEN p.dot IS NULL OR LENGTH(p.dot) < 4 OR 
                                 CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) > 99 OR
                                 CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) < 0 THEN 0
                            ELSE GREATEST(0, YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED)))
                        END as idade_anos,
                        CASE 
                            WHEN p.km_instalacao > 90000 OR 
                                 (CASE 
                                     WHEN p.dot IS NULL OR LENGTH(p.dot) < 4 OR 
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) > 99 OR
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) < 0 THEN 0
                                     ELSE GREATEST(0, YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED)))
                                 END) > 7 THEN 'Alto'
                            WHEN p.km_instalacao > 70000 OR 
                                 (CASE 
                                     WHEN p.dot IS NULL OR LENGTH(p.dot) < 4 OR 
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) > 99 OR
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) < 0 THEN 0
                                     ELSE GREATEST(0, YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED)))
                                 END) > 5 THEN 'Médio'
                            ELSE 'Baixo'
                        END as risco_falha,
                        CASE 
                            WHEN p.km_instalacao > 90000 THEN ROUND(0.6 + ((p.km_instalacao - 90000) / 10000) * 0.1, 2)
                            WHEN (CASE 
                                     WHEN p.dot IS NULL OR LENGTH(p.dot) < 4 OR 
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) > 99 OR
                                          CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED) < 0 THEN 0
                                     ELSE GREATEST(0, YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS SIGNED)))
                                 END) > 7 THEN 0.8
                            ELSE 0.2
                        END as probabilidade_falha,
                        (80000 - p.km_instalacao) as km_restante_vida_util,
                        CASE 
                            WHEN p.km_instalacao > 100000 THEN 'Troca Imediata'
                            WHEN p.km_instalacao > 80000 THEN 'Planejar Troca'
                            WHEN p.km_instalacao > 60000 THEN 'Monitorar'
                            ELSE 'Bom Estado'
                        END as recomendacao
                        FROM pneus p
                        JOIN pneus_alocacao pa ON p.id = pa.pneu_id AND pa.status = 'alocado'
                        JOIN veiculos v ON pa.veiculo_id = v.id
                        WHERE v.empresa_id = :empresa_id
                        ORDER BY probabilidade_falha DESC";
                break;
                
            case 'otimizacao_pneus':
                $sql = "SELECT 
                        v.placa, v.modelo as veiculo_modelo,
                        p.numero_serie, p.marca, p.modelo as pneu_modelo,
                        p.km_instalacao, pa.posicao_id,
                        CASE 
                            WHEN pa.posicao_id IN (1, 2) THEN 'Baixo Desgaste'
                            WHEN pa.posicao_id IN (3, 4, 5, 6) THEN 'Alto Desgaste'
                            ELSE 'Médio Desgaste'
                        END as tipo_posicao,
                        CASE 
                            WHEN p.km_instalacao > 60000 AND pa.posicao_id IN (1, 2) THEN 'Mover para posição de alto desgaste'
                            WHEN p.km_instalacao < 30000 AND pa.posicao_id IN (3, 4, 5, 6) THEN 'Mover para posição de baixo desgaste'
                            ELSE 'Posição adequada'
                        END as recomendacao_otimizacao,
                        (80000 - p.km_instalacao) as km_restante,
                        ROUND((p.km_instalacao / 80000) * 100, 2) as percentual_desgaste
                        FROM pneus p
                        JOIN pneus_alocacao pa ON p.id = pa.pneu_id AND pa.status = 'alocado'
                        JOIN veiculos v ON pa.veiculo_id = v.id
                        WHERE v.empresa_id = :empresa_id
                        ORDER BY p.km_instalacao DESC";
                break;
                
            case 'veiculos_status':
                $sql = "SELECT 
                        CASE 
                            WHEN v.status_id = 1 THEN 'Ativo'
                            WHEN v.status_id = 2 THEN 'Em Manutenção'
                            WHEN v.status_id = 3 THEN 'Inativo'
                            ELSE 'Não Definido'
                        END as status,
                        COUNT(*) AS quantidade
                        FROM veiculos v
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.status_id
                        ORDER BY quantidade DESC";
                break;
                
            case 'quilometragem':
                $sql = "SELECT v.id, v.placa, 
                        SUM(r.total_km) AS total_km
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id AND v.empresa_id = r.empresa_id
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        GROUP BY v.id, v.placa
                        ORDER BY total_km DESC";
                break;
                
            case 'recebimentos':
                $sql = "SELECT 
                        CONCAT(COALESCE(r.estado_origem, ''), ' → ', COALESCE(r.estado_destino, '')) AS trecho,
                        COUNT(*) AS quantidade_viagens,
                        COALESCE(SUM(r.frete), 0) AS total_recebido
                        FROM rotas r
                        WHERE r.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        GROUP BY r.estado_origem, r.estado_destino
                        ORDER BY total_recebido DESC";
                break;
                
            case 'contas_pagar':
                $sql = "SELECT cp.*
                        FROM contas_pagar cp
                        WHERE cp.empresa_id = :empresa_id
                        AND YEAR(cp.data_vencimento) = :year 
                        AND MONTH(cp.data_vencimento) = :month";
                break;
                
            case 'contas_pagas':
                $sql = "SELECT cp.*, fp.nome as forma_pagamento, b.nome as banco
                        FROM contas_pagar cp
                        LEFT JOIN formas_pagamento fp ON fp.id = cp.forma_pagamento_id
                        LEFT JOIN bancos b ON b.id = cp.banco_id
                        WHERE cp.empresa_id = :empresa_id
                        AND cp.status_id = 2
                        AND YEAR(cp.data_pagamento) = :year 
                        AND MONTH(cp.data_pagamento) = :month
                        ORDER BY cp.data_pagamento DESC";
                break;
                
            case 'financiamentos':
                $sql = "SELECT f.*, v.placa, v.modelo, b.nome as banco, 
                        sp.nome as status_pagamento
                        FROM financiamentos f
                        JOIN veiculos v ON v.id = f.veiculo_id
                        JOIN bancos b ON b.id = f.banco_id
                        JOIN status_pagamento sp ON sp.id = f.status_pagamento_id
                        WHERE f.empresa_id = :empresa_id
                        AND YEAR(f.data_inicio) = :year 
                        AND MONTH(f.data_inicio) = :month
                        ORDER BY f.data_inicio DESC";
                break;
                
            case 'ocorrencias':
                $sql = "SELECT m.*, v.placa, v.modelo, tm.nome as tipo_manutencao, 
                        cm.nome as componente, sm.nome as status
                        FROM manutencoes m
                        JOIN veiculos v ON v.id = m.veiculo_id
                        JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                        JOIN componentes_manutencao cm ON cm.id = m.componente_id
                        JOIN status_manutencao sm ON sm.id = m.status_manutencao_id
                        WHERE m.empresa_id = :empresa_id
                        AND YEAR(m.data_manutencao) = :year 
                        AND MONTH(m.data_manutencao) = :month
                        ORDER BY m.data_manutencao DESC";
                break;
                
            case 'desempenho':
                $sql = "SELECT v.placa, v.modelo,
                        COUNT(r.id) as total_viagens,
                        SUM(r.distancia_km) as total_km,
                        SUM(r.frete) as total_frete,
                        AVG(r.eficiencia_viagem) as media_eficiencia,
                        SUM(r.km_vazio) as total_km_vazio,
                        AVG(r.percentual_vazio) as media_percentual_vazio
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id
                            AND {$rotaPeriod}
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY total_frete DESC";
                break;
                
            // NOVOS RELATÓRIOS AVANÇADOS
            case 'produtividade_motoristas':
                $sql = "
                    SELECT 
                        m.id,
                        m.nome,
                        m.cpf,
                        COUNT(r.id) as total_rotas,
                        COALESCE(SUM(r.distancia_km), 0) as km_rodados,
                        COALESCE(AVG(r.eficiencia_viagem), 0) as avaliacao_media,
                        COALESCE(SUM(CASE WHEN r.status = 'aprovado' THEN 1 ELSE 0 END), 0) as rotas_concluidas,
                        COALESCE(SUM(CASE WHEN r.status = 'pendente' THEN 1 ELSE 0 END), 0) as rotas_em_andamento,
                        COALESCE(SUM(CASE WHEN r.status = 'rejeitado' THEN 1 ELSE 0 END), 0) as rotas_rejeitadas,
                        COALESCE(SUM(r.distancia_km) / NULLIF(COUNT(r.id), 0), 0) as km_medio_por_rota
                    FROM motoristas m
                    LEFT JOIN rotas r ON m.id = r.motorista_id AND r.empresa_id = m.empresa_id
                        AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                        AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                    WHERE m.empresa_id = :empresa_id
                    GROUP BY m.id, m.nome, m.cpf
                    ORDER BY km_rodados DESC
                ";
                break;
                
            case 'consumo_combustivel':
                $sql = "
                    SELECT 
                        v.id as veiculo_id,
                        v.placa,
                        v.modelo,
                        v.marca,
                        m.nome as motorista,
                        COUNT(a.id) as total_abastecimentos,
                        COALESCE(SUM(a.litros), 0) as total_litros,
                        COALESCE(SUM(a.valor_total), 0) as total_gasto,
                        COALESCE(AVG(a.valor_litro), 0) as preco_medio_litro,
                        0 as consumo_medio
                    FROM veiculos v
                    LEFT JOIN abastecimentos a ON v.id = a.veiculo_id 
                        AND YEAR(a.data_abastecimento) = :year 
                        AND MONTH(a.data_abastecimento) = :month
                        AND a.status = 'aprovado'
                    LEFT JOIN motoristas m ON a.motorista_id = m.id
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca, m.nome
                    HAVING total_abastecimentos > 0
                    ORDER BY total_abastecimentos DESC
                ";
                break;
                
            case 'custo_por_km':
                $sql = "
                    SELECT 
                        v.id as veiculo_id,
                        v.placa,
                        v.modelo,
                        v.marca,
                        COALESCE(SUM(r.distancia_km), 0) as km_total,
                        COALESCE(SUM(a.valor_total), 0) as custo_combustivel,
                        0 as custo_despesas_viagem,
                        0 as custo_despesas_fixas,
                        0 as custo_manutencao,
                        COALESCE(
                            SUM(a.valor_total) / NULLIF(SUM(r.distancia_km), 0), 0
                        ) as custo_por_km
                    FROM veiculos v
                    LEFT JOIN rotas r ON v.id = r.veiculo_id AND r.empresa_id = v.empresa_id
                        AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                        AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                    LEFT JOIN abastecimentos a ON v.id = a.veiculo_id AND a.empresa_id = v.empresa_id
                        AND YEAR(a.data_abastecimento) = :year2 
                        AND MONTH(a.data_abastecimento) = :month2
                        AND a.status = 'aprovado'
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca
                    HAVING km_total > 0
                    ORDER BY custo_por_km DESC
                ";
                break;
                
            case 'rentabilidade_rotas':
                $sql = "
                    SELECT 
                        r.id,
                        CONCAT(r.estado_origem, ' - ', r.estado_destino) as rota,
                        r.distancia_km,
                        COALESCE(r.data_rota, r.data_saida) AS data_viagem,
                        v.placa,
                        m.nome as motorista,
                        r.frete,
                        r.comissao,
                        COALESCE(SUM(dv.total_despviagem), 0) as despesas_viagem,
                        0 as custo_combustivel,
                        0 as custo_manutencao,
                        COALESCE(SUM(dv.total_despviagem), 0) as custo_total,
                        COALESCE(
                            r.frete - r.comissao - SUM(dv.total_despviagem), 0
                        ) as lucro_estimado,
                        COALESCE(
                            (r.frete - r.comissao - SUM(dv.total_despviagem)) / 
                            NULLIF(r.distancia_km, 0), 0
                        ) as lucro_por_km
                    FROM rotas r
                    LEFT JOIN veiculos v ON r.veiculo_id = v.id
                    LEFT JOIN motoristas m ON r.motorista_id = m.id
                    LEFT JOIN despesas_viagem dv ON r.id = dv.rota_id
                    WHERE r.empresa_id = :empresa_id
                        AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                        AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                    GROUP BY r.id, r.estado_origem, r.estado_destino, r.distancia_km, r.data_rota, r.data_saida, v.placa, m.nome, r.frete, r.comissao
                    ORDER BY lucro_estimado DESC
                ";
                break;
                
            case 'multas_motorista_veiculo':
                $sql = "
                    SELECT 
                        m.id as motorista_id,
                        m.nome as motorista,
                        m.cpf,
                        v.id as veiculo_id,
                        v.placa,
                        v.modelo,
                        COUNT(mu.id) as total_multas,
                        COALESCE(SUM(mu.valor), 0) as valor_total_multas,
                        COALESCE(SUM(mu.pontos), 0) as total_pontos,
                        COALESCE(AVG(mu.valor), 0) as valor_medio_multas,
                        COALESCE(SUM(CASE WHEN mu.status_pagamento = 'pago' THEN 1 ELSE 0 END), 0) as multas_pagas,
                        COALESCE(SUM(CASE WHEN mu.status_pagamento = 'pendente' THEN 1 ELSE 0 END), 0) as multas_pendentes
                    FROM motoristas m
                    LEFT JOIN multas mu ON m.id = mu.motorista_id 
                        AND YEAR(mu.data_infracao) = :year 
                        AND MONTH(mu.data_infracao) = :month
                    LEFT JOIN veiculos v ON mu.veiculo_id = v.id
                    WHERE m.empresa_id = :empresa_id
                    GROUP BY m.id, m.nome, m.cpf, v.id, v.placa, v.modelo
                    HAVING total_multas > 0
                    ORDER BY total_multas DESC, valor_total_multas DESC
                ";
                break;
                
            case 'ocupacao_frota':
                $sql = "
                    SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.marca,
                        COUNT(r.id) as total_viagens,
                        COALESCE(SUM(r.distancia_km), 0) as km_total,
                        COALESCE(AVG(r.distancia_km), 0) as km_medio_por_viagem,
                        COALESCE(SUM(CASE WHEN r.status = 'aprovado' THEN 1 ELSE 0 END), 0) as viagens_concluidas,
                        COALESCE(SUM(CASE WHEN r.status = 'pendente' THEN 1 ELSE 0 END), 0) as viagens_em_andamento,
                        COALESCE(SUM(CASE WHEN r.status = 'rejeitado' THEN 1 ELSE 0 END), 0) as viagens_rejeitadas,
                        COALESCE(
                            (COUNT(r.id) * 100.0) / 30, 0
                        ) as percentual_ocupacao
                    FROM veiculos v
                    LEFT JOIN rotas r ON v.id = r.veiculo_id AND r.empresa_id = v.empresa_id
                        AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                        AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca
                    ORDER BY total_viagens DESC
                ";
                break;
                
            case 'veiculos_ociosos':
                $sql = "
                    SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.marca,
                        v.km_atual,
                        'Ativo' as status,
                        COALESCE(MAX(COALESCE(r.data_rota, r.data_saida)), 'Nunca') as ultima_viagem,
                        DATEDIFF(CURDATE(), COALESCE(MAX(COALESCE(r.data_rota, r.data_saida)), v.created_at)) as dias_sem_viagem
                    FROM veiculos v
                    LEFT JOIN rotas r ON v.id = r.veiculo_id AND r.empresa_id = v.empresa_id
                        AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year 
                        AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca, v.km_atual, v.created_at
                    HAVING COUNT(r.id) = 0 OR MAX(COALESCE(r.data_rota, r.data_saida)) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                    ORDER BY dias_sem_viagem DESC
                ";
                break;
                
            case 'custos_manutencao_veiculo':
                $sql = "
                    SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.marca,
                        COUNT(pm.id) as total_manutencoes,
                        COALESCE(SUM(pm.custo), 0) as custo_total_manutencao,
                        COALESCE(AVG(pm.custo), 0) as custo_medio_manutencao,
                        COALESCE(MAX(pm.data_manutencao), 'Nunca') as ultima_manutencao
                    FROM veiculos v
                    LEFT JOIN pneu_manutencao pm ON v.id = pm.veiculo_id 
                        AND YEAR(pm.data_manutencao) = :year 
                        AND MONTH(pm.data_manutencao) = :month
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca
                    ORDER BY custo_total_manutencao DESC
                ";
                break;

            // ---- Relatórios Estratégicos (PDF/Excel) ----
            case 'comparacao_mensal':
                $prev_month = (int)$month - 1;
                $prev_year = (int)$year;
                if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
                $row_atual = getReportData('lucro_total', (string)$month, (string)$year);
                $row_ant = getReportData('lucro_total', (string)$prev_month, (string)$prev_year);
                $r0 = $row_atual[0] ?? [];
                $r1 = $row_ant[0] ?? [];
                $meses_pt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                $data = [
                    [
                        'periodo' => ($meses_pt[(int)$month] ?? $month) . '/' . $year . ' (atual)',
                        'total_frete' => $r0['total_frete'] ?? 0,
                        'total_comissao' => $r0['total_comissao'] ?? 0,
                        'total_despesas_viagem' => $r0['total_despesas_viagem'] ?? 0,
                        'total_abastecimentos' => $r0['total_abastecimentos'] ?? 0,
                        'lucro_bruto' => $r0['lucro_bruto'] ?? 0,
                        'lucro_liquido' => $r0['lucro_liquido'] ?? 0
                    ],
                    [
                        'periodo' => ($meses_pt[$prev_month] ?? $prev_month) . '/' . $prev_year . ' (anterior)',
                        'total_frete' => $r1['total_frete'] ?? 0,
                        'total_comissao' => $r1['total_comissao'] ?? 0,
                        'total_despesas_viagem' => $r1['total_despesas_viagem'] ?? 0,
                        'total_abastecimentos' => $r1['total_abastecimentos'] ?? 0,
                        'lucro_bruto' => $r1['lucro_bruto'] ?? 0,
                        'lucro_liquido' => $r1['lucro_liquido'] ?? 0
                    ]
                ];
                return $data;

            case 'tendencia_6':
            case 'tendencia_12':
                $meses = ($reportType === 'tendencia_12') ? 12 : 6;
                $data = [];
                $meses_pt = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
                $base = mktime(0, 0, 0, (int)$month, 1, (int)$year);
                for ($i = $meses - 1; $i >= 0; $i--) {
                    $t = strtotime("-$i months", $base);
                    $y = (int)date('Y', $t);
                    $m = (int)date('n', $t);
                    $row = getReportData('lucro_total', (string)$m, (string)$y);
                    $r = $row[0] ?? [];
                    $data[] = [
                        'mes_ano' => ($meses_pt[$m] ?? $m) . '/' . $y,
                        'total_frete' => $r['total_frete'] ?? 0,
                        'total_comissao' => $r['total_comissao'] ?? 0,
                        'total_abastecimentos' => $r['total_abastecimentos'] ?? 0,
                        'lucro_liquido' => $r['lucro_liquido'] ?? 0
                    ];
                }
                return $data;

            case 'margem_veiculo':
                $sql = "SELECT 
                        v.placa,
                        v.modelo,
                        COALESCE(SUM(r.frete), 0) AS receita_bruta,
                        (COALESCE(SUM(r.comissao), 0) +
                         COALESCE((SELECT SUM(a.valor_total + COALESCE(CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0)) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . " AND a.status = 'aprovado'), 0) +
                         COALESCE((SELECT SUM(dv.total_despviagem) FROM despesas_viagem dv INNER JOIN rotas rv ON rv.id = dv.rota_id WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id} AND {$rotaPeriodRvLit}), 0)) AS custos_variaveis,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) -
                         COALESCE((SELECT SUM(a.valor_total + COALESCE(CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0)) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . " AND a.status = 'aprovado'), 0) -
                         COALESCE((SELECT SUM(dv.total_despviagem) FROM despesas_viagem dv INNER JOIN rotas rv ON rv.id = dv.rota_id WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id} AND {$rotaPeriodRvLit}), 0)) AS margem_operacional,
                        CASE WHEN COALESCE(SUM(r.frete), 0) > 0 THEN ROUND((COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) -
                         COALESCE((SELECT SUM(a.valor_total + COALESCE(CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0)) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . " AND a.status = 'aprovado'), 0) -
                         COALESCE((SELECT SUM(dv.total_despviagem) FROM despesas_viagem dv INNER JOIN rotas rv ON rv.id = dv.rota_id WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id} AND {$rotaPeriodRvLit}), 0)) / COALESCE(SUM(r.frete), 1) * 100, 2) ELSE 0 END AS percentual_margem
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING receita_bruta > 0
                        ORDER BY margem_operacional DESC";
                break;

            case 'veiculos_prejuizo':
                $sql = "SELECT 
                        v.placa,
                        v.modelo,
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) -
                         COALESCE((SELECT SUM(a.valor_total + COALESCE(CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0)) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . " AND a.status = 'aprovado'), 0) -
                         COALESCE((SELECT SUM(dv.total_despviagem) FROM despesas_viagem dv INNER JOIN rotas rv ON rv.id = dv.rota_id WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id} AND {$rotaPeriodRvLit}), 0)) AS lucro_liquido
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING lucro_liquido < 0
                        ORDER BY lucro_liquido ASC";
                break;

            case 'ranking':
                $sql = "SELECT 
                        v.placa,
                        v.modelo,
                        COUNT(DISTINCT r.id) AS quantidade_rotas,
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) -
                         COALESCE((SELECT SUM(a.valor_total + COALESCE(CASE WHEN a.inclui_arla = 1 THEN a.valor_total_arla ELSE 0 END, 0)) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . " AND a.status = 'aprovado'), 0) -
                         COALESCE((SELECT SUM(dv.total_despviagem) FROM despesas_viagem dv INNER JOIN rotas rv ON rv.id = dv.rota_id WHERE rv.veiculo_id = v.id AND rv.empresa_id = {$empresa_id} AND {$rotaPeriodRvLit}), 0)) AS lucro_liquido
                        FROM veiculos v
                        LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING COALESCE(SUM(r.frete), 0) > 0 OR (SELECT COUNT(*) FROM abastecimentos a WHERE a.veiculo_id = v.id AND a.empresa_id = {$empresa_id} AND YEAR(a.data_abastecimento) = " . intval($year) . " AND MONTH(a.data_abastecimento) = " . intval($month) . ") > 0
                        ORDER BY lucro_liquido DESC";
                break;

            case 'sla_manutencao':
                $sql = "SELECT 
                        v.placa,
                        v.modelo AS veiculo_modelo,
                        m.data_manutencao,
                        m.data_conclusao,
                        DATEDIFF(COALESCE(m.data_conclusao, CURDATE()), m.data_manutencao) AS dias_duracao,
                        tm.nome AS tipo_manutencao,
                        sm.nome AS status_manutencao
                        FROM manutencoes m
                        JOIN veiculos v ON v.id = m.veiculo_id
                        JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                        LEFT JOIN status_manutencao sm ON sm.id = m.status_manutencao_id
                        WHERE v.empresa_id = :empresa_id
                        AND YEAR(m.data_manutencao) = :year 
                        AND MONTH(m.data_manutencao) = :month
                        ORDER BY dias_duracao DESC";
                break;

            case 'custo_tipo_manutencao':
                $sql = "SELECT 
                        tm.nome AS tipo_manutencao,
                        COUNT(m.id) AS quantidade,
                        COALESCE(SUM(m.valor), 0) AS total_valor
                        FROM manutencoes m
                        JOIN tipos_manutencao tm ON tm.id = m.tipo_manutencao_id
                        JOIN veiculos v ON v.id = m.veiculo_id
                        WHERE v.empresa_id = :empresa_id
                        AND YEAR(m.data_manutencao) = :year 
                        AND MONTH(m.data_manutencao) = :month
                        GROUP BY tm.id, tm.nome
                        ORDER BY total_valor DESC";
                break;

            case 'desvio_consumo':
                $conn = getConnection();
                $empresa_id = $_SESSION['empresa_id'];
                $stmt = $conn->prepare("
                    SELECT v.placa, v.modelo,
                           COALESCE(SUM(a.litros), 0) AS total_litros,
                           COALESCE(SUM(r.total_km), 0) AS total_km,
                           CASE WHEN COALESCE(SUM(a.litros), 0) > 0 THEN ROUND(COALESCE(SUM(r.total_km), 0) / SUM(a.litros), 2) ELSE 0 END AS km_por_litro
                    FROM veiculos v
                    LEFT JOIN abastecimentos a ON a.veiculo_id = v.id AND YEAR(a.data_abastecimento) = :year_abast AND MONTH(a.data_abastecimento) = :month_abast AND a.status = 'aprovado'
                    LEFT JOIN rotas r ON r.veiculo_id = v.id AND r.empresa_id = v.empresa_id AND YEAR(COALESCE(r.data_rota, r.data_saida)) = :year_rota AND MONTH(COALESCE(r.data_rota, r.data_saida)) = :month_rota
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo
                    HAVING total_litros > 0 AND total_km > 0
                ");
                $stmt->bindParam(':year_abast', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_abast', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year_rota', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_rota', $month, PDO::PARAM_INT);
                $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $media_km_litro = 0;
                $n = count($rows);
                if ($n > 0) {
                    $soma = 0;
                    foreach ($rows as $row) { $soma += (float)($row['km_por_litro'] ?? 0); }
                    $media_km_litro = $soma / $n;
                }
                $data = [];
                foreach ($rows as $row) {
                    $km_l = (float)($row['km_por_litro'] ?? 0);
                    $desvio = $media_km_litro > 0 ? round(($km_l - $media_km_litro) / $media_km_litro * 100, 2) : 0;
                    $data[] = [
                        'placa' => $row['placa'],
                        'modelo' => $row['modelo'],
                        'total_km' => $row['total_km'],
                        'total_litros' => $row['total_litros'],
                        'km_por_litro' => $row['km_por_litro'],
                        'desvio_percentual' => $desvio . '%',
                        'situacao' => $desvio < -10 ? 'Abaixo da média' : ($desvio > 10 ? 'Acima da média' : 'Na média')
                    ];
                }
                return $data;

            case 'executivo_resumido':
                $row = getReportData('lucro_total', $month, $year);
                $r = $row[0] ?? [];
                $conn = getConnection();
                $empresa_id = $_SESSION['empresa_id'];
                $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM veiculos WHERE empresa_id = :eid AND (status_id = 1 OR status_id IS NULL)");
                $stmt->execute(['eid' => $empresa_id]);
                $qtd_veiculos = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $meses_pt = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
                $data = [
                    [
                        'indicador' => 'Período',
                        'valor' => ($meses_pt[(int)$month] ?? $month) . '/' . $year
                    ],
                    [
                        'indicador' => 'Receita total (fretes)',
                        'valor' => $r['total_frete'] ?? 0
                    ],
                    [
                        'indicador' => 'Comissões',
                        'valor' => $r['total_comissao'] ?? 0
                    ],
                    [
                        'indicador' => 'Abastecimentos',
                        'valor' => $r['total_abastecimentos'] ?? 0
                    ],
                    [
                        'indicador' => 'Despesas de viagem',
                        'valor' => $r['total_despesas_viagem'] ?? 0
                    ],
                    [
                        'indicador' => 'Lucro líquido',
                        'valor' => $r['lucro_liquido'] ?? 0
                    ],
                    [
                        'indicador' => 'Veículos ativos',
                        'valor' => $qtd_veiculos
                    ]
                ];
                return $data;

            // ================= NOVOS RELATÓRIOS — Motoristas & Viagem =================
            case 'jornada_motorista':
                $sql = "SELECT m.nome AS motorista,
                        COUNT(DISTINCT DATE(j.data_hora)) AS dias_com_jornada,
                        SUM(CASE WHEN j.tipo = 'inicio' THEN 1 ELSE 0 END) AS jornadas_iniciadas,
                        SUM(CASE WHEN j.tipo = 'parada' THEN 1 ELSE 0 END) AS paradas,
                        SUM(CASE WHEN j.tipo = 'descanso' THEN 1 ELSE 0 END) AS descansos,
                        SUM(CASE WHEN j.tipo = 'refeicao' THEN 1 ELSE 0 END) AS refeicoes,
                        SUM(CASE WHEN j.tipo = 'fim' THEN 1 ELSE 0 END) AS jornadas_encerradas,
                        MIN(j.data_hora) AS primeira_atividade,
                        MAX(j.data_hora) AS ultima_atividade
                        FROM jornada_motorista j
                        JOIN motoristas m ON m.id = j.motorista_id AND m.empresa_id = j.empresa_id
                        WHERE j.empresa_id = :empresa_id
                        AND YEAR(j.data_hora) = :year AND MONTH(j.data_hora) = :month
                        GROUP BY m.id, m.nome
                        ORDER BY dias_com_jornada DESC";
                break;

            case 'ocorrencias_viagem':
                $sql = "SELECT o.data_hora, o.tipo, o.descricao, o.status,
                        m.nome AS motorista, v.placa
                        FROM ocorrencias_viagem o
                        JOIN motoristas m ON m.id = o.motorista_id AND m.empresa_id = o.empresa_id
                        LEFT JOIN veiculos v ON v.id = o.veiculo_id AND v.empresa_id = o.empresa_id
                        WHERE o.empresa_id = :empresa_id
                        AND YEAR(o.data_hora) = :year AND MONTH(o.data_hora) = :month
                        ORDER BY o.data_hora DESC";
                break;

            case 'pedagios_viagem':
                $sql = "SELECT p.data_hora, p.praca, p.valor,
                        m.nome AS motorista, v.placa
                        FROM pedagios_viagem p
                        JOIN motoristas m ON m.id = p.motorista_id AND m.empresa_id = p.empresa_id
                        LEFT JOIN veiculos v ON v.id = p.veiculo_id AND v.empresa_id = p.empresa_id
                        WHERE p.empresa_id = :empresa_id
                        AND YEAR(p.data_hora) = :year AND MONTH(p.data_hora) = :month
                        ORDER BY p.data_hora DESC";
                break;

            case 'entregas_pod':
                $sql = "SELECT c.data_hora, c.recebedor_nome, c.recebedor_doc, c.status,
                        m.nome AS motorista,
                        CASE WHEN c.assinatura_path IS NOT NULL AND c.assinatura_path <> '' THEN 'Sim' ELSE 'Não' END AS assinado,
                        CASE WHEN c.foto_nf_path IS NOT NULL AND c.foto_nf_path <> '' THEN 'Sim' ELSE 'Não' END AS tem_foto_nf
                        FROM comprovantes_entrega c
                        JOIN motoristas m ON m.id = c.motorista_id AND m.empresa_id = c.empresa_id
                        WHERE c.empresa_id = :empresa_id
                        AND YEAR(c.data_hora) = :year AND MONTH(c.data_hora) = :month
                        ORDER BY c.data_hora DESC";
                break;

            case 'telemetria_gps':
                $sql = "SELECT v.placa, v.modelo,
                        COUNT(*) AS registros_gps,
                        ROUND(AVG(g.velocidade), 1) AS velocidade_media,
                        MAX(g.velocidade) AS velocidade_maxima,
                        SUM(CASE WHEN g.velocidade > 90 THEN 1 ELSE 0 END) AS excessos_acima_90
                        FROM gps_logs g
                        JOIN veiculos v ON v.id = g.veiculo_id AND v.empresa_id = g.empresa_id
                        WHERE g.empresa_id = :empresa_id
                        AND g.velocidade IS NOT NULL
                        AND YEAR(g.data_hora) = :year AND MONTH(g.data_hora) = :month
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY excessos_acima_90 DESC, velocidade_maxima DESC";
                break;

            case 'comissoes_motorista':
                $sql = "SELECT m.nome AS motorista,
                        COUNT(cp.id) AS qtd_comissoes,
                        COALESCE(SUM(COALESCE(cp.valor, r.comissao, 0)), 0) AS valor_total,
                        COALESCE(SUM(CASE WHEN cp.status = 'pago' THEN COALESCE(cp.valor, r.comissao, 0) ELSE 0 END), 0) AS valor_pago,
                        COALESCE(SUM(CASE WHEN cp.status <> 'pago' THEN COALESCE(cp.valor, r.comissao, 0) ELSE 0 END), 0) AS valor_pendente
                        FROM comissoes_pagamentos cp
                        JOIN rotas r ON r.id = cp.rota_id AND r.empresa_id = cp.empresa_id
                        JOIN motoristas m ON m.id = r.motorista_id AND m.empresa_id = r.empresa_id
                        WHERE cp.empresa_id = :empresa_id
                        AND {$rotaPeriod}
                        GROUP BY m.id, m.nome
                        ORDER BY valor_total DESC";
                break;

            case 'vencimentos_cnh':
                $sql = "SELECT m.nome AS motorista, m.cpf, m.cnh,
                        cc.nome AS categoria,
                        m.data_validade_cnh AS validade,
                        DATEDIFF(m.data_validade_cnh, CURDATE()) AS dias_para_vencer,
                        CASE
                            WHEN m.data_validade_cnh IS NULL OR m.data_validade_cnh = '0000-00-00' THEN 'Sem data'
                            WHEN m.data_validade_cnh < CURDATE() THEN 'Vencida'
                            WHEN m.data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Vencendo'
                            ELSE 'Em dia'
                        END AS situacao
                        FROM motoristas m
                        LEFT JOIN categorias_cnh cc ON cc.id = m.categoria_cnh_id
                        WHERE m.empresa_id = :empresa_id
                        ORDER BY (m.data_validade_cnh IS NULL), m.data_validade_cnh ASC";
                break;

            case 'checklists':
                $sql = "SELECT COALESCE(c.data_checklist, c.created_at) AS data_checklist,
                        v.placa, v.modelo, mo.nome AS motorista,
                        CASE WHEN c.assinatura_path IS NOT NULL AND c.assinatura_path <> '' THEN 'Sim' ELSE 'Não' END AS assinado,
                        c.observacoes
                        FROM checklist_viagem c
                        JOIN veiculos v ON v.id = c.veiculo_id AND v.empresa_id = c.empresa_id
                        LEFT JOIN motoristas mo ON mo.id = c.motorista_id AND mo.empresa_id = c.empresa_id
                        WHERE c.empresa_id = :empresa_id
                        AND YEAR(COALESCE(c.data_checklist, c.created_at)) = :year
                        AND MONTH(COALESCE(c.data_checklist, c.created_at)) = :month
                        ORDER BY data_checklist DESC";
                break;

            case 'modo_viagem':
                $sql = "SELECT mo.nome AS motorista,
                        COUNT(DISTINCT t.rota_id) AS viagens,
                        COUNT(*) AS total_eventos,
                        SUM(CASE WHEN t.tipo = 'parada' THEN 1 ELSE 0 END) AS paradas,
                        SUM(CASE WHEN t.tipo = 'abastecimento' THEN 1 ELSE 0 END) AS abastecimentos,
                        SUM(CASE WHEN t.tipo = 'pedagio' THEN 1 ELSE 0 END) AS pedagios,
                        SUM(CASE WHEN t.tipo = 'entrega' THEN 1 ELSE 0 END) AS entregas,
                        MIN(t.evento_em) AS primeiro_evento,
                        MAX(t.evento_em) AS ultimo_evento
                        FROM trip_timeline_events t
                        JOIN motoristas mo ON mo.id = t.motorista_id AND mo.empresa_id = t.empresa_id
                        WHERE t.empresa_id = :empresa_id
                        AND YEAR(t.evento_em) = :year AND MONTH(t.evento_em) = :month
                        GROUP BY mo.id, mo.nome
                        ORDER BY viagens DESC, total_eventos DESC";
                break;

            case 'co2_emissoes':
                // Fator de emissão do diesel: ~2,68 kg CO2 por litro (padrão IPCC/GHG Protocol).
                $sql = "SELECT v.placa, v.modelo,
                        COALESCE(SUM(a.litros), 0) AS total_litros,
                        ROUND(COALESCE(SUM(a.litros), 0) * 2.68, 1) AS co2_kg,
                        ROUND(COALESCE(SUM(a.litros), 0) * 2.68 / 1000, 3) AS co2_toneladas
                        FROM veiculos v
                        LEFT JOIN abastecimentos a ON a.veiculo_id = v.id AND a.empresa_id = v.empresa_id
                            AND YEAR(a.data_abastecimento) = :year AND MONTH(a.data_abastecimento) = :month
                            AND a.status = 'aprovado'
                        WHERE v.empresa_id = :empresa_id
                        GROUP BY v.id, v.placa, v.modelo
                        HAVING total_litros > 0
                        ORDER BY co2_kg DESC";
                break;

            case 'dre_mensal':
                // DRE consolidado do mês (receitas x custos operacionais x custos fixos).
                $base = getReportData('lucro_total', $month, $year);
                $b = $base[0] ?? [];
                $frete = (float) ($b['total_frete'] ?? 0);
                $comissao = (float) ($b['total_comissao'] ?? 0);
                $abast = (float) ($b['total_abastecimentos'] ?? 0);
                $despViagem = (float) ($b['total_despesas_viagem'] ?? 0);
                $lucroOper = $frete - $comissao - $abast - $despViagem;
                $q = $conn->prepare("SELECT COALESCE(SUM(valor), 0) FROM manutencoes WHERE empresa_id = :e AND YEAR(data_manutencao) = :y AND MONTH(data_manutencao) = :m");
                $q->execute([':e' => $empresa_id, ':y' => $year, ':m' => $month]);
                $manut = (float) $q->fetchColumn();
                $q = $conn->prepare("SELECT COALESCE(SUM(valor), 0) FROM despesas_fixas WHERE empresa_id = :e AND status_pagamento_id = 2 AND YEAR(data_pagamento) = :y AND MONTH(data_pagamento) = :m");
                $q->execute([':e' => $empresa_id, ':y' => $year, ':m' => $month]);
                $fixas = (float) $q->fetchColumn();
                $q = $conn->prepare("SELECT COALESCE(SUM(pf.valor), 0) FROM parcelas_financiamento pf INNER JOIN financiamentos f ON f.id = pf.financiamento_id WHERE f.empresa_id = :e AND pf.status_id = 2 AND YEAR(pf.data_pagamento) = :y AND MONTH(pf.data_pagamento) = :m");
                $q->execute([':e' => $empresa_id, ':y' => $year, ':m' => $month]);
                $fin = (float) $q->fetchColumn();
                $q = $conn->prepare("SELECT COALESCE(SUM(valor), 0) FROM multas WHERE empresa_id = :e AND status_pagamento = 'pago' AND YEAR(data_pagamento) = :y AND MONTH(data_pagamento) = :m");
                $q->execute([':e' => $empresa_id, ':y' => $year, ':m' => $month]);
                $multas = (float) $q->fetchColumn();
                $resultado = $lucroOper - $manut - $fixas - $fin - $multas;
                $data = [
                    ['indicador' => '(+) Receita de fretes', 'valor' => $frete],
                    ['indicador' => '(-) Comissões', 'valor' => $comissao],
                    ['indicador' => '(-) Abastecimentos', 'valor' => $abast],
                    ['indicador' => '(-) Despesas de viagem', 'valor' => $despViagem],
                    ['indicador' => '(=) Lucro operacional', 'valor' => $lucroOper],
                    ['indicador' => '(-) Manutenções', 'valor' => $manut],
                    ['indicador' => '(-) Despesas fixas', 'valor' => $fixas],
                    ['indicador' => '(-) Financiamentos', 'valor' => $fin],
                    ['indicador' => '(-) Multas', 'valor' => $multas],
                    ['indicador' => '(=) Resultado líquido', 'valor' => $resultado],
                ];
                return $data;

            default:
                throw new InvalidArgumentException('Tipo de relatório inválido.');
        }
        
        if (relatorio_period_is_range()) {
            $sql = relatorio_apply_daterange($sql);
        }
        $stmt = $conn->prepare($sql);
        relatorio_bind_stmt_params($stmt, $reportType, $month, $year, $empresa_id, $sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $data;
    } catch (PDOException $e) {
        error_log("ERRO SQL ao gerar relatório $reportType: " . $e->getMessage());
        throw new RuntimeException('Não foi possível consultar os dados do relatório.');
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException || $e instanceof RuntimeException) {
            throw $e;
        }
        error_log("ERRO ao gerar relatório $reportType: " . $e->getMessage());
        throw new RuntimeException('Não foi possível gerar o relatório.');
    }
}

// Processar requisição de relatório
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
            throw new InvalidArgumentException('Sessão expirada. Atualize a página e tente novamente.');
        }

        $validated = relatorio_validate_request(
            (string) ($_POST['report_type'] ?? ''),
            $_POST['month'] ?? date('n'),
            $_POST['year'] ?? date('Y'),
            (string) ($_POST['format'] ?? 'pdf')
        );
        $reportType = $validated['report_type'];
        $month = $validated['month'];
        $year = $validated['year'];
        $format = $validated['format'];

        // Período: modo mês/ano (padrão) OU intervalo de datas (opt-in).
        $periodoModo = (string) ($_POST['periodo_modo'] ?? 'mes');
        $dataInicio = trim((string) ($_POST['data_inicio'] ?? ''));
        $dataFim = trim((string) ($_POST['data_fim'] ?? ''));
        if ($periodoModo === 'intervalo' && relatorio_valid_date($dataInicio) && relatorio_valid_date($dataFim)) {
            if ($dataFim < $dataInicio) { $tmp = $dataInicio; $dataInicio = $dataFim; $dataFim = $tmp; }
            relatorio_period_configure('intervalo', $dataInicio, $dataFim);
            // Mês/ano derivados do início — para nome do arquivo, rótulo e relatórios de resumo.
            $month = (int) date('n', strtotime($dataInicio));
            $year = (int) date('Y', strtotime($dataInicio));
        } else {
            relatorio_period_configure('mes');
        }
        // Relatórios de resumo/comparação são sempre mês/ano (comparam meses ou chamam outros relatórios).
        $monthOnlyReports = ['comparacao_mensal', 'tendencia_6', 'tendencia_12', 'executivo_resumido', 'dre_mensal'];
        if (in_array($reportType, $monthOnlyReports, true)) {
            relatorio_period_configure('mes');
        }

        // Verificar se é um relatório fiscal
        if (strpos($reportType, 'fiscal_') === 0) {
            // Relatório fiscal - redirecionar para API fiscal
            $tipoFiscal = str_replace('fiscal_', '', $reportType);
            $dataInicio = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
            $dataFim = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
            
            // Redirecionar para a API fiscal com caminho absoluto
            $url = sf_app_url('fiscal/api/relatorios_fiscais.php') . '?' . http_build_query([
                'action' => 'gerar_relatorio',
                'tipo' => $tipoFiscal,
                'formato' => $format,
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]);
            
            // Redirecionar para a API fiscal
            header("Location: $url");
            exit;
        }
        
        $data = getReportData($reportType, $month, $year);

        // Filtros opcionais por veículo (placa) / motorista — aplicados só quando a coluna existe.
        $fVeic = trim((string) ($_POST['filtro_veiculo'] ?? ''));
        $fMot = trim((string) ($_POST['filtro_motorista'] ?? ''));
        $data = relatorio_filter_rows($data, $fVeic, $fMot);

        // Cópia dos dados brutos (números puros) para calcular totais antes da formatação em R$.
        $rawData = $data;

        // Comparativo com o período anterior (variação % nos totais das colunas numéricas).
        $comparison = null;
        $noComparison = ['comparacao_mensal', 'tendencia_6', 'tendencia_12', 'executivo_resumido', 'dre_mensal',
            'veiculos_status', 'vida_util_pneus', 'otimizacao_pneus', 'analise_preditiva', 'vencimentos_cnh'];
        if ((($_POST['comparar'] ?? '') === '1') && count($rawData) > 0 && !in_array($reportType, $noComparison, true)) {
          try {
            if (relatorio_period_is_range()) {
                $lenDias = (int) round((strtotime($dataFim) - strtotime($dataInicio)) / 86400);
                $pFim = date('Y-m-d', strtotime($dataInicio . ' -1 day'));
                $pIni = date('Y-m-d', strtotime($pFim . ' -' . $lenDias . ' day'));
                relatorio_period_configure('intervalo', $pIni, $pFim);
                $prevRaw = getReportData($reportType, (int) date('n', strtotime($pIni)), (int) date('Y', strtotime($pIni)));
                relatorio_period_configure('intervalo', $dataInicio, $dataFim); // restaura
                $prevLabel = date('d/m/Y', strtotime($pIni)) . ' a ' . date('d/m/Y', strtotime($pFim));
            } else {
                $pm = $month - 1; $py = $year;
                if ($pm < 1) { $pm = 12; $py--; }
                $prevRaw = getReportData($reportType, $pm, $py);
                $prevLabel = date('m/Y', mktime(0, 0, 0, $pm, 1, $py));
            }
            $prevRaw = relatorio_filter_rows($prevRaw, $fVeic, $fMot);
            $curTotals = relatorio_numeric_totals($rawData);
            $prevTotals = relatorio_numeric_totals($prevRaw);
            $comparison = ['prev_label' => $prevLabel, 'linhas' => []];
            foreach ($curTotals as $col => $cur) {
                $prev = (float) ($prevTotals[$col] ?? 0);
                $var = ($prev != 0.0) ? (($cur - $prev) / abs($prev) * 100) : null;
                $money = relatorio_is_money_col($col);
                $fmtNum = function ($v) use ($money) {
                    return $money ? 'R$ ' . number_format($v, 2, ',', '.') : number_format($v, (fmod($v, 1) == 0.0 ? 0 : 2), ',', '.');
                };
                $comparison['linhas'][] = [
                    'metrica' => relatorio_col_label($col),
                    'atual' => $fmtNum($cur),
                    'anterior' => $fmtNum($prev),
                    'variacao' => ($var === null) ? '—' : (($var >= 0 ? '+' : '') . number_format($var, 1, ',', '.') . '%'),
                    'up' => ($var === null) ? null : ($var >= 0),
                ];
            }
          } catch (Throwable $e) {
            error_log('relatorios comparativo: ' . $e->getMessage());
            $comparison = null;
            if (relatorio_period_is_range()) { relatorio_period_configure('intervalo', $dataInicio, $dataFim); }
          }
        }

        // Tratamento especial para relatórios de lucro_total (apenas 1 linha)
        if ($reportType === 'lucro_total' && !empty($data)) {
            // Formatar valores monetários
            foreach ($data[0] as $key => $value) {
                if (in_array($key, ['total_frete', 'total_comissao', 'total_despesas_viagem', 
                                     'total_abastecimentos', 'lucro_bruto', 'lucro_liquido'])) {
                    $data[0][$key] = 'R$ ' . number_format($value, 2, ',', '.');
                }
            }
        }
        
        // Tratamento especial para relatório de lucro_veiculo (múltiplas linhas)
        if ($reportType === 'lucro_veiculo' && !empty($data)) {
            // Formatar valores monetários para cada linha
            foreach ($data as &$row) {
                foreach ($row as $key => $value) {
                    if (in_array($key, ['total_frete', 'total_comissao', 'total_abastecimentos', 
                                         'total_despesas_viagem', 'lucro_bruto', 'lucro_liquido'])) {
                        $row[$key] = 'R$ ' . number_format($value, 2, ',', '.');
                    }
                }
            }
            unset($row); // Liberar referência
        }
        
        // Tratamento especial para relatório de lucro_completo_veiculo (múltiplas linhas)
        if ($reportType === 'lucro_completo_veiculo' && !empty($data)) {
            // Formatar valores monetários para cada linha
            foreach ($data as &$row) {
                foreach ($row as $key => $value) {
                    if (in_array($key, ['total_frete', 'total_comissao', 'total_abastecimentos', 
                                         'total_despesas_viagem', 'total_financiamentos', 'total_multas', 
                                         'total_despesas_fixas', 'lucro_operacional', 'lucro_final'])) {
                        $row[$key] = 'R$ ' . number_format($value, 2, ',', '.');
                    }
                }
            }
            unset($row); // Liberar referência
        }
        
        // Formatação para relatórios estratégicos (valores monetários)
        $strategicMoneyKeys = [
            'comparacao_mensal' => ['total_frete', 'total_comissao', 'total_despesas_viagem', 'total_abastecimentos', 'lucro_bruto', 'lucro_liquido'],
            'tendencia_6' => ['total_frete', 'total_comissao', 'total_abastecimentos', 'lucro_liquido'],
            'tendencia_12' => ['total_frete', 'total_comissao', 'total_abastecimentos', 'lucro_liquido'],
            'margem_veiculo' => ['receita_bruta', 'custos_variaveis', 'margem_operacional'],
            'veiculos_prejuizo' => ['total_frete', 'lucro_liquido'],
            'ranking' => ['total_frete', 'lucro_liquido'],
            'custo_tipo_manutencao' => ['total_valor'],
            'pedagios_viagem' => ['valor'],
            'comissoes_motorista' => ['valor_total', 'valor_pago', 'valor_pendente'],
        ];
        if (isset($strategicMoneyKeys[$reportType]) && !empty($data)) {
            foreach ($data as &$row) {
                foreach ($strategicMoneyKeys[$reportType] as $key) {
                    if (isset($row[$key]) && is_numeric($row[$key])) {
                        $row[$key] = 'R$ ' . number_format((float)$row[$key], 2, ',', '.');
                    }
                }
            }
            unset($row);
        }
        if ($reportType === 'executivo_resumido' && !empty($data)) {
            $moneyIndicators = ['Receita total (fretes)', 'Comissões', 'Abastecimentos', 'Despesas de viagem', 'Lucro líquido'];
            foreach ($data as &$row) {
                if (isset($row['indicador']) && in_array($row['indicador'], $moneyIndicators) && is_numeric($row['valor'])) {
                    $row['valor'] = 'R$ ' . number_format((float)$row['valor'], 2, ',', '.');
                }
            }
            unset($row);
        }
        if ($reportType === 'dre_mensal' && !empty($data)) {
            foreach ($data as &$row) {
                if (isset($row['valor']) && is_numeric($row['valor'])) {
                    $row['valor'] = 'R$ ' . number_format((float) $row['valor'], 2, ',', '.');
                }
            }
            unset($row);
        }
        
        $reportTitles = [
            'comparacao_mensal' => 'Comparação Mensal (Mês x Mês)',
            'tendencia_6' => 'Relatório de Tendência (6 meses)',
            'tendencia_12' => 'Relatório de Tendência (12 meses)',
            'margem_veiculo' => 'Margem de Contribuição por Veículo',
            'veiculos_prejuizo' => 'Veículos com Prejuízo',
            'ranking' => 'Ranking de Veículos',
            'sla_manutencao' => 'SLA de Manutenção',
            'custo_tipo_manutencao' => 'Custo por Tipo de Manutenção',
            'desvio_consumo' => 'Desvio de Consumo',
            'executivo_resumido' => 'Relatório Executivo Resumido',
            'jornada_motorista' => 'Jornada do Motorista',
            'ocorrencias_viagem' => 'Ocorrências de Viagem',
            'pedagios_viagem' => 'Pedágios de Viagem',
            'entregas_pod' => 'Entregas / Comprovantes (POD)',
            'telemetria_gps' => 'Telemetria GPS',
            'comissoes_motorista' => 'Comissões por Motorista',
            'vencimentos_cnh' => 'Vencimentos de CNH',
            'checklists' => 'Checklists de Viagem',
            'modo_viagem' => 'Modo Viagem (linha do tempo)',
            'co2_emissoes' => 'Emissões de CO₂ (ESG)',
            'dre_mensal' => 'DRE — Demonstrativo de Resultado',
        ];
        $reportTitle = $reportTitles[$reportType] ?? ucfirst(str_replace('_', ' ', $reportType));
        $periodoLabel = relatorio_period_is_range()
            ? (date('d/m/Y', strtotime($dataInicio)) . ' a ' . date('d/m/Y', strtotime($dataFim)))
            : date('m/Y', mktime(0, 0, 0, $month, 1, $year));

        // Linha de TOTAIS (só colunas numéricas; R$ nas monetárias) — a partir dos dados brutos.
        // Relatórios de "indicador × valor" não somam (o total não faz sentido).
        $noTotalsReports = ['executivo_resumido', 'dre_mensal', 'comparacao_mensal', 'tendencia_6', 'tendencia_12', 'veiculos_status'];
        $totalsRow = [];
        if (count($data) > 1 && !in_array($reportType, $noTotalsReports, true)) {
            $totals = relatorio_numeric_totals($rawData);
            if (!empty($totals)) {
                $cols = array_keys($data[0]);
                foreach ($cols as $i => $col) {
                    if ($i === 0) {
                        $totalsRow[$col] = 'TOTAL';
                    } elseif (isset($totals[$col])) {
                        $totalsRow[$col] = relatorio_is_money_col($col)
                            ? 'R$ ' . number_format($totals[$col], 2, ',', '.')
                            : number_format($totals[$col], (fmod($totals[$col], 1) == 0.0 ? 0 : 2), ',', '.');
                    } else {
                        $totalsRow[$col] = '';
                    }
                }
            }
        }

        // ---- Pré-visualização na tela (AJAX): devolve JSON, não baixa arquivo ----
        if (($_POST['action'] ?? '') === 'preview') {
            header('Content-Type: application/json; charset=UTF-8');
            $cols = !empty($data) ? array_keys($data[0]) : [];
            $chart = null;
            if (count($rawData) > 1 && !empty($cols)) {
                $totalsForChart = relatorio_numeric_totals($rawData);
                $labelCol = $cols[0];
                $valueCol = null;
                foreach ($cols as $col) {
                    if ($col !== $labelCol && isset($totalsForChart[$col]) && relatorio_is_money_col($col)) { $valueCol = $col; break; }
                }
                if ($valueCol === null) {
                    foreach ($cols as $col) {
                        if ($col !== $labelCol && isset($totalsForChart[$col])) { $valueCol = $col; break; }
                    }
                }
                if ($valueCol !== null) {
                    $cl = []; $cv = [];
                    foreach (array_slice($rawData, 0, 15) as $r) {
                        $cl[] = (string) ($r[$labelCol] ?? '');
                        $cv[] = (float) ($r[$valueCol] ?? 0);
                    }
                    $chart = ['label' => relatorio_col_label($valueCol), 'labels' => $cl, 'values' => $cv];
                }
            }
            echo json_encode([
                'success' => true,
                'title' => $reportTitle,
                'periodo' => $periodoLabel,
                'columns' => array_map('relatorio_col_label', $cols),
                'rows' => array_map('array_values', $data),
                'totals' => !empty($totalsRow) ? array_values($totalsRow) : null,
                'chart' => $chart,
                'comparison' => $comparison,
                'count' => count($data),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $fileBase = 'relatorio_' . $reportType . '_' . $month . '_' . $year;

        if ($format === 'csv') {
            $rowsCsv = $data;
            if (!empty($totalsRow)) { $rowsCsv[] = $totalsRow; }
            generateCSV($rowsCsv, $fileBase);
        } elseif ($format === 'pdf') {
            $empresaNome = relatorio_empresa_nome();
            $html = '';
            if ($empresaNome !== '') {
                $html .= '<div style="font-size:11px;color:#666;margin-bottom:2px;">' . htmlspecialchars($empresaNome) . '</div>';
            }
            $html .= '<h1>' . htmlspecialchars($reportTitle) . '</h1>';
            $html .= '<p>Período: ' . htmlspecialchars($periodoLabel) . ' &nbsp;·&nbsp; Gerado em ' . date('d/m/Y H:i') . '</p>';
            if (!empty($data)) {
                $html .= '<table><tr>';
                foreach (array_keys($data[0]) as $header) {
                    $html .= '<th>' . htmlspecialchars(relatorio_col_label($header)) . '</th>';
                }
                $html .= '</tr>';
                foreach ($data as $row) {
                    $html .= '<tr>';
                    foreach ($row as $value) {
                        $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                }
                if (!empty($totalsRow)) {
                    $html .= '<tr style="font-weight:bold;background:#eef;">';
                    foreach ($totalsRow as $value) {
                        $html .= '<td>' . htmlspecialchars((string) $value) . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
            } else {
                $html .= '<p>Nenhum dado encontrado para o período selecionado.</p>';
            }
            if (is_array($comparison) && !empty($comparison['linhas'])) {
                $html .= '<h3 style="margin-top:14px;">Comparativo com o período anterior (' . htmlspecialchars($comparison['prev_label']) . ')</h3>';
                $html .= '<table><tr><th>Métrica</th><th>Período atual</th><th>Período anterior</th><th>Variação</th></tr>';
                foreach ($comparison['linhas'] as $lin) {
                    $html .= '<tr><td>' . htmlspecialchars($lin['metrica']) . '</td><td>' . htmlspecialchars($lin['atual'])
                        . '</td><td>' . htmlspecialchars($lin['anterior']) . '</td><td>' . htmlspecialchars($lin['variacao']) . '</td></tr>';
                }
                $html .= '</table>';
            }
            generatePDF($html, $fileBase);
        } else {
            $rowsXls = $data;
            if (!empty($totalsRow)) { $rowsXls[] = $totalsRow; }
            generateExcel($rowsXls, $fileBase);
        }
    } catch (Throwable $e) {
        error_log('relatorios POST: ' . $e->getMessage());
        $_SESSION['relatorio_error'] = ($e instanceof InvalidArgumentException)
            ? $e->getMessage()
            : 'Não foi possível gerar o relatório. Verifique o período e tente novamente.';
        header('Location: relatorios.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <style>
        /* Prevenir overflow horizontal na página */
        html, body {
            overflow-x: hidden;
            max-width: 100%;
            width: 100%;
        }
        
        .main-content {
            max-width: 100%;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Garantir que todos os containers não ultrapassem a largura */
        .main-content > * {
            max-width: 100%;
            box-sizing: border-box;
        }
        
        /* Proteção adicional para elementos com largura fixa */
        * {
            box-sizing: border-box;
        }
        
        /* ---- Barra de busca + filtros (categorias) ---- */
        .reports-toolbar { margin: 6px 0 8px; }
        .reports-search { position: relative; max-width: 460px; margin-bottom: 14px; }
        .reports-search > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
        .reports-search input {
            width: 100%; padding: 11px 40px 11px 40px; border-radius: 10px;
            border: 1px solid var(--border-color); background: var(--bg-secondary);
            color: var(--text-primary); font-size: 14px; outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .reports-search input::placeholder { color: var(--text-muted); }
        .reports-search input:focus { border-color: var(--accent-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
        .reports-search-clear {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: var(--text-muted); cursor: pointer;
            padding: 6px; border-radius: 6px; line-height: 1;
        }
        .reports-search-clear:hover { color: var(--text-primary); background: var(--bg-tertiary); }
        .reports-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .report-chip {
            display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px;
            border-radius: 999px; border: 1px solid var(--border-color); background: var(--bg-secondary);
            color: var(--text-secondary); font-size: 13px; font-weight: 500; cursor: pointer;
            transition: all .15s ease; white-space: nowrap;
        }
        .report-chip i { font-size: 12px; opacity: .85; }
        .report-chip:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        .report-chip.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #fff; }
        .report-chip.active i { opacity: 1; }
        .reports-empty {
            margin: 8px 0 20px; padding: 28px; text-align: center; color: var(--text-muted);
            border: 1px dashed var(--border-color); border-radius: 12px; font-size: 14px;
        }
        .reports-empty i { margin-right: 6px; }
        .report-badge {
            display: inline-block; margin-left: 8px; padding: 2px 8px; font-size: 10px; font-weight: 700;
            letter-spacing: .03em; text-transform: uppercase; border-radius: 999px; vertical-align: middle;
            background: rgba(16,185,129,.15); color: var(--accent-success, #10b981);
        }
        .dashboard-section.is-hidden, .report-card.is-hidden { display: none !important; }

        /* ---- Botões de exportação no rodapé do preview (layout uniforme) ---- */
        .report-preview-actions button {
            border: none; padding: 9px 16px; border-radius: 8px; color: #fff;
            font-size: 13px; font-weight: 600; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;
            transition: filter .15s ease;
        }
        .report-preview-actions .btn-pdf { background: #dc3545; }
        .report-preview-actions .btn-excel { background: #28a745; }
        .report-preview-actions .btn-csv { background: #0f766e; }
        .report-preview-actions button:hover { filter: brightness(1.1); }
        /* fallback do CSV usado fora do preview (se houver) */
        .btn-csv { background: #0f766e; color: #fff; border: none; }
        .report-preview-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1090; display: none;
        }
        .report-preview-overlay.active { display: block; }
        .report-preview-modal {
            position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: min(1100px, 96vw); max-height: 92vh; z-index: 1091; display: none;
            flex-direction: column; background: var(--bg-secondary); border: 1px solid var(--border-color);
            border-radius: 14px; box-shadow: 0 24px 60px rgba(0,0,0,.5); overflow: hidden;
        }
        .report-preview-modal.active { display: flex; }
        .report-preview-head {
            display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
            padding: 18px 22px; border-bottom: 1px solid var(--border-color);
        }
        .report-preview-head h2 { margin: 0; font-size: 1.15rem; color: var(--text-primary); }
        .report-preview-meta { margin: 4px 0 0; font-size: .82rem; color: var(--text-muted); }
        .report-preview-close {
            background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer;
            padding: 6px 10px; border-radius: 8px;
        }
        .report-preview-close:hover { background: var(--bg-tertiary); color: var(--text-primary); }
        .report-preview-body { padding: 18px 22px; overflow: auto; }
        .report-preview-loading { text-align: center; color: var(--text-muted); padding: 40px; font-size: 14px; }
        .report-preview-chartwrap { margin-bottom: 18px; max-height: 240px; }
        .report-preview-tablewrap { overflow-x: auto; }
        .report-preview-tablewrap table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .report-preview-tablewrap th, .report-preview-tablewrap td {
            border: 1px solid var(--border-color); padding: 7px 10px; text-align: left; white-space: nowrap; color: var(--text-secondary);
        }
        .report-preview-tablewrap thead th { background: var(--bg-tertiary); color: var(--text-primary); position: sticky; top: 0; }
        .report-preview-tablewrap tr.report-total-row td { font-weight: 700; background: var(--sidebar-section-bg); color: var(--text-primary); }
        .report-preview-empty { text-align: center; padding: 34px; color: var(--text-muted); }
        .report-preview-foot {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 14px 22px; border-top: 1px solid var(--border-color); background: var(--bg-primary);
        }
        .report-preview-count { font-size: .82rem; color: var(--text-muted); }
        .report-preview-actions { display: flex; gap: 8px; }

        /* ---- Toggle Mês/Ano x Intervalo + presets ---- */
        .periodo-modo-toggle { display: flex; gap: 6px; margin-bottom: 12px; }
        .periodo-modo-btn {
            flex: 1; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-color);
            background: var(--bg-tertiary); color: var(--text-secondary); font-size: 13px; font-weight: 600;
            cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 6px; transition: all .15s;
        }
        .periodo-modo-btn:hover { color: var(--text-primary); }
        .periodo-modo-btn.active { background: var(--accent-primary); border-color: var(--accent-primary); color: #fff; }
        .periodo-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
        .periodo-preset {
            padding: 6px 11px; border-radius: 999px; border: 1px solid var(--border-color);
            background: var(--bg-secondary); color: var(--text-secondary); font-size: 12px; cursor: pointer; transition: all .15s;
        }
        .periodo-preset:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        #periodoIntervalo input[type="date"] {
            width: 100%; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--border-color);
            background: var(--bg-secondary); color: var(--text-primary);
        }
        .report-comparar-row { display: flex; align-items: center; gap: 8px; margin: 10px 0 4px; }
        .report-comparar-row input { width: auto; }
        .report-comparar-row label { margin: 0; cursor: pointer; font-size: 13px; color: var(--text-secondary); }
        .report-compare-wrap { margin-bottom: 18px; }
        .report-compare-wrap h4 { margin: 0 0 8px; font-size: .95rem; color: var(--text-primary); }
        .report-compare-wrap table { width: 100%; border-collapse: collapse; font-size: .82rem; }
        .report-compare-wrap th, .report-compare-wrap td { border: 1px solid var(--border-color); padding: 6px 10px; text-align: left; color: var(--text-secondary); }
        .report-compare-wrap thead th { background: var(--bg-tertiary); color: var(--text-primary); }
        .report-var-up { color: #16a34a; font-weight: 700; }
        .report-var-down { color: #dc2626; font-weight: 700; }

        /* ---- Modal de agendamento ---- */
        .agendar-grid { display: flex; gap: 12px; margin-bottom: 12px; }
        .agendar-grid > div { flex: 1; }
        #agendarForm label { display: block; font-size: 12px; color: var(--text-secondary); margin: 8px 0 4px; }
        #agendarForm select, #agendarForm input[type="text"] {
            width: 100%; padding: 9px 10px; border-radius: 8px; border: 1px solid var(--border-color);
            background: var(--bg-secondary); color: var(--text-primary); font-size: 14px;
        }
        #agLista table { width: 100%; }
        #agLista .ag-actions button { background: none; border: none; cursor: pointer; padding: 4px 6px; font-size: 14px; }
        #agLista .ag-on { color: #16a34a; }
        #agLista .ag-off { color: var(--text-muted); }
        #agLista .ag-del { color: #dc2626; }

        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .report-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
        }

        .report-card:hover {
            box-shadow: var(--card-shadow);
            transform: translateY(-2px);
            border-color: color-mix(in srgb, var(--accent-primary) 45%, var(--border-color));
        }
        
        .report-card h3 {
            margin: 0 0 15px 0;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .report-card p {
            margin: 0 0 15px 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .report-actions {
            display: flex;
            gap: 10px;
        }
        
        .report-actions button {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }
        
        .btn-pdf {
            background-color: #dc3545;
            color: white;
        }
        
        .report-actions a.btn-pdf {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            color: #fff;
        }
        .report-actions a.btn-pdf:hover {
            filter: brightness(1.1);
            color: #fff;
        }
        
        .btn-excel {
            background-color: #28a745;
            color: white;
        }
        
        .report-actions button:focus-visible,
        .report-actions a.btn-pdf:focus-visible {
            outline: 2px solid var(--accent-primary, #3b82f6);
            outline-offset: 2px;
        }
        
        .report-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        
        .report-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .report-form fieldset.report-form-periodo {
            border: none;
            padding: 0;
            margin: 0 0 4px 0;
        }
        
        .report-form fieldset.report-form-periodo legend {
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--text-primary);
            margin-bottom: 12px;
            padding: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .form-group select,
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .report-form .btn-primary:focus-visible,
        .report-form .btn-secondary:focus-visible {
            outline: 2px solid var(--accent-primary, #3b82f6);
            outline-offset: 2px;
        }
        
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }
        
        .overlay.active {
            display: block;
        }
        
        .dashboard-section {
            margin-bottom: 40px;
            max-width: 100%;
            overflow-x: hidden;
            width: 100%;
        }
        
        .dashboard-section > div {
            max-width: 100%;
            overflow-x: hidden;
            width: 100%;
        }
        
        /* Garantir que o dashboard-content tenha padding igual ao CSS global */
        .dashboard-content {
            max-width: 100%;
            overflow-x: hidden;
            width: 100%;
            box-sizing: border-box;
            padding: var(--gutter) !important;
            padding-bottom: calc(var(--gutter) * 2) !important;
        }
        
        /* Título da página vem do header superior (.header-title) */
        .relatorio-flash {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            padding: 0.75rem 1rem;
            margin: 0 0 1rem 0;
            border-radius: 8px;
            font-size: 0.9rem;
            line-height: 1.45;
        }
        .relatorio-flash--error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #b91c1c;
        }
        .relatorio-flash--ok {
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #15803d;
        }
        .relatorios-lead {
            margin: 0 0 1.25rem 0;
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.45;
            max-width: 40rem;
        }
        
        .dashboard-section h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--accent-primary, #3b82f6);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-section h2 i {
            color: var(--accent-primary, #3b82f6);
            font-size: 1.3rem;
        }
        
        @media (prefers-reduced-motion: reduce) {
            .report-card {
                transition: none !important;
            }
        }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <?php if ($relatorio_flash_error !== ''): ?>
                <div class="relatorio-flash relatorio-flash--error" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($relatorio_flash_error); ?>
                </div>
                <?php endif; ?>
                <?php if ($relatorio_flash_success !== ''): ?>
                <div class="relatorio-flash relatorio-flash--ok" role="status">
                    <i class="fas fa-circle-check"></i>
                    <?php echo htmlspecialchars($relatorio_flash_success); ?>
                </div>
                <?php endif; ?>
                <p class="relatorios-lead" id="relatorios-descricao">Escolha o relatório e exporte em <strong>PDF</strong> ou <strong>Excel</strong>.
                    <button type="button" class="btn-help" id="helpBtn" title="Ajuda" aria-label="Ajuda" style="margin-left:12px;vertical-align:middle"><i class="fas fa-question-circle"></i></button>
                </p>

                <!-- Barra de busca + filtro por categoria -->
                <div class="reports-toolbar" role="search">
                    <div class="reports-search">
                        <i class="fas fa-search"></i>
                        <input type="text" id="reportSearch" placeholder="Buscar relatório por nome ou descrição..." aria-label="Buscar relatório" autocomplete="off">
                        <button type="button" id="reportSearchClear" class="reports-search-clear" aria-label="Limpar busca" hidden><i class="fas fa-times"></i></button>
                    </div>
                    <div class="reports-chips" id="reportChips" role="tablist" aria-label="Categorias de relatórios">
                        <button type="button" class="report-chip active" data-cat="todos"><i class="fas fa-border-all"></i> Todos</button>
                        <button type="button" class="report-chip" data-cat="favoritos"><i class="fas fa-star"></i> Favoritos</button>
                        <button type="button" class="report-chip" data-cat="recentes"><i class="fas fa-clock-rotate-left"></i> Recentes</button>
                        <button type="button" class="report-chip" data-cat="operacionais"><i class="fas fa-route"></i> Operacionais</button>
                        <button type="button" class="report-chip" data-cat="financeiros"><i class="fas fa-dollar-sign"></i> Financeiros</button>
                        <button type="button" class="report-chip" data-cat="manutencao"><i class="fas fa-tools"></i> Manutenção</button>
                        <button type="button" class="report-chip" data-cat="motoristas"><i class="fas fa-id-card"></i> Motoristas &amp; Viagem</button>
                        <button type="button" class="report-chip" data-cat="ia"><i class="fas fa-brain"></i> IA</button>
                        <button type="button" class="report-chip" data-cat="fiscais"><i class="fas fa-file-invoice"></i> Fiscais</button>
                        <button type="button" class="report-chip" data-cat="avancados"><i class="fas fa-chart-bar"></i> Avançados</button>
                    </div>
                </div>
                <div class="reports-empty" id="reportsEmpty" hidden>
                    <i class="fas fa-folder-open"></i> Nenhum relatório encontrado para a sua busca.
                </div>

                <!-- Seção de Relatórios Operacionais -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-route"></i> Relatórios Operacionais</h2>
                    <div class="reports-grid">
                        <!-- Relatório de Rotas -->
                        <div class="report-card">
                            <h3>Relatório de Rotas Realizadas</h3>
                            <p>Listar todas as rotas realizadas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('rotas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('rotas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Quilometragem -->
                        <div class="report-card">
                            <h3>Relatório de Quilometragem</h3>
                            <p>Total de km rodados por veículo no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('quilometragem', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('quilometragem', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Eficiência da Frota -->
                        <div class="report-card">
                            <h3>Relatório de Eficiência da Frota</h3>
                            <p>Indicadores de performance e eficiência operacional da frota.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('eficiencia_frota', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('eficiencia_frota', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Status dos Veículos -->
                        <div class="report-card">
                            <h3>Relatório de Status dos Veículos</h3>
                            <p>Situação atual dos veículos.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('veiculos_status', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('veiculos_status', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Abastecimentos -->
                        <div class="report-card">
                            <h3>Relatório de Abastecimentos</h3>
                            <p>Todos os abastecimentos realizados no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('abastecimentos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('abastecimentos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- Seção de Relatórios Financeiros -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-dollar-sign"></i> Relatórios Financeiros</h2>
                    <div class="reports-grid">
                        <!-- DRE mensal -->
                        <div class="report-card">
                            <h3>DRE — Demonstrativo de Resultado <span class="report-badge">Novo</span></h3>
                            <p>Receitas × custos operacionais × custos fixos → resultado líquido do mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('dre_mensal', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('dre_mensal', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Relatório de Lucro por Viagem -->
                        <div class="report-card">
                            <h3>Relatório de Lucro por Viagem</h3>
                            <p>Cálculo de lucro por viagem no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('lucro_viagem', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('lucro_viagem', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Lucro Total -->
                        <div class="report-card">
                            <h3>Relatório de Lucro Total</h3>
                            <p>Lucro total considerando todas as viagens.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('lucro_total', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('lucro_total', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Lucro por Veículo -->
                        <div class="report-card">
                            <h3>Relatório de Lucro por Veículo</h3>
                            <p>Lucro mensal de cada veículo (fretes - comissões - abastecimentos - despesas).</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('lucro_veiculo', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('lucro_veiculo', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Lucro Completo por Veículo -->
                        <div class="report-card">
                            <h3>Relatório de Lucro Completo por Veículo</h3>
                            <p>Lucro mensal completo incluindo financiamentos, multas e despesas fixas. Mostra se teve lucro ou prejuízo.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('lucro_completo_veiculo', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('lucro_completo_veiculo', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Recebimentos -->
                        <div class="report-card">
                            <h3>Relatório de Recebimentos</h3>
                            <p>Total recebido de cada cliente no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('recebimentos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('recebimentos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Contas a Pagar -->
                        <div class="report-card">
                            <h3>Relatório de Contas a Pagar</h3>
                            <p>Todas as contas agendadas para pagamento no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('contas_pagar', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('contas_pagar', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Contas Pagas -->
                        <div class="report-card">
                            <h3>Relatório de Contas Pagas</h3>
                            <p>Todas as contas efetivamente pagas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('contas_pagas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('contas_pagas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Financiamentos -->
                        <div class="report-card">
                            <h3>Relatório de Financiamentos</h3>
                            <p>Parcelas pagas e status dos financiamentos no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('financiamentos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('financiamentos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Despesas Variáveis -->
                        <div class="report-card">
                            <h3>Relatório de Despesas Variáveis</h3>
                            <p>Total de despesas variáveis por viagem no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('despesas_viagem', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('despesas_viagem', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Despesas Fixas -->
                        <div class="report-card">
                            <h3>Relatório de Despesas Fixas</h3>
                            <p>Listar todas as despesas fixas pagas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('despesas_fixas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('despesas_fixas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Análise de Custos por Veículo -->
                        <div class="report-card">
                            <h3>Análise de Custos por Veículo</h3>
                            <p>Breakdown completo de custos operacionais por veículo.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('custos_veiculo', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('custos_veiculo', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de Relatórios de Manutenção -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-tools"></i> Relatórios de Manutenção</h2>
                    <div class="reports-grid">
                        <!-- Relatório de Manutenções -->
                        <div class="report-card">
                            <h3>Relatório de Manutenções</h3>
                            <p>Listar manutenções preventivas e corretivas realizadas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('manutencoes', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('manutencoes', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Histórico de Manutenções -->
                        <div class="report-card">
                            <h3>Histórico de Manutenções Detalhado</h3>
                            <p>Histórico completo de manutenções preventivas e corretivas.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('historico_manutencoes', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('historico_manutencoes', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Pneus -->
                        <div class="report-card">
                            <h3>Relatório de Pneus</h3>
                            <p>Manutenções de pneus realizadas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('pneus', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('pneus', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Vida Útil dos Pneus -->
                        <div class="report-card">
                            <h3>Relatório de Vida Útil dos Pneus</h3>
                            <p>Análise detalhada da vida útil e desgaste dos pneus da frota.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('vida_util_pneus', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('vida_util_pneus', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Ocorrências -->
                        <div class="report-card">
                            <h3>Relatório de Ocorrências</h3>
                            <p>Todas as multas ou ocorrências registradas no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('ocorrencias', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('ocorrencias', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção Motoristas & Viagem -->
                <div class="dashboard-section" data-cat="motoristas">
                    <h2><i class="fas fa-id-card"></i> Motoristas &amp; Viagem</h2>
                    <div class="reports-grid">
                        <!-- Jornada do Motorista -->
                        <div class="report-card">
                            <h3>Jornada do Motorista <span class="report-badge">Novo</span></h3>
                            <p>Dias com jornada, paradas, descansos e refeições por motorista (Lei do Motorista).</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('jornada_motorista', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('jornada_motorista', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Ocorrências de Viagem -->
                        <div class="report-card">
                            <h3>Ocorrências de Viagem <span class="report-badge">Novo</span></h3>
                            <p>Acidentes, roubos, problemas mecânicos e de carga registrados pelo motorista.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('ocorrencias_viagem', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('ocorrencias_viagem', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Pedágios -->
                        <div class="report-card">
                            <h3>Pedágios de Viagem <span class="report-badge">Novo</span></h3>
                            <p>Pedágios lançados por praça, motorista e veículo, com valor no período.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('pedagios_viagem', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('pedagios_viagem', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Entregas / POD -->
                        <div class="report-card">
                            <h3>Entregas / Comprovantes (POD) <span class="report-badge">Novo</span></h3>
                            <p>Comprovantes de entrega: recebedor, assinatura, foto da NF e status.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('entregas_pod', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('entregas_pod', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Telemetria GPS -->
                        <div class="report-card">
                            <h3>Telemetria GPS <span class="report-badge">Novo</span></h3>
                            <p>Velocidade média/máxima e excessos acima de 90 km/h por veículo, via GPS.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('telemetria_gps', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('telemetria_gps', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Comissões por Motorista -->
                        <div class="report-card">
                            <h3>Comissões por Motorista <span class="report-badge">Novo</span></h3>
                            <p>Total de comissões no período por motorista, com pago e pendente.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('comissoes_motorista', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('comissoes_motorista', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Vencimentos de CNH -->
                        <div class="report-card">
                            <h3>Vencimentos de CNH <span class="report-badge">Novo</span></h3>
                            <p>Situação da CNH de cada motorista: vencida, vencendo (30 dias) ou em dia.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('vencimentos_cnh', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('vencimentos_cnh', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Checklists de Viagem -->
                        <div class="report-card">
                            <h3>Checklists de Viagem <span class="report-badge">Novo</span></h3>
                            <p>Checklists feitos no período por veículo/motorista, com assinatura e observações.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('checklists', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('checklists', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Modo Viagem -->
                        <div class="report-card">
                            <h3>Modo Viagem (linha do tempo) <span class="report-badge">Novo</span></h3>
                            <p>Resumo das viagens do app por motorista: paradas, abastecimentos, pedágios e entregas.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('modo_viagem', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('modo_viagem', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Seção de Relatórios com IA -->
                <div class="dashboard-section" data-cat="ia">
                    <h2><i class="fas fa-brain"></i> Relatórios Inteligentes (IA)</h2>
                    <div class="reports-grid">
                        <!-- Relatório de Análise Preditiva -->
                        <div class="report-card">
                            <h3>Análise Preditiva de Falhas</h3>
                            <p>Relatório baseado em IA para predição de falhas e manutenções.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('analise_preditiva', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('analise_preditiva', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Otimização de Pneus -->
                        <div class="report-card">
                            <h3>Otimização de Alocação de Pneus</h3>
                            <p>Recomendações de IA para otimizar a alocação e vida útil dos pneus.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('otimizacao_pneus', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('otimizacao_pneus', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de Relatórios Fiscais -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-file-invoice"></i> Relatórios Fiscais</h2>
                    <div class="reports-grid">
                        <!-- Relatório de NF-e Recebidas -->
                        <div class="report-card">
                            <h3> Relatório de NF-e Recebidas</h3>
                            <p>Todas as NF-e recebidas dos clientes no período.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('nfe_recebidas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('nfe_recebidas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de CT-e Emitidos -->
                        <div class="report-card">
                            <h3> Relatório de CT-e Emitidos</h3>
                            <p>Conhecimentos de transporte emitidos e seus status.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('cte_emitidos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('cte_emitidos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de MDF-e Gerados -->
                        <div class="report-card">
                            <h3> Relatório de MDF-e Gerados</h3>
                            <p>Manifestos de documentos fiscais e controle de viagens.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('mdfe_gerados', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('mdfe_gerados', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Eventos Fiscais -->
                        <div class="report-card">
                            <h3> Relatório de Eventos Fiscais</h3>
                            <p>Cancelamentos, correções e outros eventos fiscais.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('eventos_fiscais', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('eventos_fiscais', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Status SEFAZ -->
                        <div class="report-card">
                            <h3> Relatório de Status SEFAZ</h3>
                            <p>Status de envio e retorno dos documentos na SEFAZ.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('status_sefaz', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('status_sefaz', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Viagens Completas -->
                        <div class="report-card">
                            <h3> Relatório de Viagens Completas</h3>
                            <p>Viagens com NF-e, CT-e e MDF-e vinculados.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('viagens_completas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('viagens_completas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Alertas e Validações -->
                        <div class="report-card">
                            <h3> Relatório de Alertas</h3>
                            <p>Alertas automáticos e validações de consistência.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('alertas_validacoes', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('alertas_validacoes', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Timeline de Documentos -->
                        <div class="report-card">
                            <h3> Timeline de Documentos</h3>
                            <p>Histórico cronológico de eventos por documento.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showFiscalReportForm('timeline_documentos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showFiscalReportForm('timeline_documentos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Seção de Relatórios Avançados -->
                <div class="dashboard-section">
                    <h2><i class="fas fa-chart-bar"></i> Relatórios Avançados</h2>
                    <div class="reports-grid">
                        <!-- Emissões de CO2 (ESG) -->
                        <div class="report-card">
                            <h3>Emissões de CO₂ (ESG) <span class="report-badge">Novo</span></h3>
                            <p>Estimativa de CO₂ por veículo a partir do diesel consumido (fator 2,68 kg/L).</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('co2_emissoes', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('co2_emissoes', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <!-- Relatório de Produtividade dos Motoristas -->
                        <div class="report-card">
                            <h3> Produtividade dos Motoristas</h3>
                            <p>Análise de produtividade, km rodados e eficiência por motorista.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('produtividade_motoristas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('produtividade_motoristas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Consumo de Combustível -->
                        <div class="report-card">
                            <h3> Consumo de Combustível</h3>
                            <p>Análise de consumo médio de combustível por veículo e motorista.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('consumo_combustivel', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('consumo_combustivel', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Custo por Km -->
                        <div class="report-card">
                            <h3> Custo por Km</h3>
                            <p>Análise de custos operacionais por quilômetro rodado.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('custo_por_km', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('custo_por_km', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Rentabilidade de Rotas -->
                        <div class="report-card">
                            <h3> Rentabilidade de Rotas</h3>
                            <p>Análise de lucratividade e rentabilidade por rota.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('rentabilidade_rotas', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('rentabilidade_rotas', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Multas por Motorista/Veículo -->
                        <div class="report-card">
                            <h3> Multas por Motorista/Veículo</h3>
                            <p>Controle de multas e infrações por motorista e veículo.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('multas_motorista_veiculo', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('multas_motorista_veiculo', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Ocupação da Frota -->
                        <div class="report-card">
                            <h3> Ocupação da Frota</h3>
                            <p>Análise de utilização e ocupação da frota de veículos.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('ocupacao_frota', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('ocupacao_frota', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Veículos Ociosos -->
                        <div class="report-card">
                            <h3> Veículos Ociosos</h3>
                            <p>Identificação de veículos subutilizados ou ociosos.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('veiculos_ociosos', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('veiculos_ociosos', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                        
                        <!-- Relatório de Custos de Manutenção por Veículo -->
                        <div class="report-card">
                            <h3> Custos de Manutenção por Veículo</h3>
                            <p>Análise de custos de manutenção e peças por veículo.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('custos_manutencao_veiculo', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('custos_manutencao_veiculo', 'excel')">
                                    <i class="fas fa-file-excel"></i> Excel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Relatórios Estratégicos (em cima, alinhado com os outros cards) -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-chart-pie"></i> Relatórios Estratégicos
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted" style="margin-bottom: 1rem;">Gere PDF ou Excel por período (mês/ano).</p>
                            <div class="reports-grid">
                        <div class="report-card">
                            <h3>Comparação Mensal (Mês x Mês)</h3>
                            <p>Receita, custo e lucro mês atual x anterior.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('comparacao_mensal', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('comparacao_mensal', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Relatório de Tendência (6 ou 12 meses)</h3>
                            <p>Histórico mensal: receita, custo e lucro. Escolha o mês final no formulário.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('tendencia_6', 'pdf')"><i class="fas fa-file-pdf"></i> PDF 6m</button>
                                <button class="btn-excel" onclick="showReportForm('tendencia_6', 'excel')"><i class="fas fa-file-excel"></i> Excel 6m</button>
                                <button class="btn-pdf" onclick="showReportForm('tendencia_12', 'pdf')"><i class="fas fa-file-pdf"></i> PDF 12m</button>
                                <button class="btn-excel" onclick="showReportForm('tendencia_12', 'excel')"><i class="fas fa-file-excel"></i> Excel 12m</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Margem de Contribuição por Veículo</h3>
                            <p>Receita bruta, custos variáveis, margem operacional e % de margem por veículo.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('margem_veiculo', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('margem_veiculo', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Veículos com Prejuízo</h3>
                            <p>Veículos com lucro negativo no período.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('veiculos_prejuizo', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('veiculos_prejuizo', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Ranking de Veículos</h3>
                            <p>Ranking de veículos mais lucrativos no mês.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('ranking', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('ranking', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>SLA de Manutenção</h3>
                            <p>Tempo de duração das manutenções; dias até conclusão.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('sla_manutencao', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('sla_manutencao', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                                <a href="manutencoes.php" class="btn-pdf" style="background:#5a6268;"><i class="fas fa-tools"></i> Ver Manutenções</a>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Previsão de Fluxo de Caixa</h3>
                            <p>Contas a pagar futuras, recebimentos previstos e parcelas de financiamento.</p>
                            <div class="report-actions">
                                <span class="btn-excel" style="opacity:0.8; cursor:default;"><i class="fas fa-clock"></i> Em breve</span>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Desvio de Consumo</h3>
                            <p>Consumo por veículo vs média da frota; acima/abaixo da média.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('desvio_consumo', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('desvio_consumo', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Custo por Tipo de Manutenção</h3>
                            <p>Total por categoria (preventiva, corretiva, etc.) e quantidade.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('custo_tipo_manutencao', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('custo_tipo_manutencao', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                        <div class="report-card">
                            <h3>Relatório Executivo Resumido (1 página)</h3>
                            <p>Resumo mensal: receita, custo, lucro e veículos ativos.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('executivo_resumido', 'pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                                <button class="btn-excel" onclick="showReportForm('executivo_resumido', 'excel')"><i class="fas fa-file-excel"></i> Excel</button>
                            </div>
                        </div>
                    </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Relatórios Automáticos -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-robot"></i> Relatórios Automáticos
                            </h5>
                            <button type="button" class="report-chip" onclick="abrirAgendamentos()"><i class="fas fa-envelope"></i> Agendar envios por e-mail</button>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('gamificacao_semanal')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <h6>Gamificação Semanal</h6>
                                        <p>Pontos e níveis dos motoristas na semana atual</p>
                                        <small class="text-muted"><i class="fas fa-hand-pointer"></i> Semana atual · gere quando quiser</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('ranking_mensal')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <h6>Ranking Mensal</h6>
                                        <p>Performance e ranking dos motoristas no mês atual</p>
                                        <small class="text-muted"><i class="fas fa-hand-pointer"></i> Mês atual · gere quando quiser</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('performance_geral')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h6>Performance Geral</h6>
                                        <p>Rotas, pontualidade, multas e combustível no mês atual</p>
                                        <small class="text-muted"><i class="fas fa-hand-pointer"></i> Mês atual · gere quando quiser</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Report Form Modal -->
    <div class="overlay" id="overlay" aria-hidden="true"></div>
    <div class="report-form" id="reportForm" role="dialog" aria-modal="true" aria-labelledby="reportFormModalTitle" aria-hidden="true">
        <h2 id="reportFormModalTitle">Gerar Relatório</h2>
        <form id="generateReportForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get()); ?>">
            <input type="hidden" id="reportType" name="report_type">
            <input type="hidden" id="reportFormat" name="format">
            
            <fieldset class="report-form-periodo">
                <legend>Período do relatório</legend>
                <input type="hidden" id="periodoModo" name="periodo_modo" value="mes">
                <div class="periodo-modo-toggle">
                    <button type="button" class="periodo-modo-btn active" data-modo="mes" onclick="setPeriodoModo('mes')"><i class="fas fa-calendar-day"></i> Mês/Ano</button>
                    <button type="button" class="periodo-modo-btn" data-modo="intervalo" onclick="setPeriodoModo('intervalo')"><i class="fas fa-calendar-week"></i> Intervalo</button>
                </div>

                <div id="periodoMes">
                    <div class="form-group">
                        <label for="month">Mês</label>
                        <select id="month" name="month">
                            <option value="1">Janeiro</option>
                            <option value="2">Fevereiro</option>
                            <option value="3">Março</option>
                            <option value="4">Abril</option>
                            <option value="5">Maio</option>
                            <option value="6">Junho</option>
                            <option value="7">Julho</option>
                            <option value="8">Agosto</option>
                            <option value="9">Setembro</option>
                            <option value="10">Outubro</option>
                            <option value="11">Novembro</option>
                            <option value="12">Dezembro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Ano</label>
                        <select id="year" name="year">
                            <?php
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                                echo "<option value=\"$year\">$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div id="periodoIntervalo" hidden>
                    <div class="periodo-presets">
                        <button type="button" class="periodo-preset" onclick="setPreset('mes_atual')">Este mês</button>
                        <button type="button" class="periodo-preset" onclick="setPreset('mes_passado')">Mês passado</button>
                        <button type="button" class="periodo-preset" onclick="setPreset('30d')">Últimos 30 dias</button>
                        <button type="button" class="periodo-preset" onclick="setPreset('90d')">Últimos 90 dias</button>
                        <button type="button" class="periodo-preset" onclick="setPreset('ano')">Este ano</button>
                    </div>
                    <div class="form-group">
                        <label for="dataInicio">De</label>
                        <input type="date" id="dataInicio" name="data_inicio">
                    </div>
                    <div class="form-group">
                        <label for="dataFim">Até</label>
                        <input type="date" id="dataFim" name="data_fim">
                    </div>
                    <p style="font-size:.72rem;color:var(--text-muted);margin:2px 0 0;">Relatórios de resumo (DRE, Executivo, Comparação, Tendência) usam sempre mês/ano.</p>
                </div>
            </fieldset>

            <fieldset class="report-form-periodo">
                <legend>Filtros (opcional)</legend>
                <div class="form-group">
                    <label for="filtroVeiculo">Veículo</label>
                    <select id="filtroVeiculo" name="filtro_veiculo">
                        <option value="">Todos os veículos</option>
                        <?php foreach ($rel_veiculos as $pl): ?>
                        <option value="<?php echo htmlspecialchars($pl); ?>"><?php echo htmlspecialchars($pl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filtroMotorista">Motorista</label>
                    <select id="filtroMotorista" name="filtro_motorista">
                        <option value="">Todos os motoristas</option>
                        <?php foreach ($rel_motoristas as $mo): ?>
                        <option value="<?php echo htmlspecialchars($mo); ?>"><?php echo htmlspecialchars($mo); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p style="font-size:.75rem;color:var(--text-muted);margin:2px 0 0;">Aplicado apenas aos relatórios que têm coluna de veículo/motorista.</p>
            </fieldset>

            <div class="report-comparar-row">
                <input type="checkbox" id="compararPeriodo" name="comparar" value="1">
                <label for="compararPeriodo">Comparar com o período anterior (variação %)</label>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="hideReportForm()">Cancelar</button>
                <button type="button" class="btn-primary" id="reportPreviewBtn" onclick="previewReport()"><i class="fas fa-eye"></i> Visualizar</button>
                <button type="submit" class="btn-secondary" id="reportFormSubmitBtn" aria-label="Baixar relatório no período selecionado"><i class="fas fa-download"></i> Baixar</button>
            </div>
        </form>
    </div>

    <!-- Modal de pré-visualização do relatório (tabela + gráfico + exportar) -->
    <div class="report-preview-overlay" id="reportPreviewOverlay" aria-hidden="true"></div>
    <div class="report-preview-modal" id="reportPreviewModal" role="dialog" aria-modal="true" aria-labelledby="reportPreviewTitle" aria-hidden="true">
        <div class="report-preview-head">
            <div>
                <h2 id="reportPreviewTitle">Pré-visualização</h2>
                <p id="reportPreviewMeta" class="report-preview-meta"></p>
            </div>
            <button type="button" class="report-preview-close" onclick="closeReportPreview()" aria-label="Fechar"><i class="fas fa-times"></i></button>
        </div>
        <div class="report-preview-body" id="reportPreviewBody">
            <div class="report-preview-loading" id="reportPreviewLoading">
                <i class="fas fa-spinner fa-spin"></i> Carregando dados...
            </div>
            <div id="reportPreviewChartWrap" class="report-preview-chartwrap" hidden>
                <canvas id="reportPreviewChart" height="90"></canvas>
            </div>
            <div id="reportPreviewTableWrap" class="report-preview-tablewrap"></div>
        </div>
        <div class="report-preview-foot">
            <span id="reportPreviewCount" class="report-preview-count"></span>
            <div class="report-preview-actions">
                <button type="button" class="btn-pdf" onclick="doPreviewExport('pdf')"><i class="fas fa-file-pdf"></i> PDF</button>
                <button type="button" class="btn-excel" onclick="doPreviewExport('excel')"><i class="fas fa-file-excel"></i> Excel</button>
                <button type="button" class="btn-csv" onclick="doPreviewExport('csv')"><i class="fas fa-file-csv"></i> CSV</button>
            </div>
        </div>
    </div>

    <!-- Modal de agendamento de relatórios por e-mail -->
    <div class="report-preview-overlay" id="agendarOverlay" aria-hidden="true"></div>
    <div class="report-preview-modal" id="agendarModal" role="dialog" aria-modal="true" aria-hidden="true" style="width:min(720px,96vw);">
        <div class="report-preview-head">
            <div>
                <h2>Agendar envios por e-mail</h2>
                <p class="report-preview-meta">Envio automático no horário agendado — requer SMTP configurado em Configurações &gt; Segurança.</p>
            </div>
            <button type="button" class="report-preview-close" onclick="fecharAgendamentos()" aria-label="Fechar"><i class="fas fa-times"></i></button>
        </div>
        <div class="report-preview-body">
            <form id="agendarForm" onsubmit="return salvarAgendamento(event)">
                <div class="agendar-grid">
                    <div>
                        <label for="agReport">Relatório</label>
                        <select id="agReport">
                            <option value="gamificacao_semanal">Gamificação Semanal</option>
                            <option value="ranking_mensal">Ranking Mensal</option>
                            <option value="performance_geral">Performance Geral</option>
                        </select>
                    </div>
                    <div>
                        <label for="agFreq">Frequência</label>
                        <select id="agFreq">
                            <option value="diario">Diário</option>
                            <option value="semanal">Semanal (segunda)</option>
                            <option value="mensal" selected>Mensal (dia 1º)</option>
                        </select>
                    </div>
                </div>
                <label for="agEmails">E-mails (separados por vírgula)</label>
                <input type="text" id="agEmails" placeholder="gestor@empresa.com, diretor@empresa.com" autocomplete="off">
                <div style="margin-top:12px;"><button type="submit" class="btn-csv"><i class="fas fa-plus"></i> Adicionar agendamento</button></div>
                <p id="agMsg" style="margin:8px 0 0;font-size:.82rem;"></p>
            </form>
            <h4 style="margin:20px 0 8px;font-size:.95rem;color:var(--text-primary);">Agendamentos</h4>
            <div id="agLista" class="report-compare-wrap"><p style="color:var(--text-muted);font-size:.85rem;">Carregando…</p></div>
        </div>
    </div>
    <script>
    (function () {
        function agCsrf() {
            var i = document.querySelector('#generateReportForm input[name="csrf_token"]');
            return i ? i.value : '';
        }
        function agApi(params) {
            return fetch('../api/relatorios_agendar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(params).toString()
            }).then(function (r) { return r.json(); });
        }
        var NAMES = { gamificacao_semanal: 'Gamificação Semanal', ranking_mensal: 'Ranking Mensal', performance_geral: 'Performance Geral' };
        var FREQ = { diario: 'Diário', semanal: 'Semanal', mensal: 'Mensal' };

        window.abrirAgendamentos = function () {
            document.getElementById('agendarOverlay').classList.add('active');
            document.getElementById('agendarModal').classList.add('active');
            carregarAgendamentos();
        };
        window.fecharAgendamentos = function () {
            document.getElementById('agendarOverlay').classList.remove('active');
            document.getElementById('agendarModal').classList.remove('active');
        };
        document.getElementById('agendarOverlay').addEventListener('click', window.fecharAgendamentos);

        function carregarAgendamentos() {
            var lista = document.getElementById('agLista');
            agApi({ action: 'list' }).then(function (res) {
                if (!res.success || !res.agendamentos || !res.agendamentos.length) {
                    lista.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem;">Nenhum agendamento ainda.</p>';
                    return;
                }
                var html = '<table><thead><tr><th>Relatório</th><th>Frequência</th><th>E-mails</th><th>Próximo</th><th></th></tr></thead><tbody>';
                res.agendamentos.forEach(function (a) {
                    var prox = a.proximo_envio ? a.proximo_envio.substring(0, 10).split('-').reverse().join('/') : '—';
                    var ativo = String(a.ativo) === '1';
                    html += '<tr style="' + (ativo ? '' : 'opacity:.5;') + '">'
                        + '<td>' + (NAMES[a.report_type] || a.report_type) + '</td>'
                        + '<td>' + (FREQ[a.frequencia] || a.frequencia) + '</td>'
                        + '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(a.destinatarios) + '</td>'
                        + '<td>' + prox + '</td>'
                        + '<td class="ag-actions" style="white-space:nowrap;">'
                        + '<button class="' + (ativo ? 'ag-on' : 'ag-off') + '" title="Ativar/pausar" onclick="toggleAgendamento(' + a.id + ')"><i class="fas fa-' + (ativo ? 'toggle-on' : 'toggle-off') + '"></i></button>'
                        + '<button class="ag-del" title="Excluir" onclick="deleteAgendamento(' + a.id + ')"><i class="fas fa-trash"></i></button>'
                        + '</td></tr>';
                });
                html += '</tbody></table>';
                lista.innerHTML = html;
            }).catch(function () { lista.innerHTML = '<p style="color:#dc2626;font-size:.85rem;">Erro ao carregar.</p>'; });
        }
        function escapeHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

        window.salvarAgendamento = function (ev) {
            ev.preventDefault();
            var msg = document.getElementById('agMsg');
            msg.textContent = 'Salvando…'; msg.style.color = 'var(--text-muted)';
            agApi({
                action: 'create',
                report_type: document.getElementById('agReport').value,
                frequencia: document.getElementById('agFreq').value,
                destinatarios: document.getElementById('agEmails').value,
                csrf_token: agCsrf()
            }).then(function (res) {
                if (res.success) {
                    msg.textContent = 'Agendamento criado!'; msg.style.color = '#16a34a';
                    document.getElementById('agEmails').value = '';
                    carregarAgendamentos();
                } else {
                    msg.textContent = res.error || 'Erro ao salvar.'; msg.style.color = '#dc2626';
                }
            }).catch(function () { msg.textContent = 'Erro de conexão.'; msg.style.color = '#dc2626'; });
            return false;
        };
        window.toggleAgendamento = function (id) {
            agApi({ action: 'toggle', id: id, csrf_token: agCsrf() }).then(carregarAgendamentos);
        };
        window.deleteAgendamento = function (id) {
            if (!confirm('Excluir este agendamento?')) { return; }
            agApi({ action: 'delete', id: id, csrf_token: agCsrf() }).then(carregarAgendamentos);
        };
    })();
    </script>

    <!-- Busca + filtro por categoria de relatórios -->
    <script>
    (function () {
        var CAT_MAP = [
            { key: 'operacionais', match: 'operacionais' },
            { key: 'financeiros', match: 'financeiros' },
            { key: 'manutencao', match: 'manuten' },
            { key: 'motoristas', match: 'motoristas' },
            { key: 'ia', match: 'inteligentes' },
            { key: 'fiscais', match: 'fiscais' },
            { key: 'avancados', match: 'avançados' }
        ];
        var FAV_KEY = 'sf_rel_favoritos';
        var RECENT_KEY = 'sf_rel_recentes';
        function norm(s) { return (s || '').toLowerCase(); }
        function load(k) { try { return JSON.parse(localStorage.getItem(k) || '[]'); } catch (e) { return []; } }
        function persist(k, a) { try { localStorage.setItem(k, JSON.stringify(a)); } catch (e) {} }
        function getFavs() { return load(FAV_KEY); }
        function getRecents() { return load(RECENT_KEY); }
        function toggleFav(t) { var f = getFavs(), i = f.indexOf(t); if (i === -1) { f.push(t); } else { f.splice(i, 1); } persist(FAV_KEY, f); }
        function recordRecent(t) {
            if (!t || t.indexOf('fiscal_') === 0) return;
            var r = getRecents().filter(function (x) { return x !== t; });
            r.unshift(t); if (r.length > 8) { r = r.slice(0, 8); }
            persist(RECENT_KEY, r);
        }
        function cardType(card) {
            var btn = card.querySelector('button[onclick*="ReportForm"]');
            var m = btn && /ReportForm\('([^']+)'/.exec(btn.getAttribute('onclick') || '');
            return m ? m[1] : '';
        }

        document.addEventListener('DOMContentLoaded', function () {
            var activeCat = 'todos';
            var sections = Array.prototype.slice.call(document.querySelectorAll('.dashboard-section'));
            sections.forEach(function (sec) {
                if (sec.dataset.cat) return;
                var h2 = sec.querySelector('h2');
                var t = norm(h2 ? h2.textContent : '');
                for (var i = 0; i < CAT_MAP.length; i++) {
                    if (t.indexOf(CAT_MAP[i].match) !== -1) { sec.dataset.cat = CAT_MAP[i].key; break; }
                }
            });

            // Marca o tipo em cada card e injeta a estrela de favorito (sem editar os cards).
            Array.prototype.slice.call(document.querySelectorAll('.report-card')).forEach(function (card) {
                var type = cardType(card);
                card.dataset.reportType = type;
                if (!type) return;
                card.style.position = 'relative';
                var star = document.createElement('button');
                star.type = 'button';
                star.className = 'report-fav-star';
                star.setAttribute('aria-label', 'Favoritar relatório');
                star.style.cssText = 'position:absolute;top:12px;right:12px;background:none;border:none;cursor:pointer;font-size:15px;color:#f59e0b;z-index:2;padding:2px;';
                function paint() {
                    var on = getFavs().indexOf(type) !== -1;
                    star.innerHTML = on ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
                    star.style.opacity = on ? '1' : '.5';
                }
                paint();
                star.addEventListener('click', function (e) {
                    e.stopPropagation();
                    toggleFav(type); paint();
                    if (activeCat === 'favoritos') { apply(); }
                });
                card.appendChild(star);
            });

            var chips = Array.prototype.slice.call(document.querySelectorAll('.report-chip'));
            var searchInput = document.getElementById('reportSearch');
            var clearBtn = document.getElementById('reportSearchClear');
            var emptyEl = document.getElementById('reportsEmpty');

            function apply() {
                var q = norm(searchInput ? searchInput.value.trim() : '');
                var special = (activeCat === 'favoritos' || activeCat === 'recentes');
                var set = activeCat === 'favoritos' ? getFavs() : (activeCat === 'recentes' ? getRecents() : null);
                var anyVisible = false;
                sections.forEach(function (sec) {
                    if (!sec.querySelector('.report-card')) return;
                    var catOk = special ? true : (activeCat === 'todos' || sec.dataset.cat === activeCat);
                    var cards = Array.prototype.slice.call(sec.querySelectorAll('.report-card'));
                    var visibleCards = 0;
                    cards.forEach(function (card) {
                        var typeOk = !special || (set.indexOf(card.dataset.reportType) !== -1);
                        var match = q === '' || norm(card.textContent).indexOf(q) !== -1;
                        var show = catOk && typeOk && match;
                        card.classList.toggle('is-hidden', !show);
                        if (show) visibleCards++;
                    });
                    var showSec = visibleCards > 0;
                    sec.classList.toggle('is-hidden', !showSec);
                    if (showSec) anyVisible = true;
                });
                if (emptyEl) {
                    emptyEl.hidden = anyVisible;
                    emptyEl.innerHTML = (activeCat === 'favoritos' && getFavs().length === 0)
                        ? '<i class="fas fa-star"></i> Nenhum favorito ainda. Clique na estrela de um relatório para marcá-lo.'
                        : (activeCat === 'recentes' && getRecents().length === 0)
                            ? '<i class="fas fa-clock-rotate-left"></i> Nenhum relatório usado recentemente.'
                            : '<i class="fas fa-folder-open"></i> Nenhum relatório encontrado para a sua busca.';
                }
                if (clearBtn) clearBtn.hidden = (q === '');
            }
            window.__sfReportsApply = apply;

            chips.forEach(function (chip) {
                chip.addEventListener('click', function () {
                    chips.forEach(function (c) { c.classList.remove('active'); });
                    chip.classList.add('active');
                    activeCat = chip.dataset.cat || 'todos';
                    apply();
                });
            });
            if (searchInput) searchInput.addEventListener('input', apply);
            if (clearBtn) clearBtn.addEventListener('click', function () {
                if (searchInput) { searchInput.value = ''; searchInput.focus(); }
                apply();
            });

            // Registra "recentes" ao escolher um relatório (envolve o showReportForm existente).
            if (typeof window.showReportForm === 'function') {
                var _origShow = window.showReportForm;
                window.showReportForm = function (type, fmt) { recordRecent(type); return _origShow(type, fmt); };
            }

            // Deep-link: ?cat=financeiros abre já filtrado.
            try {
                var cat = new URLSearchParams(window.location.search).get('cat');
                if (cat) {
                    for (var i = 0; i < chips.length; i++) {
                        if (chips[i].dataset.cat === cat) { chips[i].click(); break; }
                    }
                }
            } catch (e) { /* ignora */ }

            apply();
        });
    })();
    </script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
    <script src="../js/sf-charts-premium.js"></script>
    
    <!-- JavaScript Files -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    
    <script>
        function showReportForm(reportType, format) {
            document.getElementById('reportType').value = reportType;
            document.getElementById('reportFormat').value = format;
            var formEl = document.getElementById('reportForm');
            var overlayEl = document.getElementById('overlay');
            formEl.classList.add('active');
            overlayEl.classList.add('active');
            formEl.setAttribute('aria-hidden', 'false');
            overlayEl.setAttribute('aria-hidden', 'false');
            
            // Set current month and year as default
            const now = new Date();
            document.getElementById('month').value = now.getMonth() + 1;
            document.getElementById('year').value = now.getFullYear();
            
            requestAnimationFrame(function() {
                var m = document.getElementById('month');
                if (m) m.focus({ preventScroll: true });
            });
        }
        
        function hideReportForm() {
            var formEl = document.getElementById('reportForm');
            var overlayEl = document.getElementById('overlay');
            formEl.classList.remove('active');
            overlayEl.classList.remove('active');
            formEl.setAttribute('aria-hidden', 'true');
            overlayEl.setAttribute('aria-hidden', 'true');
        }
        
        // Função para relatórios fiscais - com seleção de período
        function showFiscalReportForm(reportType, format) {
            // Usar o mesmo modal dos outros relatórios
            document.getElementById('reportType').value = 'fiscal_' + reportType;
            document.getElementById('reportFormat').value = format;
            var formEl = document.getElementById('reportForm');
            var overlayEl = document.getElementById('overlay');
            formEl.classList.add('active');
            overlayEl.classList.add('active');
            formEl.setAttribute('aria-hidden', 'false');
            overlayEl.setAttribute('aria-hidden', 'false');
            
            // Set current month and year as default
            const now = new Date();
            document.getElementById('month').value = now.getMonth() + 1;
            document.getElementById('year').value = now.getFullYear();
            
            requestAnimationFrame(function() {
                var m = document.getElementById('month');
                if (m) m.focus({ preventScroll: true });
            });
        }
        
        // Close form when clicking overlay
        document.getElementById('overlay').addEventListener('click', hideReportForm);
        
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            var formEl = document.getElementById('reportForm');
            if (!formEl || !formEl.classList.contains('active')) return;
            hideReportForm();
        });
        
        (function initRelatoriosReportActions() {
            function apply() {
                document.querySelectorAll('.report-card').forEach(function (card) {
                    var h3 = card.querySelector('h3');
                    var title = h3 ? h3.textContent.replace(/\s+/g, ' ').trim() : 'Relatório';
                    card.querySelectorAll('.report-actions button.btn-pdf, .report-actions button.btn-excel').forEach(function (btn) {
                        btn.setAttribute('type', 'button');
                        var vis = (btn.textContent || '').replace(/\s+/g, ' ').trim();
                        btn.setAttribute('aria-label', title + (vis ? ' — ' + vis : ''));
                    });
                    card.querySelectorAll('.report-actions a.btn-pdf[href]').forEach(function (a) {
                        var vis = (a.textContent || '').replace(/\s+/g, ' ').trim();
                        a.setAttribute('aria-label', (vis || 'Abrir') + ' (' + title + ')');
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', apply);
            } else {
                apply();
            }
        })();
        
        // Handle form submission
        document.getElementById('generateReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        // Relatórios automáticos — usam o modal de pré-visualização (custom) e exportam PDF/Excel/CSV.
        function generateAutoReport(reportType) {
            var names = { gamificacao_semanal: 'Gamificação Semanal', ranking_mensal: 'Ranking Mensal', performance_geral: 'Performance Geral' };
            window.__previewMode = 'auto';
            window.__autoReportType = reportType;
            document.getElementById('reportPreviewTitle').textContent = names[reportType] || 'Relatório';
            document.getElementById('reportPreviewMeta').textContent = 'Gerado sob demanda';
            document.getElementById('reportPreviewCount').textContent = '';
            document.getElementById('reportPreviewChartWrap').hidden = true;
            document.getElementById('reportPreviewTableWrap').innerHTML = '';
            document.getElementById('reportPreviewLoading').style.display = 'block';
            document.getElementById('reportPreviewOverlay').classList.add('active');
            document.getElementById('reportPreviewModal').classList.add('active');
            document.getElementById('reportPreviewOverlay').setAttribute('aria-hidden', 'false');
            document.getElementById('reportPreviewModal').setAttribute('aria-hidden', 'false');

            fetch('../api/auto_reports.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=generate_report&report_type=' + encodeURIComponent(reportType)
            }).then(function (r) { return r.json(); })
              .then(function (data) {
                  document.getElementById('reportPreviewLoading').style.display = 'none';
                  if (data && data.success) {
                      document.getElementById('reportPreviewTableWrap').innerHTML = data.html;
                  } else {
                      document.getElementById('reportPreviewTableWrap').innerHTML = '<div class="report-preview-empty"><i class="fas fa-folder-open"></i> Não foi possível gerar o relatório.</div>';
                  }
              }).catch(function () {
                  document.getElementById('reportPreviewLoading').style.display = 'none';
                  document.getElementById('reportPreviewTableWrap').innerHTML = '<div class="report-preview-empty">Erro de conexão. Tente novamente.</div>';
              });
        }

        // Compat: download direto (PDF).
        function downloadAutoReport(reportType) {
            window.open('../api/auto_reports.php?action=download&report_type=' + encodeURIComponent(reportType) + '&formato=pdf', '_blank');
        }
        function closeAutoReportModal() { if (window.closeReportPreview) { window.closeReportPreview(); } }
    </script>
    
    <style>
    .auto-report-card {
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        height: 100%;
    }
    
    .auto-report-card:hover {
        border-color: #007bff;
        background: #e3f2fd;
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,123,255,0.2);
    }
    
    .auto-report-icon {
        font-size: 2.5rem;
        color: #007bff;
        margin-bottom: 15px;
    }
    
    .auto-report-card h6 {
        color: #333;
        font-weight: bold;
        margin-bottom: 10px;
    }
    
    .auto-report-card p {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
    }
    
    .auto-report-card small {
        color: #999;
        font-size: 12px;
    }
    
    </style>
    <script>
        // Indicadores de Desempenho foram movidos para a página BI (bi.php)
        (function() {
            // placeholder para evitar erro se algum código antigo chamar
            window.loadPerformanceIndicators = function() { window.location.href = 'bi.php?visao=geral'; };
        })();
    </script>

    <!-- Pré-visualização + exportação (tabela, gráfico, PDF/Excel/CSV) -->
    <script>
    (function () {
        var previewChart = null;
        function el(id) { return document.getElementById(id); }
        function csrf() {
            var i = document.querySelector('#generateReportForm input[name="csrf_token"]');
            return i ? i.value : '';
        }

        window.previewReport = function () {
            window.__previewMode = 'tabular';
            var type = el('reportType').value;
            if (!type) { return; }
            if (type.indexOf('fiscal_') === 0) {
                alert('A pré-visualização não está disponível para relatórios fiscais. Use PDF ou Excel.');
                return;
            }
            openPreview();
            el('reportPreviewLoading').style.display = 'block';
            el('reportPreviewTableWrap').innerHTML = '';
            el('reportPreviewChartWrap').hidden = true;

            var body = new URLSearchParams();
            body.set('action', 'preview');
            body.set('report_type', type);
            body.set('month', el('month').value);
            body.set('year', el('year').value);
            body.set('format', 'pdf');
            var pm = el('periodoModo'); if (pm) { body.set('periodo_modo', pm.value); }
            var di = el('dataInicio'); if (di) { body.set('data_inicio', di.value); }
            var df = el('dataFim'); if (df) { body.set('data_fim', df.value); }
            var fv = el('filtroVeiculo'); if (fv) { body.set('filtro_veiculo', fv.value); }
            var fm = el('filtroMotorista'); if (fm) { body.set('filtro_motorista', fm.value); }
            var cp = el('compararPeriodo'); if (cp && cp.checked) { body.set('comparar', '1'); }
            body.set('csrf_token', csrf());

            fetch('relatorios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            }).then(function (r) { return r.json(); })
              .then(renderPreview)
              .catch(function () {
                  el('reportPreviewLoading').style.display = 'none';
                  el('reportPreviewTableWrap').innerHTML = '<div class="report-preview-empty">Não foi possível carregar a pré-visualização. Tente novamente.</div>';
              });
        };

        function renderPreview(res) {
            el('reportPreviewLoading').style.display = 'none';
            el('reportPreviewTitle').textContent = (res && res.title) ? res.title : 'Pré-visualização';
            el('reportPreviewMeta').textContent = 'Período: ' + ((res && res.periodo) ? res.periodo : '');
            el('reportPreviewCount').textContent = ((res && res.count) ? res.count : 0) + ' registro(s)';
            var tableWrap = el('reportPreviewTableWrap');
            var chartWrap = el('reportPreviewChartWrap');

            if (!res || !res.success || !res.columns || !res.columns.length || !res.rows || !res.rows.length) {
                tableWrap.innerHTML = '<div class="report-preview-empty"><i class="fas fa-folder-open"></i> Nenhum dado encontrado para o período selecionado.</div>';
                chartWrap.hidden = true;
                return;
            }

            var table = document.createElement('table');
            var thead = document.createElement('thead');
            var htr = document.createElement('tr');
            res.columns.forEach(function (c) { var th = document.createElement('th'); th.textContent = c; htr.appendChild(th); });
            thead.appendChild(htr); table.appendChild(thead);
            var tbody = document.createElement('tbody');
            res.rows.forEach(function (row) {
                var tr = document.createElement('tr');
                row.forEach(function (v) { var td = document.createElement('td'); td.textContent = (v === null || v === undefined) ? '' : v; tr.appendChild(td); });
                tbody.appendChild(tr);
            });
            if (res.totals) {
                var trt = document.createElement('tr'); trt.className = 'report-total-row';
                res.totals.forEach(function (v) { var td = document.createElement('td'); td.textContent = (v === null || v === undefined) ? '' : v; trt.appendChild(td); });
                tbody.appendChild(trt);
            }
            table.appendChild(tbody);
            tableWrap.innerHTML = ''; tableWrap.appendChild(table);

            // Comparativo com período anterior (acima da tabela).
            if (res.comparison && res.comparison.linhas && res.comparison.linhas.length) {
                var cw = document.createElement('div');
                cw.className = 'report-compare-wrap';
                var h = document.createElement('h4');
                h.textContent = 'Comparativo com o período anterior (' + (res.comparison.prev_label || '') + ')';
                cw.appendChild(h);
                var ct = document.createElement('table');
                ct.innerHTML = '<thead><tr><th>Métrica</th><th>Atual</th><th>Anterior</th><th>Variação</th></tr></thead>';
                var cb = document.createElement('tbody');
                res.comparison.linhas.forEach(function (l) {
                    function cell(txt, cls) { var td = document.createElement('td'); td.textContent = txt; if (cls) { td.className = cls; } return td; }
                    var tr = document.createElement('tr');
                    tr.appendChild(cell(l.metrica));
                    tr.appendChild(cell(l.atual));
                    tr.appendChild(cell(l.anterior));
                    tr.appendChild(cell(l.variacao, l.up === true ? 'report-var-up' : (l.up === false ? 'report-var-down' : '')));
                    cb.appendChild(tr);
                });
                ct.appendChild(cb);
                cw.appendChild(ct);
                tableWrap.insertBefore(cw, tableWrap.firstChild);
            }

            if (previewChart) { previewChart.destroy(); previewChart = null; }
            if (res.chart && res.chart.labels && res.chart.labels.length > 1 && typeof Chart !== 'undefined') {
                chartWrap.hidden = false;
                previewChart = new Chart(el('reportPreviewChart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: res.chart.labels, datasets: [{ label: res.chart.label, data: res.chart.values, backgroundColor: 'rgba(59,130,246,.65)', borderColor: '#3b82f6', borderWidth: 1 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
                });
            } else {
                chartWrap.hidden = true;
            }
        }

        window.exportFromPreview = function (fmt) {
            el('reportFormat').value = fmt;
            el('generateReportForm').submit();
        };

        // Dispatcher dos botões do rodapé: relatório tabular vs. relatório automático.
        window.doPreviewExport = function (fmt) {
            if (window.__previewMode === 'auto' && window.__autoReportType) {
                window.open('../api/auto_reports.php?action=export&report_type='
                    + encodeURIComponent(window.__autoReportType) + '&formato=' + fmt, '_blank');
            } else {
                window.exportFromPreview(fmt);
            }
        };

        function fmtDate(d) {
            var mm = ('0' + (d.getMonth() + 1)).slice(-2), dd = ('0' + d.getDate()).slice(-2);
            return d.getFullYear() + '-' + mm + '-' + dd;
        }
        function setPeriodoModo(modo) {
            if (el('periodoModo')) { el('periodoModo').value = modo; }
            var isRange = (modo === 'intervalo');
            if (el('periodoMes')) { el('periodoMes').hidden = isRange; }
            if (el('periodoIntervalo')) { el('periodoIntervalo').hidden = !isRange; }
            document.querySelectorAll('.periodo-modo-btn').forEach(function (b) {
                b.classList.toggle('active', b.dataset.modo === modo);
            });
            if (isRange && el('dataInicio') && !el('dataInicio').value) { setPreset('mes_atual'); }
        }
        function setPreset(kind) {
            var now = new Date(), y = now.getFullYear(), m = now.getMonth(), di, df;
            if (kind === 'mes_atual') { di = new Date(y, m, 1); df = new Date(y, m + 1, 0); }
            else if (kind === 'mes_passado') { di = new Date(y, m - 1, 1); df = new Date(y, m, 0); }
            else if (kind === '30d') { df = new Date(); di = new Date(); di.setDate(di.getDate() - 29); }
            else if (kind === '90d') { df = new Date(); di = new Date(); di.setDate(di.getDate() - 89); }
            else if (kind === 'ano') { di = new Date(y, 0, 1); df = new Date(y, 11, 31); }
            if (di && df && el('dataInicio') && el('dataFim')) {
                el('dataInicio').value = fmtDate(di);
                el('dataFim').value = fmtDate(df);
            }
        }
        window.setPeriodoModo = setPeriodoModo;
        window.setPreset = setPreset;

        function openPreview() {
            el('reportPreviewOverlay').classList.add('active');
            el('reportPreviewModal').classList.add('active');
            el('reportPreviewOverlay').setAttribute('aria-hidden', 'false');
            el('reportPreviewModal').setAttribute('aria-hidden', 'false');
        }
        window.closeReportPreview = function () {
            el('reportPreviewOverlay').classList.remove('active');
            el('reportPreviewModal').classList.remove('active');
            el('reportPreviewOverlay').setAttribute('aria-hidden', 'true');
            el('reportPreviewModal').setAttribute('aria-hidden', 'true');
            if (previewChart) { previewChart.destroy(); previewChart = null; }
        };
        var ov = el('reportPreviewOverlay');
        if (ov) { ov.addEventListener('click', window.closeReportPreview); }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && el('reportPreviewModal').classList.contains('active')) { window.closeReportPreview(); }
        });
    })();
    </script>

    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'relatorios'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 