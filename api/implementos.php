<?php
/**
 * Implementos — CRUD dos ativos rebocados (carretas, reboques, dollys…).
 * Reusa tipos_carretas para os subtipos. Somente da empresa da sessão.
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

function imp_num($v) {
    if (is_string($v)) { $v = str_replace(['.', ' '], '', $v); $v = str_replace(',', '.', $v); }
    return is_numeric($v) ? (float) $v : null;
}

try {
    $conn = getConnection();
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS implementos (
            id INT AUTO_INCREMENT PRIMARY KEY, empresa_id INT NOT NULL, placa VARCHAR(10) NOT NULL,
            tipo VARCHAR(40) NOT NULL DEFAULT "Carreta", tipo_carreta_id INT NULL, marca VARCHAR(60) NULL,
            ano SMALLINT NULL, renavam VARCHAR(20) NULL, chassi VARCHAR(30) NULL, rntrc VARCHAR(20) NULL,
            capacidade_kg DECIMAL(10,2) NULL, eixos TINYINT UNSIGNED NULL, veiculo_id INT NULL,
            status ENUM("ativo","manutencao","inativo") NOT NULL DEFAULT "ativo",
            vencimento_licenciamento DATE NULL, vencimento_seguro DATE NULL, observacoes TEXT NULL, criado_por INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_placa (empresa_id, placa), KEY idx_emp_status (empresa_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ($action === 'list') {
        $status = (string) ($_GET['status'] ?? '');
        $sql = 'SELECT i.*, v.placa AS cavalo_placa FROM implementos i
                LEFT JOIN veiculos v ON v.id = i.veiculo_id WHERE i.empresa_id = :e';
        $p = [':e' => $empresa_id];
        if (in_array($status, ['ativo', 'manutencao', 'inativo'], true)) { $sql .= ' AND i.status = :s'; $p[':s'] = $status; }
        $sql .= ' ORDER BY i.placa LIMIT 500';
        $st = $conn->prepare($sql); $st->execute($p);
        echo json_encode(['success' => true, 'implementos' => $st->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'get') {
        $st = $conn->prepare('SELECT * FROM implementos WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => (int) ($_GET['id'] ?? 0), ':e' => $empresa_id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        echo json_encode($row ? ['success' => true, 'implemento' => $row] : ['success' => false, 'error' => 'Não encontrado.']);
        exit;
    }

    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
        exit;
    }

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
        if ($placa === '') { throw new InvalidArgumentException('Informe a placa.'); }
        $campos = [
            'placa' => $placa,
            'tipo' => trim((string) ($_POST['tipo'] ?? 'Carreta')) ?: 'Carreta',
            'tipo_carreta_id' => ((int) ($_POST['tipo_carreta_id'] ?? 0)) ?: null,
            'marca' => trim((string) ($_POST['marca'] ?? '')) ?: null,
            'ano' => ((int) ($_POST['ano'] ?? 0)) ?: null,
            'renavam' => trim((string) ($_POST['renavam'] ?? '')) ?: null,
            'chassi' => trim((string) ($_POST['chassi'] ?? '')) ?: null,
            'rntrc' => trim((string) ($_POST['rntrc'] ?? '')) ?: null,
            'capacidade_kg' => imp_num($_POST['capacidade_kg'] ?? ''),
            'eixos' => ((int) ($_POST['eixos'] ?? 0)) ?: null,
            'veiculo_id' => ((int) ($_POST['veiculo_id'] ?? 0)) ?: null,
            'status' => in_array($_POST['status'] ?? '', ['ativo', 'manutencao', 'inativo'], true) ? $_POST['status'] : 'ativo',
            'vencimento_licenciamento' => ($_POST['vencimento_licenciamento'] ?? '') !== '' ? (string) $_POST['vencimento_licenciamento'] : null,
            'vencimento_seguro' => ($_POST['vencimento_seguro'] ?? '') !== '' ? (string) $_POST['vencimento_seguro'] : null,
            'observacoes' => trim((string) ($_POST['observacoes'] ?? '')) ?: null,
        ];
        try {
            if ($id > 0) {
                $set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($campos)));
                $st = $conn->prepare("UPDATE implementos SET $set WHERE id = :id AND empresa_id = :e");
                $st->execute($campos + [':id' => $id, ':e' => $empresa_id]);
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                $cols = array_keys($campos);
                $ph = implode(', ', array_map(fn($k) => ":$k", $cols));
                $st = $conn->prepare('INSERT INTO implementos (empresa_id, criado_por, ' . implode(', ', $cols) . ') VALUES (:e, :cp, ' . $ph . ')');
                $st->execute($campos + [':e' => $empresa_id, ':cp' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null]);
                echo json_encode(['success' => true, 'id' => (int) $conn->lastInsertId()]);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { throw new InvalidArgumentException('Já existe um implemento com essa placa.'); }
            throw $e;
        }
        exit;
    }

    if ($action === 'status') {
        $novo = (string) ($_POST['status'] ?? '');
        if (!in_array($novo, ['ativo', 'manutencao', 'inativo'], true)) { throw new InvalidArgumentException('Status inválido.'); }
        $st = $conn->prepare('UPDATE implementos SET status = :s WHERE id = :id AND empresa_id = :e');
        $st->execute([':s' => $novo, ':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $st = $conn->prepare('DELETE FROM implementos WHERE id = :id AND empresa_id = :e');
        $st->execute([':id' => (int) ($_POST['id'] ?? 0), ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/implementos.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao processar o implemento.']);
}
