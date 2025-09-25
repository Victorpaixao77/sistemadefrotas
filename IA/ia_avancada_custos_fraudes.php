<?php
/**
 * IA Avançada para Análise de Custos e Detecção de Fraudes
 * 
 * Sistema inteligente para:
 * - Previsão de lucro por rota
 * - Detecção avançada de fraudes
 * - Análise de custos operacionais
 * - Alertas automáticos de prejuízo
 * - Ranking de risco por motorista/veículo
 */

require_once __DIR__ . '/../includes/db_connect.php';
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];

$conn = getConnection();

// Função para inserir notificação com verificação de duplicidade
function notificacaoExiste($empresa_id, $tipo, $titulo, $mensagem) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE empresa_id = ? AND tipo = ? AND titulo = ? AND mensagem = ? AND data_criacao > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$empresa_id, $tipo, $titulo, $mensagem]);
    return $stmt->fetchColumn() > 0;
}

function inserirNotificacao($empresa_id, $tipo, $titulo, $mensagem, $ia_mensagem = null) {
    if (!notificacaoExiste($empresa_id, $tipo, $titulo, $mensagem)) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO notificacoes (empresa_id, tipo, titulo, mensagem, ia_mensagem, data_criacao, lida) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        $stmt->execute([$empresa_id, $tipo, $titulo, $mensagem, $ia_mensagem]);
    }
}

// ===== FUNÇÕES PARA ANÁLISE DE CUSTOS E LUCRO =====

/**
 * Calcular previsão de lucro por rota
 */
function calcularPrevisaoLucro($rota_id, $empresa_id) {
    global $conn;
    
    try {
        // Buscar dados da rota
        $sql = "SELECT 
                    r.*,
                    v.tipo as tipo_veiculo,
                    v.tipo_combustivel,
                    m.nome as motorista_nome,
                    co.nome as cidade_origem,
                    cd.nome as cidade_destino
                FROM rotas r
                JOIN veiculos v ON r.veiculo_id = v.id
                JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE r.id = :rota_id AND r.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':rota_id', $rota_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $rota = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$rota) {
            return null;
        }
        
        // Calcular custos variáveis
        $custos_variaveis = calcularCustosVariaveis($rota);
        
        // Calcular custos fixos
        $custos_fixos = calcularCustosFixos($rota);
        
        // Calcular receita
        $receita = floatval($rota['frete'] ?? 0);
        
        // Calcular lucro previsto
        $lucro_previsto = $receita - $custos_variaveis - $custos_fixos;
        $margem_lucro = ($receita > 0) ? ($lucro_previsto / $receita) * 100 : 0;
        
        return [
            'rota_id' => $rota_id,
            'receita' => $receita,
            'custos_variaveis' => $custos_variaveis,
            'custos_fixos' => $custos_fixos,
            'lucro_previsto' => $lucro_previsto,
            'margem_lucro' => $margem_lucro,
            'status' => $lucro_previsto > 0 ? 'lucrativa' : 'prejuizo'
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao calcular previsão de lucro: " . $e->getMessage());
        return null;
    }
}

/**
 * Calcular custos variáveis de uma rota
 */
function calcularCustosVariaveis($rota) {
    global $conn;
    
    $custos = 0;
    
    // 1. Custo de combustível estimado
    $distancia = floatval($rota['distancia_km'] ?? 0);
    $tipo_veiculo = $rota['tipo_veiculo'] ?? 'Caminhão';
    $tipo_combustivel = $rota['tipo_combustivel'] ?? 'Diesel';
    
    // Consumo médio por tipo de veículo e combustível
    $consumo_por_km = obterConsumoMedio($tipo_veiculo, $tipo_combustivel);
    $preco_combustivel = obterPrecoCombustivel($tipo_combustivel);
    
    $custo_combustivel = $distancia * $consumo_por_km * $preco_combustivel;
    $custos += $custo_combustivel;
    
    // 2. Custos de pedágio (se houver dados)
    $custo_pedagio = calcularCustoPedagio($rota);
    $custos += $custo_pedagio;
    
    // 3. Custos de manutenção estimados
    $custo_manutencao = calcularCustoManutencaoEstimado($rota);
    $custos += $custo_manutencao;
    
    // 4. Comissão do motorista
    $comissao = floatval($rota['comissao'] ?? 0);
    $custos += $comissao;
    
    return $custos;
}

/**
 * Calcular custos fixos de uma rota
 */
