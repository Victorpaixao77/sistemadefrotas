<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

// Log da sessão para debug
error_log("=== Página inicial ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Status: " . session_status());
error_log("Session Data: " . print_r($_SESSION, true));
error_log("HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'não definido'));
error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'não definido'));

// Verifica se o motorista está logado
if (!isset($_SESSION['motorista_id'])) {
    error_log("Motorista não logado, redirecionando para login.php");
    header('Location: login.php');
    exit;
}

// Obtém dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Log dos dados do motorista
error_log("Dados do motorista:");
error_log("motorista_id: " . $motorista_id);
error_log("empresa_id: " . $empresa_id);

// Obtém contadores
$rotas_pendentes = count(obter_rotas_pendentes($motorista_id));
$abastecimentos_pendentes = count(obter_abastecimentos_pendentes($motorista_id));
$checklists_pendentes = count(obter_checklists_pendentes($motorista_id));

// Log dos contadores
error_log("Contadores:");
error_log("rotas_pendentes: " . $rotas_pendentes);
error_log("abastecimentos_pendentes: " . $abastecimentos_pendentes);
error_log("checklists_pendentes: " . $checklists_pendentes);

// Verifica se o motorista está logado
validar_sessao_motorista();

// Buscar dados do motorista
$conn = getConnection();
error_log('Iniciando busca de dados para empresa_id: ' . $empresa_id . ' e motorista_id: ' . $motorista_id);

