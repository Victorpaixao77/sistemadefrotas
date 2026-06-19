<?php
/**
 * "Lembrar-me" seguro (padrão selector:validator).
 *
 * Substitui o cookie antigo base64("id:email"), que era forjável e permitia login
 * sem senha. Agora o cookie guarda dois segredos aleatórios; o banco guarda só o
 * selector e o HASH do validator. A validação usa hash_equals (tempo constante) e
 * o validator é rotacionado a cada uso.
 *
 * Requer a tabela auth_remember_tokens (ver sql/auth_remember_tokens.sql).
 */

if (!defined('SF_REMEMBER_COOKIE')) {
    define('SF_REMEMBER_COOKIE', 'remember_user');
}
if (!defined('SF_REMEMBER_TTL_DAYS')) {
    define('SF_REMEMBER_TTL_DAYS', 30);
}

if (!function_exists('sf_remember_cookie_secure')) {
    function sf_remember_cookie_secure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    }
}

if (!function_exists('sf_remember_set_cookie')) {
    function sf_remember_set_cookie(string $value, int $expires): void
    {
        setcookie(SF_REMEMBER_COOKIE, $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => sf_remember_cookie_secure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Emite um novo token para o usuário e grava o cookie. Chamar após autenticação OK.
 */
if (!function_exists('sf_remember_issue')) {
    function sf_remember_issue(PDO $conn, int $usuarioId): void
    {
        try {
            $selector = bin2hex(random_bytes(12));   // 24 hex
            $validator = bin2hex(random_bytes(32));   // 64 hex
            $validatorHash = hash('sha256', $validator);
            $expiraEm = date('Y-m-d H:i:s', time() + SF_REMEMBER_TTL_DAYS * 86400);

            $stmt = $conn->prepare(
                'INSERT INTO auth_remember_tokens (usuario_id, selector, validator_hash, expira_em)
                 VALUES (:usuario_id, :selector, :validator_hash, :expira_em)'
            );
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':selector' => $selector,
                ':validator_hash' => $validatorHash,
                ':expira_em' => $expiraEm,
            ]);

            sf_remember_set_cookie($selector . ':' . $validator, time() + SF_REMEMBER_TTL_DAYS * 86400);
        } catch (Throwable $e) {
            error_log('sf_remember_issue: ' . $e->getMessage());
        }
    }
}

/**
 * Valida o cookie. Retorna o usuario_id (int) se válido, ou null caso contrário.
 * Em caso de sucesso, rotaciona o validator (renova o cookie). Em qualquer falha,
 * apaga o token correspondente e limpa o cookie.
 */
if (!function_exists('sf_remember_validate')) {
    function sf_remember_validate(PDO $conn): ?int
    {
        if (empty($_COOKIE[SF_REMEMBER_COOKIE])) {
            return null;
        }

        $raw = (string) $_COOKIE[SF_REMEMBER_COOKIE];
        $parts = explode(':', $raw, 2);
        if (count($parts) !== 2 || strlen($parts[0]) !== 24 || strlen($parts[1]) !== 64) {
            sf_remember_clear($conn);
            return null;
        }
        [$selector, $validator] = $parts;

        try {
            $stmt = $conn->prepare(
                'SELECT id, usuario_id, validator_hash, expira_em
                 FROM auth_remember_tokens WHERE selector = :selector LIMIT 1'
            );
            $stmt->execute([':selector' => $selector]);
            $token = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$token) {
                sf_remember_clear($conn);
                return null;
            }

            // Expirado: remove e rejeita.
            if (strtotime($token['expira_em']) < time()) {
                $del = $conn->prepare('DELETE FROM auth_remember_tokens WHERE id = :id');
                $del->execute([':id' => $token['id']]);
                sf_remember_clear($conn);
                return null;
            }

            // Comparação em tempo constante.
            if (!hash_equals($token['validator_hash'], hash('sha256', $validator))) {
                // Validator errado para um selector válido = possível roubo: revoga tudo do usuário.
                $del = $conn->prepare('DELETE FROM auth_remember_tokens WHERE usuario_id = :uid');
                $del->execute([':uid' => $token['usuario_id']]);
                sf_remember_clear($conn);
                return null;
            }

            // OK — rotaciona o validator e renova o cookie.
            $newValidator = bin2hex(random_bytes(32));
            $newExpira = date('Y-m-d H:i:s', time() + SF_REMEMBER_TTL_DAYS * 86400);
            $upd = $conn->prepare(
                'UPDATE auth_remember_tokens
                 SET validator_hash = :vh, expira_em = :exp WHERE id = :id'
            );
            $upd->execute([
                ':vh' => hash('sha256', $newValidator),
                ':exp' => $newExpira,
                ':id' => $token['id'],
            ]);
            sf_remember_set_cookie($selector . ':' . $newValidator, time() + SF_REMEMBER_TTL_DAYS * 86400);

            return (int) $token['usuario_id'];
        } catch (Throwable $e) {
            error_log('sf_remember_validate: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * Remove o token atual (pelo cookie) e/ou todos os tokens do usuário, e limpa o cookie.
 */
if (!function_exists('sf_remember_clear')) {
    function sf_remember_clear(?PDO $conn = null, ?int $usuarioId = null): void
    {
        try {
            if ($conn instanceof PDO) {
                if ($usuarioId !== null) {
                    $stmt = $conn->prepare('DELETE FROM auth_remember_tokens WHERE usuario_id = :uid');
                    $stmt->execute([':uid' => $usuarioId]);
                } elseif (!empty($_COOKIE[SF_REMEMBER_COOKIE])) {
                    $selector = explode(':', (string) $_COOKIE[SF_REMEMBER_COOKIE], 2)[0];
                    if ($selector !== '') {
                        $stmt = $conn->prepare('DELETE FROM auth_remember_tokens WHERE selector = :selector');
                        $stmt->execute([':selector' => $selector]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('sf_remember_clear: ' . $e->getMessage());
        }

        if (isset($_COOKIE[SF_REMEMBER_COOKIE])) {
            sf_remember_set_cookie('', time() - 3600);
            unset($_COOKIE[SF_REMEMBER_COOKIE]);
        }
    }
}
