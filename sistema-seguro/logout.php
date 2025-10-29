<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Registrar log de logout antes de destruir a sessão
if (isset($_SESSION['seguro_logado']) && $_SESSION['seguro_logado'] === true) {
    try {
        $db = getDB();
        $empresa_id = obterEmpresaId();
        $usuario_id = obterUsuarioId();
        
        if ($empresa_id && $usuario_id) {
            registrarLog($empresa_id, $usuario_id, 'logout', 'autenticacao', 'Logout realizado');
        }
    } catch (Exception $e) {
        error_log("Erro ao registrar logout: " . $e->getMessage());
    }
}

// Limpar todas as variáveis de sessão do Sistema Seguro
unset($_SESSION['seguro_logado']);
unset($_SESSION['seguro_usuario_id']);
unset($_SESSION['seguro_usuario_nome']);
unset($_SESSION['seguro_usuario_email']);
unset($_SESSION['seguro_usuario_nivel']);
unset($_SESSION['seguro_empresa_id']);
unset($_SESSION['seguro_empresa_nome']);
unset($_SESSION['seguro_empresa_fantasia']);
unset($_SESSION['empresa_adm_id']);

// Redirecionar para o login
header('Location: login.php');
exit;
?>

