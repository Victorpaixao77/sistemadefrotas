<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class AnaliseAvancada {
    private $pdo;
    private $empresa_id;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
    }

    /**
     * Analisa a previsão de custos futuros baseado em padrões históricos
     */
    public function preverCustosFuturos() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                DATE_FORMAT(a.data_abastecimento, '%Y-%m') as mes,
                SUM(a.valor_total) as total_combustivel,
                SUM(m.valor) as total_manutencao,
                COUNT(DISTINCT a.data_abastecimento) as num_abastecimentos,
                COUNT(DISTINCT m.data_manutencao) as num_manutencoes
            FROM veiculos v
            LEFT JOIN abastecimentos a ON v.id = a.veiculo_id
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND (a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                OR m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH))
            GROUP BY v.id, DATE_FORMAT(a.data_abastecimento, '%Y-%m')
            ORDER BY v.placa, mes";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $previsoes = [];
            foreach ($dados as $registro) {
                // Calcula média móvel dos últimos 3 meses
                $media_combustivel = $this->calcularMediaMovel($dados, $registro['placa'], 'total_combustivel', 3);
                $media_manutencao = $this->calcularMediaMovel($dados, $registro['placa'], 'total_manutencao', 3);

                // Previsão para os próximos 3 meses
                $previsoes[] = [
                    'veiculo' => $registro['placa'],
                    'modelo' => $registro['modelo'],
                    'previsao_combustivel' => $media_combustivel * 3,
                    'previsao_manutencao' => $media_manutencao * 3,
                    'total_previsto' => ($media_combustivel + $media_manutencao) * 3,
                    'data_analise' => date('Y-m-d H:i:s')
                ];
            }

            return $previsoes;
        } catch (PDOException $e) {
            error_log("Erro ao prever custos futuros: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa a eficiência dos motoristas
     */
    public function analisarEficienciaMotoristas() {
        try {
            $sql = "SELECT 
                m.nome as motorista,
                v.placa,
                COUNT(DISTINCT r.id) as num_viagens,
                AVG(r.distancia_km) as media_distancia,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada)) as media_tempo,
                AVG(a.litros / r.distancia_km) as consumo_medio,
                COUNT(DISTINCT m2.id) as num_manutencoes
            FROM motoristas m
            JOIN rotas r ON m.id = r.motorista_id
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN abastecimentos a ON r.id = a.rota_id
            LEFT JOIN manutencoes m2 ON v.id = m2.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY m.id, v.id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analise = [];
            foreach ($dados as $registro) {
                $eficiencia = $this->calcularIndiceEficiencia($registro);
                $analise[] = [
                    'motorista' => $registro['motorista'],
                    'veiculo' => $registro['placa'],
                    'num_viagens' => $registro['num_viagens'],
                    'media_distancia' => $registro['media_distancia'],
                    'media_tempo' => $registro['media_tempo'],
                    'consumo_medio' => $registro['consumo_medio'],
                    'num_manutencoes' => $registro['num_manutencoes'],
                    'indice_eficiencia' => $eficiencia,
                    'data_analise' => date('Y-m-d H:i:s')
                ];
            }

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro ao analisar eficiência dos motoristas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa a vida útil dos veículos
     */
    public function analisarVidaUtilVeiculos() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                v.ano_fabricacao,
                v.km_atual,
                COUNT(DISTINCT m.id) as num_manutencoes,
                SUM(m.valor) as total_manutencao,
                COUNT(DISTINCT a.id) as num_abastecimentos,
                SUM(a.valor_total) as total_combustivel
            FROM veiculos v
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            LEFT JOIN abastecimentos a ON v.id = a.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND (m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                OR a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH))
            GROUP BY v.id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analise = [];
            foreach ($dados as $veiculo) {
                $idade = date('Y') - $veiculo['ano_fabricacao'];
                $custo_medio_mensal = ($veiculo['total_manutencao'] + $veiculo['total_combustivel']) / 12;
                $custo_por_km = ($veiculo['total_manutencao'] + $veiculo['total_combustivel']) / max($veiculo['km_atual'], 1);

                $analise[] = [
                    'veiculo' => $veiculo['placa'],
                    'modelo' => $veiculo['modelo'],
                    'idade' => $idade,
                    'km_atual' => $veiculo['km_atual'],
                    'num_manutencoes' => $veiculo['num_manutencoes'],
                    'custo_medio_mensal' => $custo_medio_mensal,
                    'custo_por_km' => $custo_por_km,
                    'recomendacao' => $this->gerarRecomendacaoVidaUtil($idade, $custo_medio_mensal, $custo_por_km),
                    'data_analise' => date('Y-m-d H:i:s')
                ];
            }

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro ao analisar vida útil dos veículos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa a otimização de rotas
     */
    public function analisarOtimizacaoRotas() {
        try {
            $sql = "SELECT 
                CONCAT(r.estado_origem, ' - ', r.cidade_origem_id) as origem,
                CONCAT(r.estado_destino, ' - ', r.cidade_destino_id) as destino,
                COUNT(DISTINCT r.id) as num_viagens,
                AVG(r.distancia_km) as distancia_media,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada)) as tempo_medio,
                AVG(a.litros / r.distancia_km) as consumo_medio,
                GROUP_CONCAT(DISTINCT v.placa) as veiculos
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN abastecimentos a ON r.id = a.rota_id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY r.estado_origem, r.cidade_origem_id, r.estado_destino, r.cidade_destino_id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $analise = [];
            foreach ($dados as $rota) {
                $eficiencia = $this->calcularEficienciaRota($rota);
                $analise[] = [
                    'origem' => $rota['origem'],
                    'destino' => $rota['destino'],
                    'num_viagens' => $rota['num_viagens'],
                    'distancia_media' => $rota['distancia_media'],
                    'tempo_medio' => $rota['tempo_medio'],
                    'consumo_medio' => $rota['consumo_medio'],
                    'veiculos' => explode(',', $rota['veiculos']),
                    'indice_eficiencia' => $eficiencia,
                    'sugestoes_otimizacao' => $this->gerarSugestoesOtimizacao($rota, $eficiencia),
                    'data_analise' => date('Y-m-d H:i:s')
                ];
            }

            return $analise;
        } catch (PDOException $e) {
            error_log("Erro ao analisar otimização de rotas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Métodos auxiliares
     */
    private function calcularMediaMovel($dados, $placa, $campo, $periodos) {
        $valores = array_filter($dados, function($item) use ($placa) {
            return $item['placa'] === $placa;
        });
        
        $valores = array_slice($valores, -$periodos);
        if (empty($valores)) return 0;
        
        $soma = array_sum(array_column($valores, $campo));
        return $soma / count($valores);
    }

    private function calcularIndiceEficiencia($dados) {
        // Fórmula: (num_viagens * media_distancia) / (media_tempo * consumo_medio * num_manutencoes)
        if ($dados['media_tempo'] == 0 || $dados['consumo_medio'] == 0) return 0;
        
        return ($dados['num_viagens'] * $dados['media_distancia']) / 
               ($dados['media_tempo'] * $dados['consumo_medio'] * max(1, $dados['num_manutencoes']));
    }

    private function gerarRecomendacaoVidaUtil($idade, $custo_medio_mensal, $custo_por_km) {
        if ($idade > 10 || $custo_medio_mensal > 5000 || $custo_por_km > 2) {
            return "Recomenda-se avaliar a substituição do veículo";
        } elseif ($idade > 7 || $custo_medio_mensal > 3000 || $custo_por_km > 1.5) {
            return "Monitorar custos e desempenho do veículo";
        } else {
            return "Veículo em boas condições de uso";
        }
    }

    private function calcularEficienciaRota($rota) {
        // Fórmula: (num_viagens * distancia_media) / (tempo_medio * consumo_medio)
        if ($rota['tempo_medio'] == 0 || $rota['consumo_medio'] == 0) return 0;
        
        return ($rota['num_viagens'] * $rota['distancia_media']) / 
               ($rota['tempo_medio'] * $rota['consumo_medio']);
    }

    private function gerarSugestoesOtimizacao($rota, $eficiencia) {
        $sugestoes = [];
        
        if ($eficiencia < 0.5) {
            $sugestoes[] = "Considerar rota alternativa";
            $sugestoes[] = "Avaliar horários de tráfego";
        }
        
        if ($rota['consumo_medio'] > 0.2) {
            $sugestoes[] = "Otimizar velocidade média";
            $sugestoes[] = "Verificar condições dos veículos";
        }
        
        if ($rota['tempo_medio'] > 4) {
            $sugestoes[] = "Considerar pontos de parada intermediários";
            $sugestoes[] = "Avaliar possibilidade de rotas mais curtas";
        }
        
        return $sugestoes;
    }
} 