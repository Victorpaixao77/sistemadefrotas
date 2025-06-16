<?php
require_once '../includes/conexao.php';
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

// Buscar estatísticas
try {
    // Total de empresas
    $stmt = $pdo->query("SELECT COUNT(*) FROM empresa_adm");
    $total_empresas = $stmt->fetchColumn();

    // Total de usuários ativos
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'");
    $total_usuarios = $stmt->fetchColumn();

    // Total de motoristas
    $stmt = $pdo->query("SELECT COUNT(*) FROM motoristas");
    $total_motoristas = $stmt->fetchColumn();

    // Total de veículos
    $stmt = $pdo->query("SELECT COUNT(*) FROM veiculos");
    $total_veiculos = $stmt->fetchColumn();

    // Empresas com contagem de veículos e valor total
    $stmt = $pdo->query("
        SELECT e.*, 
               COUNT(CASE WHEN v.status_id IN (1, 2) THEN v.id END) as total_veiculos,
               (COUNT(CASE WHEN v.status_id IN (1, 2) THEN v.id END) * e.valor_por_veiculo) as valor_total
        FROM empresa_adm e 
        LEFT JOIN empresa_clientes ec ON e.id = ec.empresa_adm_id
        LEFT JOIN veiculos v ON ec.id = v.empresa_id 
        GROUP BY e.id, e.razao_social, e.cnpj, e.email, e.data_cadastro, e.valor_por_veiculo 
        ORDER BY e.razao_social ASC
    ");
    $empresas_recentes = $stmt->fetchAll();

    // Removendo a query de últimos logins pois não será mais necessária
    $ultimos_logins = [];

} catch (PDOException $e) {
    $mensagem = "Erro ao carregar dados: " . $e->getMessage();
    $tipo_mensagem = "error";
    $total_empresas = $total_usuarios = $total_motoristas = $total_veiculos = 0;
    $empresas_recentes = $ultimos_logins = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Frotas</title>
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
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .dashboard-card h3 {
            color: #333;
            margin: 0 0 10px 0;
            font-size: 1.1rem;
        }
        .dashboard-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .recent-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .recent-section h2 {
            color: #333;
            margin: 0 0 20px 0;
            font-size: 1.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            color: #333;
        }
        th {
            font-weight: 600;
            background: #f8f9fa;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: white;
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
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Empresas Cadastradas</h3>
                <div class="value"><?php echo $total_empresas; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Usuários Ativos</h3>
                <div class="value"><?php echo $total_usuarios; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Motoristas</h3>
                <div class="value"><?php echo $total_motoristas; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Veículos</h3>
                <div class="value"><?php echo $total_veiculos; ?></div>
            </div>
        </div>

        <div class="recent-section">
            <h2>Empresas Cadastradas</h2>
            <table>
                <thead>
                    <tr>
                        <th>Razão Social</th>
                        <th>CNPJ</th>
                        <th>Email</th>
                        <th>Data de Cadastro</th>
                        <th>Total de Veículos</th>
                        <th>Valor por Veículo</th>
                        <th>Total a Pagar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empresas_recentes as $empresa): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($empresa['razao_social']); ?></td>
                            <td><?php echo htmlspecialchars($empresa['cnpj']); ?></td>
                            <td><?php echo htmlspecialchars($empresa['email']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($empresa['data_cadastro'])); ?></td>
                            <td><?php echo $empresa['total_veiculos']; ?></td>
                            <td>R$ <?php echo number_format($empresa['valor_por_veiculo'], 2, ',', '.'); ?></td>
                            <td>R$ <?php echo number_format($empresa['valor_total'], 2, ',', '.'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 