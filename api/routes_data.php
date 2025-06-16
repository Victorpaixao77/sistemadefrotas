<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize the session
session_start();

// Check if user is logged in, if not redirect to login page
require_authentication();

// Get empresa_id from session
$empresa_id = $_SESSION['empresa_id'];

// Get database connection
try {
    $conn = getConnection();
} catch (PDOException $e) {
    error_log("Erro de conexão com o banco: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => true, 'message' => 'Erro de conexão com o banco de dados']);
    exit;
}

// Get action from request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Processa a ação solicitada
if ($action === 'dashboard') {
    try {
        $month = (isset($_GET['month']) && $_GET['month'] !== '' && intval($_GET['month']) > 0) ? intval($_GET['month']) : intval(date('n'));
        $year = (isset($_GET['year']) && $_GET['year'] !== '' && intval($_GET['year']) > 0) ? intval($_GET['year']) : intval(date('Y'));
        
        // Validação básica dos parâmetros
        if ($month !== null && ($month < 1 || $month > 12)) {
            throw new Exception('Mês inválido');
        }
        if ($year !== null && ($year < 2000 || $year > 2100)) {
            throw new Exception('Ano inválido');
        }
        
        $data = getDashboardData($conn, $empresa_id, $month, $year);
        
        // Verifica se houve erro na função getDashboardData
        if (isset($data['error']) && $data['error']) {
            throw new Exception($data['message'] ?? 'Erro ao buscar dados do dashboard');
        }
        
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    } catch (Exception $e) {
        error_log("Erro no dashboard: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'error' => true,
            'message' => 'Erro ao processar dados do dashboard: ' . $e->getMessage()
        ]);
        exit;
    }
}

