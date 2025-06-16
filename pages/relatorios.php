<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Set page title
$page_title = "Relatórios";

// Função para gerar relatório em PDF
function generatePDF($html, $filename) {
    try {
        require_once '../vendor/autoload.php';
        
        error_log("Iniciando geração do PDF: $filename");
        error_log("HTML a ser convertido: " . substr($html, 0, 500) . "...");
        
        $mpdf = new \Mpdf\Mpdf([
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
        
        error_log("PDF gerado com sucesso: $filename");
        return $mpdf->Output($filename . '.pdf', 'D');
    } catch(Exception $e) {
        error_log("ERRO ao gerar PDF $filename: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("Erro ao gerar PDF: " . $e->getMessage());
    }
}

// Função para gerar relatório em Excel
function generateExcel($data, $filename) {
    try {
        require_once '../vendor/autoload.php';
        
        error_log("Iniciando geração do Excel: $filename");
        error_log("Quantidade de registros: " . count($data));
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Adicionar cabeçalhos
        $headers = array_keys($data[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
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
        
        error_log("Excel gerado com sucesso: $filename");
        
        // Configurar cabeçalho HTTP para download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch(Exception $e) {
        error_log("ERRO ao gerar Excel $filename: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("Erro ao gerar Excel: " . $e->getMessage());
    }
}

// Função para buscar dados do relatório
function getReportData($reportType, $month, $year) {
    try {
        $conn = getConnection();
        $data = [];
        
        // Log inicial
        error_log("Iniciando geração do relatório: $reportType para $month/$year");
        
        switch ($reportType) {
            case 'rotas':
                $sql = "SELECT r.id, v.placa, v.modelo, m.nome as motorista,
                        r.data_saida, r.data_chegada, 
                        CONCAT(r.estado_origem, ' - ', r.cidade_origem_id) as origem,
                        CONCAT(r.estado_destino, ' - ', r.cidade_destino_id) as destino,
                        r.distancia_km, r.frete, r.comissao, r.km_vazio,
                        r.total_km, r.percentual_vazio, r.eficiencia_viagem,
                        r.peso_carga, r.descricao_carga, r.status
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id
                        JOIN motoristas m ON m.id = r.motorista_id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        ORDER BY r.data_saida DESC";
                break;
                
            case 'despesas_viagem':
                $sql = "SELECT r.id AS id_viagem, 
                        CONCAT(r.estado_origem, ' - ', r.estado_destino) as rota,
                        SUM(dv.total_despviagem) AS total_despesas,
                        SUM(dv.arla) as total_arla,
                        SUM(dv.pedagios) as total_pedagios,
                        SUM(dv.caixinha) as total_caixinha,
                        SUM(dv.estacionamento) as total_estacionamento,
                        SUM(dv.lavagem) as total_lavagem,
                        SUM(dv.borracharia) as total_borracharia,
                        SUM(dv.eletrica_mecanica) as total_eletrica_mecanica,
                        SUM(dv.adiantamento) as total_adiantamento,
                        COUNT(dv.id) as quantidade_despesas
                        FROM despesas_viagem dv
                        JOIN rotas r ON r.id = dv.rota_id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        GROUP BY r.id, r.estado_origem, r.estado_destino
                        ORDER BY total_despesas DESC";
                break;
                
            case 'despesas_fixas':
                $sql = "SELECT df.*
                        FROM despesas_fixas df
                        WHERE YEAR(df.data_pagamento) = :year 
                        AND MONTH(df.data_pagamento) = :month";
                break;
                
            case 'manutencoes':
                $sql = "SELECT m.*, v.placa, v.modelo
                        FROM manutencoes m
                        JOIN veiculos v ON v.id = m.veiculo_id
                        WHERE YEAR(m.data_manutencao) = :year 
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
                        WHERE YEAR(a.data_abastecimento) = :year 
                        AND MONTH(a.data_abastecimento) = :month
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY total_valor DESC";
                break;
                
            case 'lucro_viagem':
                $sql = "SELECT r.id, 
                        CONCAT(r.estado_origem, ' - ', r.estado_destino) as rota,
                        r.frete, 
                        IFNULL(SUM(dv.total_despviagem), 0) AS total_despesas,
                        (r.frete - IFNULL(SUM(dv.total_despviagem), 0)) AS lucro_estimado
                        FROM rotas r
                        LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        GROUP BY r.id, r.estado_origem, r.estado_destino, r.frete
                        ORDER BY r.data_saida DESC";
                break;
                
            case 'lucro_total':
                $sql = "SELECT 
                        SUM(r.frete) AS total_frete,
                        IFNULL(SUM(dv.total_despviagem), 0) AS total_despesas,
                        (SUM(r.frete) - IFNULL(SUM(dv.total_despviagem), 0)) AS lucro_liquido
                        FROM rotas r
                        LEFT JOIN despesas_viagem dv ON dv.rota_id = r.id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month";
                break;
                
            case 'pneus':
                $sql = "SELECT pm.*, v.placa, p.numero_serie, p.marca, p.modelo, p.medida,
                        tm.nome as tipo_manutencao
                        FROM pneu_manutencao pm
                        JOIN veiculos v ON v.id = pm.veiculo_id
                        JOIN pneus p ON p.id = pm.pneu_id
                        JOIN tipos_manutencao tm ON tm.id = pm.tipo_manutencao_id
                        WHERE YEAR(pm.data_manutencao) = :year 
                        AND MONTH(pm.data_manutencao) = :month
                        ORDER BY pm.data_manutencao DESC";
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
                        GROUP BY v.status_id
                        ORDER BY quantidade DESC";
                break;
                
            case 'quilometragem':
                $sql = "SELECT v.id, v.placa, 
                        SUM(r.total_km) AS total_km
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        GROUP BY v.id, v.placa
                        ORDER BY total_km DESC";
                break;
                
            case 'recebimentos':
                $sql = "SELECT ec.id, ec.razao_social, SUM(r.frete) AS total_recebido
                        FROM rotas r
                        JOIN empresa_clientes ec ON ec.id = r.empresa_id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        GROUP BY ec.id, ec.razao_social
                        ORDER BY total_recebido DESC";
                break;
                
            case 'contas_pagar':
                $sql = "SELECT cp.*
                        FROM contas_pagar cp
                        WHERE YEAR(cp.data_vencimento) = :year 
                        AND MONTH(cp.data_vencimento) = :month";
                break;
                
            case 'contas_pagas':
                $sql = "SELECT cp.*, fp.nome as forma_pagamento, b.nome as banco
                        FROM contas_pagar cp
                        LEFT JOIN formas_pagamento fp ON fp.id = cp.forma_pagamento_id
                        LEFT JOIN bancos b ON b.id = cp.banco_id
                        WHERE cp.status_id = 2 -- Assumindo que 2 é o ID para status 'PAGO'
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
                        WHERE YEAR(f.data_inicio) = :year 
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
                        WHERE YEAR(m.data_manutencao) = :year 
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
                        LEFT JOIN rotas r ON r.veiculo_id = v.id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        GROUP BY v.id, v.placa, v.modelo
                        ORDER BY total_frete DESC";
                break;
                
            default:
                throw new Exception("Tipo de relatório inválido");
        }
        
        // Log da query
        error_log("Query SQL para $reportType: $sql");
        
        $stmt = $conn->prepare($sql);
        
        // Adicionar parâmetros apenas se o relatório precisar de mês/ano
        if (!in_array($reportType, ['veiculos_status'])) {
            $stmt->bindParam(':year', $year, PDO::PARAM_INT);
            $stmt->bindParam(':month', $month, PDO::PARAM_INT);
            error_log("Parâmetros para $reportType - Ano: $year, Mês: $month");
        }
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Log do resultado
        error_log("Quantidade de registros encontrados para $reportType: " . count($data));
        if (empty($data)) {
            error_log("AVISO: Nenhum dado encontrado para o relatório $reportType");
        }
        
        return $data;
    } catch(PDOException $e) {
        error_log("ERRO SQL ao gerar relatório $reportType: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw new Exception("Erro ao gerar relatório: " . $e->getMessage());
    } catch(Exception $e) {
        error_log("ERRO ao gerar relatório $reportType: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

// Processar requisição de relatório
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $reportType = $_POST['report_type'] ?? '';
        $month = $_POST['month'] ?? date('m');
        $year = $_POST['year'] ?? date('Y');
        $format = $_POST['format'] ?? 'pdf';
        
        error_log("Iniciando processamento do relatório: $reportType em formato $format para $month/$year");
        
        $data = getReportData($reportType, $month, $year);
        
        if ($format === 'pdf') {
            // Gerar HTML para PDF
            $html = '<h1>Relatório de ' . ucfirst(str_replace('_', ' ', $reportType)) . '</h1>';
            $html .= '<p>Período: ' . date('m/Y', mktime(0, 0, 0, $month, 1, $year)) . '</p>';
            
            if (!empty($data)) {
                $html .= '<table>';
                $html .= '<tr>';
                foreach (array_keys($data[0]) as $header) {
                    $html .= '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
                }
                $html .= '</tr>';
                
                foreach ($data as $row) {
                    $html .= '<tr>';
                    foreach ($row as $value) {
                        $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                    }
                    $html .= '</tr>';
                }
                $html .= '</table>';
            } else {
                $html .= '<p>Nenhum dado encontrado para o período selecionado.</p>';
            }
            
            generatePDF($html, 'relatorio_' . $reportType . '_' . $month . '_' . $year);
        } else {
            generateExcel($data, 'relatorio_' . $reportType . '_' . $month . '_' . $year);
        }
        
        error_log("Relatório $reportType gerado com sucesso");
    } catch (Exception $e) {
        error_log("ERRO ao processar relatório: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error'] = $e->getMessage();
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
    
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .report-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 20px;
            transition: transform 0.2s;
        }
        
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
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
        
        .btn-excel {
            background-color: #28a745;
            color: white;
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
    </style>
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
                <div class="dashboard-header">
                    <h1>Relatórios</h1>
                </div>
                
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
                    
                    <!-- Relatório de Pneus -->
                    <div class="report-card">
                        <h3>Relatório de Pneus</h3>
                        <p>Todas as manutenções ou trocas de pneus no mês.</p>
                        <div class="report-actions">
                            <button class="btn-pdf" onclick="showReportForm('pneus', 'pdf')">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                            <button class="btn-excel" onclick="showReportForm('pneus', 'excel')">
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
            
            <!-- Footer -->
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>
    
    <!-- Report Form Modal -->
    <div class="overlay" id="overlay"></div>
    <div class="report-form" id="reportForm">
        <h2>Gerar Relatório</h2>
        <form id="generateReportForm" method="POST">
            <input type="hidden" id="reportType" name="report_type">
            <input type="hidden" id="reportFormat" name="format">
            
            <div class="form-group">
                <label for="month">Mês</label>
                <select id="month" name="month" required>
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
                <select id="year" name="year" required>
                    <?php
                    $currentYear = date('Y');
                    for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
                        echo "<option value=\"$year\">$year</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="hideReportForm()">Cancelar</button>
                <button type="submit" class="btn-primary">Gerar Relatório</button>
            </div>
        </form>
    </div>
    
    <!-- JavaScript Files -->
    <script src="../js/header.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    
    <script>
        function showReportForm(reportType, format) {
            document.getElementById('reportType').value = reportType;
            document.getElementById('reportFormat').value = format;
            document.getElementById('reportForm').classList.add('active');
            document.getElementById('overlay').classList.add('active');
            
            // Set current month and year as default
            const now = new Date();
            document.getElementById('month').value = now.getMonth() + 1;
            document.getElementById('year').value = now.getFullYear();
        }
        
        function hideReportForm() {
            document.getElementById('reportForm').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }
        
        // Close form when clicking overlay
        document.getElementById('overlay').addEventListener('click', hideReportForm);
        
        // Handle form submission
        document.getElementById('generateReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
    </script>
</body>
</html> 