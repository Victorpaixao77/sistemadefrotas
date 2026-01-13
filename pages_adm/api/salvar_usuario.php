<?php
require_once '../../includes/conexao.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add':
            // Verificar se o email já existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                throw new Exception("Este email já está cadastrado.");
            }

            // Obter o ID da empresa_clientes correspondente
            $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ?");
            $stmt->execute([$_POST['empresa_id']]);
            $empresa_cliente = $stmt->fetch();
            
            if (!$empresa_cliente) {
                throw new Exception("Empresa não encontrada.");
            }

            // Hash da senha
            $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            
            // Verificar se tem acesso a todas as empresas
            $is_oculto = isset($_POST['is_oculto']) && $_POST['is_oculto'] == '1' ? 1 : 0;
            $acesso_todas_empresas = isset($_POST['acesso_todas_empresas']) && $_POST['acesso_todas_empresas'] == '1' ? 1 : 0;
            
            // Se tem acesso a todas empresas, empresa_id pode ser NULL ou primeira empresa
            $empresa_id_final = $acesso_todas_empresas ? ($empresa_cliente['id'] ?? null) : $empresa_cliente['id'];

            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha, empresa_id, tipo_usuario, status, is_admin, is_oculto, acesso_todas_empresas) VALUES (?, ?, ?, ?, ?, 'ativo', ?, ?, ?)");
            $stmt->execute([
                $_POST['nome'],
                $_POST['email'],
                $senha_hash,
                $empresa_id_final,
                $_POST['tipo_usuario'],
                $_POST['tipo_usuario'] == 'admin' ? 1 : 0,
                $is_oculto,
                $acesso_todas_empresas
            ]);
            
            $usuario_id = $pdo->lastInsertId();
            
            // Definir permissões baseadas no tipo de usuário
            $permissoes = [];
            if ($_POST['tipo_usuario'] == 'admin') {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 1,
                    'pode_criar_usuarios_sistema' => 1,
                    'pode_acessar_lucratividade' => 1,
                    'pode_acessar_relatorios_avancados' => 1,
                    'pode_gerenciar_configuracoes' => 1,
                    'pode_aprovar_abastecimentos' => 1,
                    'pode_ver_dados_financeiros' => 1
                ];
            } elseif ($_POST['tipo_usuario'] == 'gestor') {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 0,
                    'pode_criar_usuarios_sistema' => 0,
                    'pode_acessar_lucratividade' => 1,
                    'pode_acessar_relatorios_avancados' => 1,
                    'pode_gerenciar_configuracoes' => 0,
                    'pode_aprovar_abastecimentos' => 1,
                    'pode_ver_dados_financeiros' => 1
                ];
            } else {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 0,
                    'pode_criar_usuarios_sistema' => 0,
                    'pode_acessar_lucratividade' => 0,
                    'pode_acessar_relatorios_avancados' => 0,
                    'pode_gerenciar_configuracoes' => 0,
                    'pode_aprovar_abastecimentos' => 0,
                    'pode_ver_dados_financeiros' => 0
                ];
            }
            
            // Tentar atualizar permissões (pode falhar se campos não existirem, mas não é crítico)
            if (!empty($permissoes)) {
                try {
                    $campos = [];
                    $params = [];
                    foreach ($permissoes as $campo => $valor) {
                        $campos[] = "{$campo} = ?";
                        $params[] = $valor;
                    }
                    $params[] = $usuario_id;
                    
                    $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                } catch (PDOException $e) {
                    // Campos de permissão podem não existir, ignorar erro
                    error_log("Aviso: Campos de permissão não encontrados: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Usuário cadastrado com sucesso!']);
            break;
            
        case 'edit':
            // Verificar se o email já existe para outro usuário
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$_POST['email'], $_POST['id']]);
            if ($stmt->fetch()) {
                throw new Exception("Este email já está cadastrado para outro usuário.");
            }

            // Obter o ID da empresa_clientes correspondente
            $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ?");
            $stmt->execute([$_POST['empresa_id']]);
            $empresa_cliente = $stmt->fetch();
            
            if (!$empresa_cliente) {
                throw new Exception("Empresa não encontrada.");
            }

            // Verificar campos de oculto e acesso
            $is_oculto = isset($_POST['is_oculto']) && $_POST['is_oculto'] == '1' ? 1 : 0;
            $acesso_todas_empresas = isset($_POST['acesso_todas_empresas']) && $_POST['acesso_todas_empresas'] == '1' ? 1 : 0;
            
            // Se tem acesso a todas empresas, empresa_id pode ser NULL ou primeira empresa
            $empresa_id_final = $acesso_todas_empresas ? ($empresa_cliente['id'] ?? null) : $empresa_cliente['id'];
            
            // Preparar SQL de atualização
            $sql = "UPDATE usuarios SET nome = ?, email = ?, empresa_id = ?, tipo_usuario = ?, status = ?, is_admin = ?, is_oculto = ?, acesso_todas_empresas = ?";
            $params = [
                $_POST['nome'],
                $_POST['email'],
                $empresa_id_final,
                $_POST['tipo_usuario'],
                $_POST['status'],
                $_POST['tipo_usuario'] == 'admin' ? 1 : 0,
                $is_oculto,
                $acesso_todas_empresas
            ];

            // Se senha foi fornecida, atualizar
            if (!empty($_POST['senha'])) {
                $sql = str_replace("SET nome", "SET senha = ?, nome", $sql);
                array_splice($params, 0, 0, password_hash($_POST['senha'], PASSWORD_DEFAULT));
            }

            $sql .= " WHERE id = ?";
            $params[] = $_POST['id'];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Atualizar permissões
            $permissoes = [];
            if ($_POST['tipo_usuario'] == 'admin') {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 1,
                    'pode_criar_usuarios_sistema' => 1,
                    'pode_acessar_lucratividade' => 1,
                    'pode_acessar_relatorios_avancados' => 1,
                    'pode_gerenciar_configuracoes' => 1,
                    'pode_aprovar_abastecimentos' => 1,
                    'pode_ver_dados_financeiros' => 1
                ];
            } elseif ($_POST['tipo_usuario'] == 'gestor') {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 0,
                    'pode_criar_usuarios_sistema' => 0,
                    'pode_acessar_lucratividade' => 1,
                    'pode_acessar_relatorios_avancados' => 1,
                    'pode_gerenciar_configuracoes' => 0,
                    'pode_aprovar_abastecimentos' => 1,
                    'pode_ver_dados_financeiros' => 1
                ];
            } else {
                $permissoes = [
                    'pode_editar_usuarios_sistema' => 0,
                    'pode_criar_usuarios_sistema' => 0,
                    'pode_acessar_lucratividade' => 0,
                    'pode_acessar_relatorios_avancados' => 0,
                    'pode_gerenciar_configuracoes' => 0,
                    'pode_aprovar_abastecimentos' => 0,
                    'pode_ver_dados_financeiros' => 0
                ];
            }
            
            // Tentar atualizar permissões (pode falhar se campos não existirem, mas não é crítico)
            if (!empty($permissoes)) {
                try {
                    $campos = [];
                    $params_perm = [];
                    foreach ($permissoes as $campo => $valor) {
                        $campos[] = "{$campo} = ?";
                        $params_perm[] = $valor;
                    }
                    $params_perm[] = $_POST['id'];
                    
                    $sql_perm = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = ?";
                    $stmt_perm = $pdo->prepare($sql_perm);
                    $stmt_perm->execute($params_perm);
                } catch (PDOException $e) {
                    // Campos de permissão podem não existir, ignorar erro
                    error_log("Aviso: Campos de permissão não encontrados: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso!']);
            break;
            
        default:
            throw new Exception("Ação inválida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