function getDashboardData($conn, $empresa_id, $month = null, $year = null) {
    try {
        // Normaliza e valida mês/ano para garantir filtro SEMPRE
        $month = (int)$month;
        $year = (int)$year;
        if ($month < 1 || $month > 12) {
            $month = (int)date('n');
        }
        if ($year < 2000 || $year > 2100) {
            $year = (int)date('Y');
        }
        // Prepara a condição de data
        $date_condition = "AND MONTH(r.data_rota) = :month AND YEAR(r.data_rota) = :year";

        // Total de rotas
        $sql_total = "SELECT COUNT(*) as total FROM rotas r WHERE r.empresa_id = :empresa_id $date_condition";
        $stmt_total = $conn->prepare($sql_total);
        $stmt_total->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_total->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_total->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_total->execute();
        $total_rotas = $stmt_total->fetch(PDO::FETCH_ASSOC)['total'];

        // Rotas concluídas (usando no_prazo como indicador)
        $sql_concluidas = "SELECT COUNT(*) as total FROM rotas r 
                          WHERE r.empresa_id = :empresa_id 
                          AND r.no_prazo IS NOT NULL $date_condition";
        $stmt_concluidas = $conn->prepare($sql_concluidas);
        $stmt_concluidas->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_concluidas->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_concluidas->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_concluidas->execute();
        $rotas_concluidas = $stmt_concluidas->fetch(PDO::FETCH_ASSOC)['total'];

        // Distância total
        $sql_distancia = "SELECT COALESCE(SUM(r.distancia_km), 0) as total FROM rotas r 
                         WHERE r.empresa_id = :empresa_id $date_condition";
        $stmt_distancia = $conn->prepare($sql_distancia);
        $stmt_distancia->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_distancia->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_distancia->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_distancia->execute();
        $distancia_total = $stmt_distancia->fetch(PDO::FETCH_ASSOC)['total'];

        // Frete total
        $sql_frete = "SELECT COALESCE(SUM(r.frete), 0) as total FROM rotas r 
                     WHERE r.empresa_id = :empresa_id $date_condition";
        $stmt_frete = $conn->prepare($sql_frete);
        $stmt_frete->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_frete->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_frete->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_frete->execute();
        $frete_total = $stmt_frete->fetch(PDO::FETCH_ASSOC)['total'];

        // Rotas no prazo e atrasadas
        $sql_prazo = "SELECT 
                        SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as no_prazo,
                        SUM(CASE WHEN r.no_prazo = 0 THEN 1 ELSE 0 END) as atrasadas
                     FROM rotas r 
                     WHERE r.empresa_id = :empresa_id 
                     AND r.no_prazo IS NOT NULL $date_condition";
        $stmt_prazo = $conn->prepare($sql_prazo);
        $stmt_prazo->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_prazo->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_prazo->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_prazo->execute();
        $prazo_data = $stmt_prazo->fetch(PDO::FETCH_ASSOC);
        $rotas_no_prazo = $prazo_data['no_prazo'] ?? 0;
        $rotas_atrasadas = $prazo_data['atrasadas'] ?? 0;

        // Média de eficiência
        $sql_eficiencia = "SELECT COALESCE(AVG(r.eficiencia_viagem), 0) as media 
                          FROM rotas r 
                          WHERE r.empresa_id = :empresa_id 
                          AND r.eficiencia_viagem IS NOT NULL $date_condition";
        $stmt_eficiencia = $conn->prepare($sql_eficiencia);
        $stmt_eficiencia->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_eficiencia->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_eficiencia->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_eficiencia->execute();
        $media_eficiencia = $stmt_eficiencia->fetch(PDO::FETCH_ASSOC)['media'];

        // Percentual vazio
        $sql_vazio = "SELECT COALESCE(AVG(r.percentual_vazio), 0) as media 
                     FROM rotas r 
                     WHERE r.empresa_id = :empresa_id 
                     AND r.percentual_vazio IS NOT NULL $date_condition";
        $stmt_vazio = $conn->prepare($sql_vazio);
        $stmt_vazio->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_vazio->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_vazio->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_vazio->execute();
        $percentual_vazio = $stmt_vazio->fetch(PDO::FETCH_ASSOC)['media'];

        // Dados para os gráficos
        // Distância por motorista
        $sql_distancia_motorista = "SELECT m.nome, COALESCE(SUM(r.distancia_km), 0) as total
                                  FROM rotas r
                                  LEFT JOIN motoristas m ON r.motorista_id = m.id
                                  WHERE r.empresa_id = :empresa_id $date_condition
                                  GROUP BY m.id, m.nome
                                  ORDER BY total DESC
                                  LIMIT 5";
        $stmt_distancia_motorista = $conn->prepare($sql_distancia_motorista);
        $stmt_distancia_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_distancia_motorista->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_distancia_motorista->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_distancia_motorista->execute();
        $distancia_motorista = $stmt_distancia_motorista->fetchAll(PDO::FETCH_ASSOC);

        // Eficiência por motorista
        $sql_eficiencia_motorista = "SELECT m.nome, COALESCE(AVG(r.eficiencia_viagem), 0) as media
                                   FROM rotas r
                                   LEFT JOIN motoristas m ON r.motorista_id = m.id
                                   WHERE r.empresa_id = :empresa_id $date_condition
                                   GROUP BY m.id, m.nome
                                   ORDER BY media DESC
                                   LIMIT 5";
        $stmt_eficiencia_motorista = $conn->prepare($sql_eficiencia_motorista);
        $stmt_eficiencia_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_eficiencia_motorista->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_eficiencia_motorista->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_eficiencia_motorista->execute();
        $eficiencia_motorista = $stmt_eficiencia_motorista->fetchAll(PDO::FETCH_ASSOC);

        // Rotas no prazo por motorista
        $sql_rotas_prazo = "SELECT m.nome, 
                           COUNT(*) as total,
                           SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) as no_prazo
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id $date_condition
                           GROUP BY m.id, m.nome
                           ORDER BY total DESC
                           LIMIT 5";
        $stmt_rotas_prazo = $conn->prepare($sql_rotas_prazo);
        $stmt_rotas_prazo->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_rotas_prazo->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_rotas_prazo->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_rotas_prazo->execute();
        $rotas_prazo = $stmt_rotas_prazo->fetchAll(PDO::FETCH_ASSOC);

        // Frete por motorista
        $sql_frete_motorista = "SELECT m.nome, COALESCE(SUM(r.frete), 0) as total
                               FROM rotas r
                               LEFT JOIN motoristas m ON r.motorista_id = m.id
                               WHERE r.empresa_id = :empresa_id $date_condition
                               GROUP BY m.id, m.nome
                               ORDER BY total DESC
                               LIMIT 5";
        $stmt_frete_motorista = $conn->prepare($sql_frete_motorista);
        $stmt_frete_motorista->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_frete_motorista->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_frete_motorista->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_frete_motorista->execute();
        $frete_motorista = $stmt_frete_motorista->fetchAll(PDO::FETCH_ASSOC);

        // Evolução de KM (últimos 6 meses)
        $sql_evolucao_km = "SELECT 
                            DATE_FORMAT(r.data_rota, '%Y-%m') as mes,
                            m.nome,
                            SUM(r.distancia_km) as total
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id
                           AND r.data_rota >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                           GROUP BY DATE_FORMAT(r.data_rota, '%Y-%m'), m.id, m.nome
                           ORDER BY mes, m.nome";
        $stmt_evolucao_km = $conn->prepare($sql_evolucao_km);
        $stmt_evolucao_km->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_evolucao_km->execute();
        $evolucao_km = $stmt_evolucao_km->fetchAll(PDO::FETCH_ASSOC);

        // Indicadores por motorista
        $sql_indicadores = "SELECT 
                           m.nome,
                           AVG(r.eficiencia_viagem) as eficiencia,
                           AVG(r.percentual_vazio) as km_vazio,
                           SUM(CASE WHEN r.no_prazo = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as pontualidade,
                           COUNT(*) as total_rotas,
                           SUM(r.frete) as total_frete
                           FROM rotas r
                           LEFT JOIN motoristas m ON r.motorista_id = m.id
                           WHERE r.empresa_id = :empresa_id $date_condition
                           GROUP BY m.id, m.nome
                           ORDER BY total_rotas DESC
                           LIMIT 5";
        $stmt_indicadores = $conn->prepare($sql_indicadores);
        $stmt_indicadores->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
        $stmt_indicadores->bindParam(':month', $month, PDO::PARAM_INT);
        $stmt_indicadores->bindParam(':year', $year, PDO::PARAM_INT);
        $stmt_indicadores->execute();
        $indicadores = $stmt_indicadores->fetchAll(PDO::FETCH_ASSOC);

        // Formata os dados para o retorno
        $response = [
            'total_rotas' => $total_rotas,
            'rotas_concluidas' => $rotas_concluidas,
            'distancia_total' => $distancia_total,
            'frete_total' => $frete_total,
            'rotas_no_prazo' => $rotas_no_prazo,
            'rotas_atrasadas' => $rotas_atrasadas,
            'media_eficiencia' => $media_eficiencia,
            'percentual_vazio' => $percentual_vazio,
            'distancia_motorista' => [
                'labels' => array_column($distancia_motorista, 'nome'),
                'values' => array_column($distancia_motorista, 'total')
            ],
            'eficiencia_motorista' => [
                'labels' => array_column($eficiencia_motorista, 'nome'),
                'values' => array_column($eficiencia_motorista, 'media')
            ],
            'rotas_prazo' => [
                'labels' => array_column($rotas_prazo, 'nome'),
                'values' => array_column($rotas_prazo, 'no_prazo')
            ],
            'frete_motorista' => [
                'labels' => array_column($frete_motorista, 'nome'),
                'values' => array_column($frete_motorista, 'total')
            ],
            'evolucao_km' => [
                'labels' => array_unique(array_column($evolucao_km, 'mes')),
                'datasets' => []
            ],
            'indicadores_motorista' => [
                'labels' => ['Eficiência', 'KM Vazio', 'Pontualidade', 'Total Rotas', 'Frete'],
                'datasets' => []
            ]
        ];

        // Processa dados de evolução de KM
        $motoristas = array_unique(array_column($evolucao_km, 'nome'));
        $meses = array_unique(array_column($evolucao_km, 'mes'));
        $cores = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];

        foreach ($motoristas as $index => $motorista) {
            $dados = [];
            foreach ($meses as $mes) {
                $valor = 0;
                foreach ($evolucao_km as $registro) {
                    if ($registro['nome'] === $motorista && $registro['mes'] === $mes) {
                        $valor = $registro['total'];
                        break;
                    }
                }
                $dados[] = $valor;
            }
            $response['evolucao_km']['datasets'][] = [
                'label' => $motorista,
                'data' => $dados,
                'color' => $cores[$index % count($cores)]
            ];
        }

        // Processa dados de indicadores
        foreach ($indicadores as $index => $motorista) {
            $response['indicadores_motorista']['datasets'][] = [
                'label' => $motorista['nome'],
                'data' => [
                    $motorista['eficiencia'],
                    $motorista['km_vazio'],
                    $motorista['pontualidade'],
                    $motorista['total_rotas'],
                    $motorista['total_frete']
                ],
                'backgroundColor' => 'rgba(' . implode(',', sscanf($cores[$index % count($cores)], '#%02x%02x%02x')) . ',0.2)',
                'borderColor' => $cores[$index % count($cores)]
            ];
        }

        return $response;
    } catch(PDOException $e) {
        error_log("Erro ao buscar dados do dashboard: " . $e->getMessage());
        return [
            'error' => true,
            'message' => 'Erro ao buscar dados do dashboard: ' . $e->getMessage()
        ];
    }
} 