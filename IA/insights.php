<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class Insights {
    private $pdo;
    private $empresa_id;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
    }

    /**
     * Analisa padrões de consumo de combustível
     */
    public function analisarPadroesConsumo() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                DATE_FORMAT(a.data_abastecimento, '%Y-%m') as mes,
                SUM(a.litros) as total_litros,
                SUM(a.valor_total) as total_valor,
                COUNT(DISTINCT a.data_abastecimento) as num_abastecimentos,
                AVG(a.litros) as media_litros
            FROM veiculos v
            JOIN abastecimentos a ON v.id = a.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY v.id, DATE_FORMAT(a.data_abastecimento, '%Y-%m')
            ORDER BY v.placa, mes";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar se há dados suficientes
            if (count($dados) == 0) {
                return [];
            }

            $insights = [];
            $consumo_por_veiculo = [];

            // Agrupa dados por veículo
            foreach ($dados as $registro) {
                if (!isset($consumo_por_veiculo[$registro['placa']])) {
                    $consumo_por_veiculo[$registro['placa']] = [
                        'modelo' => $registro['modelo'],
                        'consumos' => []
                    ];
                }
                $consumo_por_veiculo[$registro['placa']]['consumos'][] = $registro['total_litros'];
            }

            // Analisa variações significativas
            foreach ($consumo_por_veiculo as $placa => $dados) {
                $consumos = $dados['consumos'];
                if (count($consumos) >= 3) {
                    $media = array_sum($consumos) / count($consumos);
                    
                    // Evitar divisão por zero
                    if ($media > 0) {
                        $variacao = abs(($consumos[count($consumos)-1] - $media) / $media * 100);

                        if ($variacao > 20) {
                            $tendencia = $consumos[count($consumos)-1] > $media ? 'aumento' : 'redução';
                            $insights[] = [
                                'tipo' => 'consumo',
                                'prioridade' => 'media',
                                'mensagem' => "Variação significativa no consumo de combustível do veículo {$placa}",
                                'veiculo' => $placa,
                                'modelo' => $dados['modelo'],
                                'dados' => [
                                    'variacao' => number_format($variacao, 1) . '%',
                                    'tendencia' => $tendencia,
                                    'media_mensal' => number_format($media, 1) . ' litros',
                                    'ultimo_mes' => number_format($consumos[count($consumos)-1], 1) . ' litros'
                                ],
                                'data_criacao' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                }
            }

            return $insights;
        } catch (PDOException $e) {
            error_log("Erro ao analisar padrões de consumo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa padrões de manutenção
     */
    public function analisarPadroesManutencao() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                m.descricao as tipo_manutencao,
                COUNT(m.id) as num_manutencoes,
                SUM(m.valor) as total_gasto,
                AVG(DATEDIFF(m.data_manutencao, m.data_cadastro)) as media_intervalo
            FROM veiculos v
            JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY v.id, m.descricao
            HAVING num_manutencoes > 1";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar se há dados suficientes
            if (count($dados) == 0) {
                return [];
            }

            $insights = [];
            $manutencoes_por_tipo = [];

            // Agrupa dados por tipo de manutenção
            foreach ($dados as $registro) {
                if (!isset($manutencoes_por_tipo[$registro['tipo_manutencao']])) {
                    $manutencoes_por_tipo[$registro['tipo_manutencao']] = [
                        'total_gasto' => 0,
                        'num_manutencoes' => 0,
                        'veiculos' => []
                    ];
                }
                $manutencoes_por_tipo[$registro['tipo_manutencao']]['total_gasto'] += $registro['total_gasto'];
                $manutencoes_por_tipo[$registro['tipo_manutencao']]['num_manutencoes'] += $registro['num_manutencoes'];
                $manutencoes_por_tipo[$registro['tipo_manutencao']]['veiculos'][] = [
                    'placa' => $registro['placa'],
                    'modelo' => $registro['modelo'],
                    'gasto' => $registro['total_gasto'],
                    'intervalo' => $registro['media_intervalo']
                ];
            }

            // Analisa padrões de manutenção
            foreach ($manutencoes_por_tipo as $tipo => $dados) {
                if ($dados['num_manutencoes'] >= 5) {
                    $media_gasto = $dados['total_gasto'] / $dados['num_manutencoes'];
                    $media_intervalo = array_sum(array_column($dados['veiculos'], 'intervalo')) / count($dados['veiculos']);

                    $insights[] = [
                        'tipo' => 'manutencao',
                        'prioridade' => 'media',
                        'mensagem' => "Padrão identificado em manutenções do tipo {$tipo}",
                        'dados' => [
                            'tipo_manutencao' => $tipo,
                            'total_gasto' => number_format($dados['total_gasto'], 2),
                            'num_manutencoes' => $dados['num_manutencoes'],
                            'media_gasto' => number_format($media_gasto, 2),
                            'media_intervalo' => number_format($media_intervalo, 1) . ' dias',
                            'veiculos_afetados' => count($dados['veiculos'])
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $insights;
        } catch (PDOException $e) {
            error_log("Erro ao analisar padrões de manutenção: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa padrões de rotas
     */
    public function analisarPadroesRotas() {
        try {
            $sql = "SELECT 
                CONCAT(r.estado_origem, ' - ', r.cidade_origem_id) as origem,
                CONCAT(r.estado_destino, ' - ', r.cidade_destino_id) as destino,
                COUNT(DISTINCT r.veiculo_id) as num_veiculos,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada)) as tempo_medio,
                AVG(r.distancia_km) as distancia_media,
                COUNT(DISTINCT r.data_saida) as num_viagens,
                GROUP_CONCAT(DISTINCT v.placa) as veiculos
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY r.estado_origem, r.cidade_origem_id, r.estado_destino, r.cidade_destino_id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar se há dados suficientes
            if (count($dados) == 0) {
                return [];
            }

            $insights = [];
            $rotas_por_origem = [];
            $rotas_por_destino = [];

            // Analisa padrões de origem e destino
            foreach ($dados as $rota) {
                if (!isset($rotas_por_origem[$rota['origem']])) {
                    $rotas_por_origem[$rota['origem']] = 0;
                }
                if (!isset($rotas_por_destino[$rota['destino']])) {
                    $rotas_por_destino[$rota['destino']] = 0;
                }
                $rotas_por_origem[$rota['origem']]++;
                $rotas_por_destino[$rota['destino']]++;

                // Analisa eficiência da rota
                if ($rota['num_viagens'] >= 10) {
                    $insights[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'media',
                        'mensagem' => "Rota frequente identificada: {$rota['origem']} -> {$rota['destino']}",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'dados' => [
                            'num_viagens' => $rota['num_viagens'],
                            'tempo_medio' => number_format($rota['tempo_medio'] / 60, 1) . ' horas',
                            'distancia_media' => number_format($rota['distancia_media'], 1) . ' km',
                            'veiculos_utilizados' => $rota['num_veiculos']
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            // Identifica origens e destinos mais frequentes
            arsort($rotas_por_origem);
            arsort($rotas_por_destino);

            $top_origens = array_slice($rotas_por_origem, 0, 3, true);
            $top_destinos = array_slice($rotas_por_destino, 0, 3, true);

            foreach ($top_origens as $origem => $frequencia) {
                if ($frequencia >= 5) {
                    $insights[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'baixa',
                        'mensagem' => "Origem mais frequente: {$origem}",
                        'dados' => [
                            'origem' => $origem,
                            'frequencia' => $frequencia,
                            'tipo' => 'origem'
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            foreach ($top_destinos as $destino => $frequencia) {
                if ($frequencia >= 5) {
                    $insights[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'baixa',
                        'mensagem' => "Destino mais frequente: {$destino}",
                        'dados' => [
                            'destino' => $destino,
                            'frequencia' => $frequencia,
                            'tipo' => 'destino'
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $insights;
        } catch (PDOException $e) {
            error_log("Erro ao analisar padrões de rotas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Analisa custos operacionais
     */
    public function analisarCustosOperacionais() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                SUM(a.valor_total) as total_combustivel,
                SUM(m.valor) as total_manutencao,
                COUNT(DISTINCT a.id) as num_abastecimentos,
                COUNT(DISTINCT m.id) as num_manutencoes
            FROM veiculos v
            LEFT JOIN abastecimentos a ON v.id = a.veiculo_id 
                AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id 
                AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            WHERE v.empresa_id = :empresa_id
            GROUP BY v.id
            HAVING total_combustivel > 0 OR total_manutencao > 0";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar se há dados suficientes
            if (count($dados) == 0) {
                return [];
            }

            $insights = [];
            $custos_por_veiculo = [];

            foreach ($dados as $registro) {
                $total_custo = $registro['total_combustivel'] + $registro['total_manutencao'];
                $custos_por_veiculo[$registro['placa']] = [
                    'modelo' => $registro['modelo'],
                    'total_custo' => $total_custo,
                    'combustivel' => $registro['total_combustivel'],
                    'manutencao' => $registro['total_manutencao']
                ];
            }

            // Calcula média de custos
            $media_custo = array_sum(array_column($custos_por_veiculo, 'total_custo')) / count($custos_por_veiculo);

            // Identifica veículos com custos acima da média
            foreach ($custos_por_veiculo as $placa => $dados) {
                if ($dados['total_custo'] > $media_custo * 1.5) {
                    $insights[] = [
                        'tipo' => 'custo',
                        'prioridade' => 'alta',
                        'mensagem' => "Veículo {$placa} com custos operacionais elevados",
                        'veiculo' => $placa,
                        'modelo' => $dados['modelo'],
                        'dados' => [
                            'total_custo' => number_format($dados['total_custo'], 2),
                            'combustivel' => number_format($dados['combustivel'], 2),
                            'manutencao' => number_format($dados['manutencao'], 2),
                            'media_frota' => number_format($media_custo, 2)
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $insights;
        } catch (PDOException $e) {
            error_log("Erro ao analisar custos operacionais: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém todos os insights
     */
    public function obterTodosInsights() {
        try {
            $insights = array_merge(
                $this->analisarPadroesConsumo(),
                $this->analisarPadroesManutencao(),
                $this->analisarPadroesRotas(),
                $this->analisarCustosOperacionais()
            );

            // Ordenar por prioridade (alta, media, baixa)
            usort($insights, function($a, $b) {
                $prioridades = ['alta' => 3, 'media' => 2, 'baixa' => 1];
                return $prioridades[$b['prioridade']] - $prioridades[$a['prioridade']];
            });

            return $insights;
        } catch (Exception $e) {
            error_log("Erro ao obter todos os insights: " . $e->getMessage());
            return [];
        }
    }
} 