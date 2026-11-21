<?php
/**
 * Ordens de Transporte — CRUD + mudança de status + geração da Rota existente.
 * Unifica Cotação → OT → Viagem numa entidade com status. Não duplica rotas:
 * quando aprovada, "gerar_rota" cria um registro em `rotas` e vincula rota_id.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

configure_session();
session_start();
require_authentication();

header('Content-Type: application/json; charset=UTF-8');

$empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
if ($empresa_id < 1) {
    echo json_encode(['success' => false, 'error' => 'Empresa não identificada.']);
    exit;
}
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$STATUS = ['cotacao', 'aprovada', 'em_viagem', 'concluida', 'cancelada'];

function num_val($v): float {
    if (is_string($v)) { $v = str_replace(['.', ' '], '', $v); $v = str_replace(',', '.', $v); }
    return is_numeric($v) ? (float) $v : 0.0;
}

try {
    $conn = getConnection();
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS ordens_transporte (
            id INT AUTO_INCREMENT PRIMARY KEY, empresa_id INT NOT NULL, numero VARCHAR(20) NOT NULL,
            cliente_nome VARCHAR(160) NULL, cliente_doc VARCHAR(40) NULL,
            origem VARCHAR(160) NULL, destino VARCHAR(160) NULL, estado_origem VARCHAR(2) NULL, estado_destino VARCHAR(2) NULL,
            distancia_km DECIMAL(10,2) NOT NULL DEFAULT 0, tipo_carga VARCHAR(40) NOT NULL DEFAULT "geral",
            eixos TINYINT UNSIGNED NOT NULL DEFAULT 5, peso_carga DECIMAL(10,2) NULL, descricao_carga VARCHAR(255) NULL,
            valor_frete DECIMAL(12,2) NOT NULL DEFAULT 0, valor_custo_estimado DECIMAL(12,2) NULL,
            veiculo_id INT NULL, motorista_id INT NULL, rota_id INT NULL,
            data_coleta DATETIME NULL, data_entrega DATETIME NULL,
            status ENUM("cotacao","aprovada","em_viagem","concluida","cancelada") NOT NULL DEFAULT "cotacao",
            observacoes TEXT NULL, token_rastreio VARCHAR(40) NULL, criado_por INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_num (empresa_id, numero), KEY idx_emp_status (empresa_id, status), KEY idx_rota (rota_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ($action === 'list') {
        $status = (string) ($_GET['status'] ?? '');
        $sql = 'SELECT o.*, v.placa, m.nome AS motorista_nome
                FROM ordens_transporte o
                LEFT JOIN veiculos v ON v.id = o.veiculo_id
                LEFT JOIN motoristas m ON m.id = o.motorista_id
                WHERE o.empresa_id = :e';
        $p = [':e' => $empresa_id];
        if (in_array($status, $STATUS, true)) { $sql .= ' AND o.status = :s'; $p[':s'] = $status; }
        $sql .= ' ORDER BY o.created_at DESC LIMIT 300';
        $st = $conn->prepare($sql);
        $st->execute($p);
        echo json_encode(['success' => true, 'ordens' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get') {
        $id = (int) ($_GET['id'] ?? 0);
        $st = $conn->prepare('SELECT * FROM ordens_transporte WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => $id, ':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['success' => true, 'ordem' => $row] : ['success' => false, 'error' => 'Não encontrada.']);
        exit;
    }

    // Ações que mudam estado exigem CSRF.
    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $campos = [
            'cliente_nome' => trim((string) ($_POST['cliente_nome'] ?? '')),
            'cliente_doc' => trim((string) ($_POST['cliente_doc'] ?? '')),
            'origem' => trim((string) ($_POST['origem'] ?? '')),
            'destino' => trim((string) ($_POST['destino'] ?? '')),
            'estado_origem' => strtoupper(substr(trim((string) ($_POST['estado_origem'] ?? '')), 0, 2)),
            'estado_destino' => strtoupper(substr(trim((string) ($_POST['estado_destino'] ?? '')), 0, 2)),
            'distancia_km' => num_val($_POST['distancia_km'] ?? 0),
            'tipo_carga' => preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['tipo_carga'] ?? 'geral'))) ?: 'geral',
            'eixos' => max(2, min(9, (int) ($_POST['eixos'] ?? 5))),
            'peso_carga' => num_val($_POST['peso_carga'] ?? 0),
            'descricao_carga' => trim((string) ($_POST['descricao_carga'] ?? '')),
            'valor_frete' => num_val($_POST['valor_frete'] ?? 0),
            'valor_custo_estimado' => num_val($_POST['valor_custo_estimado'] ?? 0),
            'veiculo_id' => ((int) ($_POST['veiculo_id'] ?? 0)) ?: null,
            'motorista_id' => ((int) ($_POST['motorista_id'] ?? 0)) ?: null,
            'data_coleta' => ($_POST['data_coleta'] ?? '') !== '' ? (string) $_POST['data_coleta'] : null,
            'data_entrega' => ($_POST['data_entrega'] ?? '') !== '' ? (string) $_POST['data_entrega'] : null,
            'observacoes' => trim((string) ($_POST['observacoes'] ?? '')),
        ];
        if ($id > 0) {
            $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
            $st = $conn->prepare("UPDATE ordens_transporte SET $set WHERE id = :id AND empresa_id = :e");
            $st->execute($campos + [':id' => $id, ':e' => $empresa_id]);
            echo json_encode(['success' => true, 'id' => $id]);
        } else {
            $mx = $conn->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 4) AS UNSIGNED)), 0) + 1 FROM ordens_transporte WHERE empresa_id = :e');
            $mx->execute([':e' => $empresa_id]);
            $numero = 'OT-' . str_pad((string) $mx->fetchColumn(), 6, '0', STR_PAD_LEFT);
            $cols = array_keys($campos);
            $ph = implode(', ', array_map(fn($k) => ":$k", $cols));
            $token = bin2hex(random_bytes(16));
            $st = $conn->prepare('INSERT INTO ordens_transporte (empresa_id, numero, status, token_rastreio, criado_por, ' . implode(', ', $cols) . ')
                VALUES (:e, :numero, "cotacao", :tok, :cp, ' . $ph . ')');
            $st->execute($campos + [':e' => $empresa_id, ':numero' => $numero, ':tok' => $token, ':cp' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null]);
            echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId(), 'numero' => $numero]);
        }
        exit;
    }

    if ($action === 'atribuir') {
        // Atualiza SÓ veículo/motorista/coleta (usado pelo Planejamento) — não toca no resto da OT.
        $id = (int) ($_POST['id'] ?? 0);
        $vid = ((int) ($_POST['veiculo_id'] ?? 0)) ?: null;
        $mid = ((int) ($_POST['motorista_id'] ?? 0)) ?: null;
        $dc = ($_POST['data_coleta'] ?? '') !== '' ? (string) $_POST['data_coleta'] : null;
        $st = $conn->prepare('UPDATE ordens_transporte SET veiculo_id = :v, motorista_id = :m, data_coleta = COALESCE(:d, data_coleta)
            WHERE id = :id AND empresa_id = :e');
        $st->execute([':v' => $vid, ':m' => $mid, ':d' => $dc, ':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'status') {
        $id = (int) ($_POST['id'] ?? 0);
        $novo = (string) ($_POST['status'] ?? '');
        if (!in_array($novo, $STATUS, true)) { throw new InvalidArgumentException('Status inválido.'); }
        $st = $conn->prepare('UPDATE ordens_transporte SET status = :s WHERE id = :id AND empresa_id = :e');
        $st->execute([':s' => $novo, ':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'gerar_rota') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $conn->prepare('SELECT * FROM ordens_transporte WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => $id, ':e' => $empresa_id]);
        $ot = $st->fetch(PDO::FETCH_ASSOC);
        if (!$ot) { throw new InvalidArgumentException('OT não encontrada.'); }
        if (!empty($ot['rota_id'])) { throw new InvalidArgumentException('Esta OT já gerou uma rota (#' . $ot['rota_id'] . ').'); }
        if (empty($ot['veiculo_id']) || empty($ot['motorista_id'])) { throw new InvalidArgumentException('Defina veículo e motorista na OT antes de gerar a rota.'); }
        $dataSaida = $ot['data_coleta'] ?: date('Y-m-d H:i:s');

        $ins = $conn->prepare('INSERT INTO rotas
            (empresa_id, veiculo_id, motorista_id, estado_origem, estado_destino, data_saida, data_chegada,
             data_rota, distancia_km, total_km, frete, peso_carga, descricao_carga, observacoes, status, fonte, no_prazo)
            VALUES (:e, :v, :m, :eo, :ed, :ds, :dc, :dr, :dk, :dk2, :fr, :pc, :desc, :obs, "aprovado", "ot", 0)');
        $ins->execute([
            ':e' => $empresa_id, ':v' => $ot['veiculo_id'], ':m' => $ot['motorista_id'],
            ':eo' => $ot['estado_origem'], ':ed' => $ot['estado_destino'],
            ':ds' => $dataSaida, ':dc' => $ot['data_entrega'] ?: $dataSaida, ':dr' => date('Y-m-d', strtotime($dataSaida)),
            ':dk' => $ot['distancia_km'], ':dk2' => $ot['distancia_km'], ':fr' => $ot['valor_frete'],
            ':pc' => $ot['peso_carga'], ':desc' => $ot['descricao_carga'],
            ':obs' => 'Gerada da ' . $ot['numero'] . ($ot['cliente_nome'] ? ' · ' . $ot['cliente_nome'] : ''),
        ]);
        $rotaId = (int) $conn->lastInsertId();
        $upd = $conn->prepare('UPDATE ordens_transporte SET rota_id = :r, status = "em_viagem" WHERE id = :id AND empresa_id = :e');
        $upd->execute([':r' => $rotaId, ':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true, 'rota_id' => $rotaId]);
        exit;
    }

    if ($action === 'gerar_token') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $conn->prepare('SELECT token_rastreio FROM ordens_transporte WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => $id, ':e' => $empresa_id]);
        $tok = $st->fetchColumn();
        if ($tok === false) { throw new InvalidArgumentException('OT não encontrada.'); }
        if (!$tok) {
            $tok = bin2hex(random_bytes(16));
            $conn->prepare('UPDATE ordens_transporte SET token_rastreio = :t WHERE id = :id AND empresa_id = :e')
                 ->execute([':t' => $tok, ':id' => $id, ':e' => $empresa_id]);
        }
        echo json_encode(['success' => true, 'token' => $tok]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $st = $conn->prepare('DELETE FROM ordens_transporte WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/ordens_transporte.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar a ordem de transporte.']);
}
