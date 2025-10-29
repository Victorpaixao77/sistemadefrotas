<?php
/**
 * API - Dashboard - Notificações Recentes
 * Retorna notificações baseadas em eventos recentes do sistema
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$empresa_id = obterEmpresaId();

try {
    $notificacoes = [];
    
    // Clientes cadastrados recentemente (últimas 24 horas)
    $stmt = $db->prepare("
        SELECT 
            nome_razao_social,
            data_cadastro
        FROM seguro_clientes
        WHERE seguro_empresa_id = ?
        AND data_cadastro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY data_cadastro DESC
        LIMIT 3
    ");
    $stmt->execute([$empresa_id]);
    $clientesNovos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($clientesNovos as $cliente) {
        $tempo = calcularTempoDecorrido($cliente['data_cadastro']);
        $notificacoes[] = [
            'tipo' => 'cliente',
            'mensagem' => 'Novo cliente cadastrado: ' . $cliente['nome_razao_social'],
            'tempo' => $tempo,
            'data' => $cliente['data_cadastro']
        ];
    }
    
    // Atendimentos abertos recentemente
    $stmt = $db->prepare("
        SELECT 
            a.titulo,
            a.data_abertura,
            COALESCE(c.nome_razao_social, 'Cliente não identificado') as cliente
        FROM seguro_atendimentos a
        LEFT JOIN seguro_clientes c ON a.seguro_cliente_id = c.id
        WHERE a.seguro_empresa_id = ?
        AND a.status = 'aberto'
        AND a.data_abertura >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY a.data_abertura DESC
        LIMIT 2
    ");
    $stmt->execute([$empresa_id]);
    $atendimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($atendimentos as $atend) {
        $tempo = calcularTempoDecorrido($atend['data_abertura']);
        $notificacoes[] = [
            'tipo' => 'atendimento',
            'mensagem' => 'Atendimento aberto: ' . substr($atend['titulo'], 0, 50),
            'tempo' => $tempo,
            'data' => $atend['data_abertura']
        ];
    }
    
    // Equipamentos cadastrados recentemente
    $stmt = $db->prepare("
        SELECT 
            e.descricao,
            e.data_cadastro,
            c.nome_razao_social as cliente
        FROM seguro_equipamentos e
        LEFT JOIN seguro_clientes c ON e.seguro_cliente_id = c.id
        WHERE e.seguro_empresa_id = ?
        AND e.data_cadastro >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
        ORDER BY e.data_cadastro DESC
        LIMIT 2
    ");
    $stmt->execute([$empresa_id]);
    $equipamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($equipamentos as $equip) {
        $tempo = calcularTempoDecorrido($equip['data_cadastro']);
        $notificacoes[] = [
            'tipo' => 'equipamento',
            'mensagem' => 'Equipamento cadastrado: ' . $equip['descricao'],
            'tempo' => $tempo,
            'data' => $equip['data_cadastro']
        ];
    }
    
    // Ordenar por data (mais recentes primeiro)
    usort($notificacoes, function($a, $b) {
        return strtotime($b['data']) - strtotime($a['data']);
    });
    
    // Pegar apenas as 5 mais recentes
    $notificacoes = array_slice($notificacoes, 0, 5);
    
    echo json_encode([
        'sucesso' => true,
        'notificacoes' => $notificacoes,
        'total' => count($notificacoes)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Erro ao buscar notificações'
    ], JSON_UNESCAPED_UNICODE);
}

// Função auxiliar para calcular tempo decorrido
function calcularTempoDecorrido($dataHora) {
    $agora = new DateTime();
    $data = new DateTime($dataHora);
    $diff = $agora->diff($data);
    
    if ($diff->days > 1) {
        return $diff->days . ' dias atrás';
    } elseif ($diff->days == 1) {
        return 'Ontem';
    } elseif ($diff->h > 0) {
        return 'Há ' . $diff->h . ' hora(s)';
    } elseif ($diff->i > 0) {
        return 'Há ' . $diff->i . ' minuto(s)';
    } else {
        return 'Agora mesmo';
    }
}
?>

