<?php
// IA simples para análise de dados e geração de notificações
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

// Adicionar log de debug para confirmar execução da IA
// error_log("IA/ia_regras.php executado em " . date('Y-m-d H:i:s'));

// Adicionar verificação de notificações recentes para evitar duplicidades
function notificacaoExiste($empresa_id, $tipo, $titulo, $mensagem) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE empresa_id = ? AND tipo = ? AND titulo = ? AND mensagem = ? AND data_criacao > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute([$empresa_id, $tipo, $titulo, $mensagem]);
    return $stmt->fetchColumn() > 0;
}

// Função para inserir notificação com verificação de duplicidade
function inserirNotificacao($empresa_id, $tipo, $titulo, $mensagem, $ia_mensagem = null) {
    // error_log('DEBUG IA: Chamando inserirNotificacao: ' . $titulo . ' - ' . $mensagem);
    if (!notificacaoExiste($empresa_id, $tipo, $titulo, $mensagem)) {
        global $conn;
        $stmt = $conn->prepare("INSERT INTO notificacoes (empresa_id, tipo, titulo, mensagem, ia_mensagem, data_criacao, lida) VALUES (?, ?, ?, ?, ?, NOW(), 0)");
        $stmt->execute([$empresa_id, $tipo, $titulo, $mensagem, $ia_mensagem]);
        // error_log('DEBUG IA: Notificação inserida!');
    } else {
        // error_log('DEBUG IA: Notificação já existe, não inserida.');
    }
}

// VALIDAÇÃO MELHORADA DE CONSUMO ABAIXO DO ESPERADO
$sql = "SELECT a.*, r.distancia_km, v.modelo, v.placa, v.id as veiculo_id, r.id as rota_id, 
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
    $historico_sql = "SELECT a.*, r.distancia_km, 
            (r.distancia_km / a.litros) as consumo_km_l
            FROM abastecimentos a
            JOIN rotas r ON a.rota_id = r.id
            WHERE r.veiculo_id = :veiculo_id
            ORDER BY a.data_abastecimento DESC
            LIMIT 10";
    
    $hist_stmt = $conn->prepare($historico_sql);
    $hist_stmt->execute(['veiculo_id' => $row['veiculo_id']]);
    $historico = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($historico) >= 3) {
        // Calcular média e desvio padrão do consumo
        $consumos = array_column($historico, 'consumo_km_l');
        $consumos = array_filter($consumos, function($c) { return $c > 0; });
        
        if (count($consumos) >= 3) {
            $media_consumo = array_sum($consumos) / count($consumos);
            $variancia = array_sum(array_map(function($x) use ($media_consumo) { return pow($x - $media_consumo, 2); }, $consumos)) / (count($consumos) - 1);
            $desvio_consumo = sqrt($variancia);
            
            // Limite inferior: média - 2 desvios padrão
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
    } else {
        // Se não há histórico suficiente, usar critérios conservadores baseados no tipo de veículo
        $consumo_esperado = 5.0; // Padrão conservador
        if ($row['tipo'] === 'Caminhão') $consumo_esperado = 2.5;
        elseif ($row['tipo'] === 'Carro') $consumo_esperado = 8.0;
        elseif ($row['tipo'] === 'Van') $consumo_esperado = 6.0;
        elseif ($row['tipo'] === 'Moto') $consumo_esperado = 15.0;
        
        if ($row['consumo_atual'] < ($consumo_esperado * 0.7)) {
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Consumo abaixo do esperado (Pouco Histórico)',
                "O consumo do veículo {$placa} na Rota* {$rota_nome} está abaixo do esperado.",
                "Pouco histórico disponível. Consumo: " . round($row['consumo_atual'], 2) . " km/l. Esperado para {$row['tipo']}: ~{$consumo_esperado} km/l."
            );
        }
    }
}

