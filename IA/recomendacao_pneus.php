<?php
/**
 * Sistema de Recomendação de Pneus - IA
 * 
 * Este módulo utiliza algoritmos de IA para recomendar
 * o melhor pneu para cada posição baseado em:
 * - Histórico de performance
 * - Compatibilidade de medidas
 * - Custo-benefício
 * - Disponibilidade no estoque
 */

header('Content-Type: application/json');

require_once '../includes/config.php';
require_once '../includes/functions.php';

class RecomendacaoPneus {
    private $pdo;
    private $empresa_id;
    
    public function __construct($empresa_id) {
        $this->empresa_id = $empresa_id;
        // Usar o sistema de conexão centralizado
        $this->pdo = getConnection();
    }
    
    /**
     * Recomenda pneus para uma posição específica
     */
    public function recomendarPneu($posicao, $veiculo_id, $criticidade = 'normal') {
        try {
            // 1. Buscar dados do veículo
            $veiculo = $this->buscarDadosVeiculo($veiculo_id);
            
            // 2. Analisar histórico da posição
            $historico = $this->analisarHistoricoPosicao($posicao, $veiculo_id);
            
            // 3. Buscar pneus disponíveis
            $pneus_disponiveis = $this->buscarPneusDisponiveis();
            
            // 4. Calcular scores para cada pneu
            $recomendacoes = [];
            foreach ($pneus_disponiveis as $pneu) {
                $score = $this->calcularScorePneu($pneu, $posicao, $historico, $criticidade);
                $recomendacoes[] = [
                    'pneu' => $pneu,
                    'score' => $score,
                    'motivos' => $this->gerarMotivosRecomendacao($pneu, $score)
                ];
            }
            
            // 5. Ordenar por score e retornar top 5
            usort($recomendacoes, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            return array_slice($recomendacoes, 0, 5);
            
        } catch (Exception $e) {
            error_log("Erro na recomendação de pneus: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Busca dados do veículo
     */
    private function buscarDadosVeiculo($veiculo_id) {
        $stmt = $this->pdo->prepare("
            SELECT v.*, tv.nome as tipo_veiculo_nome
            FROM veiculos v
            LEFT JOIN tipos_veiculo tv ON v.tipo_veiculo_id = tv.id
            WHERE v.id = ? AND v.empresa_id = ?
        ");
        $stmt->execute([$veiculo_id, $this->empresa_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analisa histórico da posição
     */
    private function analisarHistoricoPosicao($posicao, $veiculo_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                ip.*,
                p.marca,
                p.modelo,
                p.medida,
                p.numero_serie,
                DATEDIFF(ip.data_remocao, ip.data_instalacao) as dias_uso,
                v.quilometragem_atual - v.quilometragem_anterior as km_percorrido
            FROM instalacoes_pneus ip
            INNER JOIN pneus p ON ip.pneu_id = p.id
            INNER JOIN veiculos v ON ip.veiculo_id = v.id
            WHERE ip.veiculo_id = ? 
            AND ip.posicao = ?
            AND ip.data_remocao IS NOT NULL
            ORDER BY ip.data_instalacao DESC
            LIMIT 10
        ");
        $stmt->execute([$veiculo_id, $posicao]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca pneus disponíveis
     */
    private function buscarPneusDisponiveis() {
        $stmt = $this->pdo->prepare("
            SELECT p.*, sp.nome as status_nome
            FROM pneus p
            LEFT JOIN status_pneus sp ON p.status_id = sp.id
            WHERE p.empresa_id = ? 
            AND p.status_id IN (2, 5)
            AND p.id NOT IN (
                SELECT pneu_id 
                FROM instalacoes_pneus 
                WHERE data_remocao IS NULL
            )
            ORDER BY p.data_entrada DESC
        ");
        $stmt->execute([$this->empresa_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calcula score para um pneu
     */
    private function calcularScorePneu($pneu, $posicao, $historico, $criticidade) {
        $score = 0;
        
        // 1. Score base (0-30 pontos)
        $score += $this->calcularScoreBase($pneu);
        
        // 2. Score de compatibilidade (0-25 pontos)
        $score += $this->calcularScoreCompatibilidade($pneu, $historico);
        
        // 3. Score de performance (0-25 pontos)
        $score += $this->calcularScorePerformance($pneu, $historico);
        
        // 4. Score de custo-benefício (0-20 pontos)
        $score += $this->calcularScoreCustoBeneficio($pneu, $criticidade);
        
        return $score;
    }
    
    /**
     * Calcula score base do pneu
     */
    private function calcularScoreBase($pneu) {
        $score = 0;
        
        // Status do pneu
        if ($pneu['status_nome'] === 'novo') $score += 15;
        elseif ($pneu['status_nome'] === 'disponivel') $score += 10;
        else $score += 5;
        
        // Idade do pneu (quanto mais novo, melhor)
        $idade_dias = (time() - strtotime($pneu['data_entrada'])) / 86400;
        if ($idade_dias < 365) $score += 10;
        elseif ($idade_dias < 730) $score += 5;
        else $score += 2;
        
        // Número de recapagens (quanto menos, melhor)
        if ($pneu['numero_recapagens'] == 0) $score += 5;
        elseif ($pneu['numero_recapagens'] == 1) $score += 3;
        else $score += 1;
        
        return $score;
    }
    
    /**
     * Calcula score de compatibilidade
     */
    private function calcularScoreCompatibilidade($pneu, $historico) {
        $score = 0;
        
        if (empty($historico)) {
            // Se não há histórico, dar score médio
            return 12;
        }
        
        // Verificar se a marca/modelo já foi usada com sucesso
        $marcas_sucesso = [];
        $modelos_sucesso = [];
        
        foreach ($historico as $registro) {
            if ($registro['dias_uso'] > 30) { // Considerar sucesso se durou mais de 30 dias
                $marcas_sucesso[] = $registro['marca'];
                $modelos_sucesso[] = $registro['modelo'];
            }
        }
        
        if (in_array($pneu['marca'], $marcas_sucesso)) $score += 10;
        if (in_array($pneu['modelo'], $modelos_sucesso)) $score += 10;
        
        // Compatibilidade de medida
        $medidas_historico = array_unique(array_column($historico, 'medida'));
        if (in_array($pneu['medida'], $medidas_historico)) $score += 5;
        
        return $score;
    }
    
    /**
     * Calcula score de performance
     */
    private function calcularScorePerformance($pneu, $historico) {
        $score = 0;
        
        if (empty($historico)) {
            return 12;
        }
        
        // Calcular média de duração dos pneus similares
        $pneus_similares = array_filter($historico, function($h) use ($pneu) {
            return $h['marca'] === $pneu['marca'] || $h['modelo'] === $pneu['modelo'];
        });
        
        if (!empty($pneus_similares)) {
            $media_dias = array_sum(array_column($pneus_similares, 'dias_uso')) / count($pneus_similares);
            
            if ($media_dias > 180) $score += 15; // Excelente duração
            elseif ($media_dias > 120) $score += 10; // Boa duração
            elseif ($media_dias > 60) $score += 5; // Duração média
        }
        
        // Score baseado no DOT (quanto mais recente, melhor)
        if ($pneu['dot']) {
            $ano_dot = substr($pneu['dot'], -2);
            $ano_atual = date('y');
            $diferenca_anos = $ano_atual - $ano_dot;
            
            if ($diferenca_anos <= 2) $score += 10;
            elseif ($diferenca_anos <= 4) $score += 5;
        }
        
        return $score;
    }
    
    /**
     * Calcula score de custo-benefício
     */
    private function calcularScoreCustoBeneficio($pneu, $criticidade) {
        $score = 0;
        
        // Para posições críticas, priorizar qualidade
        if ($criticidade === 'critica') {
            if ($pneu['status_nome'] === 'novo') $score += 15;
            elseif ($pneu['numero_recapagens'] == 0) $score += 10;
        } else {
            // Para posições normais, balancear custo e benefício
            if ($pneu['numero_recapagens'] > 0) $score += 10; // Pneus recauchutados são mais baratos
            if ($pneu['status_nome'] === 'disponivel') $score += 5;
        }
        
        // Score baseado na idade (pneus mais velhos podem ser mais baratos)
        $idade_dias = (time() - strtotime($pneu['data_entrada'])) / 86400;
        if ($idade_dias > 365) $score += 5;
        
        return $score;
    }
    
    /**
     * Gera motivos da recomendação
     */
    private function gerarMotivosRecomendacao($pneu, $score) {
        $motivos = [];
        
        if ($pneu['status_nome'] === 'novo') {
            $motivos[] = "Pneu novo em excelente estado";
        }
        
        if ($pneu['numero_recapagens'] == 0) {
            $motivos[] = "Primeira vida útil";
        }
        
        if ($score > 80) {
            $motivos[] = "Alta compatibilidade com histórico";
        }
        
        if ($score > 70) {
            $motivos[] = "Bom custo-benefício";
        }
        
        return $motivos;
    }
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['posicao']) || !isset($data['veiculo_id'])) {
            throw new Exception('Parâmetros obrigatórios não fornecidos');
        }
        
        $empresa_id = $_SESSION['empresa_id'];
        $recomendador = new RecomendacaoPneus($empresa_id);
        
        $recomendacoes = $recomendador->recomendarPneu(
            $data['posicao'],
            $data['veiculo_id'],
            $data['criticidade'] ?? 'normal'
        );
        
        echo json_encode([
            'success' => true,
            'recomendacoes' => $recomendacoes
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Método não permitido'
    ]);
}
?> 