function calcularCustosFixos($rota) {
    global $conn;
    
    $custos = 0;
    
    // 1. Custo de depreciação do veículo
    $custo_depreciacao = calcularCustoDepreciacao($rota);
    $custos += $custo_depreciacao;
    
    // 2. Custo de seguro
    $custo_seguro = calcularCustoSeguro($rota);
    $custos += $custo_seguro;
    
    // 3. Custo de licenciamento
    $custo_licenciamento = calcularCustoLicenciamento($rota);
    $custos += $custo_licenciamento;
    
    return $custos;
}

/**
 * Obter consumo médio por tipo de veículo e combustível
 */
function obterConsumoMedio($tipo_veiculo, $tipo_combustivel) {
    $consumos = [
        'Caminhão' => [
            'Diesel' => 0.35,
            'Gasolina' => 0.45,
            'Etanol' => 0.65
        ],
        'Van' => [
            'Diesel' => 0.25,
            'Gasolina' => 0.30,
            'Etanol' => 0.40
        ],
        'Carreta' => [
            'Diesel' => 0.40,
            'Gasolina' => 0.50,
            'Etanol' => 0.70
        ]
    ];
    
    return $consumos[$tipo_veiculo][$tipo_combustivel] ?? 0.35;
}

/**
 * Obter preço médio do combustível
 */
function obterPrecoCombustivel($tipo_combustivel) {
    $precos = [
        'Diesel' => 4.50,
        'Gasolina' => 5.80,
        'Etanol' => 4.20
    ];
    
    return $precos[$tipo_combustivel] ?? 4.50;
}

/**
 * Calcular custo de pedágio
 */
function calcularCustoPedagio($rota) {
    global $conn;
    
    try {
        $sql = "SELECT SUM(valor) as total_pedagio 
                FROM pedagios 
                WHERE empresa_id = :empresa_id
                AND data_pedagio BETWEEN :data_inicio AND :data_fim";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $rota['empresa_id']);
        $stmt->bindParam(':data_inicio', $rota['data_saida']);
        $stmt->bindParam(':data_fim', $rota['data_chegada']);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return floatval($result['total_pedagio'] ?? 0);
        
    } catch (Exception $e) {
        error_log("Erro ao calcular custo de pedágio: " . $e->getMessage());
        return 0;
    }
}

/**
 * Calcular custo de manutenção estimado
 */
function calcularCustoManutencaoEstimado($rota) {
    $distancia = floatval($rota['distancia_km'] ?? 0);
    $tipo_veiculo = $rota['tipo_veiculo'] ?? 'Caminhão';
    
    // Custo médio de manutenção por km
    $custos_por_km = [
        'Caminhão' => 0.15,
        'Van' => 0.10,
        'Carreta' => 0.20
    ];
    
    $custo_por_km = $custos_por_km[$tipo_veiculo] ?? 0.15;
    return $distancia * $custo_por_km;
}

/**
 * Calcular custo de depreciação
 */
function calcularCustoDepreciacao($rota) {
    $distancia = floatval($rota['distancia_km'] ?? 0);
    
    // Custo médio de depreciação por km (baseado em valor do veículo)
    $custo_depreciacao_por_km = 0.25;
    
    return $distancia * $custo_depreciacao_por_km;
}

/**
 * Calcular custo de seguro
 */
function calcularCustoSeguro($rota) {
    $distancia = floatval($rota['distancia_km'] ?? 0);
    
    // Custo médio de seguro por km
    $custo_seguro_por_km = 0.08;
    
    return $distancia * $custo_seguro_por_km;
}

/**
 * Calcular custo de licenciamento
 */
function calcularCustoLicenciamento($rota) {
    $distancia = floatval($rota['distancia_km'] ?? 0);
    
    // Custo médio de licenciamento por km
    $custo_licenciamento_por_km = 0.05;
    
    return $distancia * $custo_licenciamento_por_km;
}

// ===== FUNÇÕES PARA DETECÇÃO DE FRAUDES =====

/**
 * Detectar fraudes em abastecimentos
 */
