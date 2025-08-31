<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

if (!isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Gerenciamento de Usuários';
$empresa_id = $_SESSION['empresa_id'];

// Buscar usuários de motoristas
$conn = getConnection();
$stmt = $conn->prepare('
    SELECT um.*, m.nome as nome_motorista, m.cpf 
    FROM usuarios_motoristas um 
    JOIN motoristas m ON um.motorista_id = m.id 
    WHERE um.empresa_id = :empresa_id 
    ORDER BY um.data_cadastro DESC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                
                <!-- Lista de Usuários -->
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Usuários Cadastrados</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th style="width: 25%;">Motorista</th>
                                            <th style="width: 15%;">CPF</th>
                                            <th style="width: 20%;">Usuário</th>
                                            <th style="width: 10%;">Status</th>
                                            <th style="width: 15%;">Data Cadastro</th>
                                            <th style="width: 15%;">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td style="padding: 12px 8px;"><?php echo htmlspecialchars($usuario['nome_motorista']); ?></td>
                                            <td style="padding: 12px 8px;"><?php echo htmlspecialchars($usuario['cpf']); ?></td>
                                            <td style="padding: 12px 8px;"><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                            <td style="padding: 12px 8px;">
                                                <span class="badge <?php echo $usuario['status'] === 'ativo' ? 'badge-success' : 'badge-danger'; ?>">
                                                    <?php echo ucfirst($usuario['status']); ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 8px;"><?php echo date('d/m/Y H:i', strtotime($usuario['data_cadastro'])); ?></td>
                                            <td style="padding: 12px 8px;">
                                                <button class="btn btn-sm btn-primary" onclick="editarUsuario(<?php echo $usuario['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="excluirUsuario(<?php echo $usuario['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Cadastro de Usuário -->
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Cadastro de Usuário</h3>
                        </div>
                        <div class="card-body">
                            <form id="cadastroForm" method="post" action="../api/usuarios_motoristas.php">
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
                                    <label for="nome">Nome de Usuário</label>
                                    <input type="text" id="nome" name="nome" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="senha">Senha</label>
                                    <input type="password" id="senha" name="senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha">Confirmar Senha</label>
                                    <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Cadastrar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    // Validação do formulário
    document.getElementById('cadastroForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('senha').value;
        const confirmarSenha = document.getElementById('confirmar_senha').value;
        
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
                alert('Usuário cadastrado com sucesso!');
                window.location.reload();
            } else {
                alert(data.error || 'Erro ao cadastrar usuário.');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao cadastrar usuário.');
        });
    });

    // Função para editar usuário
    function editarUsuario(id) {
        // Implementar edição
        alert('Funcionalidade em desenvolvimento');
    }

    // Função para excluir usuário
    function excluirUsuario(id) {
        if (confirm('Tem certeza que deseja excluir este usuário?')) {
            fetch('../api/usuarios_motoristas.php', {
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
    </script>
</body>
</html> 