// Adicionar tratamento de erro para evitar que o script interrompa a execução
try {
    // error_log('DEBUG IA: Entrou no bloco try da IA');
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
        
        // Análise inteligente baseada no histórico
        $historico_sql = "SELECT a.*, r.distancia_km, 
                (a.valor_total / r.distancia_km) as valor_por_km,
                (a.litros / r.distancia_km) as litros_por_km
                FROM abastecimentos a
                JOIN rotas r ON a.rota_id = r.id
                WHERE r.veiculo_id = :veiculo_id
                ORDER BY a.data_abastecimento DESC
                LIMIT 10";
        
        $hist_stmt = $conn->prepare($historico_sql);
        $hist_stmt->execute(['veiculo_id' => $row['veiculo_id']]);
        $historico = $hist_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($historico) >= 3) {
            // Calcular média e desvio padrão
            $valores_km = array_column($historico, 'valor_por_km');
            $litros_km = array_column($historico, 'litros_por_km');
            
            $media_valor = array_sum($valores_km) / count($valores_km);
            $media_litros = array_sum($litros_km) / count($litros_km);
            
            $desvio_valor = 0;
            $desvio_litros = 0;
            if (count($valores_km) > 1) {
                $variancia_valor = array_sum(array_map(function($x) use ($media_valor) { return pow($x - $media_valor, 2); }, $valores_km)) / (count($valores_km) - 1);
                $variancia_litros = array_sum(array_map(function($x) use ($media_litros) { return pow($x - $media_litros, 2); }, $litros_km)) / (count($litros_km) - 1);
                $desvio_valor = sqrt($variancia_valor);
                $desvio_litros = sqrt($variancia_litros);
            }
            
            // Aplicar limites por tipo de veículo
            $limite_veiculo = 1.0;
            if ($row['tipo'] === 'Caminhão') $limite_veiculo = 1.2;
            elseif ($row['tipo'] === 'Carro') $limite_veiculo = 0.8;
            elseif ($row['tipo'] === 'Moto') $limite_veiculo = 0.6;
            
            // Aplicar limites por tipo de combustível
            $limite_combustivel = 1.0;
            if ($row['tipo_combustivel'] === 'Diesel') $limite_combustivel = 1.1;
            elseif ($row['tipo_combustivel'] === 'Etanol') $limite_combustivel = 0.9;
            elseif ($row['tipo_combustivel'] === 'GNV') $limite_combustivel = 0.8;
            
            // Calcular limite dinâmico (média + 2 desvios)
            $limite_valor = ($media_valor + (2 * $desvio_valor)) * $limite_veiculo * $limite_combustivel;
            $limite_litros = ($media_litros + (2 * $desvio_litros)) * $limite_veiculo * $limite_combustivel;
            
            // Ajustar por condições da rota
            if ($row['com_carga']) {
                $limite_valor *= 1.2;
                $limite_litros *= 1.2;
            }
            
            // Verificar se está acima do limite
            $valor_suspeito = $row['valor_por_km'] > $limite_valor;
            $litros_suspeitos = $row['litros_por_km'] > $limite_litros;
            
            if ($valor_suspeito || $litros_suspeitos) {
                $motivos = [];
                if ($valor_suspeito) {
                    $excesso_valor = round((($row['valor_por_km'] / $limite_valor) - 1) * 100, 1);
                    $motivos[] = "valor por km ({$excesso_valor}% acima do limite)";
                }
                if ($litros_suspeitos) {
                    $excesso_litros = round((($row['litros_por_km'] / $limite_litros) - 1) * 100, 1);
                    $motivos[] = "litros por km ({$excesso_litros}% acima do limite)";
                }
                
                $titulo = 'Abastecimento suspeito (IA Melhorada)';
                $mensagem = 'Abastecimento do veículo ' . $placa . ' na Rota* ' . $rota_nome . ' está acima do esperado.';
                $ia_mensagem = 'Análise IA: ' . implode(', ', $motivos) . '. ';
                $ia_mensagem .= 'Limite dinâmico: R$ ' . number_format($limite_valor, 2, ',', '.') . '/km, ';
                $ia_mensagem .= number_format($limite_litros, 2, ',', '.') . ' L/km. ';
                $ia_mensagem .= 'Média histórica: R$ ' . number_format($media_valor, 2, ',', '.') . '/km, ';
                $ia_mensagem .= number_format($media_litros, 2, ',', '.') . ' L/km.';
                
                inserirNotificacao($empresa_id, 'alerta', $titulo, $mensagem, $ia_mensagem);
            }
        } else {
            // Se não há histórico suficiente, usar critérios conservadores
            if ($row['valor_por_km'] > 3.0 || $row['litros_por_km'] > 0.8) {
                inserirNotificacao(
                    $empresa_id,
                    'alerta',
                    'Abastecimento suspeito (Pouco Histórico)',
                    'Abastecimento do veículo ' . $placa . ' na Rota* ' . $rota_nome . ' está acima do esperado.',
                    'Pouco histórico disponível. Valor: R$ ' . number_format($row['valor_por_km'], 2, ',', '.') . '/km, Litros: ' . number_format($row['litros_por_km'], 2, ',', '.') . ' L/km.'
                );
            }
        }
    }

    // 2. Manutenção atrasada
    $sql = "SELECT m.*, v.modelo FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
            WHERE v.empresa_id = :empresa_id AND m.data_manutencao < NOW() AND sm.nome != 'Concluída'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'manutencao',
            'Manutenção atrasada',
            'Manutenção do veículo ' . $row['modelo'] . ' está atrasada.',
            'Agende a manutenção o quanto antes para evitar problemas mecânicos e custos maiores.'
        );
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
        } elseif ($row['tipo'] === 'Van') {
            $limite_base = 400;
            $limite_por_km = 2.2;
        } elseif ($row['tipo'] === 'Moto') {
            $limite_base = 200;
            $limite_por_km = 1.0;
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

    // 4. Rota demorando muito para ser concluída
    $sql = "SELECT r.*, v.placa, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id AND r.status = 'em andamento' AND TIMESTAMPDIFF(HOUR, r.data_saida, NOW()) > 48";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Rota em atraso',
            'Rota* ' . $rota_nome . ' do veículo ' . $placa . ' está em andamento há mais de 48 horas.',
            'Entre em contato com o motorista para verificar o motivo do atraso e atualizar o status da rota.'
        );
    }

    // 5. Checklist pendente
    $sql = "SELECT cv.*, v.modelo FROM checklist_viagem cv
            JOIN veiculos v ON cv.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id AND cv.data_checklist IS NULL";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Checklist pendente',
            'Checklist pendente para o veículo ' . $row['modelo'] . '.',
            'Peça ao motorista para completar o checklist antes de liberar o veículo para nova rota.'
        );
    }

    // 6. Eficiência da Viagem Abaixo do Esperado
    $sql = "SELECT r.*, v.placa, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id AND r.eficiencia_viagem < 70";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Eficiência da Viagem Baixa',
            "A eficiência da Rota* {$rota_nome} do veículo {$placa} está abaixo de 70%.",
            "Verifique se houve desvios ou tempo ocioso."
        );
    }

    // 7. Percentual de KM Vazio Alto
    $sql = "SELECT r.*, v.placa, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id AND r.percentual_vazio > 30";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Alto Percentual de KM Vazio',
            "A Rota* {$rota_nome} do veículo {$placa} teve um percentual de quilometragem vazia acima de 30%.",
            "Reavalie a logística ou tente agendar fretes de retorno."
        );
    }

    // 8. Frete vs. Custo da Viagem (Rentabilidade)
    $sql = "SELECT r.*, v.placa, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota, (SELECT COALESCE(SUM(d.total_despviagem), 0) FROM despesas_viagem d WHERE d.rota_id = r.id) as total_despesas
            FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id
            HAVING r.frete < total_despesas";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Custo da Rota Superior ao Frete',
            "O custo da Rota* {$rota_nome} do veículo {$placa} foi superior ao valor do frete.",
            "Reveja os gastos ou renegocie tarifas."
        );
    }

    // 9. Desvio de KM Percorrido
    $sql = "SELECT r.*, v.placa, co.nome as cidade_origem_nome, cd.nome as cidade_destino_nome, r.data_rota, (r.km_chegada - r.km_saida) as percorrido FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE v.empresa_id = :empresa_id AND ABS((r.km_chegada - r.km_saida) - r.distancia_km) > 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $placa = $row['placa'];
        $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
            ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Desvio de Rota Detectado',
            "Houve um desvio de rota superior a 50 km na Rota* {$rota_nome} do veículo {$placa}.",
            "Verifique o trajeto e se houve alterações."
        );
    }

    // 10. Previsão de Manutenção Próxima
    $sql = "SELECT m.* FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
            WHERE v.empresa_id = :empresa_id AND m.data_manutencao BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 5 DAY)
            AND sm.nome != 'Concluída'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'info',
            'Manutenção Agendada para os Próximos Dias',
            "Manutenção do veículo ID {$row['veiculo_id']} agendada para {$row['data_manutencao']}.",
            "Programe-se para evitar conflitos com rotas."
        );
    }

    // --- Frete zerado ---
    try {
        // error_log('DEBUG IA: INÍCIO foreach frete zerado');
        $sql = "SELECT r.*, v.placa, co.nome AS cidade_origem_nome, cd.nome AS cidade_destino_nome, r.data_rota, r.status
                FROM rotas r
                JOIN veiculos v ON r.veiculo_id = v.id
                LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                WHERE v.empresa_id = :empresa_id AND r.frete = 0 AND r.status = 'aprovado'";
        $count_frete_zerado = 0;
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count_frete_zerado++;
            // error_log('DEBUG IA: FRETE ZERADO - ROTA ID: ' . $row['id'] . ' STATUS: ' . $row['status']);
            $rota_nome = ($row['data_rota'] ? date('Y-m-d', strtotime($row['data_rota'])) : '') .
                ' - ' . ($row['cidade_origem_nome'] ?? '-') . ' → ' . ($row['cidade_destino_nome'] ?? '-');
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Rota com frete zerado',
                "A Rota* {$rota_nome} (ID {$row['id']}) do veículo {$row['placa']} foi registrada com frete igual a R$ 0,00.",
                "Verifique se houve falha no preenchimento ou rota não comercializada."
            );
        }
        // error_log('DEBUG IA: FIM foreach frete zerado. Total encontrados: ' . $count_frete_zerado);
    } catch (Exception $e) {
        // error_log("Erro em frete zerado: " . $e->getMessage());
    }

    // 2. Veículo com 3+ manutenções em 30 dias
    $sql = "SELECT m.veiculo_id, COUNT(*) AS qtd_manutencoes
            FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND m.data_conclusao IS NOT NULL
            AND m.data_conclusao > DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY m.veiculo_id
            HAVING qtd_manutencoes >= 3";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Manutenções frequentes',
            "O veículo ID {$row['veiculo_id']} passou por {$row['qtd_manutencoes']} manutenções nos últimos 30 dias.",
            "Avalie substituição ou revisão completa do veículo."
        );
    }

    // 3. Documentação de Motorista Vencida
    $sql = "SELECT m.* FROM motoristas m
            JOIN rotas r ON m.id = r.motorista_id
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id 
            AND m.data_validade_cnh < DATE_ADD(NOW(), INTERVAL 15 DAY)
            GROUP BY m.id";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'CNH vencida ou próxima do vencimento',
            "Motorista {$row['nome']} com CNH vencida ou vencendo em {$row['data_validade_cnh']}.",
            "Verifique a regularização do documento."
        );
    }

    // --- Carga excedida ---
    try {
        // error_log('DEBUG IA: INÍCIO foreach carga excedida');
        $sql = "SELECT r.*, v.placa, v.capacidade_carga, r.status FROM rotas r
                JOIN veiculos v ON r.veiculo_id = v.id
                WHERE v.empresa_id = :empresa_id AND r.peso_carga > v.capacidade_carga AND r.status = 'aprovado'";
        $count_carga_excedida = 0;
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count_carga_excedida++;
            // error_log('DEBUG IA: CARGA EXCEDIDA - ROTA ID: ' . $row['id'] . ' STATUS: ' . $row['status']);
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Excesso de carga',
                "O veículo {$row['placa']} (Rota ID {$row['id']}) excedeu a capacidade de carga em uma rota.",
                "Peso da carga: {$row['peso_carga']} kg | Capacidade: {$row['capacidade_carga']} kg."
            );
        }
        // error_log('DEBUG IA: FIM foreach carga excedida. Total encontrados: ' . $count_carga_excedida);
    } catch (Exception $e) {
        // error_log("Erro em carga excedida: " . $e->getMessage());
    }

    // 5. Parcela de Financiamento em Atraso
    $sql = "SELECT pf.* FROM parcelas_financiamento pf
            JOIN veiculos v ON pf.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND pf.data_vencimento < CURDATE() 
            AND pf.data_pagamento IS NULL 
            AND pf.status_id != 2";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Parcela de financiamento em atraso',
            "Parcela ID {$row['id']} do financiamento está vencida desde {$row['data_vencimento']}.",
            "Evite juros ou bloqueio do veículo."
        );
    }

    // Consulta para IPVA/Seguro vencido - Versão 100% testada
    try {
        // error_log('DEBUG IA: Iniciando verificação de IPVA/Seguro vencido');
        
        $sql = "SELECT 
                    df.id,
                    df.veiculo_id,
                    df.tipo_despesa_id,
                    DATE_FORMAT(df.vencimento, '%d/%m/%Y') as vencimento_formatado,
                    v.placa,
                    df.status_pagamento_id
                FROM despesas_fixas df
                INNER JOIN veiculos v ON df.veiculo_id = v.id
                WHERE df.tipo_despesa_id IN (3, 4) 
                AND df.vencimento < CURDATE() 
                AND df.status_pagamento_id != 2
                AND df.empresa_id = :empresa_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        $count = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count++;
            $tipo = ($row['tipo_despesa_id'] == 3) ? 'IPVA' : 'Seguro';
            
            inserirNotificacao(
                $empresa_id,
                'alerta',
                "{$tipo} vencido",
                "{$tipo} do veículo {$row['placa']} (ID: {$row['veiculo_id']}) está vencido desde {$row['vencimento_formatado']}",
                "Regularize o quanto antes para evitar sanções."
            );
        }
        
        // error_log("DEBUG IA: Verificação de IPVA/Seguro concluída. {$count} registros processados.");
    } catch (PDOException $e) {
        // error_log("ERRO CRÍTICO na consulta de IPVA/Seguro: " . $e->getMessage());
        // error_log("Consulta SQL: " . $sql);
        // error_log("Parâmetros: empresa_id={$empresa_id}");
    }

    // Lavagens em excesso - IA
    $sql = "SELECT r.veiculo_id, COUNT(*) AS qtd_lavagens 
    FROM despesas_viagem d
    JOIN rotas r ON d.rota_id = r.id
    JOIN veiculos v ON r.veiculo_id = v.id
    WHERE v.empresa_id = :empresa_id
      AND d.lavagem > 0 
      AND d.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY r.veiculo_id
    HAVING qtd_lavagens > 3";

    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC) as $row
    ) {
        // Buscar modelo do veículo
        $veiculo_id = $row['veiculo_id'];
        $veiculoStmt = $conn->prepare("SELECT modelo FROM veiculos WHERE id = ? AND empresa_id = ?");
        $veiculoStmt->execute([$veiculo_id, $empresa_id]);
        $veiculo = $veiculoStmt->fetch();

        if ($veiculo) {
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Lavagens em excesso',
                'O veículo ' . $veiculo['modelo'] . ' teve mais de 3 lavagens nos últimos 30 dias.',
                'Avalie se essas lavagens são justificadas ou se há exagero nos gastos.'
            );
        }
    }

    // 8. Motorista sem checklist na última rota
    $sql = "SELECT r.motorista_id, r.id AS rota_id FROM rotas r
            JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN checklist_viagem c ON r.id = c.rota_id
            WHERE v.empresa_id = :empresa_id 
            AND c.id IS NULL 
            AND r.status = 'concluida'";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Checklist ausente',
            "A Rota ID {$row['rota_id']} do motorista ID {$row['motorista_id']} foi concluída sem checklist.",
            "Oriente o motorista a preencher o checklist da viagem."
        );
    }

    // 9. Manutenção com valor acima da média
    $sql = "SELECT m.* FROM manutencoes m
            JOIN veiculos v ON m.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND m.valor > (SELECT AVG(m2.valor) FROM manutencoes m2 JOIN veiculos v2 ON m2.veiculo_id = v2.id WHERE v2.empresa_id = :empresa_id)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Manutenção cara',
            "Manutenção ID {$row['id']} teve custo de R$ " . number_format($row['valor'], 2, ',', '.') . " acima da média.",
            "Avalie justificativa ou negociação com fornecedor."
        );
    }

    // 10. Frete abaixo de R$ 1/km
    try {
        // error_log('DEBUG IA: INÍCIO foreach frete abaixo do mínimo');
        $sql = "SELECT r.* FROM rotas r
                JOIN veiculos v ON r.veiculo_id = v.id
                WHERE v.empresa_id = :empresa_id
                AND r.distancia_km > 0 
                AND r.frete / r.distancia_km < 1";
        $count_frete_baixo = 0;
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count_frete_baixo++;
            // error_log('DEBUG IA: FRETE BAIXO - ROTA ID: ' . $row['id'] . ' FRETE: ' . $row['frete'] . ' DISTÂNCIA: ' . $row['distancia_km']);
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Frete abaixo do mínimo',
                "Rota ID {$row['id']} com frete de R$ " . number_format($row['frete'], 2, ',', '.') . " para {$row['distancia_km']} km.",
                "Reveja o valor negociado, está abaixo de R$1/km."
            );
        }
        // error_log('DEBUG IA: FIM foreach frete abaixo do mínimo. Total encontrados: ' . $count_frete_baixo);
    } catch (Exception $e) {
        // error_log("Erro em frete abaixo do mínimo: " . $e->getMessage());
    }

    // 11. Adiantamento acima de 50% do frete
    try {
        // error_log('DEBUG IA: INÍCIO foreach adiantamento elevado');
        $sql = "SELECT d.*, r.frete FROM despesas_viagem d
                JOIN rotas r ON d.rota_id = r.id
                JOIN veiculos v ON r.veiculo_id = v.id
                WHERE v.empresa_id = :empresa_id
                AND d.adiantamento > r.frete * 0.5";
        $count_adiantamento = 0;
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $count_adiantamento++;
            // error_log('DEBUG IA: ADIANTAMENTO ELEVADO - ROTA ID: ' . $row['rota_id'] . ' ADIANTAMENTO: ' . $row['adiantamento'] . ' FRETE: ' . $row['frete']);
            inserirNotificacao(
                $empresa_id,
                'alerta',
                'Adiantamento elevado',
                "Na Rota ID {$row['rota_id']}, o adiantamento foi superior a 50% do valor do frete.",
                "Verifique a política de adiantamentos para motoristas."
            );
        }
        // error_log('DEBUG IA: FIM foreach adiantamento elevado. Total encontrados: ' . $count_adiantamento);
    } catch (Exception $e) {
        // error_log("Erro em adiantamento elevado: " . $e->getMessage());
    }

    // 12. Motoristas com Repetidas Pendências de Checklist
    $sql = "SELECT cv.motorista_id, COUNT(*) as pendentes FROM checklist_viagem cv
            JOIN rotas r ON cv.rota_id = r.id
            JOIN veiculos v ON r.veiculo_id = v.id
            WHERE v.empresa_id = :empresa_id
            AND cv.data_checklist IS NULL
            GROUP BY cv.motorista_id 
            HAVING pendentes > 3";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['empresa_id' => $empresa_id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        inserirNotificacao(
            $empresa_id,
            'alerta',
            'Múltiplos Checklists Pendentes',
            "Motorista ID {$row['motorista_id']} possui {$row['pendentes']} checklists pendentes.",
            "Acompanhe o cumprimento dos procedimentos."
        );
    }

    // ===== NOVAS FUNCIONALIDADES DE IA PARA PNEUS =====
    
    // 12. Pneus próximos da troca (80% da vida útil)
    try {
        $sql = "SELECT p.*, v.placa, v.modelo, ip.posicao
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND p.quilometragem > 64000"; // 80% de 80.000 km
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            inserirNotificacao(
                $empresa_id,
                'pneu',
                'Pneu próximo da troca',
                "Pneu {$row['numero_serie']} do veículo {$row['placa']} (posição {$row['posicao']}) está próximo do limite de quilometragem ({$row['quilometragem']} km).",
                "Planeje a troca do pneu nas próximas semanas para evitar problemas de segurança."
            );
        }
    } catch (Exception $e) {
        // error_log("Erro em verificação de pneus próximos da troca: " . $e->getMessage());
    }
    
    // 13. Pneus com desgaste crítico (acima de 100.000 km)
    try {
        $sql = "SELECT p.*, v.placa, v.modelo, ip.posicao
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND p.quilometragem > 100000";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            inserirNotificacao(
                $empresa_id,
                'pneu',
                '🚨 PNEU CRÍTICO - Troca URGENTE',
                "Pneu {$row['numero_serie']} do veículo {$row['placa']} (posição {$row['posicao']}) está com quilometragem CRÍTICA ({$row['quilometragem']} km).",
                "TROCA IMEDIATA NECESSÁRIA! Este pneu representa risco de segurança."
            );
        }
    } catch (Exception $e) {
        // error_log("Erro em verificação de pneus críticos: " . $e->getMessage());
    }
    
    // 14. Pneus com idade avançada (mais de 6 anos)
    try {
        $sql = "SELECT p.*, v.placa, v.modelo, ip.posicao, p.dot
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND p.dot IS NOT NULL
                AND p.dot != ''
                AND (YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS UNSIGNED))) > 6";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $idade = date('Y') - (2000 + intval(substr($row['dot'], 2, 2)));
            inserirNotificacao(
                $empresa_id,
                'pneu',
                'Pneu com idade avançada',
                "Pneu {$row['numero_serie']} do veículo {$row['placa']} (posição {$row['posicao']}) tem {$idade} anos de idade (DOT: {$row['dot']}).",
                "Verifique a integridade do pneu e considere a troca por segurança."
            );
        }
    } catch (Exception $e) {
        // error_log("Erro em verificação de idade dos pneus: " . $e->getMessage());
    }
    
    // 15. Otimização de alocação de pneus
    try {
        $sql = "SELECT 
                    p.id as pneu_id, p.numero_serie, p.quilometragem, p.marca, p.modelo,
                    v.placa, v.modelo as veiculo_modelo, ip.posicao,
                    CASE 
                        WHEN ip.posicao IN (1, 2) THEN 'baixo'
                        WHEN ip.posicao IN (3, 4, 5, 6) THEN 'alto'
                        ELSE 'medio'
                    END as desgaste_posicao
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND p.quilometragem > 60000
                AND ip.posicao IN (1, 2)"; // Pneus com alta quilometragem em posições de baixo desgaste
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            inserirNotificacao(
                $empresa_id,
                'pneu',
                '📊 Análise Preditiva: Otimizar alocação',
                "Pneu {$row['numero_serie']} ({$row['quilometragem']} km) está em posição de baixo desgaste no veículo {$row['placa']}.",
                "Considere mover este pneu para posição de maior desgaste para otimizar a vida útil da frota."
            );
        }
    } catch (Exception $e) {
        // error_log("Erro em otimização de alocação: " . $e->getMessage());
    }
    
    // 16. Análise preditiva de falhas
    try {
        $sql = "SELECT 
                    p.*, v.placa, v.modelo as veiculo_modelo, ip.posicao,
                    p.quilometragem,
                    (YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS UNSIGNED))) as idade
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND (
                    p.quilometragem > 90000 
                    OR (YEAR(NOW()) - (2000 + CAST(SUBSTRING(p.dot, 3, 2) AS UNSIGNED))) > 7
                )";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $risco = 0;
            $motivos = [];
            
            if ($row['quilometragem'] > 90000) {
                $risco += 0.6;
                $motivos[] = "alta quilometragem ({$row['quilometragem']} km)";
            }
            
            if ($row['idade'] > 7) {
                $risco += 0.4;
                $motivos[] = "idade avançada ({$row['idade']} anos)";
            }
            
            if ($risco > 0.5) {
                inserirNotificacao(
                    $empresa_id,
                    'pneu',
                    '📊 Análise Preditiva: Risco de Falha',
                    "Pneu {$row['numero_serie']} do veículo {$row['placa']} apresenta risco de falha por: " . implode(', ', $motivos) . ".",
                    "Probabilidade de falha: " . round($risco * 100, 1) . "%. Recomenda-se troca preventiva."
                );
            }
        }
    } catch (Exception $e) {
        // error_log("Erro em análise preditiva: " . $e->getMessage());
    }
    
    // 17. Verificação de pressão e calibração (simulado)
    try {
        $sql = "SELECT 
                    p.*, v.placa, v.modelo as veiculo_modelo, ip.posicao,
                    p.quilometragem
                FROM pneus p
                JOIN instalacoes_pneus ip ON p.id = ip.pneu_id
                JOIN veiculos v ON ip.veiculo_id = v.id
                WHERE p.empresa_id = :empresa_id 
                AND ip.data_remocao IS NULL
                AND p.quilometragem > 50000
                AND RAND() < 0.3"; // 30% de chance de alerta de pressão
        
        $stmt = $conn->prepare($sql);
        $stmt->execute(['empresa_id' => $empresa_id]);
        
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            inserirNotificacao(
                $empresa_id,
                'pneu',
                '🔧 Verificar Pressão e Calibração',
                "Pneu {$row['numero_serie']} do veículo {$row['placa']} (posição {$row['posicao']}) pode precisar de verificação de pressão.",
                "Verifique a pressão dos pneus e faça a calibração conforme especificações do fabricante."
            );
        }
    } catch (Exception $e) {
        // error_log("Erro em verificação de pressão: " . $e->getMessage());
    }

    // error_log('DEBUG IA: Fim do bloco try da IA');
} catch (Exception $e) {
    // error_log("Erro na execução da IA: " . $e->getMessage());
    // Não interrompe a execução do endpoint
} 