function detectarFraudeAbastecimento($abastecimento_id, $empresa_id) {
    global $conn;
    
    try {
        // Buscar dados do abastecimento
        $sql = "SELECT 
                    a.*,
                    r.distancia_km,
                    v.placa,
                    v.modelo,
                    v.tipo,
                    v.tipo_combustivel,
                    m.nome as motorista_nome,
                    co.nome as cidade_origem,
                    cd.nome as cidade_destino
                FROM abastecimentos a
                JOIN rotas r ON a.rota_id = r.id
                JOIN veiculos v ON r.veiculo_id = v.id
                JOIN motoristas m ON r.motorista_id = m.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE a.id = :abastecimento_id AND a.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':abastecimento_id', $abastecimento_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $abastecimento = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$abastecimento) {
            return null;
        }
        
        $fraudes_detectadas = [];
        $score_risco = 0;
        
        // 1. Verificar consumo excessivo
        $consumo_por_km = $abastecimento['litros'] / $abastecimento['distancia_km'];
        $consumo_esperado = obterConsumoMedio($abastecimento['tipo'], $abastecimento['tipo_combustivel']);
        
        if ($consumo_por_km > $consumo_esperado * 1.5) {
            $fraudes_detectadas[] = [
                'tipo' => 'consumo_excessivo',
                'descricao' => 'Consumo muito acima do esperado',
                'valor_atual' => $consumo_por_km,
                'valor_esperado' => $consumo_esperado,
                'severidade' => 'alta'
            ];
            $score_risco += 30;
        }
        
        // 2. Verificar preço do combustível
        $preco_por_litro = $abastecimento['valor_total'] / $abastecimento['litros'];
        $preco_esperado = obterPrecoCombustivel($abastecimento['tipo_combustivel']);
        
        if ($preco_por_litro > $preco_esperado * 1.3) {
            $fraudes_detectadas[] = [
                'tipo' => 'preco_excessivo',
                'descricao' => 'Preço do combustível muito acima do mercado',
                'valor_atual' => $preco_por_litro,
                'valor_esperado' => $preco_esperado,
                'severidade' => 'media'
            ];
            $score_risco += 20;
        }
        
        // 3. Verificar padrão de abastecimento do motorista
        $padrao_motorista = analisarPadraoAbastecimentoMotorista($abastecimento['motorista_id'], $empresa_id);
        if ($padrao_motorista['score_suspeito'] > 70) {
            $fraudes_detectadas[] = [
                'tipo' => 'padrao_suspeito_motorista',
                'descricao' => 'Padrão de abastecimento suspeito do motorista',
                'score_suspeito' => $padrao_motorista['score_suspeito'],
                'severidade' => 'alta'
            ];
            $score_risco += 25;
        }
        
        // 4. Verificar horário de abastecimento
        $hora_abastecimento = date('H', strtotime($abastecimento['data_abastecimento']));
        if ($hora_abastecimento >= 22 || $hora_abastecimento <= 5) {
            $fraudes_detectadas[] = [
                'tipo' => 'horario_suspeito',
                'descricao' => 'Abastecimento em horário suspeito',
                'hora' => $hora_abastecimento,
                'severidade' => 'baixa'
            ];
            $score_risco += 10;
        }
        
        // 5. Verificar local de abastecimento
        $local_suspeito = verificarLocalAbastecimento($abastecimento['posto_combustivel'], $abastecimento['cidade_origem']);
        if ($local_suspeito) {
            $fraudes_detectadas[] = [
                'tipo' => 'local_suspeito',
                'descricao' => 'Abastecimento em local suspeito',
                'local' => $abastecimento['posto_combustivel'],
                'severidade' => 'media'
            ];
            $score_risco += 15;
        }
        
        return [
            'abastecimento_id' => $abastecimento_id,
            'score_risco' => min(100, $score_risco),
            'fraudes_detectadas' => $fraudes_detectadas,
            'status' => $score_risco > 50 ? 'alto_risco' : ($score_risco > 25 ? 'medio_risco' : 'baixo_risco')
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao detectar fraude em abastecimento: " . $e->getMessage());
        return null;
    }
}

/**
 * Analisar padrão de abastecimento do motorista
 */
