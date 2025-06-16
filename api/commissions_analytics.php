<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar sessão antes de iniciá-la
configure_session();

// Iniciar sessão
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    $current_year = date('Y');
    
    // Query para buscar comissões pagas por mês
    $query = "
        SELECT 
            MONTH(data_rota) as mes,
            SUM(comissao) as total_comissao
        FROM rotas
        WHERE empresa_id = :empresa_id
        AND YEAR(data_rota) = :year
        AND no_prazo = 1
        GROUP BY MONTH(data_rota)
        ORDER BY mes";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->bindParam(':year', $current_year);
    $stmt->execute();
    
    $commissions_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar os dados para o gráfico
    $labels = [];
    $data = [];
    
    // Array com nomes dos meses em português
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];
    
    // Inicializar todos os meses com zero
    for ($i = 1; $i <= 12; $i++) {
        $labels[] = $meses[$i];
        $data[$i] = 0;
    }
    
    // Preencher os dados reais
    foreach ($commissions_data as $row) {
        $data[$row['mes']] = floatval($row['total_comissao']);
    }
    
    // Retornar os dados no formato esperado pelo Chart.js
    echo json_encode([
        'labels' => $labels,
        'datasets' => [
            [
                'label' => 'Comissões Pagas',
                'data' => array_values($data),
                'borderColor' => '#FF6384',
                'backgroundColor' => 'rgba(255, 99, 132, 0.1)',
                'fill' => true,
                'tension' => 0.4
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Commissions Analytics API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar dados de comissões: ' . $e->getMessage()]);
    exit;
} 