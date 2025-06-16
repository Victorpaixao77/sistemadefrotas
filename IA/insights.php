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
                            ]
                        ];
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
                        ]
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
                r.cidade_origem_id as origem,
                r.cidade_destino_id as destino,
                COUNT(DISTINCT r.veiculo_id) as num_veiculos,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada)) as tempo_medio,
                AVG(r.distancia_km) as distancia_media,
                COUNT(DISTINCT r.data_rota) as num_viagens,
                GROUP_CONCAT(DISTINCT v.placa) as veiculos
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND r.data_rota >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY r.cidade_origem_id, r.cidade_destino_id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
                        'mensagem' => "Análise de eficiência da rota {$rota['origem']} -> {$rota['destino']}",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'dados' => [
                            'num_viagens' => $rota['num_viagens'],
                            'num_veiculos' => $rota['num_veiculos'],
                            'tempo_medio' => number_format($rota['tempo_medio'] / 60, 1) . ' horas',
                            'distancia_media' => number_format($rota['distancia_media'], 1) . ' km',
                            'veiculos' => explode(',', $rota['veiculos'])
                        ]
                    ];
                }
            }

            // Identifica pontos de concentração
            foreach ($rotas_por_origem as $origem => $count) {
                if ($count >= 5) {
                    $insights[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'baixa',
                        'mensagem' => "Ponto de concentração identificado: {$origem}",
                        'dados' => [
                            'tipo' => 'origem',
                            'local' => $origem,
                            'num_rotas' => $count
                        ]
                    ];
                }
            }

            foreach ($rotas_por_destino as $destino => $count) {
                if ($count >= 5) {
                    $insights[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'baixa',
                        'mensagem' => "Ponto de concentração identificado: {$destino}",
                        'dados' => [
                            'tipo' => 'destino',
                            'local' => $destino,
                            'num_rotas' => $count
                        ]
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
                COUNT(DISTINCT a.data_abastecimento) as num_abastecimentos,
                COUNT(DISTINCT m.data_manutencao) as num_manutencoes
            FROM veiculos v
            LEFT JOIN abastecimentos a ON v.id = a.veiculo_id
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND (a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                OR m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 12 MONTH))
            GROUP BY v.id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $insights = [];
            $custos_por_veiculo = [];

            // Calcula custos totais por veículo
            foreach ($dados as $registro) {
                $custo_total = $registro['total_combustivel'] + $registro['total_manutencao'];
                $custos_por_veiculo[] = [
                    'placa' => $registro['placa'],
                    'modelo' => $registro['modelo'],
                    'custo_total' => $custo_total,
                    'custo_combustivel' => $registro['total_combustivel'],
                    'custo_manutencao' => $registro['total_manutencao']
                ];
            }

            // Calcula médias
            $media_custo = array_sum(array_column($custos_por_veiculo, 'custo_total')) / count($custos_por_veiculo);
            $media_combustivel = array_sum(array_column($custos_por_veiculo, 'custo_combustivel')) / count($custos_por_veiculo);
            $media_manutencao = array_sum(array_column($custos_por_veiculo, 'custo_manutencao')) / count($custos_por_veiculo);

            // Identifica veículos com custos significativamente acima da média
            foreach ($custos_por_veiculo as $veiculo) {
                if ($veiculo['custo_total'] > $media_custo * 1.3) {
                    $insights[] = [
                        'tipo' => 'custo',
                        'prioridade' => 'alta',
                        'mensagem' => "Custos operacionais elevados para o veículo {$veiculo['placa']}",
                        'veiculo' => $veiculo['placa'],
                        'modelo' => $veiculo['modelo'],
                        'dados' => [
                            'custo_total' => number_format($veiculo['custo_total'], 2),
                            'custo_combustivel' => number_format($veiculo['custo_combustivel'], 2),
                            'custo_manutencao' => number_format($veiculo['custo_manutencao'], 2),
                            'media_frota' => number_format($media_custo, 2),
                            'diferenca_percentual' => number_format(($veiculo['custo_total'] / $media_custo - 1) * 100, 1) . '%'
                        ]
                    ];
                }
            }

            // Adiciona insight sobre a média da frota
            $insights[] = [
                'tipo' => 'custo',
                'prioridade' => 'baixa',
                'mensagem' => "Média de custos operacionais da frota",
                'dados' => [
                    'media_custo_total' => number_format($media_custo, 2),
                    'media_custo_combustivel' => number_format($media_combustivel, 2),
                    'media_custo_manutencao' => number_format($media_manutencao, 2),
                    'num_veiculos' => count($custos_por_veiculo)
                ]
            ];

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
        $insights = array_merge(
            $this->analisarPadroesConsumo(),
            $this->analisarPadroesManutencao(),
            $this->analisarPadroesRotas(),
            $this->analisarCustosOperacionais()
        );
        
        // Ordena por prioridade
        usort($insights, function($a, $b) {
            $prioridades = ['alta' => 1, 'media' => 2, 'baixa' => 3];
            return $prioridades[$a['prioridade']] - $prioridades[$b['prioridade']];
        });
        
        return $insights;
    }
} 