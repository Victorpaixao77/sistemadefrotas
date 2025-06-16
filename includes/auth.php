<?php
// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário tem permissão para acessar uma determinada funcionalidade
 * @param string $permission Nome da permissão
 * @return bool
 */
function has_permission($permission) {
    if (!isset($_SESSION['permissions'])) {
        return false;
    }
    
    return in_array($permission, $_SESSION['permissions']);
}

/**
 * Verifica se o usuário tem permissão e redireciona se não tiver
 * @param string $permission Nome da permissão
 */
function require_permission($permission) {
    if (!has_permission($permission)) {
        // Se for uma requisição AJAX, retorna erro em JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Permissão negada'
            ]);
            exit;
        }
        
        // Se for uma requisição normal, redireciona para a página de acesso negado
        header('Location: /sistema-frotas/access-denied.php');
        exit;
    }
} 