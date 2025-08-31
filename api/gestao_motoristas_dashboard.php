<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar log de erros
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
ini_set('error_log', '../logs/php_errors.log');

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$empresa_id = $_SESSION['empresa_id'];

try {
    $conn = getConnection();
    switch ($action) {
        case 'kpis':
            try {
                // Total de motoristas ativos
                $sql = "SELECT COUNT(*) as total_ativos FROM motoristas WHERE empresa_id = :empresa_id AND disponibilidade_id = 1";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $total_ativos = $stmt->fetchColumn();

                // Checklists recentes (últimos 7 dias)
                $sql = "SELECT COUNT(DISTINCT motorista_id) FROM checklist_viagem 
                       WHERE empresa_id = :empresa_id 
                       AND data_checklist >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $checklists_recentes = $stmt->fetchColumn();

                // Infrações recentes (usando multas)
                $sql = "SELECT COUNT(*) FROM multas 
                       WHERE empresa_id = :empresa_id 
                       AND data_infracao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $infracoes_recentes = $stmt->fetchColumn();

                // Melhor eficiência
                $sql = "SELECT m.nome, ROUND(AVG(a.km_atual / NULLIF(a.litros,0)),2) as eficiencia
                        FROM abastecimentos a
                        LEFT JOIN motoristas m ON a.motorista_id = m.id
                        WHERE a.empresa_id = :empresa_id AND a.litros > 0
                        GROUP BY a.motorista_id
                        ORDER BY eficiencia DESC LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $melhor_eficiencia = $row ? ($row['nome'] . ' (' . $row['eficiencia'] . ' km/L)') : '-';

                // Mais viagens no mês
                $sql = "SELECT m.nome, COUNT(*) as total_viagens
                        FROM rotas r
                        LEFT JOIN motoristas m ON r.motorista_id = m.id
                        WHERE r.empresa_id = :empresa_id 
                        AND MONTH(r.data_rota) = MONTH(CURDATE()) 
                        AND YEAR(r.data_rota) = YEAR(CURDATE())
                        GROUP BY r.motorista_id
                        ORDER BY total_viagens DESC LIMIT 1";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $mais_viagens = $row ? ($row['nome'] . ' (' . $row['total_viagens'] . ')') : '-';

                echo json_encode(['success' => true, 'data' => [
                    'total_ativos' => $total_ativos,
                    'checklists_recentes' => $checklists_recentes,
                    'infracoes_recentes' => $infracoes_recentes,
                    'melhor_eficiencia' => $melhor_eficiencia,
                    'mais_viagens' => $mais_viagens
                ]]);
            } catch (PDOException $e) {
                error_log("Erro no endpoint KPIs: " . $e->getMessage());
                throw new Exception("Erro ao buscar KPIs: " . $e->getMessage());
            }
            break;
        case 'eficiencia_combustivel':
            // Ranking de eficiência de combustível por motorista
            $sql = "SELECT m.nome, ROUND(AVG(a.km_atual / NULLIF(a.litros,0)),2) as eficiencia
                    FROM abastecimentos a
                    LEFT JOIN motoristas m ON a.motorista_id = m.id
                    WHERE a.empresa_id = :empresa_id AND a.litros > 0
                    GROUP BY a.motorista_id
                    ORDER BY eficiencia DESC LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $labels = [];
            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $labels[] = $row['nome'];
                $data[] = floatval($row['eficiencia']);
            }
            echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
            break;
        case 'checklists_pendentes':
            try {
                // Motoristas com checklists recentes (últimos 7 dias)
                $sql = "SELECT m.nome, COUNT(*) as recentes
                        FROM checklist_viagem c
                        LEFT JOIN motoristas m ON c.motorista_id = m.id
                        WHERE c.empresa_id = :empresa_id 
                        AND c.data_checklist >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                        GROUP BY c.motorista_id
                        ORDER BY recentes DESC LIMIT 10";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $labels = [];
                $data = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $labels[] = $row['nome'];
                    $data[] = intval($row['recentes']);
                }
                echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
            } catch (PDOException $e) {
                error_log("Erro no endpoint Checklists Pendentes: " . $e->getMessage());
                throw new Exception("Erro ao buscar checklists pendentes: " . $e->getMessage());
            }
            break;
        case 'infracoes':
            try {
                $sql = "SELECT DATE_FORMAT(data_infracao, '%m/%Y') as mes, COUNT(*) as total
                        FROM multas
                        WHERE empresa_id = :empresa_id 
                        AND data_infracao >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                        GROUP BY mes ORDER BY data_infracao";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $labels = [];
                $data = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $labels[] = $row['mes'];
                    $data[] = intval($row['total']);
                }
                echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
            } catch (PDOException $e) {
                error_log("Erro no endpoint Infrações: " . $e->getMessage());
                throw new Exception("Erro ao buscar infrações: " . $e->getMessage());
            }
            break;
        case 'distribuicao_viagens':
            // Distribuição de viagens por motorista (mês atual)
            $sql = "SELECT m.nome, COUNT(*) as total
                    FROM rotas r
                    LEFT JOIN motoristas m ON r.motorista_id = m.id
                    WHERE r.empresa_id = :empresa_id AND MONTH(r.data_rota) = MONTH(CURDATE()) AND YEAR(r.data_rota) = YEAR(CURDATE())
                    GROUP BY r.motorista_id
                    ORDER BY total DESC";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':empresa_id', $empresa_id);
            $stmt->execute();
            $labels = [];
            $data = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $labels[] = $row['nome'];
                $data[] = intval($row['total']);
            }
            echo json_encode(['success' => true, 'labels' => $labels, 'data' => $data]);
            break;
        case 'documentacao_vencida':
            try {
                $sql = "SELECT m.nome, m.cnh, c.nome as categoria_nome, 
                        m.data_validade_cnh as validade_cnh, d.nome as status_nome
                        FROM motoristas m
                        LEFT JOIN categorias_cnh c ON m.categoria_cnh_id = c.id
                        LEFT JOIN disponibilidades d ON m.disponibilidade_id = d.id
                        WHERE m.empresa_id = :empresa_id 
                        AND (m.data_validade_cnh IS NOT NULL 
                        AND m.data_validade_cnh <= DATE_ADD(CURDATE(), INTERVAL 30 DAY))
                        ORDER BY m.data_validade_cnh ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(':empresa_id', $empresa_id);
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'data' => $data]);
            } catch (PDOException $e) {
                error_log("Erro no endpoint Documentação Vencida: " . $e->getMessage());
                throw new Exception("Erro ao buscar documentação vencida: " . $e->getMessage());
            }
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Ação inválida']);
    }
} catch (Exception $e) {
    error_log("Erro geral na API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 