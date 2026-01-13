<?php
require_once '../includes/conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Processar ações (mantendo toda a lógica existente)
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE cnpj = ?");
                    $stmt->execute([$_POST['cnpj']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este CNPJ já está cadastrado.");
                    }

                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE email = ?");
                    $stmt->execute([$_POST['email']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado.");
                    }

                    $pdo->beginTransaction();

                    // Sistema Seguro: Sim ou Não
                    $tem_acesso_seguro = isset($_POST['tem_acesso_seguro']) && $_POST['tem_acesso_seguro'] === 'sim' ? 'sim' : 'nao';
                    
                    // Buscar valor_por_veiculo do plano se selecionado
                    $plano_id = !empty($_POST['plano_id']) ? (int)$_POST['plano_id'] : null;
                    $valor_por_veiculo = $_POST['valor_por_veiculo'] ?? 0;
                    
                    if ($plano_id) {
                        $stmt_plano = $pdo->prepare("SELECT valor_por_veiculo FROM adm_planos WHERE id = ?");
                        $stmt_plano->execute([$plano_id]);
                        $plano_data = $stmt_plano->fetch();
                        if ($plano_data) {
                            $valor_por_veiculo = $plano_data['valor_por_veiculo'];
                        }
                    }

                    // Manter campo 'plano' como 'basic' por padrão (compatibilidade)
                    $stmt = $pdo->prepare("INSERT INTO empresa_adm (razao_social, cnpj, telefone, email, valor_por_veiculo, plano, plano_id, tem_acesso_seguro) VALUES (?, ?, ?, ?, ?, 'basic', ?, ?)");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $valor_por_veiculo,
                        $plano_id,
                        $tem_acesso_seguro
                    ]);

                    $empresa_adm_id = $pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO empresa_clientes (empresa_adm_id, razao_social, cnpj, telefone, email, status) VALUES (?, ?, ?, ?, ?, 'ativo')");
                    $stmt->execute([
                        $empresa_adm_id,
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email']
                    ]);

                    $empresa_cliente_id = $pdo->lastInsertId();

                    $nome_padrao = 'Frotec Online';
                    $logo_padrao = 'logo.png';
                    $stmt = $pdo->prepare("INSERT INTO configuracoes (
                        empresa_id, cor_menu, nome_personalizado, logo_empresa, data_criacao, data_atualizacao,
                        notificar_abastecimentos, notificar_manutencoes, notificar_viagens,
                        limite_km_manutencao, limite_dias_manutencao, notificar_pneus_vida_util,
                        notificar_pneus_recapagem, notificar_pneus_troca_frequente,
                        calcular_todas_despesas, calcular_despesas_fixas, calcular_despesas_viagem,
                        calcular_abastecimentos, calcular_manutencao, calcular_manutencao_pneus, calcular_comissoes
                    ) VALUES (
                        ?, '#343a40', ?, ?, NOW(), NOW(),
                        0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0
                    )");
                    $stmt->execute([
                        $empresa_cliente_id,
                        $nome_padrao,
                        $logo_padrao
                    ]);

                    if ($tem_acesso_seguro === 'sim') {
                        $stmt = $pdo->prepare("
                            INSERT INTO seguro_empresa_clientes 
                            (empresa_adm_id, razao_social, cnpj, email, telefone, porcentagem_fixa, unidade, status)
                            VALUES (?, ?, ?, ?, ?, 5.00, 'Matriz', 'ativo')
                        ");
                        $stmt->execute([
                            $empresa_adm_id,
                            $_POST['razao_social'],
                            $_POST['cnpj'],
                            $_POST['email'],
                            $_POST['telefone']
                        ]);

                        $seguro_empresa_id = $pdo->lastInsertId();
                        $senha_padrao = password_hash('123456', PASSWORD_DEFAULT);
                        $nome_admin = explode('@', $_POST['email'])[0];

                        $stmt = $pdo->prepare("
                            INSERT INTO seguro_usuarios 
                            (seguro_empresa_id, nome, email, senha, nivel_acesso, status)
                            VALUES (?, ?, ?, ?, 'admin', 'ativo')
                        ");
                        $stmt->execute([
                            $seguro_empresa_id,
                            $nome_admin,
                            $_POST['email'],
                            $senha_padrao
                        ]);
                    }

                    $pdo->commit();

                    $mensagem_extra = $tem_acesso_seguro === 'sim' ? ' Sistema Seguro habilitado! Login: ' . $_POST['email'] . ' | Senha: 123456' : '';
                    $mensagem = "Empresa cadastrada com sucesso!" . $mensagem_extra;
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao cadastrar empresa: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'edit':
                try {
                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE cnpj = ? AND id != ?");
                    $stmt->execute([$_POST['cnpj'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este CNPJ já está cadastrado para outra empresa.");
                    }

                    $stmt = $pdo->prepare("SELECT id FROM empresa_adm WHERE email = ? AND id != ?");
                    $stmt->execute([$_POST['email'], $_POST['id']]);
                    if ($stmt->fetch()) {
                        throw new Exception("Este email já está cadastrado para outra empresa.");
                    }

                    $pdo->beginTransaction();

                    // Sistema Seguro: Sim ou Não
                    $tem_acesso_seguro = isset($_POST['tem_acesso_seguro']) && $_POST['tem_acesso_seguro'] === 'sim' ? 'sim' : 'nao';
                    
                    // Buscar valor_por_veiculo do plano se selecionado
                    $plano_id = !empty($_POST['plano_id']) ? (int)$_POST['plano_id'] : null;
                    $valor_por_veiculo = $_POST['valor_por_veiculo'] ?? 0;
                    
                    if ($plano_id) {
                        $stmt_plano = $pdo->prepare("SELECT valor_por_veiculo FROM adm_planos WHERE id = ?");
                        $stmt_plano->execute([$plano_id]);
                        $plano_data = $stmt_plano->fetch();
                        if ($plano_data) {
                            $valor_por_veiculo = $plano_data['valor_por_veiculo'];
                        }
                    }

                    // Manter campo 'plano' como 'basic' por padrão (compatibilidade)
                    $stmt = $pdo->prepare("UPDATE empresa_adm SET razao_social = ?, cnpj = ?, telefone = ?, email = ?, valor_por_veiculo = ?, plano = 'basic', plano_id = ?, tem_acesso_seguro = ? WHERE id = ?");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $valor_por_veiculo,
                        $plano_id,
                        $tem_acesso_seguro,
                        $_POST['id']
                    ]);

                    $stmt = $pdo->prepare("UPDATE empresa_clientes SET razao_social = ?, cnpj = ?, telefone = ?, email = ? WHERE empresa_adm_id = ?");
                    $stmt->execute([
                        $_POST['razao_social'],
                        $_POST['cnpj'],
                        $_POST['telefone'],
                        $_POST['email'],
                        $_POST['id']
                    ]);

                    $mensagem_extra = '';

                    if ($tem_acesso_seguro === 'sim') {
                        $stmt = $pdo->prepare("SELECT id FROM seguro_empresa_clientes WHERE empresa_adm_id = ?");
                        $stmt->execute([$_POST['id']]);
                        $seguro_existe = $stmt->fetch();

                        if (!$seguro_existe) {
                            $stmt = $pdo->prepare("
                                INSERT INTO seguro_empresa_clientes 
                                (empresa_adm_id, razao_social, cnpj, email, telefone, porcentagem_fixa, unidade, status)
                                VALUES (?, ?, ?, ?, ?, 5.00, 'Matriz', 'ativo')
                            ");
                            $stmt->execute([
                                $_POST['id'],
                                $_POST['razao_social'],
                                $_POST['cnpj'],
                                $_POST['email'],
                                $_POST['telefone']
                            ]);

                            $seguro_empresa_id = $pdo->lastInsertId();

                            $stmt = $pdo->prepare("SELECT id FROM seguro_usuarios WHERE email = ?");
                            $stmt->execute([$_POST['email']]);
                            $usuario_existe = $stmt->fetch();

                            if (!$usuario_existe) {
                                $senha_padrao = password_hash('123456', PASSWORD_DEFAULT);
                                $nome_admin = explode('@', $_POST['email'])[0];

                                $stmt = $pdo->prepare("
                                    INSERT INTO seguro_usuarios 
                                    (seguro_empresa_id, nome, email, senha, nivel_acesso, status)
                                    VALUES (?, ?, ?, ?, 'admin', 'ativo')
                                ");
                                $stmt->execute([
                                    $seguro_empresa_id,
                                    $nome_admin,
                                    $_POST['email'],
                                    $senha_padrao
                                ]);

                                $mensagem_extra = ' | Sistema Seguro HABILITADO! Login: ' . $_POST['email'] . ' | Senha: 123456';
                            } else {
                                $stmt = $pdo->prepare("UPDATE seguro_usuarios SET status = 'ativo', seguro_empresa_id = ? WHERE email = ?");
                                $stmt->execute([$seguro_empresa_id, $_POST['email']]);
                                $mensagem_extra = ' | Sistema Seguro REATIVADO!';
                            }
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE seguro_empresa_clientes 
                                SET razao_social = ?, cnpj = ?, email = ?, telefone = ?, status = 'ativo'
                                WHERE empresa_adm_id = ?
                            ");
                            $stmt->execute([
                                $_POST['razao_social'],
                                $_POST['cnpj'],
                                $_POST['email'],
                                $_POST['telefone'],
                                $_POST['id']
                            ]);

                            $stmt = $pdo->prepare("UPDATE seguro_usuarios SET status = 'ativo' WHERE seguro_empresa_id = (SELECT id FROM seguro_empresa_clientes WHERE empresa_adm_id = ?)");
                            $stmt->execute([$_POST['id']]);
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE seguro_empresa_clientes SET status = 'inativo' WHERE empresa_adm_id = ?");
                        $stmt->execute([$_POST['id']]);

                        $stmt = $pdo->prepare("UPDATE seguro_usuarios SET status = 'inativo' WHERE seguro_empresa_id IN (SELECT id FROM seguro_empresa_clientes WHERE empresa_adm_id = ?)");
                        $stmt->execute([$_POST['id']]);
                    }

                    $pdo->commit();

                    $mensagem = "Empresa atualizada com sucesso!" . $mensagem_extra;
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $mensagem = "Erro ao atualizar empresa: " . $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;

            case 'delete':
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("DELETE FROM empresa_clientes WHERE empresa_adm_id = ?");
                    $stmt->execute([$_POST['id']]);

                    $stmt = $pdo->prepare("DELETE FROM empresa_adm WHERE id = ?");
                    $stmt->execute([$_POST['id']]);

                    $pdo->commit();

                    $mensagem = "Empresa excluída com sucesso!";
                    $tipo_mensagem = "success";
                } catch (PDOException $e) {
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

// Criar coluna plano_id se não existir
try {
    $pdo->exec("ALTER TABLE empresa_adm ADD COLUMN plano_id INT NULL COMMENT 'ID do plano de cobrança' AFTER plano");
} catch (PDOException $e) {
    // Coluna já existe
}

// Buscar empresas com dados adicionais
try {
    $stmt = $pdo->query("
        SELECT e.*, 
               (SELECT COUNT(*) FROM empresa_clientes ec WHERE ec.empresa_adm_id = e.id) as total_clientes,
               (SELECT COUNT(*) FROM veiculos v 
                INNER JOIN empresa_clientes ec ON v.empresa_id = ec.id 
                WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1, 2)) as total_veiculos_ativos,
               p.nome as plano_nome,
               p.tipo as plano_tipo,
               p.valor_por_veiculo as plano_valor_por_veiculo,
               p.limite_veiculos as plano_limite_veiculos,
               p.valor_maximo as plano_valor_maximo,
               CASE 
                   WHEN p.id IS NOT NULL AND p.tipo = 'pacote' THEN 
                       -- Planos tipo PACOTE: valor fixo (valor_maximo)
                       COALESCE(p.valor_maximo, 0)
                   WHEN p.id IS NOT NULL AND p.tipo = 'avulso' THEN 
                       -- Planos tipo AVULSO: calcula por veículo
                       (SELECT COUNT(*) FROM veiculos v 
                        INNER JOIN empresa_clientes ec ON v.empresa_id = ec.id 
                        WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1, 2)) * p.valor_por_veiculo
                   ELSE 
                       -- Sem plano: calcula por veículo usando valor_por_veiculo da empresa
                       (SELECT COUNT(*) FROM veiculos v 
                        INNER JOIN empresa_clientes ec ON v.empresa_id = ec.id 
                        WHERE ec.empresa_adm_id = e.id AND v.status_id IN (1, 2)) * e.valor_por_veiculo
               END as valor_total
        FROM empresa_adm e 
        LEFT JOIN adm_planos p ON e.plano_id = p.id
        ORDER BY e.razao_social
    ");
    $empresas = $stmt->fetchAll();
    
    // Buscar planos para o select
    $planos_stmt = $pdo->query("SELECT id, nome, tipo, limite_veiculos, valor_por_veiculo, valor_maximo FROM adm_planos WHERE status = 'ativo' ORDER BY ordem ASC, nome ASC");
    $planos = $planos_stmt->fetchAll();
} catch (PDOException $e) {
    $mensagem = "Erro ao carregar empresas: " . $e->getMessage();
    $tipo_mensagem = "error";
    $empresas = [];
    $planos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Empresas - Sistema de Frotas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f8f9fa;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            z-index: 1000;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        
        .sidebar .nav-link {
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 5px 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        /* Header */
        .header {
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        /* Table Container */
        .table-container {
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            background: white;
        }
        
        /* Action Icons */
        .action-icons {
            display: flex;
            gap: 3px;
            flex-wrap: nowrap;
            justify-content: center;
        }
        
        .action-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }
        
        .action-icon:hover {
            transform: scale(1.1);
        }
        
        .icon-view { background-color: #17a2b8; color: white; }
        .icon-edit { background-color: #ffc107; color: #212529; }
        .icon-money { background-color: #28a745; color: white; }
        .icon-log { background-color: #6c757d; color: white; }
        .icon-user { background-color: #007bff; color: white; }
        
        /* Badges */
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
                margin-left: 0;
            }
            .sidebar {
                transform: translateX(-100%);
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">
                        <i class="fas fa-building me-2"></i>
                        Gerenciar Empresas
                    </h2>
                    <p class="text-muted mb-0">Cadastro e gestão de empresas clientes</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovaEmpresa">
                    <i class="fas fa-plus me-1"></i> Nova Empresa
                </button>
            </div>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="120">Ações</th>
                            <th>Razão Social</th>
                            <th>CNPJ</th>
                            <th>Email</th>
                            <th>Telefone</th>
                            <th>Valor/Veículo</th>
                            <th>Plano</th>
                            <th>Sistema Seguro</th>
                            <th>Data Cadastro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empresas)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">Nenhuma empresa cadastrada</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empresas as $empresa): ?>
                                <tr>
                                    <td>
                                        <div class="action-icons">
                                            <div class="action-icon icon-view" title="Visualizar" onclick="abrirVisualizar(<?php echo htmlspecialchars(json_encode($empresa)); ?>)">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                            <div class="action-icon icon-edit" title="Editar" onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($empresa)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-icon icon-money" title="Posição Financeira" onclick="abrirFinanceiro(<?php echo $empresa['id']; ?>)">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <div class="action-icon icon-log" title="Log de Acessos" onclick="abrirLogAcessos(<?php echo $empresa['id']; ?>)">
                                                <i class="fas fa-history"></i>
                                            </div>
                                            <div class="action-icon icon-user" title="Usuários" onclick="abrirUsuarios(<?php echo $empresa['id']; ?>)">
                                                <i class="fas fa-users"></i>
                                            </div>
                                        </div>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($empresa['razao_social']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                                    <td><?php echo htmlspecialchars($empresa['email']); ?></td>
                                    <td><?php echo htmlspecialchars($empresa['telefone']); ?></td>
                                    <td>
                                        <?php 
                                        $total_veiculos = isset($empresa['total_veiculos_ativos']) ? (int)$empresa['total_veiculos_ativos'] : 0;
                                        $valor_total = isset($empresa['valor_total']) ? (float)$empresa['valor_total'] : 0;
                                        $plano_tipo = isset($empresa['plano_tipo']) ? $empresa['plano_tipo'] : null;
                                        $plano_limite = isset($empresa['plano_limite_veiculos']) ? (int)$empresa['plano_limite_veiculos'] : null;
                                        
                                        // Determinar valor por veículo para exibição
                                        if (!empty($empresa['plano_valor_por_veiculo'])) {
                                            $valor_por_veiculo = $empresa['plano_valor_por_veiculo'];
                                        } else {
                                            $valor_por_veiculo = $empresa['valor_por_veiculo'];
                                        }
                                        
                                        // Verificar se ultrapassou limite do plano
                                        $ultrapassou_limite = false;
                                        if ($plano_limite !== null && $total_veiculos > $plano_limite) {
                                            $ultrapassou_limite = true;
                                        }
                                        ?>
                                        <strong>R$ <?php echo number_format($valor_total, 2, ',', '.'); ?></strong>
                                        <?php if ($plano_tipo === 'pacote'): ?>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-box"></i> Valor fixo do plano
                                            </small>
                                        <?php else: ?>
                                            <br>
                                            <small class="text-muted">
                                                (<?php echo $total_veiculos; ?> veículo<?php echo $total_veiculos != 1 ? 's' : ''; ?> × R$ <?php echo number_format($valor_por_veiculo, 2, ',', '.'); ?>)
                                            </small>
                                        <?php endif; ?>
                                        <?php if ($ultrapassou_limite): ?>
                                            <br>
                                            <small class="text-danger" style="font-weight: bold;">
                                                <i class="fas fa-exclamation-triangle"></i> Excede limite (<?php echo $plano_limite; ?> veículos)
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($empresa['plano_nome'])): ?>
                                            <span class="badge bg-info">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($empresa['plano_nome']); ?>
                                            </span>
                                            <?php if ($ultrapassou_limite): ?>
                                                <br>
                                                <small class="text-danger" style="font-weight: bold;">
                                                    <i class="fas fa-exclamation-triangle"></i> Upgrade necessário
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Sem plano</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $tem_acesso = isset($empresa['tem_acesso_seguro']) && $empresa['tem_acesso_seguro'] === 'sim';
                                        if ($tem_acesso): 
                                        ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle"></i> Ativo
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="fas fa-times-circle"></i> Não
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($empresa['data_cadastro'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Nova Empresa -->
    <div class="modal fade" id="modalNovaEmpresa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Nova Empresa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="razao_social" class="form-label">Razão Social *</label>
                            <input type="text" class="form-control" id="razao_social" name="razao_social" required>
                        </div>
                        <div class="mb-3">
                            <label for="cnpj" class="form-label">CNPJ *</label>
                            <input type="text" class="form-control" id="cnpj" name="cnpj" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="telefone" name="telefone">
                        </div>
                        <div class="mb-3">
                            <label for="plano_id" class="form-label">Plano de Cobrança</label>
                            <select class="form-select" id="plano_id" name="plano_id" onchange="atualizarValorPorVeiculo()">
                                <option value="">Selecione um plano (opcional)</option>
                                <?php foreach ($planos as $plano): ?>
                                    <option value="<?php echo $plano['id']; ?>" 
                                            data-valor="<?php echo $plano['valor_por_veiculo']; ?>"
                                            data-limite="<?php echo $plano['limite_veiculos'] ?? 'Ilimitado'; ?>"
                                            data-maximo="<?php echo $plano['valor_maximo'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($plano['nome']); ?> 
                                        (R$ <?php echo number_format($plano['valor_por_veiculo'], 2, ',', '.'); ?>/veículo
                                        <?php if ($plano['limite_veiculos']): ?>
                                            - até <?php echo $plano['limite_veiculos']; ?> veículos
                                        <?php else: ?>
                                            - ilimitado
                                        <?php endif; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecione um plano para aplicar valores automáticos</small>
                        </div>
                        <div class="mb-3">
                            <label for="valor_por_veiculo" class="form-label">Valor por Veículo (R$)</label>
                            <input type="number" class="form-control" id="valor_por_veiculo" name="valor_por_veiculo" step="0.01" value="0.00">
                            <small class="text-muted">Será preenchido automaticamente se um plano for selecionado</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tem_acesso_seguro" value="sim" id="tem_acesso_seguro">
                                <label class="form-check-label" for="tem_acesso_seguro">
                                    <strong><i class="fas fa-shield-alt"></i> Acesso ao Sistema Seguro</strong>
                                </label>
                            </div>
                            <small class="text-muted">Marque esta opção se a empresa terá acesso ao módulo de gestão de clientes comissionados (Sistema Seguro).</small>
                            <div class="alert alert-info mt-2" id="infoAcessoSeguro" style="display:none;">
                                <small><strong>Credenciais de acesso:</strong> E-mail cadastrado | Senha: 123456</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Empresa -->
    <div class="modal fade" id="modalEditarEmpresa" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Empresa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_razao_social" class="form-label">Razão Social *</label>
                            <input type="text" class="form-control" id="edit_razao_social" name="razao_social" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_cnpj" class="form-label">CNPJ *</label>
                            <input type="text" class="form-control" id="edit_cnpj" name="cnpj" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_telefone" class="form-label">Telefone</label>
                            <input type="text" class="form-control" id="edit_telefone" name="telefone">
                        </div>
                        <div class="mb-3">
                            <label for="edit_plano_id" class="form-label">Plano de Cobrança</label>
                            <select class="form-select" id="edit_plano_id" name="plano_id" onchange="atualizarValorPorVeiculoEdit()">
                                <option value="">Selecione um plano (opcional)</option>
                                <?php foreach ($planos as $plano): ?>
                                    <option value="<?php echo $plano['id']; ?>" 
                                            data-valor="<?php echo $plano['valor_por_veiculo']; ?>"
                                            data-limite="<?php echo $plano['limite_veiculos'] ?? 'Ilimitado'; ?>"
                                            data-maximo="<?php echo $plano['valor_maximo'] ?? ''; ?>">
                                        <?php echo htmlspecialchars($plano['nome']); ?> 
                                        (R$ <?php echo number_format($plano['valor_por_veiculo'], 2, ',', '.'); ?>/veículo
                                        <?php if ($plano['limite_veiculos']): ?>
                                            - até <?php echo $plano['limite_veiculos']; ?> veículos
                                        <?php else: ?>
                                            - ilimitado
                                        <?php endif; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Selecione um plano para aplicar valores automáticos</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_valor_por_veiculo" class="form-label">Valor por Veículo (R$)</label>
                            <input type="number" class="form-control" id="edit_valor_por_veiculo" name="valor_por_veiculo" step="0.01">
                            <small class="text-muted">Será preenchido automaticamente se um plano for selecionado</small>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="tem_acesso_seguro" value="sim" id="edit_tem_acesso_seguro">
                                <label class="form-check-label" for="edit_tem_acesso_seguro">
                                    <strong><i class="fas fa-shield-alt"></i> Acesso ao Sistema Seguro</strong>
                                </label>
                            </div>
                            <small class="text-muted">Marque esta opção se a empresa terá acesso ao módulo de gestão de clientes comissionados (Sistema Seguro).</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Visualizar Empresa -->
    <div class="modal fade" id="modalVisualizarEmpresa" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-eye me-2"></i>Detalhes da Empresa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="conteudoVisualizarEmpresa">
                    <!-- Será preenchido via JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="button" class="btn btn-warning" onclick="editarEmpresaAtual()">
                        <i class="fas fa-edit me-1"></i> Editar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Posição Financeira -->
    <div class="modal fade" id="modalPosicaoFinanceira" tabindex="-1">
        <div class="modal-dialog modal-xl" style="max-width: 95%;">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: white; padding: 10px 20px;">
                    <h5 class="modal-title mb-0"><i class="fas fa-chart-line me-2"></i>Posição Financeira</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 0; overflow: hidden;">
                    <iframe id="iframeFinanceiro" src="" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Log de Acessos -->
    <div class="modal fade" id="modalLogAcessos" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-history me-2"></i>Log de Acessos</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="infoEmpresaLog" style="margin-bottom: 20px;"></div>
                    <iframe id="iframeLog" src="" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Usuários -->
    <div class="modal fade" id="modalUsuarios" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-users me-2"></i>Usuários da Empresa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="infoEmpresaUsuarios" style="margin-bottom: 20px;"></div>
                    
                    <!-- Botão Novo Usuário -->
                    <div class="mb-3">
                        <button class="btn btn-primary" onclick="abrirNovoUsuario()">
                            <i class="fas fa-plus me-1"></i> Novo Usuário
                        </button>
                    </div>

                    <!-- Tabela de Usuários -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nome</th>
                                    <th>Email</th>
                                    <th>Tipo</th>
                                    <th>Status</th>
                                    <th>Data Cadastro</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaUsuarios">
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <i class="fas fa-spinner fa-spin"></i> Carregando...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Novo Usuário -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Novo Usuário</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="formNovoUsuario">
                    <div class="modal-body">
                        <input type="hidden" id="novoUsuarioEmpresaId">
                        <div class="mb-3">
                            <label for="novoUsuarioNome" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="novoUsuarioNome" required>
                        </div>
                        <div class="mb-3">
                            <label for="novoUsuarioEmail" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="novoUsuarioEmail" required>
                        </div>
                        <div class="mb-3">
                            <label for="novoUsuarioSenha" class="form-label">Senha *</label>
                            <input type="password" class="form-control" id="novoUsuarioSenha" required minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label for="novoUsuarioTipo" class="form-label">Tipo de Usuário *</label>
                            <select class="form-select" id="novoUsuarioTipo" required>
                                <option value="">Selecione...</option>
                                <option value="admin">Administrador</option>
                                <option value="gestor">Gestor</option>
                                <option value="operador">Operador</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Usuário -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="formEditarUsuario">
                    <div class="modal-body">
                        <input type="hidden" id="editarUsuarioId">
                        <input type="hidden" id="editarUsuarioEmpresaId">
                        <div class="mb-3">
                            <label for="editarUsuarioNome" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="editarUsuarioNome" required>
                        </div>
                        <div class="mb-3">
                            <label for="editarUsuarioEmail" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="editarUsuarioEmail" required>
                        </div>
                        <div class="mb-3">
                            <label for="editarUsuarioSenha" class="form-label">Nova Senha (deixe em branco para manter)</label>
                            <input type="password" class="form-control" id="editarUsuarioSenha" minlength="6">
                            <small class="text-muted">Mínimo 6 caracteres</small>
                        </div>
                        <div class="mb-3">
                            <label for="editarUsuarioTipo" class="form-label">Tipo de Usuário *</label>
                            <select class="form-select" id="editarUsuarioTipo" required>
                                <option value="">Selecione...</option>
                                <option value="admin">Administrador</option>
                                <option value="gestor">Gestor</option>
                                <option value="operador">Operador</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="editarUsuarioStatus" class="form-label">Status *</label>
                            <select class="form-select" id="editarUsuarioStatus" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulário de Exclusão -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let empresaAtual = null;

        // Mostrar/ocultar info do Sistema Seguro
        document.getElementById('tem_acesso_seguro')?.addEventListener('change', function() {
            const infoAcessoSeguro = document.getElementById('infoAcessoSeguro');
            if (this.checked) {
                infoAcessoSeguro.style.display = 'block';
            } else {
                infoAcessoSeguro.style.display = 'none';
            }
        });

        // Máscaras
        function aplicarMascaraCNPJ(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 14) {
                    value = value.replace(/^(\d{2})(\d)/, '$1.$2');
                    value = value.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
                    value = value.replace(/\.(\d{3})(\d)/, '.$1/$2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                    e.target.value = value;
                }
            });
        }

        function aplicarMascaraTelefone(input) {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length <= 11) {
                    value = value.replace(/^(\d{2})(\d)/g, '($1) $2');
                    value = value.replace(/(\d)(\d{4})$/, '$1-$2');
                    e.target.value = value;
                }
            });
        }

        // Aplicar máscaras
        const cnpjInputs = document.querySelectorAll('#cnpj, #edit_cnpj');
        cnpjInputs.forEach(input => aplicarMascaraCNPJ(input));

        const telefoneInputs = document.querySelectorAll('#telefone, #edit_telefone');
        telefoneInputs.forEach(input => aplicarMascaraTelefone(input));

        // Funções de ação
        function abrirVisualizar(empresa) {
            empresaAtual = empresa;
            const content = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Razão Social:</strong><br>
                        ${empresa.razao_social}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>CNPJ:</strong><br>
                        ${empresa.cnpj}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Email:</strong><br>
                        ${empresa.email}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Telefone:</strong><br>
                        ${empresa.telefone || 'Não informado'}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Valor por Veículo:</strong><br>
                        R$ ${parseFloat(empresa.valor_por_veiculo).toFixed(2).replace('.', ',')}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Sistema Seguro:</strong><br>
                        ${empresa.tem_acesso_seguro === 'sim' ? '<span class="badge bg-success"><i class="fas fa-shield-alt"></i> Sim</span>' : '<span class="badge bg-secondary"><i class="fas fa-times-circle"></i> Não</span>'}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Data de Cadastro:</strong><br>
                        ${new Date(empresa.data_cadastro).toLocaleDateString('pt-BR')}
                    </div>
                </div>
            `;
            document.getElementById('conteudoVisualizarEmpresa').innerHTML = content;
            new bootstrap.Modal(document.getElementById('modalVisualizarEmpresa')).show();
        }

        function editarEmpresaAtual() {
            if (empresaAtual) {
                abrirEditar(empresaAtual);
                bootstrap.Modal.getInstance(document.getElementById('modalVisualizarEmpresa')).hide();
            }
        }

        function abrirEditar(empresa) {
            document.getElementById('edit_id').value = empresa.id;
            document.getElementById('edit_razao_social').value = empresa.razao_social;
            document.getElementById('edit_cnpj').value = empresa.cnpj;
            document.getElementById('edit_email').value = empresa.email;
            document.getElementById('edit_telefone').value = empresa.telefone || '';
            document.getElementById('edit_valor_por_veiculo').value = empresa.valor_por_veiculo;
            document.getElementById('edit_plano_id').value = empresa.plano_id || '';
            document.getElementById('edit_tem_acesso_seguro').checked = empresa.tem_acesso_seguro === 'sim';
            new bootstrap.Modal(document.getElementById('modalEditarEmpresa')).show();
        }

        function abrirFinanceiro(empresaId) {
            // Buscar empresa_cliente_id
            fetch(`./api/get_empresa_cliente_id.php?empresa_adm_id=${empresaId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro na resposta do servidor: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da API:', data);
                    if (data.success && data.empresa_cliente_id) {
                        const url = `./posicao_financeira.php?empresa_id=${data.empresa_cliente_id}`;
                        document.getElementById('iframeFinanceiro').src = url;
                        new bootstrap.Modal(document.getElementById('modalPosicaoFinanceira')).show();
                    } else {
                        const errorMsg = data.error || 'Empresa não encontrada';
                        console.error('Erro:', errorMsg);
                        alert('Erro ao carregar dados da empresa: ' + errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Erro na requisição:', error);
                    alert('Erro ao carregar posição financeira: ' + error.message);
                });
        }

        function abrirLogAcessos(empresaId) {
            // Buscar empresa_cliente_id
            fetch(`./api/get_empresa_cliente_id.php?empresa_adm_id=${empresaId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao buscar empresa: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.empresa_cliente_id) {
                        const url = `./log_acessos.php?empresa_id=${data.empresa_cliente_id}`;
                        document.getElementById('iframeLog').src = url;
                        
                        // Buscar nome da empresa
                        fetch(`./api/get_empresa_nome.php?empresa_adm_id=${empresaId}`)
                            .then(r => {
                                if (!r.ok) {
                                    throw new Error('Erro ao buscar nome da empresa: ' + r.status);
                                }
                                return r.json();
                            })
                            .then(d => {
                                if (d.success) {
                                    document.getElementById('infoEmpresaLog').innerHTML = `
                                        <div class="alert alert-info">
                                            <strong><i class="fas fa-building"></i> Empresa:</strong> ${d.razao_social}
                                        </div>
                                    `;
                                }
                            });
                        
                        new bootstrap.Modal(document.getElementById('modalLogAcessos')).show();
                    } else {
                        alert('Erro ao carregar dados da empresa');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar log de acessos');
                });
        }

        let empresaAtualUsuarios = null;

        function abrirUsuarios(empresaId) {
            empresaAtualUsuarios = empresaId;
            
            // Buscar nome da empresa
            fetch(`./api/get_empresa_nome.php?empresa_adm_id=${empresaId}`)
                .then(response => {
                    if (!response.ok) {
                        console.error('Erro ao buscar nome da empresa:', response.status);
                        return {success: false};
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('infoEmpresaUsuarios').innerHTML = `
                            <div class="alert alert-info">
                                <strong><i class="fas fa-building"></i> Empresa:</strong> ${data.razao_social}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro ao buscar nome da empresa:', error);
                });
            
            // Carregar usuários
            carregarUsuarios(empresaId);
            
            new bootstrap.Modal(document.getElementById('modalUsuarios')).show();
        }

        function carregarUsuarios(empresaId) {
            fetch(`./api/get_usuarios_empresa.php?empresa_adm_id=${empresaId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao carregar usuários: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    const tbody = document.getElementById('tabelaUsuarios');
                    
                    if (data.success && data.usuarios && data.usuarios.length > 0) {
                        tbody.innerHTML = data.usuarios.map(usuario => {
                            const tipoBadge = {
                                'admin': '<span class="badge bg-danger">Administrador</span>',
                                'gestor': '<span class="badge bg-warning text-dark">Gestor</span>',
                                'operador': '<span class="badge bg-info">Operador</span>'
                            }[usuario.tipo_usuario] || '<span class="badge bg-secondary">' + usuario.tipo_usuario + '</span>';
                            
                            const statusBadge = usuario.status === 'ativo' 
                                ? '<span class="badge bg-success">Ativo</span>' 
                                : '<span class="badge bg-secondary">Inativo</span>';
                            
                            return `
                                <tr>
                                    <td>${usuario.nome}</td>
                                    <td>${usuario.email}</td>
                                    <td>${tipoBadge}</td>
                                    <td>${statusBadge}</td>
                                    <td>${usuario.data_cadastro_formatada || (usuario.data_cadastro ? new Date(usuario.data_cadastro).toLocaleDateString('pt-BR') : '-')}</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" onclick="editarUsuario(${usuario.id}, ${empresaId})" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="excluirUsuario(${usuario.id})" title="Excluir">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            `;
                        }).join('');
                    } else {
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-inbox fa-2x text-muted mb-2 d-block"></i>
                                    <p class="text-muted mb-0">Nenhum usuário cadastrado</p>
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    document.getElementById('tabelaUsuarios').innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-danger">
                                Erro ao carregar usuários
                            </td>
                        </tr>
                    `;
                });
        }

        function abrirNovoUsuario() {
            document.getElementById('novoUsuarioEmpresaId').value = empresaAtualUsuarios;
            document.getElementById('formNovoUsuario').reset();
            document.getElementById('novoUsuarioEmpresaId').value = empresaAtualUsuarios;
            new bootstrap.Modal(document.getElementById('modalNovoUsuario')).show();
        }

        document.getElementById('formNovoUsuario').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('empresa_id', document.getElementById('novoUsuarioEmpresaId').value);
            formData.append('nome', document.getElementById('novoUsuarioNome').value);
            formData.append('email', document.getElementById('novoUsuarioEmail').value);
            formData.append('senha', document.getElementById('novoUsuarioSenha').value);
            formData.append('tipo_usuario', document.getElementById('novoUsuarioTipo').value);
            
            fetch('./api/salvar_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalNovoUsuario')).hide();
                    carregarUsuarios(empresaAtualUsuarios);
                    alert('Usuário cadastrado com sucesso!');
                } else {
                    alert('Erro: ' + (data.error || 'Erro ao cadastrar usuário'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao cadastrar usuário');
            });
        });

        function editarUsuario(usuarioId, empresaId) {
            fetch(`./api/get_usuario.php?id=${usuarioId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erro ao buscar usuário: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success && data.usuario) {
                        const u = data.usuario;
                        document.getElementById('editarUsuarioId').value = u.id;
                        document.getElementById('editarUsuarioEmpresaId').value = empresaId;
                        document.getElementById('editarUsuarioNome').value = u.nome;
                        document.getElementById('editarUsuarioEmail').value = u.email;
                        document.getElementById('editarUsuarioTipo').value = u.tipo_usuario;
                        document.getElementById('editarUsuarioStatus').value = u.status;
                        document.getElementById('editarUsuarioSenha').value = '';
                        
                        new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
                    } else {
                        alert('Erro ao carregar dados do usuário');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao carregar usuário');
                });
        }

        document.getElementById('formEditarUsuario').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('id', document.getElementById('editarUsuarioId').value);
            formData.append('empresa_id', document.getElementById('editarUsuarioEmpresaId').value);
            formData.append('nome', document.getElementById('editarUsuarioNome').value);
            formData.append('email', document.getElementById('editarUsuarioEmail').value);
            formData.append('tipo_usuario', document.getElementById('editarUsuarioTipo').value);
            formData.append('status', document.getElementById('editarUsuarioStatus').value);
            
            const senha = document.getElementById('editarUsuarioSenha').value;
            if (senha) {
                formData.append('senha', senha);
            }
            
            fetch('./api/salvar_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario')).hide();
                    carregarUsuarios(empresaAtualUsuarios);
                    alert('Usuário atualizado com sucesso!');
                } else {
                    alert('Erro: ' + (data.error || 'Erro ao atualizar usuário'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao atualizar usuário');
            });
        });

        function excluirUsuario(usuarioId) {
            if (!confirm('Tem certeza que deseja excluir este usuário?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', usuarioId);
            
            fetch('./api/salvar_usuario.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erro na resposta do servidor: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    carregarUsuarios(empresaAtualUsuarios);
                    alert('Usuário excluído com sucesso!');
                } else {
                    alert('Erro: ' + (data.error || 'Erro ao excluir usuário'));
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao excluir usuário');
            });
        }
    </script>
</body>
</html>