<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Gerenciamento de Usuários';
$empresa_id = $_SESSION['empresa_id'];

// Obter permissões do usuário atual
$permissions = get_user_permissions();
$can_edit_system_users = $permissions['can_edit_system_users'];
$can_create_system_users = $permissions['can_create_system_users'];
$can_access_lucratividade = $permissions['can_access_lucratividade'];

// Buscar usuários de motoristas
$conn = getConnection();
$stmt = $conn->prepare('
    SELECT um.*, m.nome as nome_motorista, m.cpf, "motorista" as tipo_usuario_sistema
    FROM usuarios_motoristas um 
    JOIN motoristas m ON um.motorista_id = m.id 
    WHERE um.empresa_id = :empresa_id 
    ORDER BY um.data_cadastro DESC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios_motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários do sistema (admin/gestor)
$stmt = $conn->prepare('
    SELECT u.*, "sistema" as tipo_usuario_sistema
    FROM usuarios u 
    WHERE u.empresa_id = :empresa_id 
    ORDER BY u.data_cadastro DESC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios_sistema = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combinar todos os usuários
$usuarios = array_merge($usuarios_motoristas, $usuarios_sistema);

// Buscar motoristas disponíveis para cadastro
$stmt = $conn->prepare('
    SELECT m.* 
    FROM motoristas m 
    LEFT JOIN usuarios_motoristas um ON m.id = um.motorista_id 
    WHERE m.empresa_id = :empresa_id 
    AND m.disponibilidade_id = 1 
    AND um.id IS NULL
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$motoristas_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            margin: 0;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        
        .checkbox-label:hover {
            background: #e9e9e9;
            border-color: #007bff;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: #007bff;
            border-color: #007bff;
        }
        
        .permissions-grid {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fafafa;
        }
        
        .permission-item {
            margin: 0;
        }
        
        .permission-item label {
            margin: 0;
            font-weight: normal;
        }

        /* Estilos para tema escuro - checkboxes de permissões */
        body:not(.light-theme) .checkbox-label {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .checkbox-label:hover {
            background: var(--bg-tertiary) !important;
            border-color: var(--accent-primary) !important;
        }
        
        body:not(.light-theme) .permissions-grid {
            background: var(--bg-secondary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .permission-item label {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .checkbox-label input[type="checkbox"] {
            accent-color: var(--accent-primary);
        }
        
        body:not(.light-theme) .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: var(--accent-primary) !important;
            border-color: var(--accent-primary) !important;
        }

        /* Estilos para tema claro - manter consistência */
        .light-theme .checkbox-label {
            background: #f9f9f9;
            color: #333;
            border-color: #ddd;
        }
        
        .light-theme .checkbox-label:hover {
            background: #e9e9e9;
            border-color: #007bff;
        }
        
        .light-theme .permissions-grid {
            background: #fafafa;
            border-color: #ddd;
        }
        
        .light-theme .permission-item label {
            color: #333;
        }
        
        /* Estilo para checkboxes (mantido para consistência) */
        .permission-item input[type="checkbox"] {
            cursor: pointer;
        }

        /* Estilos para modal no tema escuro */
        body:not(.light-theme) .modal-content {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .modal-header {
            background: var(--bg-tertiary) !important;
            border-bottom-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .modal-header h5 {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .modal-body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .form-group label {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .form-control {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .form-control:focus {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            border-color: var(--accent-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25) !important;
        }
        
        body:not(.light-theme) .btn-secondary {
            background: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .btn-secondary:hover {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                    <div class="user-permission-info" style="margin-top: 10px;">
                        <span class="badge <?php echo $permissions['is_admin'] ? 'badge-warning' : ($permissions['is_gestor'] ? 'badge-primary' : 'badge-info'); ?>">
                            <?php echo ucfirst($permissions['permission_level']); ?>
                        </span>
                        <small class="text-muted" style="margin-left: 10px;">
                            <?php if ($permissions['is_admin']): ?>
                                Acesso total - Pode gerenciar todos os usuários e acessar todas as funcionalidades
                            <?php elseif ($permissions['is_gestor']): ?>
                                Acesso limitado - Pode gerenciar apenas motoristas
                            <?php else: ?>
                                Acesso básico - Apenas visualização
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                
                <!-- Lista de Usuários -->
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Usuários Cadastrados</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" style="width: 100%; table-layout: fixed;">
                                    <thead>
                                        <tr>
                                            <th style="width: 20%; text-align: left; padding: 12px 8px;">Nome</th>
                                            <th style="width: 18%; text-align: left; padding: 12px 8px;">Email/CPF</th>
                                            <th style="width: 10%; text-align: center; padding: 12px 8px;">Tipo</th>
                                            <th style="width: 15%; text-align: center; padding: 12px 8px;">Permissões</th>
                                            <th style="width: 8%; text-align: center; padding: 12px 8px;">Status</th>
                                            <th style="width: 12%; text-align: center; padding: 12px 8px;">Data Cadastro</th>
                                            <th style="width: 15%; text-align: center; padding: 12px 8px;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td style="padding: 12px 8px; text-align: left; word-wrap: break-word;">
                                                <?php 
                                                if ($usuario['tipo_usuario_sistema'] === 'motorista') {
                                                    echo htmlspecialchars($usuario['nome_motorista']);
                                                } else {
                                                    echo htmlspecialchars($usuario['nome']);
                                                }
                                                ?>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: left; word-wrap: break-word;">
                                                <?php 
                                                if ($usuario['tipo_usuario_sistema'] === 'motorista') {
                                                    echo htmlspecialchars($usuario['cpf']);
                                                } else {
                                                    echo htmlspecialchars($usuario['email']);
                                                }
                                                ?>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center;">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'motorista'): ?>
                                                    <span class="badge badge-info">Motorista</span>
                                                <?php else: ?>
                                                    <span class="badge <?php echo $usuario['tipo_usuario'] === 'admin' ? 'badge-warning' : 'badge-primary'; ?>">
                                                        <?php echo ucfirst($usuario['tipo_usuario']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center;">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'sistema'): ?>
                                                    <div class="permissions-display" style="font-size: 11px;">
                                                        <?php if ($usuario['tipo_usuario'] === 'admin'): ?>
                                                            <span class="badge badge-success" style="font-size: 10px;">Todas</span>
                                                        <?php else: ?>
                                                            <?php 
                                                            $permissions = [];
                                                            if (isset($usuario['pode_acessar_lucratividade']) && $usuario['pode_acessar_lucratividade']) $permissions[] = 'Lucratividade';
                                                            if (isset($usuario['pode_editar_usuarios_sistema']) && $usuario['pode_editar_usuarios_sistema']) $permissions[] = 'Editar Usuários';
                                                            if (isset($usuario['pode_criar_usuarios_sistema']) && $usuario['pode_criar_usuarios_sistema']) $permissions[] = 'Criar Usuários';
                                                            if (isset($usuario['pode_acessar_relatorios_avancados']) && $usuario['pode_acessar_relatorios_avancados']) $permissions[] = 'Relatórios';
                                                            if (isset($usuario['pode_gerenciar_configuracoes']) && $usuario['pode_gerenciar_configuracoes']) $permissions[] = 'Configurações';
                                                            if (isset($usuario['pode_aprovar_abastecimentos']) && $usuario['pode_aprovar_abastecimentos']) $permissions[] = 'Gestão de Motoristas';
                                                            if (isset($usuario['pode_ver_dados_financeiros']) && $usuario['pode_ver_dados_financeiros']) $permissions[] = 'Dados Financeiros';
                                                            if (isset($usuario['pode_acessar_sistema_fiscal']) && $usuario['pode_acessar_sistema_fiscal']) $permissions[] = 'Sistema Fiscal';
                                                            if (isset($usuario['pode_acessar_gestao_pneus']) && $usuario['pode_acessar_gestao_pneus']) $permissions[] = 'Gestão de Pneus';
                                                            ?>
                                                            <?php if (empty($permissions)): ?>
                                                                <span class="text-muted" style="font-size: 10px;">Básicas</span>
                                                            <?php else: ?>
                                                                <?php foreach ($permissions as $permission): ?>
                                                                    <span class="badge badge-info" style="font-size: 9px; margin: 1px;"><?php echo $permission; ?></span>
                                                                <?php endforeach; ?>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size: 10px;">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center;">
                                                <span class="badge <?php echo $usuario['status'] === 'ativo' ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo ucfirst($usuario['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 8px; text-align: center;"><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>
                                            <td style="padding: 12px 8px; text-align: center;">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'motorista' || $can_edit_system_users): ?>
                                                    <button class="btn btn-sm btn-primary" onclick="editarUsuario(<?php echo $usuario['id']; ?>, '<?php echo $usuario['tipo_usuario_sistema']; ?>')" style="margin-right: 5px;">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                    <button class="btn btn-sm btn-danger" onclick="excluirUsuario(<?php echo $usuario['id']; ?>, '<?php echo $usuario['tipo_usuario_sistema']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php else: ?>
                                                    <span class="text-muted">Sem permissão</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Cadastro de Usuário Motorista -->
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Cadastro de Usuário Motorista</h3>
                        </div>
                        <div class="card-body">
                            <form id="cadastroMotoristaForm" method="post" action="../api/usuarios_motoristas.php">
                                <input type="hidden" name="action" value="create">
                                <div class="form-group">
                                    <label for="motorista_id">Motorista</label>
                                    <select id="motorista_id" name="motorista_id" class="form-control" required>
                                        <option value="">Selecione um motorista</option>
                                        <?php foreach ($motoristas_disponiveis as $motorista): ?>
                                        <option value="<?php echo $motorista['id']; ?>">
                                            <?php echo htmlspecialchars($motorista['nome'] . ' - ' . $motorista['cpf']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="nome_motorista">Nome de Usuário</label>
                                    <input type="text" id="nome_motorista" name="nome" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="senha_motorista">Senha</label>
                                    <input type="password" id="senha_motorista" name="senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha_motorista">Confirmar Senha</label>
                                    <input type="password" id="confirmar_senha_motorista" name="confirmar_senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Cadastrar Motorista
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Cadastro de Usuário do Sistema -->
                    <?php if ($can_create_system_users): ?>
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Cadastro de Usuário do Sistema</h3>
                        </div>
                        <div class="card-body">
                            <form id="cadastroSistemaForm" method="post" action="../api/usuarios.php">
                                <input type="hidden" name="action" value="create">
                                <div class="form-group">
                                    <label for="nome_sistema">Nome Completo</label>
                                    <input type="text" id="nome_sistema" name="nome" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_sistema">Email</label>
                                    <input type="email" id="email_sistema" name="email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="tipo_usuario">Tipo de Usuário</label>
                                    <select id="tipo_usuario" name="tipo_usuario" class="form-control" required>
                                        <option value="">Selecione o tipo</option>
                                        <option value="admin">Administrador</option>
                                        <option value="gestor">Gestor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="senha_sistema">Senha</label>
                                    <input type="password" id="senha_sistema" name="senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha_sistema">Confirmar Senha</label>
                                    <input type="password" id="confirmar_senha_sistema" name="confirmar_senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="status_sistema">Status</label>
                                    <select id="status_sistema" name="status" class="form-control">
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Permissões Especiais</label>
                                    <div class="permissions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_edit_system_users" name="can_edit_system_users" value="1">
                                                <span class="checkmark"></span>
                                                Editar Usuários do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_create_system_users" name="can_create_system_users" value="1">
                                                <span class="checkmark"></span>
                                                Criar Usuários do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_lucratividade" name="can_access_lucratividade" value="1">
                                                <span class="checkmark"></span>
                                                Acessar Lucratividade
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_advanced_reports" name="can_access_advanced_reports" value="1">
                                                <span class="checkmark"></span>
                                                Relatórios Avançados
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_manage_system_settings" name="can_manage_system_settings" value="1">
                                                <span class="checkmark"></span>
                                                Configurações do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_approve_refuels" name="can_approve_refuels" value="1">
                                                <span class="checkmark"></span>
                                                Gestão de Motoristas
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_view_financial_data" name="can_view_financial_data" value="1">
                                                <span class="checkmark"></span>
                                                Dados Financeiros
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_fiscal_system" name="can_access_fiscal_system" value="1">
                                                <span class="checkmark"></span>
                                                Sistema Fiscal
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_tire_management" name="can_access_tire_management" value="1">
                                                <span class="checkmark"></span>
                                                Gestão de Pneus
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Cadastrar Usuário do Sistema
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Editar Usuário</h3>
                <span class="close" onclick="fecharModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" id="edit_tipo" name="tipo_usuario_sistema">
                    <input type="hidden" name="action" value="update">
                    
                    <div id="edit_motorista_fields" style="display: none;">
                        <div class="form-group">
                            <label for="edit_motorista_id">Motorista</label>
                            <select id="edit_motorista_id" name="motorista_id" class="form-control">
                                <option value="">Selecione um motorista</option>
                                <?php foreach ($motoristas_disponiveis as $motorista): ?>
                                <option value="<?php echo $motorista['id']; ?>">
                                    <?php echo htmlspecialchars($motorista['nome'] . ' - ' . $motorista['cpf']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_nome">Nome</label>
                        <input type="text" id="edit_nome" name="nome" class="form-control" required>
                    </div>
                    
                    <div id="edit_email_field" style="display: none;">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control">
                        </div>
                    </div>
                    
                    <div id="edit_tipo_usuario_field" style="display: none;">
                        <div class="form-group">
                            <label for="edit_tipo_usuario">Tipo de Usuário</label>
                            <select id="edit_tipo_usuario" name="tipo_usuario" class="form-control">
                                <option value="admin">Administrador</option>
                                <option value="gestor">Gestor</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Permissões Granulares (apenas para gestores) -->
                    <div id="edit_permissions_field" style="display: none;">
                        <div class="form-group">
                            <label>Permissões Especiais</label>
                            <div class="permissions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_edit_system_users" name="can_edit_system_users" value="1">
                                        <span class="checkmark"></span>
                                        Editar Usuários do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_create_system_users" name="can_create_system_users" value="1">
                                        <span class="checkmark"></span>
                                        Criar Usuários do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_lucratividade" name="can_access_lucratividade" value="1">
                                        <span class="checkmark"></span>
                                        Acessar Lucratividade
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_advanced_reports" name="can_access_advanced_reports" value="1">
                                        <span class="checkmark"></span>
                                        Relatórios Avançados
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_manage_system_settings" name="can_manage_system_settings" value="1">
                                        <span class="checkmark"></span>
                                        Configurações do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_approve_refuels" name="can_approve_refuels" value="1">
                                        <span class="checkmark"></span>
                                        Gestão de Motoristas
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_view_financial_data" name="can_view_financial_data" value="1">
                                        <span class="checkmark"></span>
                                        Dados Financeiros
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_fiscal_system" name="can_access_fiscal_system" value="1">
                                        <span class="checkmark"></span>
                                        Sistema Fiscal
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_tire_management" name="can_access_tire_management" value="1">
                                        <span class="checkmark"></span>
                                        Gestão de Pneus
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_senha">Nova Senha (deixe em branco para manter a atual)</label>
                        <input type="password" id="edit_senha" name="senha" class="form-control" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="fecharModal()">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    // Validação do formulário de motoristas
    document.getElementById('cadastroMotoristaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('senha_motorista').value;
        const confirmarSenha = document.getElementById('confirmar_senha_motorista').value;
        
        if (senha !== confirmarSenha) {
            alert('As senhas não coincidem!');
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../api/usuarios_motoristas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário motorista cadastrado com sucesso!');
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao cadastrar usuário motorista.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao cadastrar usuário motorista.');
        });
    });

    // Validação do formulário de usuários do sistema
    document.getElementById('cadastroSistemaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('senha_sistema').value;
        const confirmarSenha = document.getElementById('confirmar_senha_sistema').value;
        
        if (senha !== confirmarSenha) {
            alert('As senhas não coincidem!');
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../api/usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário do sistema cadastrado com sucesso!');
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao cadastrar usuário do sistema.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao cadastrar usuário do sistema.');
        });
    });

    // Validação do formulário de edição
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('edit_senha').value;
        const confirmarSenha = document.getElementById('edit_confirmar_senha') ? document.getElementById('edit_confirmar_senha').value : senha;
        
        if (senha && senha !== confirmarSenha) {
            alert('As senhas não coincidem!');
            return;
        }
        
        const formData = new FormData(this);
        const tipoUsuario = document.getElementById('edit_tipo').value;
        const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';
        
        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário atualizado com sucesso!');
                fecharModal();
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao atualizar usuário.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao atualizar usuário.');
        });
    });

    // Função para editar usuário
    function editarUsuario(id, tipoUsuario) {
        // Verificar permissões
        const canEditSystemUsers = <?php echo $can_edit_system_users ? 'true' : 'false'; ?>;
        
        if (tipoUsuario === 'sistema' && !canEditSystemUsers) {
            alert('Você não tem permissão para editar usuários do sistema.');
            return;
        }
        
        const modal = document.getElementById('editModal');
        const modalTitle = document.getElementById('modalTitle');
        const editTipo = document.getElementById('edit_tipo');
        const editMotoristaFields = document.getElementById('edit_motorista_fields');
        const editEmailField = document.getElementById('edit_email_field');
        const editTipoUsuarioField = document.getElementById('edit_tipo_usuario_field');
        
        // Configurar campos baseado no tipo de usuário
        const editPermissionsField = document.getElementById('edit_permissions_field');
        
        if (tipoUsuario === 'motorista') {
            modalTitle.textContent = 'Editar Usuário Motorista';
            editTipo.value = 'motorista';
            editMotoristaFields.style.display = 'block';
            editEmailField.style.display = 'none';
            editTipoUsuarioField.style.display = 'none';
            editPermissionsField.style.display = 'none';
        } else {
            modalTitle.textContent = 'Editar Usuário do Sistema';
            editTipo.value = 'sistema';
            editMotoristaFields.style.display = 'none';
            editEmailField.style.display = 'block';
            editTipoUsuarioField.style.display = 'block';
            editPermissionsField.style.display = 'block';
        }
        
        // Buscar dados do usuário
        const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';
        
        fetch(`${apiUrl}?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.data;
                document.getElementById('edit_id').value = usuario.id;
                document.getElementById('edit_nome').value = usuario.nome;
                document.getElementById('edit_status').value = usuario.status;
                
                if (tipoUsuario === 'sistema') {
                    document.getElementById('edit_email').value = usuario.email;
                    document.getElementById('edit_tipo_usuario').value = usuario.tipo_usuario;
                    
                    // Se for admin, marcar todas as permissões automaticamente e desabilitar
                    if (usuario.tipo_usuario === 'admin') {
                        const adminCheckboxes = [
                            'edit_can_edit_system_users',
                            'edit_can_create_system_users', 
                            'edit_can_access_lucratividade',
                            'edit_can_access_advanced_reports',
                            'edit_can_manage_system_settings',
                            'edit_can_approve_refuels',
                            'edit_can_view_financial_data',
                            'edit_can_access_fiscal_system',
                            'edit_can_access_tire_management'
                        ];
                        
                        adminCheckboxes.forEach(id => {
                            const checkbox = document.getElementById(id);
                            if (checkbox) {
                                checkbox.checked = true;
                                checkbox.disabled = false; // Admin pode desmarcar se quiser
                            }
                        });
                    } else {
                        // Carregar permissões granulares para usuários não-admin e habilitar checkboxes
                        const nonAdminCheckboxes = [
                            'edit_can_edit_system_users',
                            'edit_can_create_system_users', 
                            'edit_can_access_lucratividade',
                            'edit_can_access_advanced_reports',
                            'edit_can_manage_system_settings',
                            'edit_can_approve_refuels',
                            'edit_can_view_financial_data',
                            'edit_can_access_fiscal_system',
                            'edit_can_access_tire_management'
                        ];
                        
                        // Valores das permissões do usuário
                        const permissionValues = {
                            'edit_can_edit_system_users': usuario.pode_editar_usuarios_sistema == 1,
                            'edit_can_create_system_users': usuario.pode_criar_usuarios_sistema == 1,
                            'edit_can_access_lucratividade': usuario.pode_acessar_lucratividade == 1,
                            'edit_can_access_advanced_reports': usuario.pode_acessar_relatorios_avancados == 1,
                            'edit_can_manage_system_settings': usuario.pode_gerenciar_configuracoes == 1,
                            'edit_can_approve_refuels': usuario.pode_aprovar_abastecimentos == 1,
                            'edit_can_view_financial_data': usuario.pode_ver_dados_financeiros == 1,
                            'edit_can_access_fiscal_system': usuario.pode_acessar_sistema_fiscal == 1,
                            'edit_can_access_tire_management': usuario.pode_acessar_gestao_pneus == 1
                        };
                        
                        nonAdminCheckboxes.forEach(id => {
                            const checkbox = document.getElementById(id);
                            if (checkbox) {
                                checkbox.checked = permissionValues[id];
                                checkbox.disabled = false;
                            }
                        });
                    }
                } else {
                    document.getElementById('edit_motorista_id').value = usuario.motorista_id || '';
                }
                
                modal.style.display = 'block';
            } else {
                alert(data.error || 'Erro ao carregar dados do usuário.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao carregar dados do usuário.');
        });
    }

    // Função para excluir usuário
    function excluirUsuario(id, tipoUsuario) {
        // Verificar permissões
        const canEditSystemUsers = <?php echo $can_edit_system_users ? 'true' : 'false'; ?>;
        
        if (tipoUsuario === 'sistema' && !canEditSystemUsers) {
            alert('Você não tem permissão para excluir usuários do sistema.');
            return;
        }
        
        if (confirm('Tem certeza que deseja excluir este usuário?')) {
            const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';
            
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'delete',
                    id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Usuário excluído com sucesso!');
                    window.location.reload();
                } else {
                    alert(data.error || 'Erro ao excluir usuário.');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir usuário.');
            });
        }
    }

    // Função para fechar modal
    function fecharModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Fechar modal ao clicar fora dele
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            fecharModal();
        }
    }

    // Controlar exibição de permissões baseado no tipo de usuário
    document.addEventListener('DOMContentLoaded', function() {
        const tipoUsuarioSelect = document.getElementById('edit_tipo_usuario');
        const permissionsField = document.getElementById('edit_permissions_field');
        
        if (tipoUsuarioSelect && permissionsField) {
            tipoUsuarioSelect.addEventListener('change', function() {
                if (this.value === 'gestor') {
                    permissionsField.style.display = 'block';
                    // Habilitar todos os checkboxes para gestor
                    const checkboxes = permissionsField.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                } else if (this.value === 'admin') {
                    permissionsField.style.display = 'block';
                    // Marcar todas as permissões para admin mas manter habilitado (pode desmarcar se quiser)
                    const checkboxes = permissionsField.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = true;
                        checkbox.disabled = false;
                    });
                }
            });
        }
    });
    </script>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html> 