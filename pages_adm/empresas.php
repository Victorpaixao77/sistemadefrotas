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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    // Verificar se o CNPJ já existe
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE cnpj = ?");
                    $stmt->execute([$_POST['cnpj']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este CNPJ já está cadastrado.");
                    }

                    // Verificar se o email já existe
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado.");
                    }

                    // Iniciar transação
                    $pdo->beginTransaction();

                    // Inserir na tabela empresa_adm
                    $stmt = $pdo->prepare("INSERT INTO empresa_adm (razao_social, cnpj, telefone, email, valor_por_veiculo, plano) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $_POST['valor_por_veiculo'],
                        $_POST['plano']
                    ]);

                    // Obter o ID da empresa_adm inserida
                    $empresa_adm_id = $pdo->lastInsertId();

                    // Inserir na tabela empresa_clientes
                    $stmt = $pdo->prepare("INSERT INTO empresa_clientes (empresa_adm_id, razao_social, cnpj, telefone, email, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                    $stmt->execute([
                        $empresa_adm_id,
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email']
                    ]);

                    // Confirmar transação
                    $pdo->commit();

                    $mensagem = "Empresa cadastrada com sucesso!";
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    // Reverter transação em caso de erro
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao cadastrar empresa: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'edit':
                try {
                    // Verificar se o CNPJ já existe para outra empresa
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE cnpj = ? AND id != ?");
                    $stmt->execute([$_POST['cnpj'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este CNPJ já está cadastrado para outra empresa.");
                    }

                    // Verificar se o email já existe para outra empresa
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado para outra empresa.");
                    }

                    // Iniciar transação
                    $pdo->beginTransaction();

                    // Atualizar na tabela empresa_adm
                    $stmt = $pdo->prepare("UPDATE empresa_adm SET razao_social = ?, cnpj = ?, telefone = ?, email = ?, valor_por_veiculo = ?, plano = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $_POST['valor_por_veiculo'],
                        $_POST['plano'],
                        $_POST['id']
                    ]);

                    // Atualizar na tabela empresa_clientes
                    $stmt = $pdo->prepare("UPDATE empresa_clientes SET razao_social = ?, cnpj = ?, telefone = ?, email = ? WHERE empresa_adm_id = ?");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $_POST['id']
                    ]);

                    // Confirmar transação
                    $pdo->commit();

                    $mensagem = "Empresa atualizada com sucesso!";
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    // Reverter transação em caso de erro
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao atualizar empresa: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'delete':
                try {
                    // Iniciar transação
                    $pdo->beginTransaction();

                    // Excluir da tabela empresa_clientes primeiro (devido à chave estrangeira)
                    $stmt = $pdo->prepare("DELETE FROM empresa_clientes WHERE empresa_adm_id = ?");
                    $stmt->execute([$_POST['id']]);

                    // Excluir da tabela empresa_adm
                    $stmt = $pdo->prepare("DELETE FROM empresa_adm WHERE id = ?");
                    $stmt->execute([$_POST['id']]);

                    // Confirmar transação
                    $pdo->commit();

                    $mensagem = "Empresa excluída com sucesso!";
                    $tipo_mensagem = "success";
                } catch (PDOException $e) {
                    // Reverter transação em caso de erro
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao excluir empresa: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
        }
    }
}

// Buscar empresas
try {
    $stmt = $pdo->query("SELECT * FROM empresa_adm ORDER BY razao_social");
    $empresas = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar empresas: " . $e->getMessage();
    $tipo_mensagem = "error";
    $empresas = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Empresas - Sistema de Frotas</title>
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
        .badge-basic {
            background: #6c757d;
        }
        .badge-premium {
            background: #28a745;
        }
        .badge-enterprise {
            background: #007bff;
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
            <h1>Gerenciar Empresas</h1>
            <button class="btn" onclick="showAddModal()">
                <i class="fas fa-plus"></i> Nova Empresa
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
                        <th>Razão Social</th>
                        <th>CNPJ</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Valor por Veículo</th>
                        <th>Plano</th>
                        <th>Data de Cadastro</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas as $empresa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($empresa['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                            <td><?php echo htmlspecialchars($empresa['email']); ?></td>
                            <td><?php echo htmlspecialchars($empresa['telefone']); ?></td>
                            <td>R$ <?php echo number_format($empresa['valor_por_veiculo'], 2, ',', '.'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($empresa['plano']); ?>">
                                    <?php echo ucfirst($empresa['plano']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($empresa['data_cadastro'])); ?></td>
                            <td class="actions">
                                <button class="btn" onclick="showEditModal(<?php echo htmlspecialchars(json_encode($empresa)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-danger" onclick="confirmDelete(<?php echo $empresa['id']; ?>)">
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
            <h2>Nova Empresa</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="razao_social">Razão Social:</label>
                    <input type="text" id="razao_social" name="razao_social" required>
                </div>
                <div class="form-group">
                    <label for="cnpj">CNPJ:</label>
                    <input type="text" id="cnpj" name="cnpj" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="telefone">Telefone:</label>
                    <input type="text" id="telefone" name="telefone">
                </div>
                <div class="form-group">
                    <label for="valor_por_veiculo">Valor por Veículo:</label>
                    <input type="number" id="valor_por_veiculo" name="valor_por_veiculo" step="0.01" value="0.00">
                </div>
                <div class="form-group">
                    <label for="plano">Plano:</label>
                    <select id="plano" name="plano">
                        <option value="">Selecione um plano</option>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
                </div>
                <button type="submit" class="btn">Salvar</button>
            </form>
        </div>
    </div>

    <!-- Modal Editar -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Editar Empresa</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="form-group">
                    <label for="edit_razao_social">Razão Social:</label>
                    <input type="text" id="edit_razao_social" name="razao_social" required>
                </div>
                <div class="form-group">
                    <label for="edit_cnpj">CNPJ:</label>
                    <input type="text" id="edit_cnpj" name="cnpj" required>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="edit_telefone">Telefone:</label>
                    <input type="text" id="edit_telefone" name="telefone">
                </div>
                <div class="form-group">
                    <label for="edit_valor_por_veiculo">Valor por Veículo:</label>
                    <input type="number" id="edit_valor_por_veiculo" name="valor_por_veiculo" step="0.01">
                </div>
                <div class="form-group">
                    <label for="edit_plano">Plano:</label>
                    <select id="edit_plano" name="plano">
                        <option value="">Selecione um plano</option>
                        <option value="basic">Basic</option>
                        <option value="premium">Premium</option>
                        <option value="enterprise">Enterprise</option>
                    </select>
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

        function showEditModal(empresa) {
            document.getElementById('edit_id').value = empresa.id;
            document.getElementById('edit_razao_social').value = empresa.razao_social;
            document.getElementById('edit_cnpj').value = empresa.cnpj;
            document.getElementById('edit_email').value = empresa.email;
            document.getElementById('edit_telefone').value = empresa.telefone;
            document.getElementById('edit_valor_por_veiculo').value = empresa.valor_por_veiculo;
            document.getElementById('edit_plano').value = empresa.plano;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmDelete(id) {
            if (confirm('Tem certeza que deseja excluir esta empresa?')) {
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

        // Máscara para CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        document.getElementById('edit_cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d)(\d{4})$/, '$1-$2');
                e.target.value = value;
            }
        });

        document.getElementById('edit_telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                value = value.replace(/(\d)(\d{4})$/, '$1-$2');
                e.target.value = value;
            }
        });
    </script>
</body>
</html> 