// Buscar dados do motorista
$stmt = $conn->prepare('
    SELECT m.*
    FROM motoristas m
    WHERE m.id = :motorista_id
    AND m.empresa_id = :empresa_id
');
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$motorista = $stmt->fetch(PDO::FETCH_ASSOC);

error_log('Dados do motorista encontrados: ' . print_r($motorista, true));

// Buscar rotas do dia
$stmt = $conn->prepare('
    SELECT r.*, 
           c1.nome as cidade_origem_nome,
           c2.nome as cidade_destino_nome
    FROM rotas r
    LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
    LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
    WHERE r.empresa_id = :empresa_id
    AND r.motorista_id = :motorista_id
    AND DATE(r.data_rota) = CURDATE()
    ORDER BY r.data_rota ASC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->execute();
$rotas_hoje = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log('Rotas de hoje encontradas: ' . print_r($rotas_hoje, true));

// Buscar últimas rotas
$stmt = $conn->prepare('
    SELECT r.*, 
           c1.nome as cidade_origem_nome,
           c2.nome as cidade_destino_nome
    FROM rotas r
    LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
    LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
    WHERE r.empresa_id = :empresa_id
    AND r.motorista_id = :motorista_id
    ORDER BY r.data_rota DESC
    LIMIT 5
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->execute();
$ultimas_rotas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar últimos abastecimentos
$stmt = $conn->prepare('
    SELECT a.*, v.placa
    FROM abastecimentos a
    JOIN veiculos v ON a.veiculo_id = v.id
    WHERE a.empresa_id = :empresa_id
    AND a.motorista_id = :motorista_id
    ORDER BY a.data_abastecimento DESC
    LIMIT 5
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->execute();
$ultimos_abastecimentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar últimos checklists
$stmt = $conn->prepare('
    SELECT cv.*, 
           v.placa,
           c1.nome as cidade_origem_nome,
           c2.nome as cidade_destino_nome
    FROM checklist_viagem cv
    JOIN veiculos v ON cv.veiculo_id = v.id
    JOIN rotas r ON cv.rota_id = r.id
    LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
    LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
    WHERE cv.empresa_id = :empresa_id
    AND cv.motorista_id = :motorista_id
    ORDER BY cv.data_checklist DESC
    LIMIT 5
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->execute();
$ultimos_checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - Motorista</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <style>
        .container {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .back-btn {
            color: var(--text-primary);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-btn:hover {
            color: var(--primary-color);
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .dashboard-header h1 {
            margin: 0;
            font-size: 24px;
        }
        
        .dashboard-header p {
            margin: 0;
            color: #666;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .dashboard-card {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            margin-bottom: 15px;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .card-body {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .card-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        
        .card-info h4 {
            margin: 0;
            color: var(--text-primary);
        }
        
        .card-info p {
            margin: 5px 0 0;
            color: var(--text-secondary);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-color-dark);
        }
        
        .btn-link {
            color: var(--primary-color);
            text-decoration: none;
            padding: 0;
            background: none;
            border: none;
            font-weight: 500;
        }
        
        .btn-link:hover {
            color: var(--primary-color-dark);
            text-decoration: underline;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 15px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table td {
            color: var(--text-secondary);
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-pendente {
            background: var(--warning-color);
            color: var(--warning-color-dark);
        }
        
        .status-concluido {
            background: var(--success-color);
            color: var(--success-color-dark);
        }
        
        .status-cancelado {
            background: var(--danger-color);
            color: var(--danger-color-dark);
        }
        
        .card-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .card-footer h4 {
            margin: 0 0 10px;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .card-body {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .card-content {
                flex-direction: column;
            }
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            background: var(--danger-color-dark);
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 12px;
        }
        
        .btn-info {
            background: var(--info-color);
            color: white;
            border: none;
        }
        
        .btn-info:hover {
            background: var(--info-color-dark);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-content">
            <div class="dashboard-header">
                <div>
                    <h1>Bem-vindo, <?php echo htmlspecialchars($motorista['nome']); ?></h1>
                </div>
                <div>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="dashboard-grid">
                    <!-- Card de Rotas -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-content">
                                <div class="card-icon">
                                    <i class="fas fa-route"></i>
                                </div>
                                <div class="card-info">
                                    <h4>Rotas do Dia</h4>
                                    <p>Visualize e gerencie suas rotas</p>
                                </div>
                            </div>
                            <a href="rotas.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Acessar
                            </a>
                        </div>
                        <!-- Histórico de Rotas -->
                        <div class="card-footer">
                            <h4>Últimas Rotas</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Origem</th>
                                            <th>Destino</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimas_rotas as $rota): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($rota['data_rota'])); ?></td>
                                            <td><?php echo htmlspecialchars($rota['cidade_origem_nome']); ?></td>
                                            <td><?php echo htmlspecialchars($rota['cidade_destino_nome']); ?></td>
                                            <td><span class="status-badge status-<?php echo $rota['status']; ?>"><?php echo ucfirst($rota['status']); ?></span></td>
                                            <td>
                                                <a href="despesas.php?rota_id=<?php echo $rota['id']; ?>" class="btn btn-sm btn-info" title="Registrar Despesas">
                                                    <i class="fas fa-receipt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="rotas.php" class="btn btn-link">Ver todas as rotas</a>
                        </div>
                    </div>

                    <!-- Card de Abastecimentos -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-content">
                                <div class="card-icon">
                                    <i class="fas fa-gas-pump"></i>
                                </div>
                                <div class="card-info">
                                    <h4>Registre Abastecimentos</h4>
                                    <p>Controle de combustível</p>
                                </div>
                            </div>
                            <a href="abastecimento.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Acessar
                            </a>
                        </div>
                        <!-- Histórico de Abastecimentos -->
                        <div class="card-footer">
                            <h4>Últimos Abastecimentos</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Veículo</th>
                                            <th>Litros</th>
                                            <th>Valor</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimos_abastecimentos as $abastecimento): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($abastecimento['data_abastecimento'])); ?></td>
                                            <td><?php echo htmlspecialchars($abastecimento['placa']); ?></td>
                                            <td><?php echo number_format($abastecimento['litros'], 2, ',', '.'); ?> L</td>
                                            <td>R$ <?php echo number_format($abastecimento['valor_total'], 2, ',', '.'); ?></td>
                                            <td><span class="status-badge status-<?php echo $abastecimento['status']; ?>"><?php echo ucfirst($abastecimento['status']); ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="abastecimento.php" class="btn btn-link">Ver todos os abastecimentos</a>
                        </div>
                    </div>

                    <!-- Card de Checklists -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Checklists</h3>
                        </div>
                        <div class="card-body">
                            <div class="card-content">
                                <div class="card-icon">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="card-info">
                                    <h4>Checklists de Viagem</h4>
                                    <p>Verificações de segurança</p>
                                </div>
                            </div>
                            <a href="checklists.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Acessar
                            </a>
                        </div>
                        <!-- Histórico de Checklists -->
                        <div class="card-footer">
                            <h4>Últimos Checklists</h4>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Data</th>
                                            <th>Veículo</th>
                                            <th>Rota</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimos_checklists as $checklist): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i', strtotime($checklist['data_checklist'])); ?></td>
                                            <td><?php echo htmlspecialchars($checklist['placa']); ?></td>
                                            <td><?php echo htmlspecialchars($checklist['cidade_origem_nome']); ?> → <?php echo htmlspecialchars($checklist['cidade_destino_nome']); ?></td>
                                            <td><span class="status-badge status-pendente">Pendente</span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <a href="checklists.php" class="btn btn-link">Ver todos os checklists</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
</body>
</html> 