<?php
class Alertas {
    private $pdo;
    private $empresa_id;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
    }

    public function verificarManutencao() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                v.km_atual,
                m.km_atual as km_proxima_manutencao,
                m.data_manutencao as ultima_manutencao,
                DATEDIFF(NOW(), m.data_manutencao) as dias_desde_manutencao
            FROM veiculos v
            LEFT JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND (
                v.km_atual >= m.km_atual * 0.8
                OR DATEDIFF(NOW(), m.data_manutencao) >= 60
            )";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $alertas = [];
            foreach ($veiculos as $veiculo) {
                $prioridade = 'media';
                if ($veiculo['km_atual'] >= $veiculo['km_proxima_manutencao'] || 
                    $veiculo['dias_desde_manutencao'] >= 90) {
                    $prioridade = 'alta';
                }

                $alertas[] = [
                    'tipo' => 'manutencao',
                    'prioridade' => $prioridade,
                    'titulo' => 'Manutenção Pendente',
                    'mensagem' => "O veículo {$veiculo['placa']} ({$veiculo['modelo']}) necessita de manutenção. " .
                                "KM atual: {$veiculo['km_atual']}, Próxima manutenção: {$veiculo['km_proxima_manutencao']}",
                    'veiculo' => $veiculo['placa'],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar manutenção: " . $e->getMessage());
            return [];
        }
    }

    public function verificarDocumentos() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                m.data_manutencao,
                m.km_atual,
                m.descricao,
                m.tipo_manutencao_id
            FROM veiculos v
            JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND m.data_manutencao <= DATE_ADD(NOW(), INTERVAL 30 DAY)
            AND m.status_manutencao_id = 1"; // Assumindo que 1 é o status de pendente

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $alertas = [];
            foreach ($documentos as $doc) {
                $alertas[] = [
                    'tipo' => 'manutencao',
                    'prioridade' => 'media',
                    'titulo' => 'Manutenção Pendente',
                    'mensagem' => "O veículo {$doc['placa']} ({$doc['modelo']}) necessita de manutenção. " .
                                "KM atual: {$doc['km_atual']}, " .
                                "Tipo: {$doc['descricao']}",
                    'veiculo' => $doc['placa'],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar documentos: " . $e->getMessage());
            return [];
        }
    }

    public function verificarConsumo() {
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

            $alertas = [];
            foreach ($veiculos as $veiculo) {
                if ($veiculo['consumo_medio'] > $consumo_medio_geral * 1.2) {
                    $alertas[] = [
                        'tipo' => 'consumo',
                        'prioridade' => 'alta',
                        'titulo' => 'Consumo de Combustível Elevado',
                        'mensagem' => "O veículo {$veiculo['placa']} ({$veiculo['modelo']}) está consumindo " .
                                    number_format(($veiculo['consumo_medio'] / $consumo_medio_geral - 1) * 100, 1) .
                                    "% mais combustível que a média da frota",
                        'veiculo' => $veiculo['placa'],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar consumo: " . $e->getMessage());
            return [];
        }
    }

    public function verificarRotas() {
        try {
            $sql = "SELECT 
                CONCAT(r.estado_origem, ' - ', r.cidade_origem_id) as origem,
                CONCAT(r.estado_destino, ' - ', r.cidade_destino_id) as destino,
                COUNT(DISTINCT r.veiculo_id) as num_veiculos,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada)) as tempo_medio,
                AVG(r.distancia_km) as distancia_media,
                COUNT(DISTINCT r.data_saida) as num_viagens
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY r.estado_origem, r.cidade_origem_id, r.estado_destino, r.cidade_destino_id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $alertas = [];
            foreach ($rotas as $rota) {
                if ($rota['num_veiculos'] < 2) {
                    $alertas[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'media',
                        'titulo' => 'Rota com Poucos Veículos',
                        'mensagem' => "A rota {$rota['origem']} -> {$rota['destino']} está sendo atendida por apenas " .
                                    "{$rota['num_veiculos']} veículo(s), o que pode causar sobrecarga",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }

                if ($rota['tempo_medio'] > 120) { // Mais de 2 horas
                    $alertas[] = [
                        'tipo' => 'rota',
                        'prioridade' => 'alta',
                        'titulo' => 'Rota com Tempo de Viagem Elevado',
                        'mensagem' => "A rota {$rota['origem']} -> {$rota['destino']} está levando em média " .
                                    number_format($rota['tempo_medio'] / 60, 1) . " horas para ser concluída",
                        'rota' => "{$rota['origem']} -> {$rota['destino']}",
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar rotas: " . $e->getMessage());
            return [];
        }
    }

    public function obterTodosAlertas() {
        try {
            // Primeiro, gerar novos alertas baseados nas verificações
            $novos_alertas = array_merge(
                $this->verificarManutencao(),
                $this->verificarDocumentos(),
                $this->verificarConsumo(),
                $this->verificarRotas()
            );
            
            // Salvar novos alertas na tabela
            $this->salvarAlertas($novos_alertas);
            
            // Buscar alertas ativos da tabela
            $sql = "SELECT * FROM alertas_sistema 
                    WHERE empresa_id = :empresa_id 
                    AND status = 'ativo'
                    ORDER BY prioridade DESC, data_criacao DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':empresa_id', $this->empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Converter dados JSON de volta para array
            foreach ($alertas as &$alerta) {
                if (!empty($alerta['dados'])) {
                    $alerta['dados'] = json_decode($alerta['dados'], true);
                }
            }
            
            return $alertas;
        } catch (Exception $e) {
            error_log("Erro ao obter todos os alertas: " . $e->getMessage());
            return [];
        }
    }
    
    private function salvarAlertas($alertas) {
        try {
            foreach ($alertas as $alerta) {
                // Verificar se já existe um alerta similar ativo
                $sql_check = "SELECT id FROM alertas_sistema 
                             WHERE empresa_id = :empresa_id 
                             AND tipo = :tipo 
                             AND titulo = :titulo 
                             AND status = 'ativo'";
                
                $stmt_check = $this->pdo->prepare($sql_check);
                $stmt_check->bindParam(':empresa_id', $this->empresa_id, PDO::PARAM_INT);
                $stmt_check->bindParam(':tipo', $alerta['tipo']);
                $stmt_check->bindParam(':titulo', $alerta['mensagem']);
                $stmt_check->execute();
                
                if (!$stmt_check->fetch()) {
                    // Inserir novo alerta
                    $sql_insert = "INSERT INTO alertas_sistema (
                                    empresa_id, tipo, prioridade, titulo, mensagem, dados,
                                    veiculo_id, motorista_id, rota_id, status
                                  ) VALUES (
                                    :empresa_id, :tipo, :prioridade, :titulo, :mensagem, :dados,
                                    :veiculo_id, :motorista_id, :rota_id, 'ativo'
                                  )";
                    
                    $stmt_insert = $this->pdo->prepare($sql_insert);
                    $stmt_insert->bindParam(':empresa_id', $this->empresa_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':tipo', $alerta['tipo']);
                    $stmt_insert->bindParam(':prioridade', $alerta['prioridade']);
                    $stmt_insert->bindParam(':titulo', $alerta['mensagem']);
                    $stmt_insert->bindParam(':mensagem', $alerta['mensagem']);
                    $stmt_insert->bindParam(':dados', json_encode($alerta['dados'] ?? []));
                    $veiculo_id = $alerta['veiculo_id'] ?? null;
                    $motorista_id = $alerta['motorista_id'] ?? null;
                    $rota_id = $alerta['rota_id'] ?? null;
                    
                    $stmt_insert->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
                    $stmt_insert->bindParam(':rota_id', $rota_id, PDO::PARAM_INT);
                    $stmt_insert->execute();
                }
            }
        } catch (Exception $e) {
            error_log("Erro ao salvar alertas: " . $e->getMessage());
        }
    }
} 