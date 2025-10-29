<?php
/**
 * API - Atualizar Perfil do Usuário
 * Permite atualizar dados pessoais e alterar senha
 */

header('Content-Type: application/json; charset=utf-8');
require_once '../config/database.php';
require_once '../config/auth.php';

// Verificar se está logado
verificarLogin();

$db = getDB();
$usuario_id = obterUsuarioId();
$empresa_id = obterEmpresaId();

// Obter dados da requisição
$json = file_get_contents('php://input');
$dados = json_decode($json, true);

if (!$dados || !isset($dados['acao'])) {
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Dados inválidos'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    
    if ($dados['acao'] === 'dados_pessoais') {
        // ===== ATUALIZAR DADOS PESSOAIS =====
        
        $nome = $dados['nome'] ?? '';
        $email = $dados['email'] ?? '';
        $telefone = $dados['telefone'] ?? null;
        
        if (empty($nome) || empty($email)) {
            throw new Exception('Nome e e-mail são obrigatórios');
        }
        
        // Verificar se o e-mail já existe em outro usuário
        $stmt = $db->prepare("
            SELECT id FROM seguro_usuarios 
            WHERE email = ? AND id != ? AND seguro_empresa_id = ?
        ");
        $stmt->execute([$email, $usuario_id, $empresa_id]);
        
        if ($stmt->fetch()) {
            throw new Exception('Este e-mail já está sendo usado por outro usuário');
        }
        
        // Limpar máscara do telefone se houver
        $telefoneLimpo = $telefone;
        if (!empty($telefone)) {
            $telefoneLimpo = preg_replace('/\D/', '', $telefone);
        }
        
        // Log de debug
        error_log("Atualizando perfil - Telefone original: '$telefone', Limpo: '$telefoneLimpo'");
        
        // Atualizar dados
        $stmt = $db->prepare("
            UPDATE seguro_usuarios 
            SET 
                nome = ?,
                email = ?,
                telefone = ?
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        
        $resultado = $stmt->execute([$nome, $email, $telefoneLimpo, $usuario_id, $empresa_id]);
        
        if (!$resultado) {
            throw new Exception('Erro ao executar atualização no banco de dados');
        }
        
        // Verificar se realmente atualizou
        $linhasAfetadas = $stmt->rowCount();
        error_log("Linhas afetadas: $linhasAfetadas");
        
        // Atualizar sessão
        $_SESSION['seguro_usuario_nome'] = $nome;
        $_SESSION['seguro_usuario_email'] = $email;
        
        // Registrar log
        registrarLog($empresa_id, $usuario_id, 'editar', 'usuarios', 'Dados pessoais atualizados');
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Dados pessoais atualizados com sucesso!',
            'debug' => [
                'telefone_recebido' => $telefone,
                'telefone_salvo' => $telefoneLimpo,
                'linhas_afetadas' => $linhasAfetadas
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($dados['acao'] === 'alterar_senha') {
        // ===== ALTERAR SENHA =====
        
        $senhaAtual = $dados['senha_atual'] ?? '';
        $novaSenha = $dados['nova_senha'] ?? '';
        
        if (empty($senhaAtual) || empty($novaSenha)) {
            throw new Exception('Preencha todos os campos de senha');
        }
        
        if (strlen($novaSenha) < 6) {
            throw new Exception('A nova senha deve ter no mínimo 6 caracteres');
        }
        
        // Buscar senha atual do usuário
        $stmt = $db->prepare("
            SELECT senha FROM seguro_usuarios 
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        $stmt->execute([$usuario_id, $empresa_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            throw new Exception('Usuário não encontrado');
        }
        
        // Verificar senha atual
        if (!password_verify($senhaAtual, $usuario['senha'])) {
            throw new Exception('Senha atual incorreta!');
        }
        
        // Criptografar nova senha
        $novaSenhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
        
        // Atualizar senha
        $stmt = $db->prepare("
            UPDATE seguro_usuarios 
            SET senha = ?
            WHERE id = ? AND seguro_empresa_id = ?
        ");
        $stmt->execute([$novaSenhaHash, $usuario_id, $empresa_id]);
        
        // Registrar log
        registrarLog($empresa_id, $usuario_id, 'alterar_senha', 'usuarios', 'Senha alterada');
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Senha alterada com sucesso!'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    error_log("Erro ao atualizar perfil: " . $e->getMessage());
    echo json_encode([
        'sucesso' => false,
        'mensagem' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>

