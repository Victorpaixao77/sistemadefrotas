<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure the request is authenticated
require_authentication();

// Create database connection
$conn = getConnection();

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

/**
 * Atualiza plano de manutenção (ultimo_km, ultima_data) quando uma preventiva é concluída.
 */
function atualizar_plano_apos_manutencao($conn, $empresa_id, $veiculo_id, $componente_id, $tipo_manutencao_id, $status_manutencao_id, $data_manutencao, $km_atual) {
    try {
        $conn->query("SELECT 1 FROM planos_manutencao LIMIT 1");
    } catch (Exception $e) {
        return;
    }
    $stmt = $conn->prepare("SELECT id FROM status_manutencao WHERE id = :id AND LOWER(TRIM(nome)) LIKE '%conclu%'");
    $stmt->execute(['id' => $status_manutencao_id]);
    if (!$stmt->fetch()) return;
    $stmt = $conn->prepare("SELECT id FROM tipos_manutencao WHERE id = :id AND LOWER(TRIM(nome)) LIKE '%preventiva%'");
    $stmt->execute(['id' => $tipo_manutencao_id]);
    if (!$stmt->fetch()) return;
    $stmt = $conn->prepare("UPDATE planos_manutencao SET ultimo_km = :km, ultima_data = :data, updated_at = NOW() WHERE empresa_id = :eid AND veiculo_id = :vid AND componente_id = :cid AND tipo_manutencao_id = :tid");
    $stmt->execute([
        'km' => $km_atual,
        'data' => $data_manutencao,
        'eid' => $empresa_id,
        'vid' => $veiculo_id,
        'cid' => $componente_id,
        'tid' => $tipo_manutencao_id
    ]);
}

// Debug session state
error_log("Session state in manutencoes.php API: " . print_r($_SESSION, true));

