<?php
/**
 * API das políticas de segurança da empresa (configuracoes_seguranca) e da
 * whitelist de IP de administradores (admin_ip_whitelist).
 *
 * Ações (GET action=...): get, ip_list
 * Ações (POST action=...): save, ip_add, ip_delete
 */
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/csrf.php';
require_once '../includes/api_json.php';
require_once '../includes/security.php';

configure_session();
session_start();

header('Content-Type: application/json');
require_authentication();
require_permission('manage_system_settings');

api_require_csrf_json();

$empresa_id = (int) $_SESSION['empresa_id'];
$conn = getConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get':
            $cfg = sf_security_config($empresa_id);
            // Não expor segredos completos por rede (senha SMTP / secret reCAPTCHA).
            $cfg['smtp_pass'] = !empty($cfg['smtp_pass']) ? '********' : '';
            echo json_encode(['success' => true, 'data' => $cfg]);
            break;

        case 'save':
            $b = $_POST;
            // Atualização PARCIAL: cada card envia só os campos que gerencia (em _fields),
            // permitindo dashboards independentes sem zerar os demais.
            $fields = array_filter(array_map('trim', explode(',', (string) ($b['_fields'] ?? ''))));
            if (!$fields) {
                api_json_error('Nada para salvar.', 422);
            }

            // Colunas permitidas e seus tipos (whitelist — nomes vão direto no SQL).
            $tipos = [
                'idle_timeout_min' => 'idle',
                'login_max_tentativas' => 'int:3:20:5',
                'login_bloqueio_min' => 'int:1:1440:15',
                'senha_forte' => 'bool',
                'two_factor_obrigatorio' => 'bool',
                'novo_acesso_email' => 'bool',
                'smtp_host' => 'str',
                'smtp_port' => 'intnull',
                'smtp_user' => 'str',
                'smtp_pass' => 'secret',
                'smtp_secure' => 'enum:,ssl,tls',
                'smtp_from' => 'str',
                'ip_whitelist_enabled' => 'bool',
                'geo_block_enabled' => 'bool',
                'geo_paises_permitidos' => 'paises',
            ];

            $atual = sf_security_config($empresa_id);
            $sets = [];
            $params = [':empresa_id' => $empresa_id];
            $idleNovo = null;

            foreach ($fields as $f) {
                if (!isset($tipos[$f])) {
                    continue;
                }
                $t = $tipos[$f];
                $ph = ':' . $f;
                if ($t === 'bool') {
                    $val = (isset($b[$f]) && in_array((string) $b[$f], ['1', 'true', 'on'], true)) ? 1 : 0;
                } elseif ($t === 'idle') {
                    $val = (int) ($b[$f] ?? 0);
                    if (!in_array($val, [0, 15, 30, 60, 120], true)) {
                        $val = 0;
                    }
                    $idleNovo = $val;
                } elseif (strncmp($t, 'int:', 4) === 0) {
                    [, $min, $max, $def] = explode(':', $t);
                    $val = isset($b[$f]) ? (int) $b[$f] : (int) $def;
                    $val = max((int) $min, min((int) $max, $val));
                } elseif ($t === 'intnull') {
                    $val = (isset($b[$f]) && trim((string) $b[$f]) !== '') ? (int) $b[$f] : null;
                } elseif ($t === 'secret') {
                    $val = trim((string) ($b[$f] ?? ''));
                    if ($val === '' || $val === '********') {
                        $val = (string) ($atual[$f] ?? '');
                    }
                } elseif (strncmp($t, 'enum:', 5) === 0) {
                    $allowed = explode(',', substr($t, 5));
                    $val = trim((string) ($b[$f] ?? ''));
                    if (!in_array($val, $allowed, true)) {
                        $val = $allowed[0];
                    }
                } elseif ($t === 'paises') {
                    $val = trim((string) ($b[$f] ?? '')) ?: 'BR';
                } else { // str
                    $val = trim((string) ($b[$f] ?? ''));
                }
                $sets[] = "$f = $ph";
                $params[$ph] = $val;
            }

            if (!$sets) {
                api_json_error('Nenhum campo válido para salvar.', 422);
            }

            $sql = 'UPDATE configuracoes_seguranca SET ' . implode(', ', $sets) . ' WHERE empresa_id = :empresa_id';
            $conn->prepare($sql)->execute($params);

            if ($idleNovo !== null) {
                $_SESSION['idle_timeout_min'] = $idleNovo;
            }
            echo json_encode(['success' => true]);
            break;

        case 'ip_list':
            $stmt = $conn->prepare('SELECT id, ip, descricao, criado_em FROM admin_ip_whitelist WHERE empresa_id = :e ORDER BY criado_em DESC');
            $stmt->execute([':e' => $empresa_id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'ip_add':
            $ip = trim((string) ($_POST['ip'] ?? ''));
            $desc = trim((string) ($_POST['descricao'] ?? ''));
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                api_json_error('IP inválido.', 422);
            }
            $stmt = $conn->prepare('INSERT INTO admin_ip_whitelist (empresa_id, ip, descricao) VALUES (:e, :ip, :d)');
            $stmt->execute([':e' => $empresa_id, ':ip' => $ip, ':d' => ($desc !== '' ? $desc : null)]);
            echo json_encode(['success' => true, 'id' => $conn->lastInsertId()]);
            break;

        case 'ip_delete':
            $id = (int) ($_POST['id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM admin_ip_whitelist WHERE id = :id AND empresa_id = :e');
            $stmt->execute([':id' => $id, ':e' => $empresa_id]);
            echo json_encode(['success' => true]);
            break;

        default:
            api_json_error('Ação inválida.', 400);
    }
} catch (Throwable $e) {
    error_log('api/seguranca.php: ' . $e->getMessage());
    api_json_error('Erro ao processar a solicitação.', 500);
}
