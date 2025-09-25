<?php
/**
 * Sistema de Competições
 * Implementa competições e rankings por período
 */

class CompetitionManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Criar nova competição
     */
    public function createCompetition($empresa_id, $nome, $tipo, $periodo_inicio, $periodo_fim, $premio, $criterios) {
        try {
            $sql = "INSERT INTO competicoes (empresa_id, nome, tipo, periodo_inicio, periodo_fim, premio, criterios, status, created_at) 
                    VALUES (:empresa_id, :nome, :tipo, :periodo_inicio, :periodo_fim, :premio, :criterios, 'ativa', NOW())";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':nome', $nome);
            $stmt->bindParam(':tipo', $tipo);
            $stmt->bindParam(':periodo_inicio', $periodo_inicio);
            $stmt->bindParam(':periodo_fim', $periodo_fim);
            $stmt->bindParam(':premio', $premio);
            $stmt->bindParam(':criterios', json_encode($criterios));
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Erro ao criar competição: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter competições ativas
     */
    public function getActiveCompetitions($empresa_id) {
        try {
            $sql = "SELECT * FROM competicoes 
                    WHERE empresa_id = :empresa_id 
                    AND status = 'ativa' 
                    AND periodo_inicio <= NOW() 
                    AND periodo_fim >= NOW()
                    ORDER BY created_at DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->execute();
            
            $competitions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decodificar critérios JSON
            foreach ($competitions as &$competition) {
                $competition['criterios'] = json_decode($competition['criterios'], true);
            }
            
            return $competitions;
        } catch (Exception $e) {
            error_log("Erro ao buscar competições: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calcular ranking da competição
     */
    public function calculateCompetitionRanking($competition_id) {
        try {
            // Obter dados da competição
            $sql = "SELECT * FROM competicoes WHERE id = :competition_id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':competition_id', $competition_id);
            $stmt->execute();
            $competition = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$competition) {
                return false;
            }
            
            $criterios = json_decode($competition['criterios'], true);
            $empresa_id = $competition['empresa_id'];
            $periodo_inicio = $competition['periodo_inicio'];
            $periodo_fim = $competition['periodo_fim'];
            
            // Calcular pontuação baseada nos critérios
            $sql = "SELECT 
                        m.id as motorista_id,
                        m.nome as motorista_nome,
                        COALESCE(SUM(r.frete), 0) as total_frete,
                        COALESCE(COUNT(r.id), 0) as total_rotas,
                        COALESCE(AVG(r.eficiencia_viagem), 0) as eficiencia_media,
                        COALESCE(COUNT(CASE WHEN r.no_prazo = 1 THEN 1 END), 0) as rotas_pontuais,
                        COALESCE(COUNT(mt.id), 0) as total_multas,
                        COALESCE(COUNT(cv.id), 0) as total_checklists
                    FROM motoristas m
                    LEFT JOIN rotas r ON m.id = r.motorista_id 
                        AND r.data_rota BETWEEN :periodo_inicio AND :periodo_fim
                        AND r.status IN ('aprovado', 'finalizada')
                    LEFT JOIN multas mt ON m.id = mt.motorista_id 
                        AND mt.data_infracao BETWEEN :periodo_inicio AND :periodo_fim
                    LEFT JOIN checklist_viagem cv ON m.id = cv.motorista_id 
                        AND cv.data_checklist BETWEEN :periodo_inicio AND :periodo_fim
                    WHERE m.empresa_id = :empresa_id
                    GROUP BY m.id, m.nome
                    HAVING total_rotas > 0";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':empresa_id', $empresa_id);
            $stmt->bindParam(':periodo_inicio', $periodo_inicio);
            $stmt->bindParam(':periodo_fim', $periodo_fim);
            $stmt->execute();
            
            $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calcular pontuação para cada motorista
            $rankings = [];
            foreach ($motoristas as $motorista) {
                $pontuacao = 0;
                
                // Aplicar critérios de pontuação
                if (isset($criterios['pontualidade']) && $criterios['pontualidade'] > 0) {
                    $pontuacao += ($motorista['rotas_pontuais'] / $motorista['total_rotas']) * 100 * $criterios['pontualidade'];
                }
                
                if (isset($criterios['eficiencia']) && $criterios['eficiencia'] > 0) {
                    $pontuacao += $motorista['eficiencia_media'] * $criterios['eficiencia'];
                }
                
                if (isset($criterios['rotas']) && $criterios['rotas'] > 0) {
                    $pontuacao += $motorista['total_rotas'] * $criterios['rotas'];
                }
                
                if (isset($criterios['frete']) && $criterios['frete'] > 0) {
                    $pontuacao += ($motorista['total_frete'] / 1000) * $criterios['frete'];
                }
                
                // Penalizar multas
                if (isset($criterios['penalidade_multas']) && $criterios['penalidade_multas'] > 0) {
                    $pontuacao -= $motorista['total_multas'] * $criterios['penalidade_multas'];
                }
                
                $rankings[] = [
                    'motorista_id' => $motorista['motorista_id'],
                    'motorista_nome' => $motorista['motorista_nome'],
                    'pontuacao' => round($pontuacao, 2),
                    'total_rotas' => $motorista['total_rotas'],
                    'rotas_pontuais' => $motorista['rotas_pontuais'],
                    'eficiencia_media' => round($motorista['eficiencia_media'], 2),
                    'total_multas' => $motorista['total_multas']
                ];
            }
            
            // Ordenar por pontuação
            usort($rankings, function($a, $b) {
                return $b['pontuacao'] <=> $a['pontuacao'];
            });
            
            // Adicionar posição
            foreach ($rankings as $index => &$ranking) {
                $ranking['posicao'] = $index + 1;
            }
            
            return $rankings;
            
        } catch (Exception $e) {
            error_log("Erro ao calcular ranking da competição: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obter competições por categoria
     */
    public function getCompetitionsByCategory($empresa_id) {
        $competitions = $this->getActiveCompetitions($empresa_id);
        $categorized = [
            'semanal' => [],
            'mensal' => [],
            'especial' => []
        ];
        
        foreach ($competitions as $competition) {
            $tipo = $competition['tipo'];
            if (isset($categorized[$tipo])) {
                $categorized[$tipo][] = $competition;
            }
        }
        
        return $categorized;
    }
    
    /**
     * Gerar HTML das competições
     */
    public function generateCompetitionsHTML($empresa_id) {
        $competitions = $this->getCompetitionsByCategory($empresa_id);
        
        $html = '<div class="competitions-container">';
        
        foreach ($competitions as $categoria => $comps) {
            if (empty($comps)) continue;
            
            $categoria_nome = ucfirst($categoria);
            $categoria_icon = $categoria === 'semanal' ? '📅' : ($categoria === 'mensal' ? '📆' : '🏆');
            
            $html .= '<div class="competition-category">';
            $html .= '<h5>' . $categoria_icon . ' Competições ' . $categoria_nome . '</h5>';
            $html .= '<div class="competitions-grid">';
            
            foreach ($comps as $comp) {
                $html .= '<div class="competition-card">';
                $html .= '<div class="competition-header">';
                $html .= '<h6>' . $comp['nome'] . '</h6>';
                $html .= '<span class="competition-type badge badge-primary">' . ucfirst($comp['tipo']) . '</span>';
                $html .= '</div>';
                
                $html .= '<div class="competition-body">';
                $html .= '<p><i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($comp['periodo_inicio'])) . ' - ' . date('d/m/Y', strtotime($comp['periodo_fim'])) . '</p>';
                $html .= '<p><i class="fas fa-trophy"></i> ' . $comp['premio'] . '</p>';
                $html .= '</div>';
                
                $html .= '<div class="competition-footer">';
                $html .= '<button class="btn btn-sm btn-outline-primary" onclick="viewCompetition(' . $comp['id'] . ')">Ver Ranking</button>';
                $html .= '</div>';
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Obter CSS para competições
     */
    public static function getCSS() {
        return '
        <style>
        .competitions-container {
            margin: 20px 0;
        }
        
        .competition-category {
            margin-bottom: 30px;
        }
        
        .competition-category h5 {
            color: #007bff;
            margin-bottom: 15px;
            font-weight: bold;
        }
        
        .competitions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .competition-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #007bff;
            transition: transform 0.3s ease;
        }
        
        .competition-card:hover {
            transform: translateY(-5px);
        }
        
        .competition-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .competition-header h6 {
            margin: 0;
            color: #333;
            font-weight: bold;
        }
        
        .competition-body p {
            margin: 8px 0;
            color: #666;
            font-size: 14px;
        }
        
        .competition-body i {
            margin-right: 8px;
            color: #007bff;
        }
        
        .competition-footer {
            margin-top: 15px;
            text-align: right;
        }
        
        @media (max-width: 768px) {
            .competitions-grid {
                grid-template-columns: 1fr;
            }
        }
        </style>';
    }
}

// Criar tabela de competições se não existir
function createCompetitionsTable($conn) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS competicoes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            nome VARCHAR(255) NOT NULL,
            tipo ENUM('semanal', 'mensal', 'especial') DEFAULT 'mensal',
            periodo_inicio DATE NOT NULL,
            periodo_fim DATE NOT NULL,
            premio TEXT NOT NULL,
            criterios JSON NOT NULL,
            status ENUM('ativa', 'finalizada', 'cancelada') DEFAULT 'ativa',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_empresa (empresa_id),
            INDEX idx_periodo (periodo_inicio, periodo_fim),
            INDEX idx_status (status),
            FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
        )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        error_log("Erro ao criar tabela de competições: " . $e->getMessage());
        return false;
    }
}
?>
