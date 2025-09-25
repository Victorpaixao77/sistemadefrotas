<?php
/**
 * IA Melhorada para Análise de Dados e Geração de Notificações
 * 
 * Versão aprimorada com validações mais inteligentes e baseadas em histórico
 */

require_once __DIR__ . '/../includes/db_connect.php';
session_start();

// Permitir empresa_id via GET, CLI ou variável de ambiente
if (php_sapi_name() === 'cli') {
    global $argv;
    $empresa_id = 1;
    foreach ($argv as $arg) {
        if (strpos($arg, 'empresa_id=') === 0) {
            $empresa_id = (int)str_replace('empresa_id=', '', $arg);
        }
    }
    if (!$empresa_id) $empresa_id = 1;
} elseif (isset($_GET['empresa_id'])) {
    $empresa_id = (int)$_GET['empresa_id'];
} elseif (isset($_SESSION['empresa_id'])) {
    $empresa_id = $_SESSION['empresa_id'];
} elseif (getenv('EMPRESA_ID')) {
    $empresa_id = (int)getenv('EMPRESA_ID');
} else {
    $empresa_id = 1;
}

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

// ===== FUNÇÕES AUXILIARES PARA ANÁLISE INTELIGENTE =====

/**
 * Obtém histórico de abastecimentos de um veículo
 */
