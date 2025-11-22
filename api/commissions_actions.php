<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

header('Content-Type: application/json');

try {
    $conn = getConnection();
    $empresa_id = $_SESSION['empresa_id'];

    ensureCommissionPaymentsTable($conn);

    $action = $_POST['action'] ?? null;
    $rota_id = isset($_POST['rota_id']) ? (int) $_POST['rota_id'] : 0;

    if (!$action || !$rota_id) {
        throw new Exception('Parâmetros inválidos.');
    }

    $stmt = $conn->prepare("SELECT id, comissao FROM rotas WHERE id = :rota_id AND empresa_id = :empresa_id AND comissao > 0");
    $stmt->bindValue(':rota_id', $rota_id, PDO::PARAM_INT);
    $stmt->bindValue(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt->execute();
    $rota = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$rota) {
        throw new Exception('Comissão não encontrada.');
    }

    if ($action === 'mark_paid') {
        markCommissionStatus($conn, $rota_id, 'pago', $rota['comissao']);
    } elseif ($action === 'mark_pending') {
        markCommissionStatus($conn, $rota_id, 'pendente', $rota['comissao']);
    } else {
        throw new Exception('Ação inválida.');
    }

    echo json_encode(['success' => true]);
    exit;
} catch (Exception $e) {
    error_log("Commissions Actions Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

function ensureCommissionPaymentsTable(PDO $conn): void
{
    $conn->exec("
        CREATE TABLE IF NOT EXISTS comissoes_pagamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rota_id INT NOT NULL UNIQUE,
            status ENUM('pago', 'pendente') NOT NULL DEFAULT 'pendente',
            valor DECIMAL(10,2) DEFAULT NULL,
            data_pagamento DATE DEFAULT NULL,
            observacao TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_comissoes_pag_rotas FOREIGN KEY (rota_id) REFERENCES rotas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function markCommissionStatus(PDO $conn, int $rota_id, string $status, float $valorComissao): void
{
    $stmt = $conn->prepare("
        INSERT INTO comissoes_pagamentos (rota_id, status, valor, data_pagamento)
        VALUES (:rota_id, :status, :valor, :data_pagamento)
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            valor = VALUES(valor),
            data_pagamento = VALUES(data_pagamento),
            updated_at = CURRENT_TIMESTAMP
    ");

    $stmt->bindValue(':rota_id', $rota_id, PDO::PARAM_INT);
    $stmt->bindValue(':status', $status, PDO::PARAM_STR);
    $stmt->bindValue(':valor', $valorComissao, PDO::PARAM_STR);
    $stmt->bindValue(':data_pagamento', $status === 'pago' ? date('Y-m-d') : null, $status === 'pago' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->execute();
}

