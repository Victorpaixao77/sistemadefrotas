<?php
/**
 * Helpers compartilhados — Gestão de Pneus.
 */

if (!function_exists('pneus_empresa_id')) {
    function pneus_empresa_id(): int
    {
        return (int) ($_SESSION['empresa_id'] ?? 0);
    }
}

if (!function_exists('pneus_require_page_access')) {
    function pneus_require_page_access(): void
    {
        if (!function_exists('can_access_tire_management')) {
            require_once __DIR__ . '/permissions.php';
        }
        require_permission('access_tire_management');
    }
}

if (!function_exists('pneus_require_api_access')) {
    function pneus_require_api_access(): void
    {
        configure_session();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        require_authentication();
        if (!function_exists('can_access_tire_management')) {
            require_once __DIR__ . '/permissions.php';
        }
        if (!can_access_tire_management()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Sem permissão para gestão de pneus'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('pneus_validar_pneu_empresa')) {
    function pneus_validar_pneu_empresa(PDO $conn, int $pneu_id, int $empresa_id): bool
    {
        if ($pneu_id <= 0 || $empresa_id <= 0) {
            return false;
        }
        $st = $conn->prepare('SELECT id FROM pneus WHERE id = ? AND empresa_id = ? LIMIT 1');
        $st->execute([$pneu_id, $empresa_id]);
        return (bool) $st->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_validar_veiculo_empresa')) {
    function pneus_validar_veiculo_empresa(PDO $conn, ?int $veiculo_id, int $empresa_id): bool
    {
        if ($veiculo_id === null || $veiculo_id <= 0) {
            return true;
        }
        $st = $conn->prepare('SELECT id FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1');
        $st->execute([$veiculo_id, $empresa_id]);
        return (bool) $st->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('pneus_esta_alocado')) {
    function pneus_esta_alocado(PDO $conn, int $pneu_id): bool
    {
        $queries = [
            'SELECT 1 FROM instalacoes_pneus WHERE pneu_id = ? AND data_remocao IS NULL LIMIT 1',
            'SELECT 1 FROM alocacoes_pneus_flexiveis WHERE pneu_id = ? AND ativo = 1 LIMIT 1',
            "SELECT 1 FROM pneus_alocacao WHERE pneu_id = ? AND status = 'alocado' LIMIT 1",
            "SELECT 1 FROM eixo_pneus WHERE pneu_id = ? AND status = 'alocado' LIMIT 1",
        ];
        foreach ($queries as $sql) {
            try {
                $st = $conn->prepare($sql);
                $st->execute([$pneu_id]);
                if ($st->fetch(PDO::FETCH_ASSOC)) {
                    return true;
                }
            } catch (Throwable $e) {
                // tabela pode não existir em instalações antigas
            }
        }
        return false;
    }
}

if (!function_exists('pneus_sql_nao_alocado')) {
    /** Condição SQL: pneu não está em nenhum modelo de alocação ativo. */
    function pneus_sql_nao_alocado(string $pneuCol = 'p.id'): string
    {
        $p = preg_replace('/[^a-z0-9_.]/', '', $pneuCol) ?: 'p.id';
        return "NOT EXISTS (SELECT 1 FROM instalacoes_pneus ip WHERE ip.pneu_id = {$p} AND ip.data_remocao IS NULL)
            AND NOT EXISTS (SELECT 1 FROM alocacoes_pneus_flexiveis apf WHERE apf.pneu_id = {$p} AND apf.ativo = 1)
            AND NOT EXISTS (SELECT 1 FROM pneus_alocacao pa WHERE pa.pneu_id = {$p} AND pa.status = 'alocado')
            AND NOT EXISTS (SELECT 1 FROM eixo_pneus ep WHERE ep.pneu_id = {$p} AND ep.status = 'alocado')";
    }
}

if (!function_exists('pneus_status_ids_disponiveis')) {
    /** IDs de status elegíveis para estoque/disponibilidade (reserva, novo, usado). */
    function pneus_status_ids_disponiveis(PDO $conn): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        try {
            $st = $conn->query("SELECT id FROM status_pneus WHERE LOWER(TRIM(nome)) IN ('reserva', 'novo', 'usado')");
            $cache = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        } catch (Throwable $e) {
            $cache = [];
        }
        if ($cache === []) {
            $cache = [2, 5, 6];
        }
        return $cache;
    }
}

if (!function_exists('pneus_status_id_por_nome')) {
    function pneus_status_id_por_nome(PDO $conn, string $nome): ?int
    {
        $nome = strtolower(trim($nome));
        $st = $conn->prepare('SELECT id FROM status_pneus WHERE LOWER(TRIM(nome)) = ? LIMIT 1');
        $st->execute([$nome]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? (int) $row['id'] : null;
    }
}

if (!function_exists('pneus_status_id_manutencao')) {
    /** Resolve ID de status para pneu em manutenção/reparo (nomes variam entre instalações). */
    function pneus_status_id_manutencao(PDO $conn): ?int
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        foreach (['em manutenção', 'em manutencao', 'manutenção', 'manutencao', 'furado'] as $nome) {
            $id = pneus_status_id_por_nome($conn, $nome);
            if ($id !== null) {
                $cache = $id;
                return $cache;
            }
        }
        $cache = null;
        return null;
    }
}

if (!function_exists('pneus_ensure_estoque_row')) {
    function pneus_ensure_estoque_row(PDO $conn, int $pneu_id, int $status_id, int $disponivel = 1): void
    {
        $st = $conn->prepare('SELECT pneu_id FROM estoque_pneus WHERE pneu_id = ? LIMIT 1');
        $st->execute([$pneu_id]);
        if ($st->fetch(PDO::FETCH_ASSOC)) {
            return;
        }
        $ins = $conn->prepare('INSERT INTO estoque_pneus (pneu_id, status_id, disponivel, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
        $ins->execute([$pneu_id, $status_id, $disponivel]);
    }
}

if (!function_exists('pneus_sync_status_estoque')) {
    function pneus_sync_status_estoque(PDO $conn, int $pneu_id, int $empresa_id, int $status_id, int $disponivel): void
    {
        $st = $conn->prepare('UPDATE pneus SET status_id = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
        $st->execute([$status_id, $pneu_id, $empresa_id]);
        pneus_ensure_estoque_row($conn, $pneu_id, $status_id, $disponivel);
        $up = $conn->prepare('UPDATE estoque_pneus SET status_id = ?, disponivel = ?, updated_at = NOW() WHERE pneu_id = ?');
        $up->execute([$status_id, $disponivel, $pneu_id]);
    }
}

if (!function_exists('pneus_json_error')) {
    function pneus_json_error(string $logMsg, int $code = 500): void
    {
        error_log($logMsg);
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => 'Erro ao processar solicitação de pneus'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('pneus_column_exists')) {
    function pneus_column_exists(PDO $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        try {
            $st = $conn->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);
            $cache[$key] = (bool) $st->fetchColumn();
        } catch (Throwable $e) {
            $cache[$key] = false;
        }
        return $cache[$key];
    }
}

if (!function_exists('pneus_get_veiculo_km')) {
    /** KM atual do veículo (odômetro cadastrado). */
    function pneus_get_veiculo_km(PDO $conn, int $veiculo_id, int $empresa_id): ?int
    {
        if ($veiculo_id <= 0) {
            return null;
        }
        foreach (['km_atual', 'quilometragem', 'km'] as $col) {
            if (!pneus_column_exists($conn, 'veiculos', $col)) {
                continue;
            }
            $st = $conn->prepare("SELECT {$col} FROM veiculos WHERE id = ? AND empresa_id = ? LIMIT 1");
            $st->execute([$veiculo_id, $empresa_id]);
            $v = $st->fetchColumn();
            if ($v !== false && $v !== null && (int) $v > 0) {
                return (int) $v;
            }
        }
        return null;
    }
}

if (!function_exists('pneus_ensure_qr_token')) {
    function pneus_ensure_qr_token(PDO $conn, int $pneu_id, int $empresa_id): ?string
    {
        if (!pneus_column_exists($conn, 'pneus', 'qr_token')) {
            return null;
        }
        if (!pneus_validar_pneu_empresa($conn, $pneu_id, $empresa_id)) {
            return null;
        }
        $st = $conn->prepare('SELECT qr_token FROM pneus WHERE id = ? AND empresa_id = ? LIMIT 1');
        $st->execute([$pneu_id, $empresa_id]);
        $token = $st->fetchColumn();
        if (is_string($token) && $token !== '') {
            return $token;
        }
        do {
            $token = bin2hex(random_bytes(16));
            $chk = $conn->prepare('SELECT id FROM pneus WHERE qr_token = ? LIMIT 1');
            $chk->execute([$token]);
        } while ($chk->fetch(PDO::FETCH_ASSOC));
        $up = $conn->prepare('UPDATE pneus SET qr_token = ?, updated_at = NOW() WHERE id = ? AND empresa_id = ?');
        $up->execute([$token, $pneu_id, $empresa_id]);
        return $token;
    }
}

if (!function_exists('pneus_get_veiculo_alocado')) {
    /** Retorna veiculo_id se pneu estiver alocado (flexível ou legado). */
    function pneus_get_veiculo_alocado(PDO $conn, int $pneu_id, int $empresa_id): ?int
    {
        try {
            $st = $conn->prepare(
                'SELECT ev.veiculo_id FROM alocacoes_pneus_flexiveis apf
                 INNER JOIN eixos_veiculos ev ON ev.id = apf.eixo_veiculo_id
                 WHERE apf.pneu_id = ? AND apf.empresa_id = ? AND apf.ativo = 1 LIMIT 1'
            );
            $st->execute([$pneu_id, $empresa_id]);
            $v = $st->fetchColumn();
            if ($v) {
                return (int) $v;
            }
        } catch (Throwable $e) {
        }
        try {
            $st = $conn->prepare(
                'SELECT veiculo_id FROM instalacoes_pneus
                 WHERE pneu_id = ? AND data_remocao IS NULL LIMIT 1'
            );
            $st->execute([$pneu_id]);
            $v = $st->fetchColumn();
            if ($v) {
                return (int) $v;
            }
        } catch (Throwable $e) {
        }
        return null;
    }
}
