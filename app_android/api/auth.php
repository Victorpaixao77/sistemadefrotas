<?php
/**
 * API de autenticação - App Android Motoristas
 * POST login: nome, senha -> token (acesso), refresh_token, motorista_id, empresa_id, nome
 * POST action=refresh: refresh_token -> novo access; refresh mantém-se (BD migrada) ou rotação legada
 * GET me: Bearer access token
 * POST logout: Bearer access token -> remove sessão
 */

require_once __DIR__ . '/../config.php';

function api_access_token_ttl(): string
{
    return getenv('SF_API_ACCESS_TTL') ?: '+7 days';
}

function api_refresh_token_ttl(): string
{
    return getenv('SF_API_REFRESH_TTL') ?: '+90 days';
}

function api_tokens_has_refresh_columns(PDO $conn): bool
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    try {
        $st = $conn->query("SHOW COLUMNS FROM api_tokens_motorista LIKE 'refresh_token'");
        $cache = $st && $st->rowCount() > 0;
    } catch (PDOException $e) {
        $cache = false;
    }

    return $cache;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'POST') {
    $action = $_POST['action'] ?? '';
    $input = $_POST;
    // php://input só pode ser lido UMA vez: duas chamadas a file_get_contents esvaziam o corpo e o JSON do app (login) sumia.
    $rawBody = file_get_contents('php://input');
    if ($rawBody !== false && $rawBody !== '') {
        $decoded = json_decode($rawBody, true);
        if (is_array($decoded)) {
            $input = array_merge($_POST, $decoded);
            if (empty($action)) {
                $action = $input['action'] ?? $input['login'] ?? '';
            }
        }
    }

    if ($action === 'refresh') {
        $rt = trim($input['refresh_token'] ?? '');
        if (empty($rt) || !preg_match('/^[a-f0-9]{64}$/i', $rt)) {
            api_error('Refresh token não informado.', 400);
        }
        try {
            $conn = getConnection();
            $row = null;
            if (api_tokens_has_refresh_columns($conn)) {
                $stmt = $conn->prepare('
                    SELECT id, motorista_id, empresa_id
                    FROM api_tokens_motorista
                    WHERE refresh_token = :rt AND refresh_expira_em IS NOT NULL AND refresh_expira_em > NOW()
                    LIMIT 1
                ');
                $stmt->execute([':rt' => $rt]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $conn->prepare('
                    SELECT id, motorista_id, empresa_id
                    FROM api_tokens_motorista
                    WHERE token = :rt AND expira_em > NOW()
                    LIMIT 1
                ');
                $stmt->execute([':rt' => $rt]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if (!$row) {
                api_error('Refresh token inválido ou expirado.', 401);
            }

            $st2 = $conn->prepare('
                SELECT m.nome AS nome_motorista
                FROM motoristas m
                WHERE m.id = :mid AND m.empresa_id = :eid
                LIMIT 1
            ');
            $st2->execute([':mid' => $row['motorista_id'], ':eid' => $row['empresa_id']]);
            $u = $st2->fetch(PDO::FETCH_ASSOC);

            $new_access = bin2hex(random_bytes(32));
            $expira_access = date('Y-m-d H:i:s', strtotime(api_access_token_ttl()));

            if (api_tokens_has_refresh_columns($conn)) {
                $stmt = $conn->prepare('
                    UPDATE api_tokens_motorista
                    SET token = :token, expira_em = :expira_em
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':token' => $new_access,
                    ':expira_em' => $expira_access,
                    ':id' => $row['id'],
                ]);
                $refreshOut = $rt;
            } else {
                $expira_legacy = date('Y-m-d H:i:s', strtotime(api_refresh_token_ttl()));
                $stmt = $conn->prepare('
                    UPDATE api_tokens_motorista
                    SET token = :token, expira_em = :expira_em
                    WHERE id = :id
                ');
                $stmt->execute([
                    ':token' => $new_access,
                    ':expira_em' => $expira_legacy,
                    ':id' => $row['id'],
                ]);
                $refreshOut = $new_access;
            }

            api_success([
                'token' => $new_access,
                'refresh_token' => $refreshOut,
                'motorista_id' => (int) $row['motorista_id'],
                'empresa_id' => (int) $row['empresa_id'],
                'nome' => $u['nome_motorista'] ?? '',
                'expira_em' => $expira_access,
            ], 'Token renovado.');
        } catch (PDOException $e) {
            error_log('API refresh: ' . $e->getMessage());
            api_error('Erro ao renovar token.', 500);
        }
    }

    if ($action === 'logout') {
        require_motorista_token();
        $token = get_bearer_token();
        try {
            $conn = getConnection();
            $stmt = $conn->prepare('DELETE FROM api_tokens_motorista WHERE token = :token');
            $stmt->execute([':token' => $token]);
            api_success(null, 'Logout realizado.');
        } catch (PDOException $e) {
            api_error('Erro ao encerrar sessão.', 500);
        }
    }

    if ($action === 'login' || empty($action)) {
        $nome = trim($input['nome'] ?? $input['usuario'] ?? '');
        $senha = $input['senha'] ?? $input['password'] ?? '';
        if (empty($nome) || empty($senha)) {
            api_error('E-mail e senha são obrigatórios.', 400);
        }
        try {
            $conn = getConnection();
            $login = strtolower(trim($nome));
            $stmt = $conn->prepare('
                SELECT um.id, um.nome, um.empresa_id, um.motorista_id, um.status, um.senha,
                       m.nome AS nome_motorista
                FROM usuarios_motoristas um
                INNER JOIN motoristas m ON m.id = um.motorista_id AND m.empresa_id = um.empresa_id
                WHERE um.status = "ativo"
                  AND (
                    LOWER(TRIM(um.nome)) = :login
                    OR LOWER(TRIM(COALESCE(m.email, ""))) = :login2
                  )
            ');
            $stmt->execute([':login' => $login, ':login2' => $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !password_verify($senha, $user['senha'])) {
                api_error('Usuário ou senha inválidos.', 401);
            }
            $motorista_id = (int) $user['motorista_id'];
            $empresa_id = (int) $user['empresa_id'];
            $access = bin2hex(random_bytes(32));
            $refresh = bin2hex(random_bytes(32));
            $expira_access = date('Y-m-d H:i:s', strtotime(api_access_token_ttl()));
            $expira_refresh = date('Y-m-d H:i:s', strtotime(api_refresh_token_ttl()));

            $payload = [
                'token' => $access,
                'refresh_token' => $refresh,
                'motorista_id' => $motorista_id,
                'empresa_id' => $empresa_id,
                'nome' => !empty($user['nome_motorista']) ? $user['nome_motorista'] : $user['nome'],
                'expira_em' => $expira_access,
            ];

            try {
                $stmt = $conn->prepare('
                    INSERT INTO api_tokens_motorista (motorista_id, empresa_id, token, refresh_token, expira_em, refresh_expira_em)
                    VALUES (:motorista_id, :empresa_id, :token, :refresh_token, :expira_em, :refresh_expira_em)
                ');
                $stmt->execute([
                    ':motorista_id' => $motorista_id,
                    ':empresa_id' => $empresa_id,
                    ':token' => $access,
                    ':refresh_token' => $refresh,
                    ':expira_em' => $expira_access,
                    ':refresh_expira_em' => $expira_refresh,
                ]);
                $payload['refresh_expira_em'] = $expira_refresh;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Unknown column') !== false) {
                    $stmt = $conn->prepare('
                        INSERT INTO api_tokens_motorista (motorista_id, empresa_id, token, expira_em)
                        VALUES (:motorista_id, :empresa_id, :token, :expira_em)
                    ');
                    $stmt->execute([
                        ':motorista_id' => $motorista_id,
                        ':empresa_id' => $empresa_id,
                        ':token' => $access,
                        ':expira_em' => $expira_refresh,
                    ]);
                    $payload['refresh_token'] = $access;
                    $payload['expira_em'] = $expira_refresh;
                } else {
                    throw $e;
                }
            }

            api_success($payload, 'Login realizado com sucesso.');
        } catch (PDOException $e) {
            error_log('API login: ' . $e->getMessage());
            api_error('Erro ao fazer login.', 500);
        }
    }
    api_error('Ação inválida.', 400);
}

if ($method === 'GET') {
    require_motorista_token();
    $motorista_id = get_motorista_id();
    $empresa_id = get_empresa_id();
    try {
        $conn = getConnection();
        $stmt = $conn->prepare('SELECT m.id, m.nome FROM motoristas m WHERE m.id = :id AND m.empresa_id = :empresa_id');
        $stmt->execute([':id' => $motorista_id, ':empresa_id' => $empresa_id]);
        $m = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$m) {
            api_error('Motorista não encontrado.', 404);
        }
        $porcentagem = null;
        try {
            $st2 = $conn->prepare('SELECT porcentagem_comissao FROM motoristas WHERE id = :id AND empresa_id = :empresa_id');
            $st2->execute([':id' => $motorista_id, ':empresa_id' => $empresa_id]);
            $row = $st2->fetch(PDO::FETCH_ASSOC);
            if (!empty($row['porcentagem_comissao'])) {
                $porcentagem = (float) $row['porcentagem_comissao'];
            }
        } catch (Throwable $e) {
            // Coluna pode não existir
        }
        api_success([
            'motorista_id' => (int) $m['id'],
            'empresa_id' => $empresa_id,
            'nome' => $m['nome'],
            'porcentagem_comissao' => $porcentagem,
        ]);
    } catch (PDOException $e) {
        api_error('Erro ao obter dados.', 500);
    }
}

api_error('Método não permitido.', 405);
