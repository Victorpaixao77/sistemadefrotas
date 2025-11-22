<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/auto_reports.php';

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

// Função para gerar relatório em PDF
function generatePDF($html, $filename) {
    try {
        require_once '../vendor/autoload.php';
        
        error_log("Iniciando geração do PDF: $filename");
        error_log("HTML a ser convertido: " . substr($html, 0, 500) . "...");
        
        // Criar diretório temporário se não existir
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
        
        if (empty($data)) {
            throw new Exception("Nenhum dado disponível para gerar o relatório Excel");
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
                        SUM(dv.descarga) as total_descarga,
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
                // Lucro por viagem: Frete - Comissão - Despesas Viagem - Abastecimentos
                $sql = "SELECT r.id, 
                        CONCAT(r.estado_origem, ' - ', r.estado_destino) as rota,
                        r.data_saida,
                        v.placa,
                        r.frete, 
                        r.comissao,
                        -- Despesas de viagem (subconsulta para não multiplicar linhas)
                        (
                            SELECT COALESCE(SUM(total_despviagem), 0)
                            FROM despesas_viagem dv
                            WHERE dv.rota_id = r.id
                        ) AS total_despesas_viagem,
                        -- Abastecimentos (subconsulta)
                        (
                            SELECT COALESCE(SUM(a.valor_total), 0)
                            FROM abastecimentos a
                            WHERE a.veiculo_id = r.veiculo_id
                              AND DATE(a.data_abastecimento) = DATE(r.data_saida)
                        ) AS total_abastecimentos,
                        -- Lucro Bruto (Frete - Comissão)
                        (r.frete - COALESCE(r.comissao, 0)) AS lucro_bruto,
                        -- Lucro Líquido (Frete - Comissão - Despesas - Abastecimentos)
                        (
                            r.frete - COALESCE(r.comissao, 0) - 
                            COALESCE((
                                SELECT SUM(total_despviagem)
                                FROM despesas_viagem dv
                                WHERE dv.rota_id = r.id
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total)
                                FROM abastecimentos a
                                WHERE a.veiculo_id = r.veiculo_id
                                  AND DATE(a.data_abastecimento) = DATE(r.data_saida)
                            ), 0)
                        ) AS lucro_liquido
                        FROM rotas r
                        JOIN veiculos v ON v.id = r.veiculo_id
                        WHERE YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                        ORDER BY r.data_saida DESC";
                break;
                
            case 'lucro_total':
                // Lucro total do período: Frete - Comissão - Despesas Viagem - Abastecimentos
                // Usando valores literais nas subconsultas para evitar erro de parâmetros duplicados
                $sql = "SELECT 
                        -- Totais
                        COALESCE(SUM(r.frete), 0) AS total_frete,
                        COALESCE(SUM(r.comissao), 0) AS total_comissao,
                        -- Despesas de viagem (subconsulta com valores literais)
                        (
                            SELECT COALESCE(SUM(dv.total_despviagem), 0)
                            FROM despesas_viagem dv
                            WHERE dv.rota_id IN (
                                SELECT id FROM rotas 
                                WHERE YEAR(data_saida) = " . intval($year) . "
                                AND MONTH(data_saida) = " . intval($month) . "
                            )
                        ) AS total_despesas_viagem,
                        -- Abastecimentos (subconsulta com valores literais)
                        (
                            SELECT COALESCE(SUM(a.valor_total), 0)
                            FROM abastecimentos a
                            WHERE YEAR(a.data_abastecimento) = " . intval($year) . "
                            AND MONTH(a.data_abastecimento) = " . intval($month) . "
                        ) AS total_abastecimentos,
                        -- Lucro Bruto (Frete - Comissão)
                        (COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0)) AS lucro_bruto,
                        -- Lucro Líquido (Frete - Comissão - Despesas - Abastecimentos)
                        (
                            COALESCE(SUM(r.frete), 0) - COALESCE(SUM(r.comissao), 0) - 
                            COALESCE((
                                SELECT SUM(dv.total_despviagem)
                                FROM despesas_viagem dv
                                WHERE dv.rota_id IN (
                                    SELECT id FROM rotas 
                                    WHERE YEAR(data_saida) = " . intval($year) . "
                                    AND MONTH(data_saida) = " . intval($month) . "
                                )
                            ), 0) - 
                            COALESCE((
                                SELECT SUM(a.valor_total)
                                FROM abastecimentos a
                                WHERE YEAR(a.data_abastecimento) = " . intval($year) . "
                                AND MONTH(a.data_abastecimento) = " . intval($month) . "
                            ), 0)
                        ) AS lucro_liquido
                        FROM rotas r
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
                        LEFT JOIN rotas r ON v.id = r.veiculo_id 
                            AND YEAR(r.data_saida) = :year_rota 
                            AND MONTH(r.data_saida) = :month_rota
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
                        LEFT JOIN rotas r ON v.id = r.veiculo_id 
                            AND YEAR(r.data_saida) = :year_rota 
                            AND MONTH(r.data_saida) = :month_rota
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
                            WHEN m.valor > (SELECT AVG(valor) FROM manutencoes) THEN 'Acima da Média'
                            WHEN m.valor < (SELECT AVG(valor) FROM manutencoes) THEN 'Abaixo da Média'
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
                    LEFT JOIN rotas r ON m.id = r.motorista_id 
                        AND YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
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
                    LEFT JOIN rotas r ON v.id = r.veiculo_id 
                        AND YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                    LEFT JOIN abastecimentos a ON v.id = a.veiculo_id 
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
                        r.data_saida,
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
                        AND YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                    GROUP BY r.id, r.estado_origem, r.estado_destino, r.distancia_km, r.data_saida, v.placa, m.nome, r.frete, r.comissao
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
                    LEFT JOIN rotas r ON v.id = r.veiculo_id 
                        AND YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
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
                        COALESCE(MAX(r.data_saida), 'Nunca') as ultima_viagem,
                        DATEDIFF(CURDATE(), COALESCE(MAX(r.data_saida), v.created_at)) as dias_sem_viagem
                    FROM veiculos v
                    LEFT JOIN rotas r ON v.id = r.veiculo_id 
                        AND YEAR(r.data_saida) = :year 
                        AND MONTH(r.data_saida) = :month
                    WHERE v.empresa_id = :empresa_id
                    GROUP BY v.id, v.placa, v.modelo, v.marca, v.km_atual, v.created_at
                    HAVING COUNT(r.id) = 0 OR MAX(r.data_saida) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
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
                
            default:
                throw new Exception("Tipo de relatório inválido");
        }
        
        // Log da query
        error_log("Query SQL para $reportType: $sql");
        
        $stmt = $conn->prepare($sql);
        
        // Adicionar parâmetros apenas se o relatório precisar de mês/ano
        if (!in_array($reportType, ['veiculos_status', 'vida_util_pneus', 'otimizacao_pneus', 'analise_preditiva'])) {
            if ($reportType === 'eficiencia_frota') {
                $stmt->bindParam(':year_rota', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_rota', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year_abastecimento', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_abastecimento', $month, PDO::PARAM_INT);
            } elseif ($reportType === 'custos_veiculo') {
                $stmt->bindParam(':year_abastecimento', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_abastecimento', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year_manutencao', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_manutencao', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year_rota', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_rota', $month, PDO::PARAM_INT);
                $stmt->bindParam(':year_despesa', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month_despesa', $month, PDO::PARAM_INT);
            } else {
                $stmt->bindParam(':year', $year, PDO::PARAM_INT);
                $stmt->bindParam(':month', $month, PDO::PARAM_INT);
            }
            error_log("Parâmetros para $reportType - Ano: $year, Mês: $month");
        }
        
        // Adicionar empresa_id para relatórios que precisam
        if (in_array($reportType, ['vida_util_pneus', 'custos_veiculo', 'eficiencia_frota', 'historico_manutencoes', 'analise_preditiva', 'otimizacao_pneus', 'produtividade_motoristas', 'consumo_combustivel', 'custo_por_km', 'rentabilidade_rotas', 'multas_motorista_veiculo', 'ocupacao_frota', 'veiculos_ociosos', 'custos_manutencao_veiculo'])) {
            $empresa_id = $_SESSION['empresa_id'];
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            error_log("Parâmetro empresa_id para $reportType: $empresa_id");
        }
        
        // Adicionar parâmetros duplicados para relatórios específicos
        if ($reportType === 'custo_por_km') {
            $stmt->bindParam(':year2', $year, PDO::PARAM_INT);
            $stmt->bindParam(':month2', $month, PDO::PARAM_INT);
        }
        
        // Executar a query
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
        
        // Verificar se é um relatório fiscal
        if (strpos($reportType, 'fiscal_') === 0) {
            // Relatório fiscal - redirecionar para API fiscal
            $tipoFiscal = str_replace('fiscal_', '', $reportType);
            $dataInicio = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
            $dataFim = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
            
            // Redirecionar para a API fiscal com caminho absoluto
            $url = "/sistema-frotas/fiscal/api/relatorios_fiscais.php?" . http_build_query([
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
        
        if ($format === 'pdf') {
            // Gerar HTML para PDF
            $html = '<h1>Relatório de ' . ucfirst(str_replace('_', ' ', $reportType)) . '</h1>';
            $html .= '<p>Período: ' . date('m/Y', mktime(0, 0, 0, $month, 1, $year)) . '</p>';
            
            if (!empty($data)) {
                $html .= '<table>';
                $html .= '<tr>';
                
                // Cabeçalhos
                $firstRow = is_array($data[0]) ? $data[0] : (count($data) > 0 ? $data[0] : []);
                if (!empty($firstRow)) {
                    foreach (array_keys($firstRow) as $header) {
                        $html .= '<th>' . ucfirst(str_replace('_', ' ', $header)) . '</th>';
                    }
                    $html .= '</tr>';
                    
                    // Dados
                    foreach ($data as $row) {
                        $html .= '<tr>';
                        foreach ($row as $value) {
                            $html .= '<td>' . htmlspecialchars($value ?? '') . '</td>';
                        }
                        $html .= '</tr>';
                    }
                } else {
                    $html .= '<tr><td>Nenhum dado disponível</td></tr>';
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
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
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
        
        .dashboard-section {
            margin-bottom: 40px;
        }
        
        .dashboard-section h2 {
            color: var(--text-primary);
            font-size: 1.5rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .dashboard-section h2 i {
            color: var(--primary-color);
            font-size: 1.3rem;
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
                        
                        <!-- Relatório de Desempenho -->
                        <div class="report-card">
                            <h3>Relatório de Desempenho</h3>
                            <p>Performance geral dos veículos e motoristas.</p>
                            <div class="report-actions">
                                <button class="btn-pdf" onclick="showReportForm('desempenho', 'pdf')">
                                    <i class="fas fa-file-pdf"></i> PDF
                                </button>
                                <button class="btn-excel" onclick="showReportForm('desempenho', 'excel')">
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
                
                <!-- Seção de Relatórios com IA -->
                <div class="dashboard-section">
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
            
            <!-- Relatórios Automáticos -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-robot"></i> Relatórios Automáticos
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('gamificacao_semanal')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <h6>Gamificação Semanal</h6>
                                        <p>Relatório automático de pontos e níveis dos motoristas</p>
                                        <small class="text-muted">Gerado toda segunda-feira</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('ranking_mensal')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <h6>Ranking Mensal</h6>
                                        <p>Relatório de performance e ranking dos motoristas</p>
                                        <small class="text-muted">Gerado todo dia 1º do mês</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="auto-report-card" onclick="generateAutoReport('performance_geral')">
                                        <div class="auto-report-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <h6>Performance Geral</h6>
                                        <p>Relatório completo de performance da frota</p>
                                        <small class="text-muted">Gerado todo dia 1º do mês</small>
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
        
        // Função para relatórios fiscais - com seleção de período
        function showFiscalReportForm(reportType, format) {
            // Usar o mesmo modal dos outros relatórios
            document.getElementById('reportType').value = 'fiscal_' + reportType;
            document.getElementById('reportFormat').value = format;
            document.getElementById('reportForm').classList.add('active');
            document.getElementById('overlay').classList.add('active');
            
            // Set current month and year as default
            const now = new Date();
            document.getElementById('month').value = now.getMonth() + 1;
            document.getElementById('year').value = now.getFullYear();
        }
        
        // Close form when clicking overlay
        document.getElementById('overlay').addEventListener('click', hideReportForm);
        
        // Handle form submission
        document.getElementById('generateReportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            this.submit();
        });
        
        // Função para gerar relatórios automáticos
        function generateAutoReport(reportType) {
            const reportNames = {
                'gamificacao_semanal': 'Gamificação Semanal',
                'ranking_mensal': 'Ranking Mensal',
                'performance_geral': 'Performance Geral'
            };
            
            if (confirm(`Gerar relatório de ${reportNames[reportType]}?`)) {
                // Mostrar loading
                const loadingHtml = `
                    <div class="text-center p-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Carregando...</span>
                        </div>
                        <p class="mt-2">Gerando relatório...</p>
                    </div>
                `;
                
                // Criar modal para mostrar o relatório
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.id = 'autoReportModal';
                modal.innerHTML = `
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${reportNames[reportType]}</h5>
                                <button type="button" class="close" onclick="closeAutoReportModal()">
                                    <span>&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" id="reportContent">
                                ${loadingHtml}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeAutoReportModal()">Fechar</button>
                                <button type="button" class="btn btn-primary" onclick="downloadAutoReport('${reportType}')">Download PDF</button>
                            </div>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                
                // Tentar usar Bootstrap 5 nativo primeiro, depois jQuery como fallback
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modalInstance = new bootstrap.Modal(modal);
                        modalInstance.show();
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modal).modal('show');
                    } else {
                        // Fallback para JavaScript puro
                        modal.style.display = 'block';
                        modal.classList.add('show');
                        document.body.classList.add('modal-open');
                        
                        // Adicionar backdrop
                        const backdrop = document.createElement('div');
                        backdrop.className = 'modal-backdrop fade show';
                        backdrop.id = 'modalBackdrop';
                        document.body.appendChild(backdrop);
                    }
                } catch (error) {
                    console.error('Erro ao abrir modal:', error);
                    // Fallback para JavaScript puro
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    document.body.classList.add('modal-open');
                }
                
                // Fazer requisição para gerar o relatório
                fetch('../api/auto_reports.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=generate_report&report_type=${reportType}&empresa_id=<?php echo $_SESSION['empresa_id']; ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('reportContent').innerHTML = data.html;
                    } else {
                        document.getElementById('reportContent').innerHTML = `
                            <div class="alert alert-danger">
                                <h5>Erro ao gerar relatório</h5>
                                <p>${data.error || 'Erro desconhecido'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('reportContent').innerHTML = `
                        <div class="alert alert-danger">
                            <h5>Erro de conexão</h5>
                            <p>Não foi possível gerar o relatório. Tente novamente.</p>
                        </div>
                    `;
                });
            }
        }
        
        // Função para download do relatório
        function downloadAutoReport(reportType) {
            window.open(`../api/auto_reports.php?action=download&report_type=${reportType}&empresa_id=<?php echo $_SESSION['empresa_id']; ?>`, '_blank');
        }
        
        // Função para fechar modal de relatório
        function closeAutoReportModal() {
            const modal = document.getElementById('autoReportModal');
            if (modal) {
                // Tentar usar Bootstrap 5 nativo primeiro
                try {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modalInstance = bootstrap.Modal.getInstance(modal);
                        if (modalInstance) {
                            modalInstance.hide();
                        } else {
                            const newInstance = new bootstrap.Modal(modal);
                            newInstance.hide();
                        }
                    } else if (typeof $ !== 'undefined' && $.fn.modal) {
                        $(modal).modal('hide');
                    } else {
                        // Fallback para JavaScript puro
                        modal.style.display = 'none';
                        modal.classList.remove('show');
                        document.body.classList.remove('modal-open');
                        
                        // Remover backdrop
                        const backdrop = document.getElementById('modalBackdrop');
                        if (backdrop) {
                            backdrop.remove();
                        }
                    }
                } catch (error) {
                    console.error('Erro ao fechar modal:', error);
                    // Fallback para JavaScript puro
                    modal.style.display = 'none';
                    modal.classList.remove('show');
                    document.body.classList.remove('modal-open');
                    
                    const backdrop = document.getElementById('modalBackdrop');
                    if (backdrop) {
                        backdrop.remove();
                    }
                }
                
                // Sempre remover o modal do DOM após um pequeno delay
                setTimeout(() => {
                    if (modal && modal.parentNode) {
                        modal.remove();
                    }
                    
                    // Limpar qualquer backdrop restante
                    const backdrops = document.querySelectorAll('.modal-backdrop');
                    backdrops.forEach(backdrop => backdrop.remove());
                    
                    // Restaurar scroll da página
                    document.body.style.overflow = 'auto';
                    document.body.classList.remove('modal-open');
                }, 300);
            }
        }
        
        // Adicionar event listeners para fechar modal
        document.addEventListener('click', function(e) {
            // Fechar ao clicar no botão X
            if (e.target.classList.contains('close') || e.target.closest('.close')) {
                closeAutoReportModal();
            }
            
            // Fechar ao clicar no backdrop
            if (e.target.classList.contains('modal') && e.target.id === 'autoReportModal') {
                closeAutoReportModal();
            }
            
            // Fechar ao clicar no botão "Fechar"
            if (e.target.textContent === 'Fechar' && e.target.classList.contains('btn-secondary')) {
                closeAutoReportModal();
            }
        });
        
        // Fechar com tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const modal = document.getElementById('autoReportModal');
                if (modal && modal.style.display !== 'none') {
                    closeAutoReportModal();
                }
            }
        });
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
</body>
</html> 