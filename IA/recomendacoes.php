<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class Recomendacoes {
    private $pdo;
    private $empresa_id;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
    }

    /**
     * Gera recomendações de otimização de rotas
     */
    public function gerarRecomendacoesRotas() {
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
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY r.estado_origem, r.cidade_origem_id, r.estado_destino, r.cidade_destino_id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $recomendacoes = [];
            foreach ($rotas as $rota) {
                // Verifica distribuição de veículos
                if ($rota['num_veiculos'] < 2) {
                    $recomendacoes[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'alta',
                        'mensagem' => "Distribuir melhor os veículos na rota {$rota['origem']} -> {$rota['destino']}",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'acoes' => [
                            "Adicionar mais veículos para esta rota",
                            "Verificar disponibilidade de outros veículos da frota",
                            "Considerar ajustar o cronograma de viagens"
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }

                // Verifica tempo de viagem
                if ($rota['tempo_medio'] > 120) { // Mais de 2 horas
                    $recomendacoes[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'alta',
                        'mensagem' => "Otimizar o tempo de viagem na rota {$rota['origem']} -> {$rota['destino']}",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'acoes' => [
                            "Analisar alternativas de rota",
                            "Verificar horários de trânsito",
                            "Considerar dividir a rota em etapas"
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }

                // Verifica distância
                if ($rota['distancia_media'] > 100) { // Mais de 100km
                    $recomendacoes[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'media',
                        'mensagem' => "Avaliar a viabilidade de dividir a rota {$rota['origem']} -> {$rota['destino']}",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'acoes' => [
                            "Analisar pontos intermediários para paradas",
                            "Verificar necessidade de troca de motorista",
                            "Considerar uso de veículos mais adequados para longas distâncias"
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $recomendacoes;
        } catch (PDOException $e) {
            error_log("Erro ao gerar recomendações de rotas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera recomendações de manutenção
     */
    public function gerarRecomendacoesManutencao() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                v.km_atual,
                m.km_atual as km_proxima_manutencao,
                m.data_manutencao as ultima_manutencao,
                COUNT(m.id) as num_manutencoes,
                SUM(m.valor) as total_gasto
            FROM veiculos v
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND (v.km_atual >= m.km_atual * 0.8
                OR DATEDIFF(NOW(), m.data_manutencao) >= 60)
            GROUP BY v.id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $recomendacoes = [];
            foreach ($veiculos as $veiculo) {
                $prioridade = 'media';
                if ($veiculo['km_atual'] >= $veiculo['km_proxima_manutencao'] || 
                    strtotime($veiculo['ultima_manutencao']) <= strtotime('-90 days')) {
                    $prioridade = 'alta';
                }

                $recomendacoes[] = [
                    'tipo' => 'manutencao',
                    'prioridade' => $prioridade,
                    'mensagem' => "Agendar manutenção preventiva para o veículo {$veiculo['placa']}",
                    'veiculo' => $veiculo['placa'],
                    'acoes' => [
                        "Verificar disponibilidade de oficinas",
                        "Agendar manutenção preventiva",
                        "Preparar relatório de histórico de manutenções"
                    ],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            return $recomendacoes;
        } catch (PDOException $e) {
            error_log("Erro ao gerar recomendações de manutenção: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera recomendações de economia
     */
    public function gerarRecomendacoesEconomia() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                AVG(a.litros / r.distancia_km) as consumo_medio,
                COUNT(DISTINCT r.id) as num_viagens
            FROM veiculos v
            JOIN rotas r ON v.id = r.veiculo_id
            JOIN abastecimentos a ON v.id = a.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY v.id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Verificar se há dados suficientes
            if (count($veiculos) == 0) {
                return [];
            }

            // Calcula a média de consumo de todos os veículos
            $consumo_medio_geral = array_sum(array_column($veiculos, 'consumo_medio')) / count($veiculos);
            
            // Evitar divisão por zero
            if ($consumo_medio_geral <= 0) {
                return [];
            }

            $recomendacoes = [];
            foreach ($veiculos as $veiculo) {
                if ($veiculo['consumo_medio'] > $consumo_medio_geral * 1.2) {
                    $recomendacoes[] = [
                        'tipo' => 'consumo',
                        'prioridade' => 'alta',
                        'mensagem' => "Implementar medidas para reduzir o consumo de combustível do veículo {$veiculo['placa']}",
                        'veiculo' => $veiculo['placa'],
                        'acoes' => [
                            "Realizar treinamento de direção econômica",
                            "Verificar pressão dos pneus",
                            "Analisar necessidade de manutenção do motor",
                            "Considerar uso de combustível alternativo"
                        ],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $recomendacoes;
        } catch (PDOException $e) {
            error_log("Erro ao gerar recomendações de economia: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera recomendações de segurança
     */
    public function gerarRecomendacoesSeguranca() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                m.data_manutencao,
                m.km_atual,
                m.descricao,
                m.tipo_manutencao_id,
                COUNT(m.id) as num_manutencoes_seguranca
            FROM veiculos v
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            AND m.tipo_manutencao_id IN (SELECT id FROM tipos_manutencao WHERE descricao IN ('freios', 'pneus', 'suspensao', 'direcao'))
            AND m.data_manutencao >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            WHERE v.empresa_id = :empresa_id
            GROUP BY v.id
            HAVING num_manutencoes_seguranca > 0";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $recomendacoes = [];
            foreach ($veiculos as $veiculo) {
                $recomendacoes[] = [
                    'tipo' => 'seguranca',
                    'prioridade' => 'alta',
                    'mensagem' => "Realizar verificação de segurança no veículo {$veiculo['placa']}",
                    'veiculo' => $veiculo['placa'],
                    'acoes' => [
                        "Verificar sistema de freios",
                        "Inspecionar pneus e suspensão",
                        "Testar sistema de direção",
                        "Verificar documentação de segurança"
                    ],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            return $recomendacoes;
        } catch (PDOException $e) {
            error_log("Erro ao gerar recomendações de segurança: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém todas as recomendações
     */
    public function obterTodasRecomendacoes() {
        try {
            $recomendacoes = array_merge(
                $this->gerarRecomendacoesRotas(),
                $this->gerarRecomendacoesManutencao(),
                $this->gerarRecomendacoesEconomia(),
                $this->gerarRecomendacoesSeguranca()
            );

            // Ordenar por prioridade (alta, media, baixa)
            usort($recomendacoes, function($a, $b) {
                $prioridades = ['alta' => 3, 'media' => 2, 'baixa' => 1];
                return $prioridades[$b['prioridade']] - $prioridades[$a['prioridade']];
            });

            return $recomendacoes;
        } catch (Exception $e) {
            error_log("Erro ao obter todas as recomendações: " . $e->getMessage());
            return [];
        }
    }
} 