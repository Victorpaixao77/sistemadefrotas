<?php
/**
 * SISTEMA SEGURO - Autenticação e Sessão
 * 
 * Gerencia autenticação e controle de sessão dos usuários
 */

// Iniciar sessão se ainda não foi iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o usuário está logado no Sistema Seguro
 */
function verificarLogin() {
    if (!isset($_SESSION['seguro_logado']) || $_SESSION['seguro_logado'] !== true) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Verifica se a empresa tem acesso ao Sistema Seguro (Plano Premium)
 */
function verificarAcessoPremium($empresa_adm_id) {
    require_once 'database.php';
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT tem_acesso_seguro, plano 
            FROM empresa_adm 
            WHERE id = ? AND status = 'ativo'
        ");
        $stmt->execute([$empresa_adm_id]);
        $empresa = $stmt->fetch();
        
        if (!$empresa) {
            return false;
        }
        
        // Verifica se tem plano Premium ou superior e acesso liberado
        $planosPermitidos = ['premium', 'enterprise'];
        $temAcesso = in_array(strtolower($empresa['plano']), $planosPermitidos);
        
        // Se existe o campo tem_acesso_seguro, verificar também
        if (isset($empresa['tem_acesso_seguro'])) {
            $temAcesso = $temAcesso && ($empresa['tem_acesso_seguro'] === 'sim');
        }
        
        return $temAcesso;
        
    } catch(PDOException $e) {
        error_log("Erro ao verificar acesso premium: " . $e->getMessage());
        return false;
    }
}

/**
 * Realiza login no Sistema Seguro
 */
function fazerLogin($email, $senha) {
    require_once 'database.php';
    $db = getDB();
    
    try {
        $stmt = $db->prepare("
            SELECT su.*, sec.razao_social, sec.empresa_adm_id 
            FROM seguro_usuarios su
            INNER JOIN seguro_empresa_clientes sec ON su.seguro_empresa_id = sec.id
            WHERE su.email = ? AND su.status = 'ativo' AND sec.status = 'ativo'
        ");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch();
        
        if ($usuario && password_verify($senha, $usuario['senha'])) {
            // Verificar se a empresa tem acesso premium
            if (!verificarAcessoPremium($usuario['empresa_adm_id'])) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Sua empresa não possui plano Premium ativo para acessar o Sistema Seguro.'
                ];
            }
            
            // Criar sessão
            $_SESSION['seguro_logado'] = true;
            $_SESSION['seguro_usuario_id'] = $usuario['id'];
            $_SESSION['seguro_usuario_nome'] = $usuario['nome'];
            $_SESSION['seguro_usuario_email'] = $usuario['email'];
            $_SESSION['seguro_usuario_nivel'] = $usuario['nivel_acesso'];
            $_SESSION['seguro_empresa_id'] = $usuario['seguro_empresa_id'];
            $_SESSION['seguro_empresa_nome'] = $usuario['razao_social'];
            $_SESSION['empresa_adm_id'] = $usuario['empresa_adm_id'];
            
            // Atualizar último acesso
            $stmtUpdate = $db->prepare("
                UPDATE seguro_usuarios 
                SET ultimo_acesso = NOW() 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$usuario['id']]);
            
            // Registrar log
            registrarLog($usuario['seguro_empresa_id'], $usuario['id'], 'login', 'autenticacao', 'Login realizado com sucesso');
            
            return [
                'sucesso' => true,
                'usuario' => $usuario
            ];
        }
        
        return [
            'sucesso' => false,
            'mensagem' => 'E-mail ou senha incorretos.'
        ];
        
    } catch(PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        return [
            'sucesso' => false,
            'mensagem' => 'Erro ao processar login. Tente novamente.'
        ];
    }
}

/**
 * Realiza logout do Sistema Seguro
 */
function fazerLogout() {
    // Registrar log antes de destruir sessão
    if (isset($_SESSION['seguro_empresa_id']) && isset($_SESSION['seguro_usuario_id'])) {
        registrarLog(
            $_SESSION['seguro_empresa_id'], 
            $_SESSION['seguro_usuario_id'], 
            'logout', 
            'autenticacao', 
            'Logout realizado'
        );
    }
    
    // Limpar variáveis de sessão do Sistema Seguro
    unset($_SESSION['seguro_logado']);
    unset($_SESSION['seguro_usuario_id']);
    unset($_SESSION['seguro_usuario_nome']);
    unset($_SESSION['seguro_usuario_email']);
    unset($_SESSION['seguro_usuario_nivel']);
    unset($_SESSION['seguro_empresa_id']);
    unset($_SESSION['seguro_empresa_nome']);
    
    // Redirecionar para login
    header('Location: login.php');
    exit;
}

/**
 * Obtém dados do usuário logado
 */
function obterUsuarioLogado() {
    if (!isset($_SESSION['seguro_logado']) || $_SESSION['seguro_logado'] !== true) {
        return null;
    }
    
    return [
        'id' => $_SESSION['seguro_usuario_id'] ?? null,
        'nome' => $_SESSION['seguro_usuario_nome'] ?? 'Usuário',
        'email' => $_SESSION['seguro_usuario_email'] ?? '',
        'nivel' => $_SESSION['seguro_usuario_nivel'] ?? 'operador',
        'empresa_id' => $_SESSION['seguro_empresa_id'] ?? null,
        'empresa_nome' => $_SESSION['seguro_empresa_nome'] ?? '',
        'empresa_adm_id' => $_SESSION['empresa_adm_id'] ?? null
    ];
}

/**
 * Verifica se usuário tem permissão para determinada ação
 */
function temPermissao($nivelRequerido) {
    $usuario = obterUsuarioLogado();
    if (!$usuario) {
        return false;
    }
    
    $hierarquia = [
        'visualizador' => 1,
        'operador' => 2,
        'gerente' => 3,
        'admin' => 4
    ];
    
    $nivelUsuario = $hierarquia[$usuario['nivel']] ?? 0;
    $nivelRequer = $hierarquia[$nivelRequerido] ?? 0;
    
    return $nivelUsuario >= $nivelRequer;
}

/**
 * Registra log de atividade
 */
function registrarLog($empresa_id, $usuario_id, $acao, $modulo, $descricao = null) {
    require_once 'database.php';
    $db = getDB();
    
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'N/A';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
        
        $stmt = $db->prepare("
            INSERT INTO seguro_logs 
            (seguro_empresa_id, usuario_id, acao, modulo, descricao, ip, user_agent, data_hora)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $empresa_id,
            $usuario_id,
            $acao,
            $modulo,
            $descricao,
            $ip,
            substr($userAgent, 0, 255) // Limitar tamanho
        ]);
        
        return true;
    } catch(PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém ID da empresa do usuário logado
 */
function obterEmpresaId() {
    return $_SESSION['seguro_empresa_id'] ?? null;
}

/**
 * Obtém ID do usuário logado
 */
function obterUsuarioId() {
    return $_SESSION['seguro_usuario_id'] ?? null;
}

?>

