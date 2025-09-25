<?php
/**
 * Sistema de Permissões do Sistema de Frotas
 * Controla acesso baseado em tipo de usuário
 */

// Iniciar sessão se ainda não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário atual é admin
 * @return bool
 */
function is_admin() {
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'motorista';
    $is_admin = $_SESSION['is_admin'] ?? 0;
    
    return ($tipo_usuario === 'admin' || $is_admin == 1);
}

/**
 * Verifica se o usuário atual é gestor
 * @return bool
 */
function is_gestor() {
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'motorista';
    
    return ($tipo_usuario === 'gestor');
}

/**
 * Verifica se o usuário atual é motorista
 * @return bool
 */
function is_motorista() {
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'motorista';
    
    return ($tipo_usuario === 'motorista');
}

/**
 * Obtém permissões granulares do usuário atual do banco de dados
 * @return array
 */
function get_user_granular_permissions() {
    if (!isset($_SESSION['id'])) {
        return [];
    }
    
    try {
        require_once 'config.php';
        $conn = getConnection();
        
        $stmt = $conn->prepare('
            SELECT pode_editar_usuarios_sistema, pode_criar_usuarios_sistema, pode_acessar_lucratividade,
                   pode_acessar_relatorios_avancados, pode_gerenciar_configuracoes, pode_aprovar_abastecimentos,
                   pode_ver_dados_financeiros, pode_acessar_sistema_fiscal, pode_acessar_gestao_pneus, 
                   tipo_usuario, is_admin
            FROM usuarios 
            WHERE id = :user_id
        ');
        $stmt->bindParam(':user_id', $_SESSION['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $permissions = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$permissions) {
            // Fallback para permissões padrão baseadas no tipo
            return get_default_permissions();
        }
        
        return $permissions;
        
    } catch (Exception $e) {
        error_log("Erro ao obter permissões granulares: " . $e->getMessage());
        return get_default_permissions();
    }
}

/**
 * Obtém permissões padrão baseadas no tipo de usuário
 * @return array
 */
function get_default_permissions() {
    $tipo_usuario = $_SESSION['tipo_usuario'] ?? 'motorista';
    $is_admin = $_SESSION['is_admin'] ?? 0;
    
    if ($tipo_usuario === 'admin' || $is_admin == 1) {
        return [
            'pode_editar_usuarios_sistema' => 1,
            'pode_criar_usuarios_sistema' => 1,
            'pode_acessar_lucratividade' => 1,
            'pode_acessar_relatorios_avancados' => 1,
            'pode_gerenciar_configuracoes' => 1,
            'pode_aprovar_abastecimentos' => 1,
            'pode_ver_dados_financeiros' => 1,
            'pode_acessar_sistema_fiscal' => 1,
            'pode_acessar_gestao_pneus' => 1
        ];
    } elseif ($tipo_usuario === 'gestor') {
        return [
            'pode_editar_usuarios_sistema' => 0,
            'pode_criar_usuarios_sistema' => 0,
            'pode_acessar_lucratividade' => 0,
            'pode_acessar_relatorios_avancados' => 0,
            'pode_gerenciar_configuracoes' => 0,
            'pode_aprovar_abastecimentos' => 1,
            'pode_ver_dados_financeiros' => 0,
            'pode_acessar_sistema_fiscal' => 0,
            'pode_acessar_gestao_pneus' => 0
        ];
    } else {
        return [
            'pode_editar_usuarios_sistema' => 0,
            'pode_criar_usuarios_sistema' => 0,
            'pode_acessar_lucratividade' => 0,
            'pode_acessar_relatorios_avancados' => 0,
            'pode_gerenciar_configuracoes' => 0,
            'pode_aprovar_abastecimentos' => 0,
            'pode_ver_dados_financeiros' => 0,
            'pode_acessar_sistema_fiscal' => 0,
            'pode_acessar_gestao_pneus' => 0
        ];
    }
}

/**
 * Verifica se o usuário pode editar usuários do sistema
 * @return bool
 */
function can_edit_system_users() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_editar_usuarios_sistema']) ? (bool)$permissions['pode_editar_usuarios_sistema'] : is_admin();
}

/**
 * Verifica se o usuário pode criar usuários do sistema
 * @return bool
 */
function can_create_system_users() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_criar_usuarios_sistema']) ? (bool)$permissions['pode_criar_usuarios_sistema'] : is_admin();
}

/**
 * Verifica se o usuário pode editar motoristas
 * @return bool
 */