function obterHistoricoAbastecimentos($veiculo_id, $limite = 10) {
    global $conn;
    
    $sql = "SELECT a.*, r.distancia_km, 
            (a.valor_total / r.distancia_km) as valor_por_km,
            (a.litros / r.distancia_km) as litros_por_km,
            (r.distancia_km / a.litros) as consumo_km_l
            FROM abastecimentos a
            JOIN rotas r ON a.rota_id = r.id
            WHERE r.veiculo_id = :veiculo_id
            ORDER BY a.data_abastecimento DESC
            LIMIT :limite";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
    $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calcula média de um array de valores
 */
function calcularMedia($dados, $campo) {
    if (empty($dados)) return 0;
    
    $soma = 0;
    $count = 0;
    
    foreach ($dados as $item) {
        if (isset($item[$campo]) && is_numeric($item[$campo])) {
            $soma += $item[$campo];
            $count++;
        }
    }
    
    return $count > 0 ? $soma / $count : 0;
}

/**
 * Calcula desvio padrão de um array de valores
 */
function calcularDesvioPadrao($dados, $campo) {
    if (empty($dados)) return 0;
    
    $media = calcularMedia($dados, $campo);
    $soma_quadrados = 0;
    $count = 0;
    
    foreach ($dados as $item) {
        if (isset($item[$campo]) && is_numeric($item[$campo])) {
            $diferenca = $item[$campo] - $media;
            $soma_quadrados += $diferenca * $diferenca;
            $count++;
        }
    }
    
    return $count > 1 ? sqrt($soma_quadrados / ($count - 1)) : 0;
}

/**
 * Obtém limites por tipo de veículo
 */
function obterLimitePorTipoVeiculo($tipo_veiculo) {
    $limites = [
        'Caminhão' => 1.2,      // 20% mais tolerante
        'Carro' => 0.8,         // 20% mais rigoroso
        'Van' => 1.0,           // Padrão
        'Moto' => 0.6,          // 40% mais rigoroso
        'Ônibus' => 1.3,        // 30% mais tolerante
    ];
    
    return $limites[$tipo_veiculo] ?? 1.0;
}

/**
 * Obtém limites por tipo de combustível
 */
function obterLimitePorCombustivel($tipo_combustivel) {
    $limites = [
        'Diesel' => 1.1,        // 10% mais tolerante (mais caro)
        'Gasolina' => 1.0,      // Padrão
        'Etanol' => 0.9,        // 10% mais rigoroso (mais barato)
        'GNV' => 0.8,           // 20% mais rigoroso (muito barato)
    ];
    
    return $limites[$tipo_combustivel] ?? 1.0;
}

/**
 * Detecta abastecimento suspeito usando análise inteligente
 */
function detectarAbastecimentoSuspeito($abastecimento, $veiculo, $rota) {
    global $conn;
    
    // 1. Obter histórico do veículo (últimos 10 abastecimentos)
    $historico = obterHistoricoAbastecimentos($veiculo['id'], 10);
    
    if (count($historico) < 3) {
        // Se não há histórico suficiente, usar critérios conservadores
        return $abastecimento['valor_por_km'] > 3.0 || $abastecimento['litros_por_km'] > 0.8;
    }
    
    // 2. Calcular média e desvio padrão do histórico
    $media_valor_km = calcularMedia($historico, 'valor_por_km');
    $desvio_valor_km = calcularDesvioPadrao($historico, 'valor_por_km');
    $media_litros_km = calcularMedia($historico, 'litros_por_km');
    $desvio_litros_km = calcularDesvioPadrao($historico, 'litros_por_km');
    
    // 3. Aplicar limites por tipo de veículo
    $limite_veiculo = obterLimitePorTipoVeiculo($veiculo['tipo'] ?? 'Caminhão');
    
    // 4. Aplicar limites por tipo de combustível
    $limite_combustivel = obterLimitePorCombustivel($veiculo['tipo_combustivel'] ?? 'Diesel');
    
    // 5. Calcular limite dinâmico (média + 2 desvios padrão)
    $limite_valor_suspeito = $media_valor_km + (2 * $desvio_valor_km);
    $limite_litros_suspeito = $media_litros_km + (2 * $desvio_litros_km);
    
    // 6. Aplicar multiplicadores por tipo de veículo e combustível
    $limite_valor_suspeito *= $limite_veiculo * $limite_combustivel;
    $limite_litros_suspeito *= $limite_veiculo * $limite_combustivel;
    
    // 7. Considerar condições da rota
    if ($rota['com_carga'] ?? false) {
        $limite_valor_suspeito *= 1.2; // 20% mais tolerante para cargas
        $limite_litros_suspeito *= 1.2;
    }
    
    // 8. Verificar se está acima do limite
    $valor_suspeito = $abastecimento['valor_por_km'] > $limite_valor_suspeito;
    $litros_suspeitos = $abastecimento['litros_por_km'] > $limite_litros_suspeito;
    
    return [
        'suspeito' => $valor_suspeito || $litros_suspeitos,
        'valor_suspeito' => $valor_suspeito,
        'litros_suspeitos' => $litros_suspeitos,
        'limite_valor' => $limite_valor_suspeito,
        'limite_litros' => $limite_litros_suspeito,
        'media_valor' => $media_valor_km,
        'media_litros' => $media_litros_km,
        'desvio_valor' => $desvio_valor_km,
        'desvio_litros' => $desvio_litros_km
    ];
}

// ===== EXECUÇÃO DAS VALIDAÇÕES MELHORADAS =====

try {
    // 1. VALIDAÇÃO MELHORADA DE ABASTECIMENTO SUSPEITO
    $sql = "SELECT a.*, r.distancia_km, v.placa, v.modelo, v.tipo, v.tipo_combustivel, r.id as rota_id, 
            co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota, r.com_carga,
            (a.valor_total / r.distancia_km) as valor_por_km,
            (a.litros / r.distancia_km) as litros_por_km
            FROM abastecimentos a
            JOIN rotas r ON a.rota_id = r.id
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id
            ORDER BY a.data_abastecimento DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        
        // Usar validação inteligente
        $resultado = detectarAbastecimentoSuspeito($row, $row, $row);
        
        if ($resultado['suspeito']) {
            $motivos = [];
            if ($resultado['valor_suspeito']) {
                $excesso_valor = round((($row['valor_por_km'] / $resultado['limite_valor']) - 1) * 100, 1);
                $motivos[] = "valor por km ({$excesso_valor}% acima do limite)";
            }
            if ($resultado['litros_suspeitos']) {
                $excesso_litros = round((($row['litros_por_km'] / $resultado['limite_litros']) - 1) * 100, 1);
                $motivos[] = "litros por km ({$excesso_litros}% acima do limite)";
            }
            
            $titulo = 'Abastecimento suspeito (IA Melhorada)';
            $mensagem = 'Abastecimento do veículo ' . $placa . ' na Rota* ' . $rota_nome . ' está acima do esperado.';
            $ia_mensagem = 'Análise IA: ' . implode(', ', $motivos) . '. ';
            $ia_mensagem .= 'Limite dinâmico: R$ ' . number_format($resultado['limite_valor'], 2, ',', '.') . '/km, ';
            $ia_mensagem .= number_format($resultado['limite_litros'], 2, ',', '.') . ' L/km. ';
            $ia_mensagem .= 'Média histórica: R$ ' . number_format($resultado['media_valor'], 2, ',', '.') . '/km, ';
            $ia_mensagem .= number_format($resultado['media_litros'], 2, ',', '.') . ' L/km.';
            
            inserirNotificacao($empresa_id, 'alerta', $titulo, $mensagem, $ia_mensagem);
        }
    }
    
    // 2. VALIDAÇÃO MELHORADA DE CONSUMO ABAIXO DO ESPERADO
    $sql = "SELECT a.*, r.distancia_km, v.modelo, v.placa, r.id as rota_id, 
            co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota,
            (r.distancia_km / a.litros) as consumo_atual
            FROM abastecimentos a
            JOIN rotas r ON a.rota_id = r.id
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $modelo = $row['modelo'];
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        
        // Obter histórico de consumo do veículo
        $historico_consumo = obterHistoricoAbastecimentos($row['veiculo_id'], 10);
        $consumos = array_column($historico_consumo, 'consumo_km_l');
        $consumos = array_filter($consumos, function($c) { return $c > 0; });
        
        if (count($consumos) >= 3) {
            $media_consumo = calcularMedia($historico_consumo, 'consumo_km_l');
            $desvio_consumo = calcularDesvioPadrao($historico_consumo, 'consumo_km_l');
            $limite_consumo_baixo = $media_consumo - (2 * $desvio_consumo);
            
            if ($row['consumo_atual'] < $limite_consumo_baixo) {
                $titulo = 'Consumo abaixo do esperado (IA Melhorada)';
                $mensagem = "O consumo do veículo {$placa} na Rota* {$rota_nome} está abaixo do esperado.";
                $ia_mensagem = "Análise IA: Consumo atual: " . round($row['consumo_atual'], 2) . " km/l. ";
                $ia_mensagem .= "Média histórica: " . round($media_consumo, 2) . " km/l. ";
                $ia_mensagem .= "Limite inferior: " . round($limite_consumo_baixo, 2) . " km/l. ";
                $ia_mensagem .= "Verifique se há problemas mecânicos ou uso inadequado.";
                
                inserirNotificacao($empresa_id, 'alerta', $titulo, $mensagem, $ia_mensagem);
            }
        }
    }
    
    // 3. VALIDAÇÃO MELHORADA DE DESPESA DE VIAGEM ALTA
    $sql = "SELECT d.*, r.id as rota_id, v.placa, v.tipo, co.nome as cidade_origem_nome, 
            cd.nome as cidade_destino_nome, r.data_rota, r.distancia_km, r.com_carga
            FROM despesas_viagem d
            JOIN rotas r ON d.rota_id = r.id
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        
        // Calcular limite dinâmico baseado no tipo de veículo e distância
        $limite_base = 500; // R$ 500 base
        $limite_por_km = 2.0; // R$ 2 por km
        
        if ($row['tipo'] === 'Caminhão') {
            $limite_base = 800;
            $limite_por_km = 3.0;
        } elseif ($row['tipo'] === 'Carro') {
            $limite_base = 300;
            $limite_por_km = 1.5;
        }
        
        if ($row['com_carga']) {
            $limite_base *= 1.3; // 30% mais tolerante para cargas
        }
        
        $limite_dinamico = $limite_base + ($row['distancia_km'] * $limite_por_km);
        
        if ($row['total_despviagem'] > $limite_dinamico) {
            $excesso = round((($row['total_despviagem'] / $limite_dinamico) - 1) * 100, 1);
            $titulo = 'Despesa de viagem alta (IA Melhorada)';
            $mensagem = 'Despesa de viagem na Rota* ' . $rota_nome . ' do veículo ' . $placa . ' ultrapassou o limite.';
            $ia_mensagem = "Análise IA: Despesa: R$ " . number_format($row['total_despviagem'], 2, ',', '.') . ". ";
            $ia_mensagem .= "Limite dinâmico: R$ " . number_format($limite_dinamico, 2, ',', '.') . " ({$excesso}% acima). ";
            $ia_mensagem .= "Tipo: {$row['tipo']}, Distância: {$row['distancia_km']} km, Carga: " . ($row['com_carga'] ? 'Sim' : 'Não') . ".";
            
            inserirNotificacao($empresa_id, 'alerta', $titulo, $mensagem, $ia_mensagem);
        }
    }
    
    // 4. MANTER OUTRAS VALIDAÇÕES EXISTENTES (sem alterações)
    // ... (código das outras validações permanece igual)
    
} catch (Exception $e) {
    error_log("Erro na execução da IA melhorada: " . $e->getMessage());
}
?>
