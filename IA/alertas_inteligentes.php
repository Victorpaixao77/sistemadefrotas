<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

class AlertasInteligentes {
    private $conn;
    private $empresa_id;

    public function __construct($empresa_id) {
        $this->conn = getConnection();
        $this->empresa_id = $empresa_id;
    }

    /**
     * Verifica alertas de manutenção preventiva
     */
    public function verificarManutencaoPreventiva() {
        try {
            $query = "SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.ultima_manutencao,
                        v.km_atual,
                        v.km_proxima_manutencao,
                        DATEDIFF(NOW(), v.ultima_manutencao) as dias_desde_manutencao
                    FROM veiculos v
                    WHERE v.empresa_id = :empresa_id
                    AND (
                        v.km_atual >= v.km_proxima_manutencao
                        OR DATEDIFF(NOW(), v.ultima_manutencao) >= 90
                    )";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $alertas = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $alertas[] = [
                    'tipo' => 'manutencao',
                    'prioridade' => 'alta',
                    'veiculo' => $row['placa'],
                    'mensagem' => "Manutenção preventiva necessária para o veículo {$row['placa']}",
                    'detalhes' => [
                        'km_atual' => $row['km_atual'],
                        'km_proxima' => $row['km_proxima_manutencao'],
                        'dias_desde_manutencao' => $row['dias_desde_manutencao']
                    ]
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar manutenção preventiva: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica alertas de documentos
     */
    public function verificarDocumentos() {
        try {
            $query = "SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        v.vencimento_documentos,
                        DATEDIFF(v.vencimento_documentos, NOW()) as dias_para_vencimento
                    FROM veiculos v
                    WHERE v.empresa_id = :empresa_id
                    AND v.vencimento_documentos <= DATE_ADD(NOW(), INTERVAL 30 DAY)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $alertas = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $prioridade = $row['dias_para_vencimento'] <= 7 ? 'alta' : 'media';
                
                $alertas[] = [
                    'tipo' => 'documento',
                    'prioridade' => $prioridade,
                    'veiculo' => $row['placa'],
                    'mensagem' => "Documentos do veículo {$row['placa']} próximos do vencimento",
                    'detalhes' => [
                        'data_vencimento' => $row['vencimento_documentos'],
                        'dias_para_vencimento' => $row['dias_para_vencimento']
                    ]
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar documentos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica alertas de consumo de combustível
     */
    public function verificarConsumoCombustivel() {
        try {
            $query = "SELECT 
                        v.id,
                        v.placa,
                        v.modelo,
                        AVG(a.quantidade_litros) as media_consumo,
                        COUNT(a.id) as num_abastecimentos
                    FROM veiculos v
                    JOIN abastecimentos a ON v.id = a.veiculo_id
                    WHERE v.empresa_id = :empresa_id
                    AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY v.id
                    HAVING media_consumo > (
                        SELECT AVG(quantidade_litros)
                        FROM abastecimentos a2
                        JOIN veiculos v2 ON a2.veiculo_id = v2.id
                        WHERE v2.empresa_id = :empresa_id
                        AND a2.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    )";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $alertas = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $alertas[] = [
                    'tipo' => 'consumo',
                    'prioridade' => 'media',
                    'veiculo' => $row['placa'],
                    'mensagem' => "Consumo de combustível acima da média para o veículo {$row['placa']}",
                    'detalhes' => [
                        'media_consumo' => $row['media_consumo'],
                        'num_abastecimentos' => $row['num_abastecimentos']
                    ]
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar consumo de combustível: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica alertas de rotas
     */
    public function verificarRotas() {
        try {
            $query = "SELECT 
                        r.id,
                        r.origem,
                        r.destino,
                        COUNT(DISTINCT rv.veiculo_id) as num_veiculos,
                        AVG(rv.tempo_estimado) as tempo_medio,
                        COUNT(DISTINCT rv.data_rota) as num_viagens
                    FROM rotas r
                    JOIN rotas_veiculos rv ON r.id = rv.rota_id
                    JOIN veiculos v ON rv.veiculo_id = v.id
                    WHERE v.empresa_id = :empresa_id
                    AND rv.data_rota >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY r.id
                    HAVING tempo_medio > 120 OR (num_veiculos < 2 AND num_viagens > 10)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':empresa_id', $this->empresa_id);
            $stmt->execute();

            $alertas = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $alertas[] = [
                    'tipo' => 'rota',
                    'prioridade' => 'baixa',
                    'rota' => $row['origem'] . ' - ' . $row['destino'],
                    'mensagem' => "Possível otimização necessária para a rota {$row['origem']} - {$row['destino']}",
                    'detalhes' => [
                        'tempo_medio' => $row['tempo_medio'],
                        'num_veiculos' => $row['num_veiculos'],
                        'num_viagens' => $row['num_viagens']
                    ]
                ];
            }

            return $alertas;
        } catch (PDOException $e) {
            error_log("Erro ao verificar rotas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtém todos os alertas
     */
    public function obterTodosAlertas() {
        $alertas = array_merge(
            $this->verificarManutencaoPreventiva(),
            $this->verificarDocumentos(),
            $this->verificarConsumoCombustivel(),
            $this->verificarRotas()
        );

        // Ordena por prioridade
        usort($alertas, function($a, $b) {
            $prioridades = ['alta' => 3, 'media' => 2, 'baixa' => 1];
            return $prioridades[$b['prioridade']] - $prioridades[$a['prioridade']];
        });

        return $alertas;
    }
} 