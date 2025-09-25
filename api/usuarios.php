<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    echo json_encode(['success' => false, 'error' => 'Não autorizado']);
    exit;
}

$empresa_id = $_SESSION['empresa_id'];
$conn = getConnection();

// Processar requisições
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        // Validar dados
        if (empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['senha']) || empty($_POST['tipo_usuario'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        // Verificar se o email já existe
        $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = :email');
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->execute();
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Este email já está em uso']);
            exit;
        }

        // Inserir novo usuário
        try {
            $stmt = $conn->prepare('
                INSERT INTO usuarios (
                    empresa_id, nome, email, senha, tipo_usuario, status, is_admin,
                    pode_editar_usuarios_sistema, pode_criar_usuarios_sistema, pode_acessar_lucratividade,
                    pode_acessar_relatorios_avancados, pode_gerenciar_configuracoes, pode_aprovar_abastecimentos, 
                    pode_ver_dados_financeiros, pode_acessar_sistema_fiscal, pode_acessar_gestao_pneus
                ) VALUES (
                    :empresa_id, :nome, :email, :senha, :tipo_usuario, :status, :is_admin,
                    :pode_editar_usuarios_sistema, :pode_criar_usuarios_sistema, :pode_acessar_lucratividade,
                    :pode_acessar_relatorios_avancados, :pode_gerenciar_configuracoes, :pode_aprovar_abastecimentos,
                    :pode_ver_dados_financeiros, :pode_acessar_sistema_fiscal, :pode_acessar_gestao_pneus
                )
            ');

            $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
            $is_admin = ($_POST['tipo_usuario'] === 'admin') ? 1 : 0;
            $status = $_POST['status'] ?? 'ativo';
            
            // Definir permissões baseadas no tipo de usuário
            $pode_editar_usuarios_sistema = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_edit_system_users']) ? 1 : 0);
            $pode_criar_usuarios_sistema = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_create_system_users']) ? 1 : 0);
            $pode_acessar_lucratividade = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_lucratividade']) ? 1 : 0);
            $pode_acessar_relatorios_avancados = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_advanced_reports']) ? 1 : 0);
            $pode_gerenciar_configuracoes = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_manage_system_settings']) ? 1 : 0);
            $pode_aprovar_abastecimentos = isset($_POST['can_approve_refuels']) ? 1 : 0;
            $pode_ver_dados_financeiros = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_view_financial_data']) ? 1 : 0);
            $pode_acessar_sistema_fiscal = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_fiscal_system']) ? 1 : 0);
            $pode_acessar_gestao_pneus = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_tire_management']) ? 1 : 0);

            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $_POST['nome']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':senha', $senha_hash);
            $stmt->bindParam(':tipo_usuario', $_POST['tipo_usuario']);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);
            $stmt->bindParam(':pode_editar_usuarios_sistema', $pode_editar_usuarios_sistema, PDO::PARAM_INT);
            $stmt->bindParam(':pode_criar_usuarios_sistema', $pode_criar_usuarios_sistema, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_lucratividade', $pode_acessar_lucratividade, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_relatorios_avancados', $pode_acessar_relatorios_avancados, PDO::PARAM_INT);
            $stmt->bindParam(':pode_gerenciar_configuracoes', $pode_gerenciar_configuracoes, PDO::PARAM_INT);
            $stmt->bindParam(':pode_aprovar_abastecimentos', $pode_aprovar_abastecimentos, PDO::PARAM_INT);
            $stmt->bindParam(':pode_ver_dados_financeiros', $pode_ver_dados_financeiros, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_sistema_fiscal', $pode_acessar_sistema_fiscal, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_gestao_pneus', $pode_acessar_gestao_pneus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao cadastrar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao cadastrar usuário']);
        }
        break;

    case 'update':
        // Validar dados
        if (empty($_POST['id']) || empty($_POST['nome']) || empty($_POST['email']) || empty($_POST['tipo_usuario'])) {
            echo json_encode(['success' => false, 'error' => 'Dados incompletos']);
            exit;
        }

        // Verificar se o email já existe em outro usuário (apenas se o email mudou)
        $stmt = $conn->prepare('SELECT email FROM usuarios WHERE id = :id');
        $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
        $stmt->execute();
        $usuario_atual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($usuario_atual && $usuario_atual['email'] !== $_POST['email']) {
            // Email mudou, verificar se já existe
            $stmt = $conn->prepare('SELECT id FROM usuarios WHERE email = :email AND id != :id');
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Este email já está em uso por outro usuário']);
                exit;
            }
        }

        // Atualizar usuário
        try {
            $is_admin = ($_POST['tipo_usuario'] === 'admin') ? 1 : 0;
            $status = $_POST['status'] ?? 'ativo';
            
            // Definir permissões baseadas no tipo de usuário
            $pode_editar_usuarios_sistema = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_edit_system_users']) ? 1 : 0);
            $pode_criar_usuarios_sistema = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_create_system_users']) ? 1 : 0);
            $pode_acessar_lucratividade = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_lucratividade']) ? 1 : 0);
            $pode_acessar_relatorios_avancados = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_advanced_reports']) ? 1 : 0);
            $pode_gerenciar_configuracoes = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_manage_system_settings']) ? 1 : 0);
            $pode_aprovar_abastecimentos = isset($_POST['can_approve_refuels']) ? 1 : 0;
            $pode_ver_dados_financeiros = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_view_financial_data']) ? 1 : 0);
            $pode_acessar_sistema_fiscal = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_fiscal_system']) ? 1 : 0);
            $pode_acessar_gestao_pneus = ($_POST['tipo_usuario'] === 'admin') ? 1 : (isset($_POST['can_access_tire_management']) ? 1 : 0);

            // Se senha foi fornecida, atualizar senha
            if (!empty($_POST['senha'])) {
                $stmt = $conn->prepare('
                    UPDATE usuarios 
                    SET nome = :nome, email = :email, senha = :senha, 
                        tipo_usuario = :tipo_usuario, status = :status, is_admin = :is_admin,
                        pode_editar_usuarios_sistema = :pode_editar_usuarios_sistema,
                        pode_criar_usuarios_sistema = :pode_criar_usuarios_sistema,
                        pode_acessar_lucratividade = :pode_acessar_lucratividade,
                        pode_acessar_relatorios_avancados = :pode_acessar_relatorios_avancados,
                        pode_gerenciar_configuracoes = :pode_gerenciar_configuracoes,
                        pode_aprovar_abastecimentos = :pode_aprovar_abastecimentos,
                        pode_ver_dados_financeiros = :pode_ver_dados_financeiros,
                        pode_acessar_sistema_fiscal = :pode_acessar_sistema_fiscal,
                        pode_acessar_gestao_pneus = :pode_acessar_gestao_pneus
                    WHERE id = :id AND empresa_id = :empresa_id
                ');
                $senha_hash = password_hash($_POST['senha'], PASSWORD_DEFAULT);
                $stmt->bindParam(':senha', $senha_hash);
            } else {
                $stmt = $conn->prepare('
                    UPDATE usuarios 
                    SET nome = :nome, email = :email, 
                        tipo_usuario = :tipo_usuario, status = :status, is_admin = :is_admin,
                        pode_editar_usuarios_sistema = :pode_editar_usuarios_sistema,
                        pode_criar_usuarios_sistema = :pode_criar_usuarios_sistema,
                        pode_acessar_lucratividade = :pode_acessar_lucratividade,
                        pode_acessar_relatorios_avancados = :pode_acessar_relatorios_avancados,
                        pode_gerenciar_configuracoes = :pode_gerenciar_configuracoes,
                        pode_aprovar_abastecimentos = :pode_aprovar_abastecimentos,
                        pode_ver_dados_financeiros = :pode_ver_dados_financeiros,
                        pode_acessar_sistema_fiscal = :pode_acessar_sistema_fiscal,
                        pode_acessar_gestao_pneus = :pode_acessar_gestao_pneus
                    WHERE id = :id AND empresa_id = :empresa_id
                ');
            }

            $stmt->bindParam(':id', $_POST['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->bindParam(':nome', $_POST['nome']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':tipo_usuario', $_POST['tipo_usuario']);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_INT);
            $stmt->bindParam(':pode_editar_usuarios_sistema', $pode_editar_usuarios_sistema, PDO::PARAM_INT);
            $stmt->bindParam(':pode_criar_usuarios_sistema', $pode_criar_usuarios_sistema, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_lucratividade', $pode_acessar_lucratividade, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_relatorios_avancados', $pode_acessar_relatorios_avancados, PDO::PARAM_INT);
            $stmt->bindParam(':pode_gerenciar_configuracoes', $pode_gerenciar_configuracoes, PDO::PARAM_INT);
            $stmt->bindParam(':pode_aprovar_abastecimentos', $pode_aprovar_abastecimentos, PDO::PARAM_INT);
            $stmt->bindParam(':pode_ver_dados_financeiros', $pode_ver_dados_financeiros, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_sistema_fiscal', $pode_acessar_sistema_fiscal, PDO::PARAM_INT);
            $stmt->bindParam(':pode_acessar_gestao_pneus', $pode_acessar_gestao_pneus, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao atualizar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao atualizar usuário']);
        }
        break;

    case 'delete':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }

        try {
            $stmt = $conn->prepare('
                DELETE FROM usuarios 
                WHERE id = :id AND empresa_id = :empresa_id
            ');
            $stmt->bindParam(':id', $data['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao excluir usuário']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao excluir usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao excluir usuário']);
        }
        break;

    case 'get':
        if (empty($_GET['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID não fornecido']);
            exit;
        }

        try {
            $stmt = $conn->prepare('
                SELECT id, nome, email, tipo_usuario, status, data_cadastro, is_admin, foto_perfil,
                       pode_editar_usuarios_sistema, pode_criar_usuarios_sistema, pode_acessar_lucratividade,
                       pode_acessar_relatorios_avancados, pode_gerenciar_configuracoes, pode_aprovar_abastecimentos, 
                       pode_ver_dados_financeiros, pode_acessar_sistema_fiscal, pode_acessar_gestao_pneus
                FROM usuarios 
                WHERE id = :id AND empresa_id = :empresa_id
            ');
            $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($usuario) {
                echo json_encode(['success' => true, 'data' => $usuario]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
            }
        } catch (PDOException $e) {
            error_log('Erro ao buscar usuário: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Erro ao buscar usuário']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Ação inválida']);
        break;
}