function analisarPadraoAbastecimentoMotorista($motorista_id, $empresa_id) {
    global $conn;
    
    try {
        // Buscar histórico de abastecimentos do motorista (últimos 30 dias)
        $sql = "SELECT 
                    a.valor_total,
                    a.litros,
                    r.distancia_km,
                    a.posto_combustivel
                FROM abastecimentos a
                JOIN rotas r ON a.rota_id = r.id
                WHERE r.motorista_id = :motorista_id
                AND a.empresa_id = :empresa_id
                AND a.data_abastecimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY a.data_abastecimento DESC
                LIMIT 20";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':motorista_id', $motorista_id);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($abastecimentos) < 3) {
            return ['score_suspeito' => 0, 'motivo' => 'Histórico insuficiente'];
        }
        
        $score_suspeito = 0;
        $motivos = [];
        
        // 1. Verificar variação excessiva nos valores
        $valores = array_column($abastecimentos, 'valor_total');
        $cv_valores = calcularCoeficienteVariacao($valores);
        
        if ($cv_valores > 0.5) {
            $score_suspeito += 20;
            $motivos[] = 'Variação excessiva nos valores de abastecimento';
        }
        
        // 2. Verificar consumo inconsistente
        $consumos = [];
        foreach ($abastecimentos as $abast) {
            if ($abast['distancia_km'] > 0) {
                $consumos[] = $abast['litros'] / $abast['distancia_km'];
            }
        }
        
        if (count($consumos) > 0) {
            $cv_consumo = calcularCoeficienteVariacao($consumos);
            if ($cv_consumo > 0.4) {
                $score_suspeito += 25;
                $motivos[] = 'Consumo muito inconsistente';
            }
        }
        
        // 3. Verificar postos de combustível sempre diferentes
        $postos = array_unique(array_column($abastecimentos, 'posto_combustivel'));
        if (count($postos) / count($abastecimentos) > 0.8) {
            $score_suspeito += 15;
            $motivos[] = 'Muitos postos diferentes (possível fraude)';
        }
        
        return [
            'score_suspeito' => min(100, $score_suspeito),
            'motivos' => $motivos,
            'total_abastecimentos' => count($abastecimentos)
        ];
        
    } catch (Exception $e) {
        error_log("Erro ao analisar padrão do motorista: " . $e->getMessage());
        return ['score_suspeito' => 0, 'motivo' => 'Erro na análise'];
    }
}

/**
 * Verificar se o local de abastecimento é suspeito
 */
function verificarLocalAbastecimento($posto, $cidade) {
    // Lista de postos suspeitos (pode ser expandida)
    $postos_suspeitos = [
        'Posto Fantasma',
        'Posto Teste',
        'Abastecimento Manual'
    ];
    
    foreach ($postos_suspeitos as $posto_suspeito) {
        if (stripos($posto, $posto_suspeito) !== false) {
            return true;
        }
    }
    
    return false;
}

/**
 * Calcular coeficiente de variação
 */
function calcularCoeficienteVariacao($valores) {
    if (count($valores) < 2) {
        return 0;
    }
    
    $media = array_sum($valores) / count($valores);
    $variancia = 0;
    
    foreach ($valores as $valor) {
        $variancia += pow($valor - $media, 2);
    }
    
    $variancia /= count($valores);
    $desvio_padrao = sqrt($variancia);
    
    return $media > 0 ? $desvio_padrao / $media : 0;
}

// ===== FUNÇÕES PARA RANKING DE RISCO =====

/**
 * Calcular ranking de risco por motorista
 */
function calcularRankingRiscoMotoristas($empresa_id) {
    global $conn;
    
    try {
        $sql = "SELECT 
                    m.id,
                    m.nome,
                    COUNT(r.id) as total_rotas,
                    AVG(CASE WHEN r.no_prazo = 0 THEN 1 ELSE 0 END) as taxa_atraso,
                    COUNT(mult.id) as total_multas,
                    COUNT(a.id) as total_abastecimentos,
                    AVG(a.valor_total / NULLIF(r.distancia_km, 0)) as custo_medio_km
                FROM motoristas m
                LEFT JOIN rotas r ON m.id = r.motorista_id AND r.empresa_id = :empresa_id
                LEFT JOIN multas mult ON m.id = mult.motorista_id AND mult.empresa_id = :empresa_id
                LEFT JOIN abastecimentos a ON r.id = a.rota_id
                WHERE m.empresa_id = :empresa_id
                GROUP BY m.id, m.nome
                HAVING total_rotas > 0
                ORDER BY (taxa_atraso * 100 + total_multas * 10 + custo_medio_km * 5) DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':empresa_id', $empresa_id);
        $stmt->execute();
        
        $motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $ranking = [];
        foreach ($motoristas as $index => $motorista) {
            $score_risco = calcularScoreRiscoMotorista($motorista);
            
            $ranking[] = [
                'motorista_id' => $motorista['id'],
                'motorista_nome' => $motorista['nome'],
                'posicao' => $index + 1,
                'score_risco' => $score_risco,
                'categoria_risco' => $score_risco > 70 ? 'Alto Risco' : ($score_risco > 40 ? 'Médio Risco' : 'Baixo Risco'),
                'total_rotas' => $motorista['total_rotas'],
                'taxa_atraso' => $motorista['taxa_atraso'] * 100,
                'total_multas' => $motorista['total_multas'],
                'custo_medio_km' => $motorista['custo_medio_km']
            ];
        }
        
        return $ranking;
        
    } catch (Exception $e) {
        error_log("Erro ao calcular ranking de risco: " . $e->getMessage());
        return [];
    }
}

/**
 * Calcular score de risco de um motorista
 */
