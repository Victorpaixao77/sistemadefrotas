<?php
/**
 * API de Debug para buscar rotas com coordenadas para Google Maps
 * Sistema de Gestão de Frotas
 */

// Habilitar exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Debug: Verificar se a sessão está funcionando
$debug_info = [
    'session_status' => session_status(),
    'session_id' => session_id(),
    'empresa_id' => isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : 'não definido',
    'get_params' => $_GET,
    'post_params' => $_POST
];

try {
    // Verificar se empresa_id existe
    $empresa_id = isset($_SESSION["empresa_id"]) ? $_SESSION["empresa_id"] : null;
    
    if (!$empresa_id) {
        throw new Exception('ID da empresa não encontrado na sessão');
    }

    // Get parameters
    $mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
    $ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
    
    $debug_info['filters'] = [
        'mes' => $mes,
        'ano' => $ano
    ];

    $conn = getConnection();
    
    // Primeiro, vamos verificar se existem rotas para este período
    $sql_count = "SELECT COUNT(*) as total 
                  FROM rotas r
                  LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                  LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                  WHERE r.empresa_id = :empresa_id
                  AND MONTH(r.data_rota) = :mes
                  AND YEAR(r.data_rota) = :ano
                  AND r.status = 'aprovado'";
    
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_count->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt_count->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt_count->execute();
    $total_rotas = $stmt_count->fetch(PDO::FETCH_ASSOC)['total'];
    
    $debug_info['total_rotas_periodo'] = $total_rotas;
    
    // Verificar quantas têm coordenadas
    $sql_coords = "SELECT COUNT(*) as total 
                   FROM rotas r
                   LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                   LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                   WHERE r.empresa_id = :empresa_id
                   AND MONTH(r.data_rota) = :mes
                   AND YEAR(r.data_rota) = :ano
                   AND r.status = 'aprovado'
                   AND co.latitude IS NOT NULL 
                   AND co.longitude IS NOT NULL
                   AND cd.latitude IS NOT NULL 
                   AND cd.longitude IS NOT NULL";
    
    $stmt_coords = $conn->prepare($sql_coords);
    $stmt_coords->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_coords->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt_coords->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt_coords->execute();
    $total_com_coords = $stmt_coords->fetch(PDO::FETCH_ASSOC)['total'];
    
    $debug_info['total_com_coordenadas'] = $total_com_coords;
    
    // Query principal para buscar rotas com coordenadas
    $sql = "SELECT 
                r.id,
                r.data_rota,
                r.data_saida,
                r.data_chegada,
                r.distancia_km,
                r.frete,
                r.status,
                r.no_prazo,
                r.eficiencia_viagem,
                r.percentual_vazio,
                r.peso_carga,
                r.descricao_carga,
                r.observacoes,
                
                -- Dados do motorista
                m.nome as motorista_nome,
                m.telefone as motorista_telefone,
                
                -- Dados do veículo
                v.placa as veiculo_placa,
                v.modelo as veiculo_modelo,
                
                -- Dados de origem
                co.nome as cidade_origem_nome,
                co.latitude as lat_origem,
                co.longitude as lng_origem,
                eo.uf as estado_origem,
                
                -- Dados de destino
                cd.nome as cidade_destino_nome,
                cd.latitude as lat_destino,
                cd.longitude as lng_destino,
                ed.uf as estado_destino
                
            FROM rotas r
            LEFT JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            LEFT JOIN estados eo ON co.uf = eo.uf
            LEFT JOIN estados ed ON cd.uf = ed.uf
            WHERE r.empresa_id = :empresa_id
            AND MONTH(r.data_rota) = :mes
            AND YEAR(r.data_rota) = :ano
            AND r.status = 'aprovado'
            AND co.latitude IS NOT NULL 
            AND co.longitude IS NOT NULL
            AND cd.latitude IS NOT NULL 
            AND cd.longitude IS NOT NULL
            ORDER BY r.data_rota DESC, r.id DESC
            LIMIT 10"; // Limitar para debug
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt->execute();
    
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $debug_info['rotas_encontradas'] = count($rotas);
    $debug_info['primeira_rota'] = !empty($rotas) ? $rotas[0] : null;
    
    // Processar dados para o Google Maps
    $processedRotas = [];
    foreach ($rotas as $rota) {
        $processedRotas[] = [
            'id' => $rota['id'],
            'data_rota' => $rota['data_rota'],
            'data_saida' => $rota['data_saida'],
            'data_chegada' => $rota['data_chegada'],
            'distancia_km' => floatval($rota['distancia_km']),
            'frete' => floatval($rota['frete']),
            'status' => $rota['status'],
            'no_prazo' => (bool)$rota['no_prazo'],
            'eficiencia_viagem' => floatval($rota['eficiencia_viagem']),
            'percentual_vazio' => floatval($rota['percentual_vazio']),
            'peso_carga' => floatval($rota['peso_carga']),
            'descricao_carga' => $rota['descricao_carga'],
            'observacoes' => $rota['observacoes'],
            
            // Dados do motorista
            'motorista' => [
                'nome' => $rota['motorista_nome'],
                'telefone' => $rota['motorista_telefone']
            ],
            
            // Dados do veículo
            'veiculo' => [
                'placa' => $rota['veiculo_placa'],
                'modelo' => $rota['veiculo_modelo']
            ],
            
            // Coordenadas de origem
            'origem' => [
                'cidade' => $rota['cidade_origem_nome'],
                'estado' => $rota['estado_origem'],
                'latitude' => floatval($rota['lat_origem']),
                'longitude' => floatval($rota['lng_origem'])
            ],
            
            // Coordenadas de destino
            'destino' => [
                'cidade' => $rota['cidade_destino_nome'],
                'estado' => $rota['estado_destino'],
                'latitude' => floatval($rota['lat_destino']),
                'longitude' => floatval($rota['lng_destino'])
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processedRotas,
        'debug' => $debug_info,
        'filters' => [
            'mes' => $mes,
            'ano' => $ano,
            'total' => count($processedRotas)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de rotas Google Maps Debug: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados das rotas',
        'debug' => $debug_info,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
