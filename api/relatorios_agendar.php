<?php
/**
 * CRUD de agendamentos de relatórios automáticos (envio por e-mail).
 * Ações: list | create | toggle | delete
 */
require_once '../includes/config.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';

configure_session();
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = (int) $_SESSION['empresa_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$allowedTypes = ['gamificacao_semanal', 'ranking_mensal', 'performance_geral'];

function ra_proximo_envio(string $freq): string {
    $base = strtotime('06:00 tomorrow');
    if ($freq === 'semanal') {
        $base = strtotime('monday next week 06:00');
    } elseif ($freq === 'mensal') {
        $base = strtotime('first day of next month 06:00');
    }
    return date('Y-m-d H:i:s', $base);
}

try {
    $conn = getConnection();
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS relatorios_agendados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            empresa_id INT NOT NULL,
            report_type VARCHAR(60) NOT NULL,
            frequencia ENUM("diario","semanal","mensal") NOT NULL DEFAULT "mensal",
            destinatarios TEXT NOT NULL,
            formato ENUM("pdf","html") NOT NULL DEFAULT "html",
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            ultimo_envio DATETIME NULL,
            proximo_envio DATETIME NULL,
            criado_por INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_emp (empresa_id, ativo),
            KEY idx_prox (proximo_envio, ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    if ($action === 'list') {
        $stmt = $conn->prepare('SELECT id, report_type, frequencia, destinatarios, ativo, ultimo_envio, proximo_envio
            FROM relatorios_agendados WHERE empresa_id = :e ORDER BY created_at DESC');
        $stmt->execute([':e' => $empresa_id]);
        echo json_encode(['success' => true, 'agendamentos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // Ações que mudam estado exigem CSRF.
    if (!csrf_token_validate($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Atualize a página.']);
        exit;
    }

    if ($action === 'create') {
        $report_type = (string) ($_POST['report_type'] ?? '');
        if (!in_array($report_type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Relatório inválido.');
        }
        $freq = (string) ($_POST['frequencia'] ?? 'mensal');
        if (!in_array($freq, ['diario', 'semanal', 'mensal'], true)) {
            $freq = 'mensal';
        }
        // Valida e normaliza e-mails.
        $raw = preg_split('/[,;\s]+/', (string) ($_POST['destinatarios'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $emails = [];
        foreach ($raw as $em) {
            $em = trim($em);
            if (filter_var($em, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $em;
            }
        }
        if (empty($emails)) {
            throw new InvalidArgumentException('Informe ao menos um e-mail válido.');
        }
        $stmt = $conn->prepare('INSERT INTO relatorios_agendados
            (empresa_id, report_type, frequencia, destinatarios, formato, ativo, proximo_envio, criado_por)
            VALUES (:e, :t, :f, :d, "html", 1, :p, :u)');
        $stmt->execute([
            ':e' => $empresa_id,
            ':t' => $report_type,
            ':f' => $freq,
            ':d' => implode(', ', $emails),
            ':p' => ra_proximo_envio($freq),
            ':u' => (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0) ?: null,
        ]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('UPDATE relatorios_agendados SET ativo = 1 - ativo WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $conn->prepare('DELETE FROM relatorios_agendados WHERE id = :id AND empresa_id = :e');
        $stmt->execute([':id' => $id, ':e' => $empresa_id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Ação inválida.');
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('api/relatorios_agendar.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro interno.']);
}