switch ($method) {
    case 'GET':
        // Handle GET request (fetch maintenance)
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if ($id) {
                // Fetch specific maintenance
                $sql = "SELECT * FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    echo json_encode(['success' => true, 'data' => $result]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Manutenção não encontrada']);
                }
            } elseif (isset($_GET['list']) && $_GET['list'] === '1') {
                // Listagem paginada (para AJAX – não recarrega a página)
                $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
                $per_page = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 10;
                if (!in_array($per_page, [5, 10, 25, 50, 100], true)) $per_page = 10;
                $data_inicio = isset($_GET['data_inicio']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_inicio']) ? $_GET['data_inicio'] : null;
                $data_fim = isset($_GET['data_fim']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data_fim']) ? $_GET['data_fim'] : null;
                $order_by = isset($_GET['order']) && in_array($_GET['order'], ['data_manutencao', 'valor', 'veiculo_placa', 'tipo_nome', 'status_nome', 'fornecedor', 'descricao'], true) ? $_GET['order'] : 'data_manutencao';
                $order_dir = isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC' ? 'ASC' : 'DESC';
                $cols = ['data_manutencao' => 'm.data_manutencao', 'valor' => 'm.valor', 'veiculo_placa' => 'v.placa', 'tipo_nome' => 'tm.nome', 'status_nome' => 'sm.nome', 'fornecedor' => 'm.fornecedor', 'descricao' => 'm.descricao'];
                $order_col = $cols[$order_by] ?? 'm.data_manutencao';
                $where = "m.empresa_id = :empresa_id";
                $params = [':empresa_id' => $_SESSION['empresa_id']];
                if ($data_inicio) { $where .= " AND m.data_manutencao >= :data_inicio"; $params[':data_inicio'] = $data_inicio; }
                if ($data_fim) { $where .= " AND m.data_manutencao <= :data_fim"; $params[':data_fim'] = $data_fim; }
                $offset = ($page - 1) * $per_page;
                $sql_count = "SELECT COUNT(*) as total FROM manutencoes m LEFT JOIN veiculos v ON m.veiculo_id = v.id LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id WHERE $where";
                $stmt = $conn->prepare($sql_count);
                foreach ($params as $k => $v) $stmt->bindValue($k, $v);
                $stmt->execute();
                $total = (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
                $sql = "SELECT m.*, v.placa as veiculo_placa, tm.nome as tipo_nome, sm.nome as status_nome FROM manutencoes m LEFT JOIN veiculos v ON m.veiculo_id = v.id LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id WHERE $where ORDER BY $order_col $order_dir, m.id $order_dir LIMIT " . (int)$per_page . " OFFSET " . (int)$offset;
                $stmt = $conn->prepare($sql);
                foreach ($params as $k => $v) $stmt->bindValue($k, $v);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $custo_12m = [];
                try {
                    $st = $conn->prepare("SELECT veiculo_id, COALESCE(SUM(valor), 0) as custo_12m FROM manutencoes WHERE empresa_id = :eid AND data_manutencao >= DATE_SUB(CURRENT_DATE(), INTERVAL 12 MONTH) GROUP BY veiculo_id");
                    $st->bindValue(':eid', $_SESSION['empresa_id'], PDO::PARAM_INT);
                    $st->execute();
                    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                        $custo_12m[(int)$row['veiculo_id']] = (float)$row['custo_12m'];
                    }
                } catch (PDOException $e) { /* ignore */ }
                foreach ($result as &$r) {
                    $r['custo_veiculo_12m'] = $custo_12m[(int)($r['veiculo_id'] ?? 0)] ?? 0;
                }
                unset($r);
                $total_paginas = $per_page > 0 ? (int)ceil($total / $per_page) : 1;
                echo json_encode(['success' => true, 'data' => $result, 'total' => $total, 'pagina_atual' => $page, 'total_paginas' => $total_paginas, 'per_page' => $per_page]);
            } else {
                // Fetch all maintenances (opcional: filtrar por veiculo_id para histórico por veículo)
                $veiculo_id = isset($_GET['veiculo_id']) ? (int)$_GET['veiculo_id'] : null;
                $sql = "SELECT m.*, v.placa as veiculo_placa, tm.nome as tipo_nome, sm.nome as status_nome
                        FROM manutencoes m
                        LEFT JOIN veiculos v ON m.veiculo_id = v.id
                        LEFT JOIN tipos_manutencao tm ON m.tipo_manutencao_id = tm.id
                        LEFT JOIN status_manutencao sm ON m.status_manutencao_id = sm.id
                        WHERE m.empresa_id = :empresa_id" . ($veiculo_id ? " AND m.veiculo_id = :veiculo_id" : "") . " ORDER BY m.data_manutencao DESC";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
                if ($veiculo_id) $stmt->bindParam(':veiculo_id', $veiculo_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $total_custo = 0;
                $custo_12m = 0;
                $total_preventivas = 0;
                $total_corretivas = 0;
                $doze_meses_atras = date('Y-m-d', strtotime('-12 months'));
                foreach ($result as $r) {
                    $v = (float)($r['valor'] ?? 0);
                    $total_custo += $v;
                    if (isset($r['data_manutencao']) && $r['data_manutencao'] >= $doze_meses_atras) {
                        $custo_12m += $v;
                    }
                    $tipo = isset($r['tipo_nome']) ? strtolower(trim($r['tipo_nome'])) : '';
                    if (strpos($tipo, 'preventiva') !== false) {
                        $total_preventivas++;
                    } else {
                        $total_corretivas++;
                    }
                }
                echo json_encode([
                    'success' => true,
                    'data' => $result,
                    'total_custo_veiculo' => round($total_custo, 2),
                    'custo_12m' => round($custo_12m, 2),
                    'total_preventivas' => $total_preventivas,
                    'total_corretivas' => $total_corretivas
                ]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao buscar manutenções: ' . $e->getMessage()]);
        }
        break;

    case 'POST':
        // Handle POST request (create maintenance)
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                throw new Exception('Dados inválidos');
            }
            
            // Required fields validation
            $required_fields = ['data_manutencao', 'veiculo_id', 'tipo_manutencao_id', 'componente_id', 
                              'status_manutencao_id', 'km_atual', 'valor', 'descricao', 'descricao_servico', 
                              'responsavel_aprovacao'];
            
            foreach ($required_fields as $field) {
                if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                    throw new Exception("Campo obrigatório não preenchido: $field");
                }
            }
            if (empty((int)$data['tipo_manutencao_id'])) {
                throw new Exception('Tipo de manutenção é obrigatório');
            }
            // Quando status for Concluída, exige data_conclusao
            $stmt_st = $conn->prepare("SELECT nome FROM status_manutencao WHERE id = :id");
            $stmt_st->execute(['id' => $data['status_manutencao_id']]);
            $status_nome = $stmt_st->fetchColumn();
            if ($status_nome && stripos($status_nome, 'conclu') !== false && empty($data['data_conclusao'])) {
                throw new Exception('Data de conclusão é obrigatória quando o status é Concluída');
            }
            
            // Insert maintenance record
            $sql = "INSERT INTO manutencoes (
                        empresa_id, data_manutencao, data_conclusao, veiculo_id, tipo_manutencao_id, 
                        componente_id, status_manutencao_id, km_atual, fornecedor,
                        valor, custo_total, nota_fiscal, descricao, descricao_servico,
                        observacoes, responsavel_aprovacao
                    ) VALUES (
                        :empresa_id, :data_manutencao, :data_conclusao, :veiculo_id, :tipo_manutencao_id,
                        :componente_id, :status_manutencao_id, :km_atual, :fornecedor,
                        :valor, :custo_total, :nota_fiscal, :descricao, :descricao_servico,
                        :observacoes, :responsavel_aprovacao
                    )";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->bindParam(':data_manutencao', $data['data_manutencao']);
            $stmt->bindValue(':data_conclusao', !empty($data['data_conclusao']) ? $data['data_conclusao'] : null);
            $stmt->bindParam(':veiculo_id', $data['veiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':tipo_manutencao_id', $data['tipo_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':componente_id', $data['componente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status_manutencao_id', $data['status_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':km_atual', $data['km_atual'], PDO::PARAM_INT);
            $stmt->bindParam(':fornecedor', $data['fornecedor']);
            $stmt->bindParam(':valor', $data['valor'], PDO::PARAM_STR);
            $stmt->bindParam(':custo_total', $data['custo_total'], PDO::PARAM_STR);
            $stmt->bindParam(':nota_fiscal', $data['nota_fiscal']);
            $stmt->bindParam(':descricao', $data['descricao']);
            $stmt->bindParam(':descricao_servico', $data['descricao_servico']);
            $stmt->bindParam(':observacoes', $data['observacoes']);
            $stmt->bindParam(':responsavel_aprovacao', $data['responsavel_aprovacao']);
            
            $stmt->execute();
            $id = $conn->lastInsertId();
            
            // Opcional: atualizar veiculos.ultima_manutencao e km_ultima_manutencao (se as colunas existirem)
            try {
                $update_veiculo_sql = "UPDATE veiculos SET ultima_manutencao = :data_manutencao, km_ultima_manutencao = :km_atual WHERE id = :veiculo_id AND empresa_id = :empresa_id";
                $update_stmt = $conn->prepare($update_veiculo_sql);
                $update_stmt->bindParam(':data_manutencao', $data['data_manutencao']);
                $update_stmt->bindParam(':km_atual', $data['km_atual'], PDO::PARAM_INT);
                $update_stmt->bindParam(':veiculo_id', $data['veiculo_id'], PDO::PARAM_INT);
                $update_stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
                $update_stmt->execute();
            } catch (PDOException $e) {
                // Colunas ultima_manutencao/km_ultima_manutencao podem não existir na tabela veiculos
            }
            
            // Atualizar plano de manutenção (ultimo_km, ultima_data) se for preventiva concluída
            atualizar_plano_apos_manutencao($conn, $_SESSION['empresa_id'], (int)$data['veiculo_id'], (int)$data['componente_id'], (int)$data['tipo_manutencao_id'], (int)$data['status_manutencao_id'], $data['data_manutencao'], (int)$data['km_atual']);
            
            echo json_encode(['success' => true, 'message' => 'Manutenção cadastrada com sucesso', 'id' => $id]);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'PUT':
        // Handle PUT request (update maintenance)
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data || !isset($data['id'])) {
                throw new Exception('Dados inválidos ou ID não fornecido');
            }
            
            // Verify if maintenance exists and belongs to the company
            $check_sql = "SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $check_stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Manutenção não encontrada ou não pertence à empresa');
            }
            if (empty((int)($data['tipo_manutencao_id'] ?? 0))) {
                throw new Exception('Tipo de manutenção é obrigatório');
            }
            $stmt_st = $conn->prepare("SELECT nome FROM status_manutencao WHERE id = :id");
            $stmt_st->execute(['id' => $data['status_manutencao_id']]);
            $status_nome = $stmt_st->fetchColumn();
            if ($status_nome && stripos($status_nome, 'conclu') !== false && empty($data['data_conclusao'])) {
                throw new Exception('Data de conclusão é obrigatória quando o status é Concluída');
            }
            
            // Update maintenance record
            $sql = "UPDATE manutencoes SET 
                        data_manutencao = :data_manutencao,
                        data_conclusao = :data_conclusao,
                        veiculo_id = :veiculo_id,
                        tipo_manutencao_id = :tipo_manutencao_id,
                        componente_id = :componente_id,
                        status_manutencao_id = :status_manutencao_id,
                        km_atual = :km_atual,
                        fornecedor = :fornecedor,
                        valor = :valor,
                        custo_total = :custo_total,
                        nota_fiscal = :nota_fiscal,
                        descricao = :descricao,
                        descricao_servico = :descricao_servico,
                        observacoes = :observacoes,
                        responsavel_aprovacao = :responsavel_aprovacao
                    WHERE id = :id AND empresa_id = :empresa_id";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->bindParam(':data_manutencao', $data['data_manutencao']);
            $stmt->bindValue(':data_conclusao', !empty($data['data_conclusao']) ? $data['data_conclusao'] : null);
            $stmt->bindParam(':veiculo_id', $data['veiculo_id'], PDO::PARAM_INT);
            $stmt->bindParam(':tipo_manutencao_id', $data['tipo_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':componente_id', $data['componente_id'], PDO::PARAM_INT);
            $stmt->bindParam(':status_manutencao_id', $data['status_manutencao_id'], PDO::PARAM_INT);
            $stmt->bindParam(':km_atual', $data['km_atual'], PDO::PARAM_INT);
            $stmt->bindParam(':fornecedor', $data['fornecedor']);
            $stmt->bindParam(':valor', $data['valor'], PDO::PARAM_STR);
            $stmt->bindParam(':custo_total', $data['custo_total'], PDO::PARAM_STR);
            $stmt->bindParam(':nota_fiscal', $data['nota_fiscal']);
            $stmt->bindParam(':descricao', $data['descricao']);
            $stmt->bindParam(':descricao_servico', $data['descricao_servico']);
            $stmt->bindParam(':observacoes', $data['observacoes']);
            $stmt->bindParam(':responsavel_aprovacao', $data['responsavel_aprovacao']);
            
            $stmt->execute();
            
            atualizar_plano_apos_manutencao($conn, $_SESSION['empresa_id'], (int)$data['veiculo_id'], (int)$data['componente_id'], (int)$data['tipo_manutencao_id'], (int)$data['status_manutencao_id'], $data['data_manutencao'], (int)$data['km_atual']);
            
            echo json_encode(['success' => true, 'message' => 'Manutenção atualizada com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    case 'DELETE':
        // Handle DELETE request
        try {
            $id = isset($_GET['id']) ? intval($_GET['id']) : null;
            
            if (!$id) {
                throw new Exception('ID não fornecido');
            }
            
            // Verify if maintenance exists and belongs to the company
            $check_sql = "SELECT id FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $check_stmt->execute();
            
            if (!$check_stmt->fetch()) {
                throw new Exception('Manutenção não encontrada ou não pertence à empresa');
            }
            
            // Delete maintenance record
            $sql = "DELETE FROM manutencoes WHERE id = :id AND empresa_id = :empresa_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $_SESSION['empresa_id'], PDO::PARAM_INT);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Manutenção excluída com sucesso']);
            
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método não permitido']);
        break;
} 