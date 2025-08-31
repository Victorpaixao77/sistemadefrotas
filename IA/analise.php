<?php
class Analise {
    private $pdo;
    private $empresa_id;

    public function __construct($pdo, $empresa_id) {
        $this->pdo = $pdo;
        $this->empresa_id = $empresa_id;
    }

    public function analisarConsumo() {
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar consumo: " . $e->getMessage());
            return [];
        }
    }

    public function analisarManutencao() {
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar manutenção: " . $e->getMessage());
            return [];
        }
    }

    public function analisarRotas() {
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar rotas: " . $e->getMessage());
            return [];
        }
    }

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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar custos operacionais: " . $e->getMessage());
            return [];
        }
    }

    public function analisarDocumentos() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                m.data_manutencao,
                m.km_atual,
                m.descricao,
                m.tipo_manutencao_id,
                m.status_manutencao_id
            FROM veiculos v
            JOIN manutencoes m ON v.id = m.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND m.data_manutencao <= DATE_ADD(NOW(), INTERVAL 30 DAY)
            AND m.status_manutencao_id = 1"; // Assumindo que 1 é o status de pendente

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar documentos: " . $e->getMessage());
            return [];
        }
    }

    public function analisarSeguranca() {
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
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar segurança: " . $e->getMessage());
            return [];
        }
    }

    public function analisarEficiencia() {
        try {
            $sql = "SELECT 
                v.placa,
                v.modelo,
                AVG(a.litros / r.distancia_km) as consumo_medio,
                AVG(TIMESTAMPDIFF(HOUR, r.data_saida, r.data_chegada) / r.distancia_km) as tempo_medio_km,
                COUNT(DISTINCT r.id) as num_viagens
            FROM veiculos v
            JOIN rotas r ON v.id = r.veiculo_id
            JOIN abastecimentos a ON v.id = a.veiculo_id
            WHERE v.empresa_id = :empresa_id
            AND r.data_saida >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
            GROUP BY v.id
            HAVING num_viagens > 5";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['empresa_id' => $this->empresa_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao analisar eficiência: " . $e->getMessage());
            return [];
        }
    }

    public function obterTodasAnalises() {
        return [
            'consumo' => $this->analisarConsumo(),
            'manutencao' => $this->analisarManutencao(),
            'rotas' => $this->analisarRotas(),
            'custos' => $this->analisarCustosOperacionais(),
            'documentos' => $this->analisarDocumentos(),
            'seguranca' => $this->analisarSeguranca(),
            'eficiencia' => $this->analisarEficiencia()
        ];
    }
} 