<?php
require_once '../includes/conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Processar ações
$mensagem = '';
$tipo_mensagem = '';

// Verificar mensagem de sucesso na URL
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted':
            $mensagem = "Usuário excluído com sucesso!";
            $tipo_mensagem = "success";
            break;
        case 'created':
            $mensagem = "Usuário cadastrado com sucesso!";
            $tipo_mensagem = "success";
            break;
        case 'updated':
            $mensagem = "Usuário atualizado com sucesso!";
            $tipo_mensagem = "success";
            break;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Verificar se o email já existe
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado.");
                    }

                    // Obter o ID da empresa_clientes correspondente
                    $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1");
                    $stmt->execute([$_POST['empresa_id']]); // Usar empresa_id do formulário
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
                    
                    // Obter o ID do usuário recém-criado
                    $usuario_id = $pdo->lastInsertId();
                    
                    // Definir permissões granulares baseadas no tipo de usuário
                    if ($_POST['tipo_usuario'] == 'admin') {
                        // Definir todas as permissões de admin
                        $permissoes_admin = [
                            'pode_editar_usuarios_sistema' => 1,
                            'pode_criar_usuarios_sistema' => 1,
                            'pode_acessar_lucratividade' => 1,
                            'pode_acessar_relatorios_avancados' => 1,
                            'pode_gerenciar_configuracoes' => 1,
                            'pode_aprovar_abastecimentos' => 1,
                            'pode_ver_dados_financeiros' => 1
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_admin as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $usuario_id;
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                        
                    } elseif ($_POST['tipo_usuario'] == 'gestor') {
                        // Definir permissões de gestor
                        $permissoes_gestor = [
                            'pode_editar_usuarios_sistema' => 0,
                            'pode_criar_usuarios_sistema' => 0,
                            'pode_acessar_lucratividade' => 1,
                            'pode_acessar_relatorios_avancados' => 1,
                            'pode_gerenciar_configuracoes' => 0,
                            'pode_aprovar_abastecimentos' => 1,
                            'pode_ver_dados_financeiros' => 1
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_gestor as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $usuario_id;
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                        
                    } else {
                        // Definir permissões de motorista (padrão)
                        $permissoes_motorista = [
                            'pode_editar_usuarios_sistema' => 0,
                            'pode_criar_usuarios_sistema' => 0,
                            'pode_acessar_lucratividade' => 0,
                            'pode_acessar_relatorios_avancados' => 0,
                            'pode_gerenciar_configuracoes' => 0,
                            'pode_aprovar_abastecimentos' => 0,
                            'pode_ver_dados_financeiros' => 0
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_motorista as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $usuario_id;
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                    }
                    
                    // Redirecionar para evitar reenvio do formulário
                    header('Location: usuarios.php?msg=created');
                    exit;
                } catch (Exception $e) {
                    $mensagem = "Erro ao cadastrar usuário: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'edit':
                try {
                    // Verificar se o email já existe para outro usuário
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado para outro usuário.");
                    }

                    // Obter o ID da empresa_clientes correspondente
                    $stmt = $pdo->prepare("SELECT id FROM empresa_clientes WHERE empresa_adm_id = ? LIMIT 1");
                    $stmt->execute([$_POST['empresa_id']]); // Usar empresa_id do formulário
                    $empresa_cliente = $stmt->fetch();
                    
                    if (!$empresa_cliente) {
                        throw new Exception("Empresa não encontrada.");
                    }

                    // Verificar campos de oculto e acesso
                    $is_oculto = isset($_POST['is_oculto']) && $_POST['is_oculto'] == '1' ? 1 : 0;
                    $acesso_todas_empresas = isset($_POST['acesso_todas_empresas']) && $_POST['acesso_todas_empresas'] == '1' ? 1 : 0;
                    
                    // Se tem acesso a todas empresas, empresa_id pode ser NULL ou primeira empresa
                    $empresa_id_final = $acesso_todas_empresas ? ($empresa_cliente['id'] ?? null) : $empresa_cliente['id'];
                    
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

                    // Se uma nova senha foi fornecida
                    if (!empty($_POST['senha'])) {
                        $sql = str_replace("SET nome", "SET senha = ?, nome", $sql);
                        array_splice($params, 0, 0, password_hash($_POST['senha'], PASSWORD_DEFAULT));
                    }

                    $sql .= " WHERE id = ?";
                    $params[] = $_POST['id'];
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);

                    // Atualizar permissões granulares baseadas no tipo de usuário
                    if ($_POST['tipo_usuario'] == 'admin') {
                        // Definir todas as permissões de admin
                        $permissoes_admin = [
                            'pode_editar_usuarios_sistema' => 1,
                            'pode_criar_usuarios_sistema' => 1,
                            'pode_acessar_lucratividade' => 1,
                            'pode_acessar_relatorios_avancados' => 1,
                            'pode_gerenciar_configuracoes' => 1,
                            'pode_aprovar_abastecimentos' => 1,
                            'pode_ver_dados_financeiros' => 1
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_admin as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $_POST['id'];
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                        
                    } elseif ($_POST['tipo_usuario'] == 'gestor') {
                        // Definir permissões de gestor
                        $permissoes_gestor = [
                            'pode_editar_usuarios_sistema' => 0,
                            'pode_criar_usuarios_sistema' => 0,
                            'pode_acessar_lucratividade' => 1,
                            'pode_acessar_relatorios_avancados' => 1,
                            'pode_gerenciar_configuracoes' => 0,
                            'pode_aprovar_abastecimentos' => 1,
                            'pode_ver_dados_financeiros' => 1
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_gestor as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $_POST['id'];
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                        
                    } else {
                        // Definir permissões de motorista (padrão)
                        $permissoes_motorista = [
                            'pode_editar_usuarios_sistema' => 0,
                            'pode_criar_usuarios_sistema' => 0,
                            'pode_acessar_lucratividade' => 0,
                            'pode_acessar_relatorios_avancados' => 0,
                            'pode_gerenciar_configuracoes' => 0,
                            'pode_aprovar_abastecimentos' => 0,
                            'pode_ver_dados_financeiros' => 0
                        ];
                        
                        $sql_permissoes = "UPDATE usuarios SET ";
                        $params_permissoes = [];
                        $campos = [];
                        
                        foreach ($permissoes_motorista as $campo => $valor) {
                            $campos[] = "{$campo} = ?";
                            $params_permissoes[] = $valor;
                        }
                        
                        $sql_permissoes .= implode(', ', $campos) . " WHERE id = ?";
                        $params_permissoes[] = $_POST['id'];
                        
                        $stmt_permissoes = $pdo->prepare($sql_permissoes);
                        $stmt_permissoes->execute($params_permissoes);
                    }

                    // Redirecionar para evitar reenvio do formulário
                    header('Location: usuarios.php?msg=updated');
                    exit;
                } catch (Exception $e) {
                    $mensagem = "Erro ao atualizar usuário: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'delete':
                try {
                    // Verificar se o usuário existe antes de excluir
                    $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
                    $stmt_check->execute([$_POST['id']]);
                    if (!$stmt_check->fetch()) {
                        throw new Exception("Usuário não encontrado.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    
                    // Redirecionar para evitar reenvio do formulário
                    header('Location: usuarios.php?msg=deleted');
                    exit;
                } catch (PDOException $e) {
                    $mensagem = "Erro ao excluir usuário: " . $e->getMessage();
                    $tipo_mensagem = "error";
                } catch (Exception $e) {
                    $mensagem = $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
        }
    }
}

// Buscar usuários com informações da empresa (incluindo ocultos para admins)
// Usar subquery para pegar apenas o registro mais antigo (menor ID) de cada email
try {
    // Buscar todos os usuários usando subqueries para evitar duplicação no JOIN
    // Isso garante que cada usuário apareça apenas uma vez, mesmo se houver múltiplos registros em empresa_clientes
    // Usar DISTINCT para garantir unicidade
    $stmt = $pdo->query("
        SELECT DISTINCT
            u.id, u.nome, u.email, u.senha, u.empresa_id, u.tipo_usuario, 
            u.status, u.is_admin, u.is_oculto, u.acesso_todas_empresas, u.data_cadastro,
            COALESCE(
                (SELECT razao_social FROM empresa_clientes WHERE id = u.empresa_id LIMIT 1),
                'N/A'
            ) as empresa_nome,
            COALESCE(
                (SELECT empresa_adm_id FROM empresa_clientes WHERE id = u.empresa_id LIMIT 1),
                NULL
            ) as empresa_adm_id
        FROM usuarios u 
        ORDER BY u.is_oculto DESC, u.nome, u.id
    ");
    $todos_usuarios = $stmt->fetchAll();
    
    // Filtrar apenas duplicados por ID usando array associativo (mais eficiente e seguro)
    // NÃO filtrar por email - se o banco está correto, todos os usuários devem aparecer
    $usuarios_por_id = [];
    foreach ($todos_usuarios as $usuario) {
        $id = $usuario['id'];
        // Usar ID como chave do array - garante unicidade automaticamente
        if (!isset($usuarios_por_id[$id])) {
            $usuarios_por_id[$id] = $usuario;
        }
    }
    
    // Converter de volta para array indexado e garantir ordem
    $usuarios = array_values($usuarios_por_id);
    
    // Debug: verificar se há duplicados (remover depois)
    $ids_finais = array_column($usuarios, 'id');
    $ids_unicos = array_unique($ids_finais);
    if (count($ids_finais) !== count($ids_unicos)) {
        error_log("AVISO: Há IDs duplicados no array usuarios! Total: " . count($ids_finais) . ", Únicos: " . count($ids_unicos));
    }
    
    // Garantir que empresa_adm_id esteja sempre preenchido para todos os usuários
    // Usar índice numérico para evitar problemas com referências
    for ($i = 0; $i < count($usuarios); $i++) {
        // Se empresa_adm_id estiver vazio/null, buscar novamente
        if (empty($usuarios[$i]['empresa_adm_id']) && !empty($usuarios[$i]['empresa_id'])) {
            $stmt_emp = $pdo->prepare("SELECT empresa_adm_id FROM empresa_clientes WHERE id = ? LIMIT 1");
            $stmt_emp->execute([$usuarios[$i]['empresa_id']]);
            $emp = $stmt_emp->fetch();
            if ($emp && !empty($emp['empresa_adm_id'])) {
                $usuarios[$i]['empresa_adm_id'] = $emp['empresa_adm_id'];
            }
        }
        // Se ainda estiver vazio, tentar buscar de outra forma
        if (empty($usuarios[$i]['empresa_adm_id']) && !empty($usuarios[$i]['empresa_id'])) {
            $stmt_emp = $pdo->prepare("
                SELECT ec.empresa_adm_id 
                FROM empresa_clientes ec 
                WHERE ec.id = ? 
                LIMIT 1
            ");
            $stmt_emp->execute([$usuarios[$i]['empresa_id']]);
            $emp = $stmt_emp->fetch(PDO::FETCH_ASSOC);
            if ($emp && isset($emp['empresa_adm_id']) && !empty($emp['empresa_adm_id'])) {
                $usuarios[$i]['empresa_adm_id'] = $emp['empresa_adm_id'];
            }
        }
    }

    // Buscar empresas para o select
    // Usar razao_social de empresa_clientes (que é o que aparece na listagem de usuários)
    // IMPORTANTE: usar empresa_clientes.razao_social, não empresa_adm.razao_social
    $stmt = $pdo->query("
        SELECT DISTINCT
            ec.empresa_adm_id,
            ec.razao_social 
        FROM empresa_clientes ec 
        WHERE ec.status = 'ativo'
        ORDER BY ec.razao_social ASC
    ");
    $empresas = $stmt->fetchAll();
    
    // Debug: verificar se as empresas estão sendo carregadas corretamente
    if (empty($empresas)) {
        error_log("AVISO: Nenhuma empresa encontrada para o select em usuarios.php");
    }
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar dados: " . $e->getMessage();
    $tipo_mensagem = "error";
    $usuarios = [];
    $empresas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Usuários - Sistema de Frotas</title>
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background: #f8f9fa;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header h1 {
            color: #333;
            margin: 0;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            background: #007bff;
            border: none;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #0056b3;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .actions {
            display: flex;
            gap: 10px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            position: relative;
        }
        .modal-content h2 {
            color: #333;
            margin-top: 0;
        }
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #333;
        }
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .badge-admin {
            background: #dc3545;
        }
        .badge-user {
            background: #28a745;
        }
        .badge-ativo {
            background: #28a745;
        }
        .badge-inativo {
            background: #dc3545;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Gerenciar Usuários</h1>
            <button class="btn" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Novo Usuário
            </button>
        </div>

        <?php if ($mensagem): ?>
            <div class="message <?php echo $tipo_mensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Empresa</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Oculto</th>
                        <th>Acesso Global</th>
                        <th>Data de Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ids_exibidos = [];
                    foreach ($usuarios as $usuario): 
                        // Proteção extra: garantir que cada ID seja exibido apenas uma vez
                        $id = $usuario['id'];
                        if (in_array($id, $ids_exibidos)) {
                            continue; // Pular se já foi exibido
                        }
                        $ids_exibidos[] = $id;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                            <td><?php echo htmlspecialchars($usuario['empresa_nome'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php echo $usuario['tipo_usuario'] == 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                    <?php 
                                    $tipo_display = $usuario['tipo_usuario'];
                                    if ($tipo_display == 'gestor') {
                                        $tipo_display = 'Usuário';
                                    }
                                    echo ucfirst($tipo_display); 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $usuario['status'] == 'ativo' ? 'badge-ativo' : 'badge-inativo'; ?>">
                                    <?php echo ucfirst($usuario['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($usuario['is_oculto']) && $usuario['is_oculto'] == 1): ?>
                                    <span class="badge badge-admin">Sim</span>
                                <?php else: ?>
                                    <span class="badge badge-inativo">Não</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($usuario['acesso_todas_empresas']) && $usuario['acesso_todas_empresas'] == 1): ?>
                                    <span class="badge badge-admin">Sim</span>
                                <?php else: ?>
                                    <span class="badge badge-inativo">Não</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></td>
                            <td class="actions">
                                <button class="btn" onclick="showEditModal(<?php 
                                    // Garantir que empresa_adm_id seja sempre um valor válido (não null)
                                    $usuario_js = $usuario;
                                    if (empty($usuario_js['empresa_adm_id']) || $usuario_js['empresa_adm_id'] === null) {
                                        $usuario_js['empresa_adm_id'] = '';
                                    } else {
                                        // Garantir que seja string ou número
                                        $usuario_js['empresa_adm_id'] = (string)$usuario_js['empresa_adm_id'];
                                    }
                                    echo htmlspecialchars(json_encode($usuario_js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); 
                                ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="confirmDelete(<?php echo $usuario['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Adicionar -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h2>Novo Usuário</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="nome">Nome:</label>
                    <input type="text" id="nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha:</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <div class="form-group">
                    <label for="empresa_id">Empresa:</label>
                    <select id="empresa_id" name="empresa_id" required>
                        <option value="">Selecione uma empresa</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['empresa_adm_id']; ?>">
                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="tipo_usuario">Tipo de Usuário:</label>
                    <select id="tipo_usuario" name="tipo_usuario" required>
                        <option value="gestor">Usuário</option>
                        <option value="admin">Administrador</option>
                        <option value="motorista">Motorista</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="is_oculto" name="is_oculto" value="1">
                        <span>Usuário Oculto (não aparece nas listagens)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="acesso_todas_empresas" name="acesso_todas_empresas" value="1">
                        <span>Acesso a Todas as Empresas</span>
                    </label>
                </div>
                <button type="submit" class="btn">Salvar</button>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Editar Usuário</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_nome">Nome:</label>
                    <input type="text" id="edit_nome" name="nome" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_senha">Nova Senha: (deixe em branco para manter a atual)</label>
                    <input type="password" id="edit_senha" name="senha">
                </div>
                <div class="form-group">
                    <label for="edit_empresa_id">Empresa:</label>
                    <select id="edit_empresa_id" name="empresa_id" required>
                        <option value="">Selecione uma empresa</option>
                        <?php foreach ($empresas as $empresa): ?>
                            <option value="<?php echo $empresa['empresa_adm_id']; ?>">
                                <?php echo htmlspecialchars($empresa['razao_social']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_tipo_usuario">Tipo de Usuário:</label>
                    <select id="edit_tipo_usuario" name="tipo_usuario" required>
                        <option value="gestor">Usuário</option>
                        <option value="admin">Administrador</option>
                        <option value="motorista">Motorista</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select id="edit_status" name="status" required>
                        <option value="ativo">Ativo</option>
                        <option value="inativo">Inativo</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="edit_is_oculto" name="is_oculto" value="1">
                        <span>Usuário Oculto (não aparece nas listagens)</span>
                    </label>
                </div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" id="edit_acesso_todas_empresas" name="acesso_todas_empresas" value="1">
                        <span>Acesso a Todas as Empresas</span>
                    </label>
                </div>
                <button type="submit" class="btn">Atualizar</button>
            </form>
        </div>
    </div>

    <!-- Formulário de Exclusão -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function showEditModal(usuario) {
            document.getElementById('edit_id').value = usuario.id;
            document.getElementById('edit_nome').value = usuario.nome || '';
            document.getElementById('edit_email').value = usuario.email || '';
            
            // Definir empresa - garantir que o valor seja válido
            let empresaAdmId = usuario.empresa_adm_id;
            if (!empresaAdmId || empresaAdmId === 'null' || empresaAdmId === 'undefined') {
                // Se empresa_adm_id não estiver disponível, tentar buscar via empresa_id
                console.warn('empresa_adm_id não encontrado para usuário:', usuario.id);
                empresaAdmId = '';
            }
            
            // Verificar se o valor existe nas opções do select
            const selectEmpresa = document.getElementById('edit_empresa_id');
            
            // Converter para string para comparação correta
            empresaAdmId = String(empresaAdmId);
            
            // Debug: mostrar valores disponíveis
            console.log('Buscando empresa_adm_id:', empresaAdmId);
            console.log('Opções disponíveis:', Array.from(selectEmpresa.options).map(opt => ({value: opt.value, text: opt.text})));
            
            // Tentar encontrar a opção (comparação estrita como string)
            let optionFound = false;
            for (let i = 0; i < selectEmpresa.options.length; i++) {
                if (String(selectEmpresa.options[i].value) === empresaAdmId) {
                    selectEmpresa.selectedIndex = i;
                    optionFound = true;
                    console.log('Empresa selecionada:', selectEmpresa.options[i].text);
                    break;
                }
            }
            
            if (!optionFound && empresaAdmId) {
                console.warn('Empresa ID não encontrado nas opções:', empresaAdmId, 'para usuário:', usuario.id);
                selectEmpresa.value = '';
            }
            
            // Mapear o valor para o select
            let tipoUsuario = usuario.tipo_usuario;
            if (tipoUsuario === 'gestor') {
                tipoUsuario = 'gestor'; // Mantém como gestor para o value
            }
            document.getElementById('edit_tipo_usuario').value = tipoUsuario || 'gestor';
            document.getElementById('edit_status').value = usuario.status || 'ativo';
            
            // Preencher checkboxes
            document.getElementById('edit_is_oculto').checked = usuario.is_oculto == 1;
            document.getElementById('edit_acesso_todas_empresas').checked = usuario.acesso_todas_empresas == 1;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Tem certeza que deseja excluir este usuário?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html> 