function can_edit_motoristas() {
    return is_admin() || is_gestor();
}

/**
 * Verifica se o usuário pode criar motoristas
 * @return bool
 */
function can_create_motoristas() {
    return is_admin() || is_gestor();
}

/**
 * Verifica se o usuário pode acessar a página de lucratividade
 * @return bool
 */
function can_access_lucratividade() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_acessar_lucratividade']) ? (bool)$permissions['pode_acessar_lucratividade'] : is_admin();
}

/**
 * Verifica se o usuário pode acessar relatórios avançados
 * @return bool
 */
function can_access_advanced_reports() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_acessar_relatorios_avancados']) ? (bool)$permissions['pode_acessar_relatorios_avancados'] : is_admin();
}

/**
 * Verifica se o usuário pode gerenciar configurações do sistema
 * @return bool
 */
function can_manage_system_settings() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_gerenciar_configuracoes']) ? (bool)$permissions['pode_gerenciar_configuracoes'] : is_admin();
}

/**
 * Verifica se o usuário pode aprovar abastecimentos
 * @return bool
 */
function can_approve_refuels() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_aprovar_abastecimentos']) ? (bool)$permissions['pode_aprovar_abastecimentos'] : (is_admin() || is_gestor());
}

/**
 * Verifica se o usuário pode visualizar dados financeiros detalhados
 * @return bool
 */
function can_view_financial_data() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_ver_dados_financeiros']) ? (bool)$permissions['pode_ver_dados_financeiros'] : is_admin();
}

/**
 * Verifica se o usuário pode acessar o sistema fiscal
 * @return bool
 */
function can_access_fiscal_system() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_acessar_sistema_fiscal']) ? (bool)$permissions['pode_acessar_sistema_fiscal'] : is_admin();
}

/**
 * Verifica se o usuário pode acessar a gestão de pneus
 * @return bool
 */
function can_access_tire_management() {
    $permissions = get_user_granular_permissions();
    return isset($permissions['pode_acessar_gestao_pneus']) ? (bool)$permissions['pode_acessar_gestao_pneus'] : is_admin();
}


/**
 * Verifica permissão e redireciona se não tiver acesso
 * @param string $permission Nome da permissão
 * @param string $redirect_url URL para redirecionar em caso de negação
 */
function require_permission($permission, $redirect_url = '../index.php?error=access_denied') {
    $has_permission = false;
    
    switch ($permission) {
        case 'edit_system_users':
            $has_permission = can_edit_system_users();
            break;
        case 'create_system_users':
            $has_permission = can_create_system_users();
            break;
        case 'edit_motoristas':
            $has_permission = can_edit_motoristas();
            break;
        case 'create_motoristas':
            $has_permission = can_create_motoristas();
            break;
        case 'access_lucratividade':
            $has_permission = can_access_lucratividade();
            break;
        case 'access_advanced_reports':
            $has_permission = can_access_advanced_reports();
            break;
        case 'manage_system_settings':
            $has_permission = can_manage_system_settings();
            break;
        case 'approve_refuels':
            $has_permission = can_approve_refuels();
            break;
        default:
            $has_permission = false;
    }
    
    if (!$has_permission) {
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
        
        // Se for uma requisição normal, redireciona
        header('Location: ' . $redirect_url);
        exit;
    }
}

/**
 * Retorna o nível de permissão do usuário atual
 * @return string
 */
function get_user_permission_level() {
    if (is_admin()) {
        return 'admin';
    } elseif (is_gestor()) {
        return 'gestor';
    } else {
        return 'motorista';
    }
}

/**
 * Retorna array com todas as permissões do usuário atual
 * @return array
 */
function get_user_permissions() {
    return [
        'is_admin' => is_admin(),
        'is_gestor' => is_gestor(),
        'is_motorista' => is_motorista(),
        'can_edit_system_users' => can_edit_system_users(),
        'can_create_system_users' => can_create_system_users(),
        'can_edit_motoristas' => can_edit_motoristas(),
        'can_create_motoristas' => can_create_motoristas(),
        'can_access_lucratividade' => can_access_lucratividade(),
        'can_access_advanced_reports' => can_access_advanced_reports(),
        'can_manage_system_settings' => can_manage_system_settings(),
        'can_approve_refuels' => can_approve_refuels(),
        'can_view_financial_data' => can_view_financial_data(),
        'permission_level' => get_user_permission_level()
    ];
}
