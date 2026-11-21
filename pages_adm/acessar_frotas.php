<?php
/**
 * Ponte ADM → sistema de frotas: prepara sessão e abre seleção de empresa.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/adm_auth.php';
require_once SF_PROJECT_ROOT . '/includes/sf_paths.php';
require_once SF_PROJECT_ROOT . '/includes/functions.php';

$dest = sf_app_url('selecionar_empresa.php');

if (!empty($_SESSION['loggedin']) && !empty($_SESSION['acesso_todas_empresas'])) {
    unset($_SESSION['empresa_id'], $_SESSION['empresa_nome']);
    header('Location: ' . $dest);
    exit;
}

$email = trim((string) ($_SESSION['admin_email'] ?? ''));
$user = null;

if ($email !== '') {
    $stmt = $pdo->prepare(
        "SELECT id, nome, email, tipo_usuario, foto_perfil
         FROM usuarios
         WHERE email = ? AND status = 'ativo'
         LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$_SESSION['loggedin'] = true;
$_SESSION['acesso_todas_empresas'] = true;
unset($_SESSION['empresa_id'], $_SESSION['empresa_nome']);

if ($user) {
    $uid = (int) $user['id'];
    $_SESSION['usuario_id'] = $uid;
    $_SESSION['id'] = $uid;
    $_SESSION['nome'] = $user['nome'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
    $_SESSION['foto_perfil'] = $user['foto_perfil'] ?? null;
    registrarLogAcesso($uid, 0, 'login', 'sucesso', 'Acesso ao sistema de frotas via painel ADM');
} else {
    $_SESSION['usuario_id'] = 0;
    $_SESSION['id'] = 0;
    $_SESSION['nome'] = (string) ($_SESSION['admin_nome'] ?? 'Administrador');
    $_SESSION['email'] = $email;
    $_SESSION['tipo_usuario'] = 'admin';
    $_SESSION['foto_perfil'] = null;
}

header('Location: ' . $dest);
exit;