function calcularScoreRiscoMotorista($motorista) {
    $score = 0;
    
    // Taxa de atraso (0-40 pontos)
    $score += min(40, $motorista['taxa_atraso'] * 100 * 0.4);
    
    // Multas (0-30 pontos)
    $score += min(30, $motorista['total_multas'] * 5);
    
    // Custo médio por km (0-30 pontos)
    $custo_km = floatval($motorista['custo_medio_km'] ?? 0);
    if ($custo_km > 3.0) {
        $score += min(30, ($custo_km - 3.0) * 10);
    }
    
    return min(100, $score);
}

// ===== EXECUÇÃO PRINCIPAL =====

try {
    // 1. ANÁLISE DE CUSTOS E LUCRO
    echo "🔍 Analisando custos e lucro das rotas...\n";
    
    $sql_rotas = "SELECT id FROM rotas WHERE empresa_id = :empresa_id AND status = 'aprovado' AND data_rota >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $stmt_rotas = $conn->prepare($sql_rotas);
    $stmt_rotas->bindParam(':empresa_id', $empresa_id);
    $stmt_rotas->execute();
    
    $rotas = $stmt_rotas->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($rotas as $rota_id) {
        $previsao = calcularPrevisaoLucro($rota_id, $empresa_id);
        
        if ($previsao && $previsao['status'] === 'prejuizo') {
            inserirNotificacao(
                $empresa_id,
                'alerta',
                '⚠️ Rota com Prejuízo Detectado',
                "Rota #{$rota_id} pode gerar prejuízo de R$ " . number_format(abs($previsao['lucro_previsto']), 2, ',', '.') . ". Margem: " . number_format($previsao['margem_lucro'], 1) . "%",
                "IA detectou que esta rota pode não ser lucrativa. Receita: R$ " . number_format($previsao['receita'], 2, ',', '.') . " | Custos: R$ " . number_format($previsao['custos_variaveis'] + $previsao['custos_fixos'], 2, ',', '.')
            );
        }
    }
    
    // 2. DETECÇÃO DE FRAUDES EM ABASTECIMENTOS
    echo "🕵️ Detectando fraudes em abastecimentos...\n";
    
    $sql_abastecimentos = "SELECT id FROM abastecimentos WHERE empresa_id = :empresa_id AND data_abastecimento >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    $stmt_abastecimentos = $conn->prepare($sql_abastecimentos);
    $stmt_abastecimentos->bindParam(':empresa_id', $empresa_id);
    $stmt_abastecimentos->execute();
    
    $abastecimentos = $stmt_abastecimentos->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($abastecimentos as $abastecimento_id) {
        $fraude = detectarFraudeAbastecimento($abastecimento_id, $empresa_id);
        
        if ($fraude && $fraude['status'] === 'alto_risco') {
            $fraudes_texto = implode(', ', array_column($fraude['fraudes_detectadas'], 'descricao'));
            
            inserirNotificacao(
                $empresa_id,
                'alerta',
                '🚨 Fraude Detectada em Abastecimento',
                "Abastecimento #{$abastecimento_id} apresenta alto risco de fraude (Score: {$fraude['score_risco']}/100). Tipos: {$fraudes_texto}",
                "IA detectou possíveis fraudes: " . $fraudes_texto
            );
        }
    }
    
    // 3. RANKING DE RISCO DE MOTORISTAS
    echo "📊 Calculando ranking de risco dos motoristas...\n";
    
    $ranking_risco = calcularRankingRiscoMotoristas($empresa_id);
    
    foreach ($ranking_risco as $motorista) {
        if ($motorista['categoria_risco'] === 'Alto Risco') {
            inserirNotificacao(
                $empresa_id,
                'alerta',
                '⚠️ Motorista de Alto Risco',
                "Motorista {$motorista['motorista_nome']} está na categoria de Alto Risco (Score: {$motorista['score_risco']}/100). Taxa de atraso: {$motorista['taxa_atraso']}% | Multas: {$motorista['total_multas']}",
                "IA identificou padrões de risco: alta taxa de atraso, multas ou custos elevados"
            );
        }
    }
    
    echo "✅ Análise de custos e detecção de fraudes concluída!\n";
    echo "📈 Rotas analisadas: " . count($rotas) . "\n";
    echo "🔍 Abastecimentos verificados: " . count($abastecimentos) . "\n";
    echo "👥 Motoristas avaliados: " . count($ranking_risco) . "\n";
    
} catch (Exception $e) {
    error_log("Erro na IA avançada de custos e fraudes: " . $e->getMessage());
    echo "❌ Erro na análise: " . $e->getMessage() . "\n";
}
?>
