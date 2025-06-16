<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class AnaliseDados {
    private $conn;
    private $empresa_id;

    public function __construct($empresa_id) {
        $this->conn = getConnection();
        $this->empresa_id = $empresa_id;
    }

    /**
     * Analisa o consumo de combustível
     */
    public function analisarConsumoCombustivel() {
        try {
            $query = "SELECT 
                        v.placa,
                        v.modelo,
                        SUM(a.quantidade_litros) as total_litros,
                        SUM(a.valor_total) as total_valor,
                        COUNT(DISTINCT a.data_abastecimento) as num_abastecimentos,
                        AVG(a.quantidade_litros) as media_litros,
                        AVG(a.valor_total) as media_valor
                    FROM abastecimentos a
                    JOIN veiculos v ON a.veiculo_id = v.id
                    WHERE v.empresa_id = :empresa_id
                    AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY v.id
                    ORDER BY total_litros DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Análise de padrões
            $analise = [
                'veiculos' => $resultados,
                'total_geral' => array_sum(array_column($resultados, 'total_valor')),
                'media_consumo' => array_sum(array_column($resultados, 'media_litros')) / count($resultados),
                'recomendacoes' => $this->gerarRecomendacoesConsumo($resultados)
            ];

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro na análise de consumo: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Analisa as manutenções
     */
    public function analisarManutencoes() {
        try {
            $query = "SELECT 
                        v.placa,
                        v.modelo,
                        COUNT(m.id) as num_manutencoes,
                        SUM(m.valor_total) as total_gasto,
                        MAX(m.data_manutencao) as ultima_manutencao,
                        GROUP_CONCAT(DISTINCT m.tipo_manutencao) as tipos_manutencao
                    FROM manutencoes m
                    JOIN veiculos v ON m.veiculo_id = v.id
                    WHERE v.empresa_id = :empresa_id
                    AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                    GROUP BY v.id
                    ORDER BY total_gasto DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Análise de padrões
            $analise = [
                'veiculos' => $resultados,
                'total_geral' => array_sum(array_column($resultados, 'total_gasto')),
                'media_manutencoes' => array_sum(array_column($resultados, 'num_manutencoes')) / count($resultados),
                'recomendacoes' => $this->gerarRecomendacoesManutencao($resultados)
            ];

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro na análise de manutenções: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Analisa as rotas
     */
    public function analisarRotas() {
        try {
            $query = "SELECT 
                        r.id,
                        r.origem,
                        r.destino,
                        COUNT(DISTINCT rv.veiculo_id) as num_veiculos,
                        AVG(rv.tempo_estimado) as tempo_medio,
                        AVG(rv.distancia) as distancia_media,
                        COUNT(DISTINCT rv.data_rota) as num_viagens
                    FROM rotas r
                    JOIN rotas_veiculos rv ON r.id = rv.rota_id
                    JOIN veiculos v ON rv.veiculo_id = v.id
                    WHERE v.empresa_id = :empresa_id
                    AND rv.data_rota >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY r.id
                    ORDER BY num_viagens DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Análise de padrões
            $analise = [
                'rotas' => $resultados,
                'total_viagens' => array_sum(array_column($resultados, 'num_viagens')),
                'media_tempo' => array_sum(array_column($resultados, 'tempo_medio')) / count($resultados),
                'recomendacoes' => $this->gerarRecomendacoesRotas($resultados)
            ];

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro na análise de rotas: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Gera recomendações baseadas no consumo
     */
    private function gerarRecomendacoesConsumo($dados) {
        $recomendacoes = [];
        
        foreach ($dados as $veiculo) {
            // Verifica consumo acima da média
            if ($veiculo['media_litros'] > $this->calcularMediaConsumo($dados)) {
                $recomendacoes[] = [
                    'tipo' => 'alerta',
                    'veiculo' => $veiculo['placa'],
                    'mensagem' => "Consumo de combustível acima da média. Recomenda-se verificar a eficiência do veículo."
                ];
            }
            
            // Verifica frequência de abastecimento
            if ($veiculo['num_abastecimentos'] > 15) {
                $recomendacoes[] = [
                    'tipo' => 'sugestao',
                    'veiculo' => $veiculo['placa'],
                    'mensagem' => "Alta frequência de abastecimentos. Considere otimizar as rotas para reduzir o consumo."
                ];
            }
        }
        
        return $recomendacoes;
    }

    /**
     * Gera recomendações baseadas nas manutenções
     */
    private function gerarRecomendacoesManutencao($dados) {
        $recomendacoes = [];
        
        foreach ($dados as $veiculo) {
            // Verifica manutenções frequentes
            if ($veiculo['num_manutencoes'] > 3) {
                $recomendacoes[] = [
                    'tipo' => 'alerta',
                    'veiculo' => $veiculo['placa'],
                    'mensagem' => "Número elevado de manutenções. Recomenda-se uma inspeção detalhada do veículo."
                ];
            }
            
            // Verifica gastos altos
            if ($veiculo['total_gasto'] > $this->calcularMediaGastos($dados)) {
                $recomendacoes[] = [
                    'tipo' => 'sugestao',
                    'veiculo' => $veiculo['placa'],
                    'mensagem' => "Gastos com manutenção acima da média. Considere avaliar a viabilidade de substituição do veículo."
                ];
            }
        }
        
        return $recomendacoes;
    }

    /**
     * Gera recomendações baseadas nas rotas
     */
    private function gerarRecomendacoesRotas($dados) {
        $recomendacoes = [];
        
        foreach ($dados as $rota) {
            // Verifica tempo de viagem
            if ($rota['tempo_medio'] > 120) { // mais de 2 horas
                $recomendacoes[] = [
                    'tipo' => 'sugestao',
                    'rota' => $rota['origem'] . ' - ' . $rota['destino'],
                    'mensagem' => "Tempo de viagem elevado. Considere buscar rotas alternativas."
                ];
            }
            
            // Verifica número de veículos
            if ($rota['num_veiculos'] < 2 && $rota['num_viagens'] > 10) {
                $recomendacoes[] = [
                    'tipo' => 'sugestao',
                    'rota' => $rota['origem'] . ' - ' . $rota['destino'],
                    'mensagem' => "Rota com alta frequência e poucos veículos. Considere distribuir entre mais veículos."
                ];
            }
        }
        
        return $recomendacoes;
    }

    /**
     * Calcula a média de consumo
     */
    private function calcularMediaConsumo($dados) {
        $soma = 0;
        $count = 0;
        
        foreach ($dados as $veiculo) {
            $soma += $veiculo['media_litros'];
            $count++;
        }
        
        return $count > 0 ? $soma / $count : 0;
    }

    /**
     * Calcula a média de gastos
     */
    private function calcularMediaGastos($dados) {
        $soma = 0;
        $count = 0;
        
        foreach ($dados as $veiculo) {
            $soma += $veiculo['total_gasto'];
            $count++;
        }
        
        return $count > 0 ? $soma / $count : 0;
    }
} 