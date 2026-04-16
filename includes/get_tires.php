<?php
require_once 'config.php';
require_once 'functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar se o usuário está autenticado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

try {
    $pdo = getConnection();
    $empresa_id = $_SESSION['empresa_id'];
    
    // Se foi solicitado um pneu específico (veículo/posição: instalacoes_pneus, alocacoes_pneus_flexiveis ou eixo_pneus)
    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $has_inst = false;
        $has_eixo = false;
        $has_apf = false;
        try {
            $r = $pdo->query("SHOW TABLES LIKE 'instalacoes_pneus'");
            $has_inst = $r && $r->rowCount() > 0;
            $r = $pdo->query("SHOW TABLES LIKE 'eixo_pneus'");
            $has_eixo = $r && $r->rowCount() > 0;
            $r = $pdo->query("SHOW TABLES LIKE 'alocacoes_pneus_flexiveis'");
            $has_apf = $r && $r->rowCount() > 0;
        } catch (Exception $e) {
        }
        $has_apf_posicao = false;
        if ($has_apf) {
            try {
                $r = $pdo->query("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
                $has_apf_posicao = $r && $r->rowCount() > 0;
            } catch (Exception $e) {
            }
        }
        $data = null;
        try {
            if ($has_inst || $has_apf || $has_eixo) {
                $parts_v = [];
                $parts_p = [];
                if ($has_inst) {
                    $parts_v[] = 'v_inst.placa';
                    $parts_p[] = 'pp_inst.nome';
                }
                if ($has_apf) {
                    $parts_v[] = 'v_flex.placa';
                    if ($has_apf_posicao) {
                        $parts_p[] = 'pp_flex.nome';
                    }
                }
                if ($has_eixo) {
                    $parts_v[] = 'v.placa';
                    $parts_p[] = 'pp.nome';
                }
                $v_sql = count($parts_v) > 0 ? 'COALESCE(' . implode(', ', $parts_v) . ')' : 'NULL';
                $p_sql = count($parts_p) > 0 ? 'COALESCE(' . implode(', ', $parts_p) . ')' : 'NULL';
                $join_apf = $has_apf ? "
                    LEFT JOIN alocacoes_pneus_flexiveis apf ON p.id = apf.pneu_id AND apf.ativo = 1
                    LEFT JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                    LEFT JOIN veiculos v_flex ON v_flex.id = ev.veiculo_id AND v_flex.empresa_id = p.empresa_id
                    " . ($has_apf_posicao ? "LEFT JOIN posicoes_pneus pp_flex ON pp_flex.id = apf.posicao_id" : "") . "
                " : "";
                $join_inst = $has_inst ? "
                    LEFT JOIN instalacoes_pneus ip ON p.id = ip.pneu_id AND ip.data_remocao IS NULL
                    LEFT JOIN veiculos v_inst ON v_inst.id = ip.veiculo_id AND v_inst.empresa_id = p.empresa_id
                    LEFT JOIN posicoes_pneus pp_inst ON pp_inst.id = ip.posicao_id
                " : "";
                $join_eixo = $has_eixo ? "
                    LEFT JOIN eixo_pneus ep ON p.id = ep.pneu_id AND ep.status = 'alocado'
                    LEFT JOIN eixos e ON ep.eixo_id = e.id
                    LEFT JOIN veiculos v ON v.id = e.veiculo_id AND v.empresa_id = p.empresa_id
                    LEFT JOIN posicoes_pneus pp ON pp.id = ep.posicao_id
                " : "";
                $stmt = $pdo->prepare("
                    SELECT p.*, s.nome as status_nome, $v_sql as veiculo_placa, $p_sql as posicao_nome
                    FROM pneus p
                    LEFT JOIN status_pneus s ON s.id = p.status_id
                    $join_inst $join_apf $join_eixo
                    WHERE p.id = ? AND p.empresa_id = ?
                ");
                $stmt->execute([$id, $empresa_id]);
                $data = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
        }
        if ($data === null) {
            $stmt = $pdo->prepare("
                SELECT p.*, s.nome as status_nome, NULL as veiculo_placa, NULL as posicao_nome
                FROM pneus p
                LEFT JOIN status_pneus s ON s.id = p.status_id
                WHERE p.id = ? AND p.empresa_id = ?
            ");
            $stmt->execute([$id, $empresa_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if ($data && !isset($data['posicao_nome'])) {
            $data['posicao_nome'] = null;
        }
        if ($data && !isset($data['veiculo_placa'])) {
            $data['veiculo_placa'] = null;
        }
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        // Paginação
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
        $allowed_per_page = [5, 10, 25, 50, 100];
        if (!in_array($per_page, $allowed_per_page)) {
            $per_page = 10;
        }
        $offset = ($page - 1) * $per_page;
        
        // Filtros
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $veiculo = isset($_GET['veiculo']) ? trim($_GET['veiculo']) : '';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        
        $where = ["p.empresa_id = ?"];
        $params = [$empresa_id];
        
        if ($status !== '') {
            $where[] = "p.status_id = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $term = '%' . $search . '%';
            $where[] = "(p.numero_serie LIKE ? OR p.marca LIKE ? OR p.modelo LIKE ? OR p.dot LIKE ? OR p.medida LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        
        $where_sql = implode(' AND ', $where);
        
        // Verificar se as tabelas de alocação existem
        $has_inst = false;
        $has_eixo = false;
        $has_apf = false;
        try {
            $r = $pdo->query("SHOW TABLES LIKE 'instalacoes_pneus'");
            $has_inst = $r && $r->rowCount() > 0;
            $r = $pdo->query("SHOW TABLES LIKE 'eixo_pneus'");
            $has_eixo = $r && $r->rowCount() > 0;
            $r = $pdo->query("SHOW TABLES LIKE 'alocacoes_pneus_flexiveis'");
            $has_apf = $r && $r->rowCount() > 0;
        } catch (Exception $e) {
            // ignora
        }
        $has_apf_posicao = false;
        if ($has_apf) {
            try {
                $r = $pdo->query("SHOW COLUMNS FROM alocacoes_pneus_flexiveis LIKE 'posicao_id'");
                $has_apf_posicao = $r && $r->rowCount() > 0;
            } catch (Exception $e) {
            }
        }
        
        $parts_v = [];
        $parts_p = [];
        $join = "";
        if ($has_inst) {
            $join .= "
                LEFT JOIN instalacoes_pneus ip ON p.id = ip.pneu_id AND ip.data_remocao IS NULL
                LEFT JOIN veiculos v_inst ON v_inst.id = ip.veiculo_id AND v_inst.empresa_id = p.empresa_id
                LEFT JOIN posicoes_pneus pp_inst ON pp_inst.id = ip.posicao_id
            ";
            $parts_v[] = 'v_inst.placa';
            $parts_p[] = 'pp_inst.nome';
        }
        if ($has_apf) {
            $join .= "
                LEFT JOIN alocacoes_pneus_flexiveis apf ON p.id = apf.pneu_id AND apf.ativo = 1
                LEFT JOIN eixos_veiculos ev ON apf.eixo_veiculo_id = ev.id
                LEFT JOIN veiculos v_flex ON v_flex.id = ev.veiculo_id AND v_flex.empresa_id = p.empresa_id
                " . ($has_apf_posicao ? "LEFT JOIN posicoes_pneus pp_flex ON pp_flex.id = apf.posicao_id" : "") . "
            ";
            $parts_v[] = 'v_flex.placa';
            if ($has_apf_posicao) {
                $parts_p[] = 'pp_flex.nome';
            }
        }
        if ($has_eixo) {
            $join .= "
                LEFT JOIN eixo_pneus ep ON p.id = ep.pneu_id AND ep.status = 'alocado'
                LEFT JOIN eixos e ON ep.eixo_id = e.id
                LEFT JOIN veiculos v ON v.id = e.veiculo_id AND v.empresa_id = p.empresa_id
                LEFT JOIN posicoes_pneus pp ON pp.id = ep.posicao_id
            ";
            $parts_v[] = 'v.placa';
            $parts_p[] = 'pp.nome';
        }
        $veiculo_placa_sql = count($parts_v) > 0 ? 'COALESCE(' . implode(', ', $parts_v) . ')' : 'NULL';
        $posicao_nome_sql = count($parts_p) > 0 ? 'COALESCE(' . implode(', ', $parts_p) . ')' : 'NULL';
        if ($veiculo !== '') {
            $veiculo_cond = [];
            if ($has_inst) $veiculo_cond[] = 'ip.veiculo_id = ?';
            if ($has_apf) $veiculo_cond[] = 'ev.veiculo_id = ?';
            if ($has_eixo) $veiculo_cond[] = 'e.veiculo_id = ?';
            if (count($veiculo_cond) > 0) {
                $where_sql .= ' AND (' . implode(' OR ', $veiculo_cond) . ')';
                for ($i = 0; $i < count($veiculo_cond); $i++) {
                    $params[] = $veiculo;
                }
            }
        }
        
        if ($join === "") {
            $veiculo_placa_sql = "NULL";
            $posicao_nome_sql = "NULL";
        }
        
        // Conta o total com os mesmos filtros e JOIN (para filtro por veículo)
        $count_sql = "SELECT COUNT(DISTINCT p.id) as total FROM pneus p $join WHERE $where_sql";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Buscar pneus paginados (sem GROUP BY para compatibilidade com MySQL strict)
        $sql = "
            SELECT p.*, 
                   s.nome as status_nome,
                   $veiculo_placa_sql as veiculo_placa,
                   $posicao_nome_sql as posicao_nome
            FROM pneus p
            LEFT JOIN status_pneus s ON s.id = p.status_id
            $join
            WHERE $where_sql
            ORDER BY p.id DESC
            LIMIT ? OFFSET ?
        ";
        $params[] = $per_page;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Normalizar posicao_nome para a tabela (evitar null)
        foreach ($data as &$row) {
            if (!array_key_exists('posicao_nome', $row) || $row['posicao_nome'] === null) {
                $row['posicao_nome'] = '-';
            }
        }
        unset($row);
        
        echo json_encode([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'current_page' => $page,
                'per_page' => $per_page,
                'total_pages' => $total > 0 ? (int) ceil($total / $per_page) : 1
            ]
        ]);
    }
} catch (Exception $e) {
    error_log('Erro em get_tires.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 