<?php
/**
 * API para buscar rotas com coordenadas para Google Maps
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

// Verificar autenticação de forma mais robusta
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Empresa não identificada na sessão'
    ]);
    exit;
}

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Get parameters
$mes = isset($_GET['mes']) ? intval($_GET['mes']) : date('m');
$ano = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');

try {
    $conn = getConnection();
    
    // Debug: Log dos parâmetros recebidos
    error_log("API rotas_google_maps.php - Parâmetros: mes=$mes, ano=$ano, empresa_id=$empresa_id");
    
    // Query para buscar rotas usando coordenadas diretas da tabela cidades
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
                co.codigo_ibge as codigo_ibge_origem,
                co.uf as estado_origem,
                co.latitude as latitude_origem,
                co.longitude as longitude_origem,
                
                -- Dados de destino
                cd.nome as cidade_destino_nome,
                cd.codigo_ibge as codigo_ibge_destino,
                cd.uf as estado_destino,
                cd.latitude as latitude_destino,
                cd.longitude as longitude_destino
                
            FROM rotas r
            LEFT JOIN motoristas m ON r.motorista_id = m.id
            LEFT JOIN veiculos v ON r.veiculo_id = v.id
            LEFT JOIN cidades co ON r.cidade_origem_id = co.id
            LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
            WHERE r.empresa_id = :empresa_id
            AND MONTH(r.data_rota) = :mes
            AND YEAR(r.data_rota) = :ano
            AND r.status = 'aprovado'
            AND co.latitude IS NOT NULL 
            AND co.longitude IS NOT NULL
            AND cd.latitude IS NOT NULL 
            AND cd.longitude IS NOT NULL
            ORDER BY r.data_rota DESC, r.id DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->bindParam(':mes', $mes, PDO::PARAM_INT);
    $stmt->bindParam(':ano', $ano, PDO::PARAM_INT);
    $stmt->execute();
    
    $rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Log do resultado da query
    error_log("API rotas_google_maps.php - Rotas encontradas: " . count($rotas));
    
    // Agora usamos coordenadas diretas da tabela cidades, não precisamos mais de conversões
    
    // Processar dados para o Google Maps usando coordenadas diretas da tabela cidades
    $processedRotas = [];
    foreach ($rotas as $rota) {
        // Usar coordenadas diretas da tabela cidades
        $origem_coords = [
            'lat' => floatval($rota['latitude_origem']),
            'lng' => floatval($rota['longitude_origem'])
        ];
        
        $destino_coords = [
            'lat' => floatval($rota['latitude_destino']),
            'lng' => floatval($rota['longitude_destino'])
        ];
        
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
                'latitude' => $origem_coords['lat'],
                'longitude' => $origem_coords['lng'],
                'codigo_ibge' => $rota['codigo_ibge_origem']
            ],
            
            // Coordenadas de destino
            'destino' => [
                'cidade' => $rota['cidade_destino_nome'],
                'estado' => $rota['estado_destino'],
                'latitude' => $destino_coords['lat'],
                'longitude' => $destino_coords['lng'],
                'codigo_ibge' => $rota['codigo_ibge_destino']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $processedRotas,
        'filters' => [
            'mes' => $mes,
            'ano' => $ano,
            'total' => count($processedRotas)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Erro na API de rotas Google Maps: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar dados das rotas',
        'debug' => DEBUG_MODE ? $e->getMessage() : null
    ]);
}
?>
