<?php
/**
 * Sistema de Relatórios Automáticos
 * Implementa geração e envio automático de relatórios
 */

class AutoReportManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Gerar relatório semanal de gamificação
     */
    public function generateWeeklyGamificationReport($empresa_id) {
        try {
            $week_start = date('Y-m-d', strtotime('monday this week'));
            $week_end = date('Y-m-d', strtotime('sunday this week'));
            
            $sql = "SELECT 
                        m.nome as motorista_nome,
                        g.pontos_totais,
                        g.nivel_atual,
                        g.rotas_concluidas,
                        g.streak_atual,
                        COALESCE(COUNT(r.id), 0) as rotas_semana
                    FROM gamificacao_motoristas g
                    JOIN motoristas m ON g.motorista_id = m.id
                    LEFT JOIN rotas r ON m.id = r.motorista_id 
                        AND r.data_rota BETWEEN :week_start AND :week_end
                        AND r.status IN ('aprovado', 'finalizada')
                    WHERE g.empresa_id = :empresa_id
                    GROUP BY g.id, m.id
                    ORDER BY g.pontos_totais DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':week_start', $week_start);
            $stmt->bindParam(':week_end', $week_end);
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'type' => 'gamificacao_semanal',
                'period' => $week_start . ' a ' . $week_end,
                'data' => $data,
                'summary' => [
                    'total_motoristas' => count($data),
                    'total_pontos' => array_sum(array_column($data, 'pontos_totais')),
                    'media_pontos' => count($data) > 0 ? round(array_sum(array_column($data, 'pontos_totais')) / count($data), 2) : 0,
                    'total_rotas' => array_sum(array_column($data, 'rotas_semana'))
                ]
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório semanal de gamificação: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar relatório mensal de ranking
     */
    public function generateMonthlyRankingReport($empresa_id) {
        try {
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');
            
            $sql = "SELECT 
                        m.nome as motorista_nome,
                        r.nota_total,
                        r.posicao_ranking,
                        r.nota_consumo,
                        r.nota_pontualidade,
                        r.nota_multas,
                        r.nota_eficiencia,
                        r.total_rotas,
                        r.rotas_pontuais
                    FROM ranking_motoristas r
                    JOIN motoristas m ON r.motorista_id = m.id
                    WHERE r.empresa_id = :empresa_id
                    ORDER BY r.posicao_ranking ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'type' => 'ranking_mensal',
                'period' => $month_start . ' a ' . $month_end,
                'data' => $data,
                'summary' => [
                    'total_motoristas' => count($data),
                    'media_nota' => count($data) > 0 ? round(array_sum(array_column($data, 'nota_total')) / count($data), 2) : 0,
                    'total_rotas' => array_sum(array_column($data, 'total_rotas')),
                    'rotas_pontuais' => array_sum(array_column($data, 'rotas_pontuais'))
                ]
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório mensal de ranking: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar relatório de performance geral
     */
    public function generatePerformanceReport($empresa_id) {
        try {
            $month_start = date('Y-m-01');
            $month_end = date('Y-m-t');
            
            // Dados de rotas
            $sql_rotas = "SELECT 
                            COUNT(*) as total_rotas,
                            COUNT(CASE WHEN no_prazo = 1 THEN 1 END) as rotas_pontuais,
                            AVG(eficiencia_viagem) as eficiencia_media,
                            SUM(frete) as total_frete
                          FROM rotas 
                          WHERE empresa_id = :empresa_id 
                          AND data_rota BETWEEN :month_start AND :month_end
                          AND status IN ('aprovado', 'finalizada')";
            
            $stmt = $this->conn->prepare($sql_rotas);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':month_start', $month_start);
            $stmt->bindParam(':month_end', $month_end);
            $stmt->execute();
            $rotas_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Dados de multas
            $sql_multas = "SELECT COUNT(*) as total_multas
                          FROM multas m
                          JOIN motoristas mot ON m.motorista_id = mot.id
                          WHERE mot.empresa_id = :empresa_id 
                          AND m.data_infracao BETWEEN :month_start AND :month_end";
            
            $stmt = $this->conn->prepare($sql_multas);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':month_start', $month_start);
            $stmt->bindParam(':month_end', $month_end);
            $stmt->execute();
            $multas_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Dados de abastecimentos
            $sql_abastecimentos = "SELECT 
                                    COUNT(*) as total_abastecimentos,
                                    SUM(litros) as total_litros,
                                    SUM(valor_total) as total_valor
                                  FROM abastecimentos a
                                  JOIN rotas r ON a.rota_id = r.id
                                  WHERE r.empresa_id = :empresa_id 
                                  AND a.data_abastecimento BETWEEN :month_start AND :month_end";
            
            $stmt = $this->conn->prepare($sql_abastecimentos);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':month_start', $month_start);
            $stmt->bindParam(':month_end', $month_end);
            $stmt->execute();
            $abastecimentos_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'type' => 'performance_geral',
                'period' => $month_start . ' a ' . $month_end,
                'data' => [
                    'rotas' => $rotas_data,
                    'multas' => $multas_data,
                    'abastecimentos' => $abastecimentos_data
                ],
                'summary' => [
                    'total_rotas' => $rotas_data['total_rotas'],
                    'taxa_pontualidade' => $rotas_data['total_rotas'] > 0 ? 
                        round(($rotas_data['rotas_pontuais'] / $rotas_data['total_rotas']) * 100, 2) : 0,
                    'eficiencia_media' => round($rotas_data['eficiencia_media'], 2),
                    'total_multas' => $multas_data['total_multas'],
                    'total_abastecimentos' => $abastecimentos_data['total_abastecimentos'],
                    'total_litros' => $abastecimentos_data['total_litros'],
                    'total_valor_combustivel' => $abastecimentos_data['total_valor']
                ]
            ];
        } catch (Exception $e) {
            error_log("Erro ao gerar relatório de performance: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Gerar HTML do relatório
     */
    public function generateReportHTML($report_data) {
        $html = '<div class="auto-report">';
        $html .= '<div class="report-header">';
        $html .= '<h3>📊 Relatório Automático - ' . ucfirst(str_replace('_', ' ', $report_data['type'])) . '</h3>';
        $html .= '<p class="report-period">Período: ' . $report_data['period'] . '</p>';
        $html .= '<p class="report-date">Gerado em: ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';
        
        $html .= '<div class="report-summary">';
        $html .= '<h4>📈 Resumo Executivo</h4>';
        $html .= '<div class="summary-grid">';
        
        foreach ($report_data['summary'] as $key => $value) {
            $label = ucfirst(str_replace('_', ' ', $key));
            $html .= '<div class="summary-item">';
            $html .= '<span class="summary-label">' . $label . ':</span>';
            $html .= '<span class="summary-value">' . $value . '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</div>';
        
        if (isset($report_data['data']) && is_array($report_data['data'])) {
            $html .= '<div class="report-details">';
            $html .= '<h4>📋 Detalhes</h4>';
            
            if ($report_data['type'] === 'gamificacao_semanal') {
                $html .= $this->generateGamificationTable($report_data['data']);
            } elseif ($report_data['type'] === 'ranking_mensal') {
                $html .= $this->generateRankingTable($report_data['data']);
            } elseif ($report_data['type'] === 'performance_geral') {
                $html .= $this->generatePerformanceTable($report_data['data']);
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Gerar tabela de gamificação
     */
    private function generateGamificationTable($data) {
        $html = '<table class="table table-striped">';
        $html .= '<thead><tr><th>Posição</th><th>Motorista</th><th>Pontos</th><th>Nível</th><th>Rotas</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($data as $index => $motorista) {
            $html .= '<tr>';
            $html .= '<td>' . ($index + 1) . '</td>';
            $html .= '<td>' . $motorista['motorista_nome'] . '</td>';
            $html .= '<td>' . $motorista['pontos_totais'] . '</td>';
            $html .= '<td>' . $motorista['nivel_atual'] . '</td>';
            $html .= '<td>' . $motorista['rotas_semana'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Gerar tabela de ranking
     */
    private function generateRankingTable($data) {
        $html = '<table class="table table-striped">';
        $html .= '<thead><tr><th>Posição</th><th>Motorista</th><th>Nota Total</th><th>Consumo</th><th>Pontualidade</th><th>Multas</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($data as $motorista) {
            $html .= '<tr>';
            $html .= '<td>' . $motorista['posicao_ranking'] . '</td>';
            $html .= '<td>' . $motorista['motorista_nome'] . '</td>';
            $html .= '<td>' . $motorista['nota_total'] . '</td>';
            $html .= '<td>' . $motorista['nota_consumo'] . '</td>';
            $html .= '<td>' . $motorista['nota_pontualidade'] . '</td>';
            $html .= '<td>' . $motorista['nota_multas'] . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table>';
        return $html;
    }
    
    /**
     * Gerar tabela de performance
     */
    private function generatePerformanceTable($data) {
        $html = '<div class="performance-metrics">';
        
        $html .= '<div class="metric-card">';
        $html .= '<h5>🚛 Rotas</h5>';
        $html .= '<p>Total: ' . $data['rotas']['total_rotas'] . '</p>';
        $html .= '<p>Pontuais: ' . $data['rotas']['rotas_pontuais'] . '</p>';
        $html .= '<p>Eficiência Média: ' . round($data['rotas']['eficiencia_media'], 2) . '%</p>';
        $html .= '</div>';
        
        $html .= '<div class="metric-card">';
        $html .= '<h5>⚠️ Multas</h5>';
        $html .= '<p>Total: ' . $data['multas']['total_multas'] . '</p>';
        $html .= '</div>';
        
        $html .= '<div class="metric-card">';
        $html .= '<h5>⛽ Abastecimentos</h5>';
        $html .= '<p>Total: ' . $data['abastecimentos']['total_abastecimentos'] . '</p>';
        $html .= '<p>Litros: ' . $data['abastecimentos']['total_litros'] . '</p>';
        $html .= '<p>Valor: R$ ' . number_format($data['abastecimentos']['total_valor'], 2, ',', '.') . '</p>';
        $html .= '</div>';
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Obter CSS para relatórios
     */
    public static function getCSS() {
        return '
        <style>
        .auto-report {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .report-header {
            border-bottom: 2px solid #007bff;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .report-header h3 {
            color: #007bff;
            margin: 0;
        }
        
        .report-period, .report-date {
            margin: 5px 0;
            color: #666;
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .summary-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }
        
        .summary-label {
            font-weight: bold;
            color: #333;
        }
        
        .summary-value {
            color: #007bff;
            font-weight: bold;
            float: right;
        }
        
        .performance-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .metric-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #28a745;
        }
        
        .metric-card h5 {
            color: #28a745;
            margin-bottom: 15px;
        }
        
        .metric-card p {
            margin: 8px 0;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .summary-grid, .performance-metrics {
                grid-template-columns: 1fr;
            }
        }
        </style>';
    }
}
?>
