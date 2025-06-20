<?php
// Verifica se o usuário está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: /sistema-frotas/login.php');
    exit;
}

// Obtém o ID da empresa do usuário
$empresa_id = $_SESSION['empresa_id'];

// Busca os dados necessários
try {
    // Busca veículos da empresa
    $veiculos = $veiculoRepository->findByEmpresa($empresa_id);
    
    // Busca pneus disponíveis
    $pneusDisponiveis = $pneuRepository->findDisponiveis($empresa_id);
    
    // Busca pneus em uso
    $pneusEmUso = $pneuRepository->findEmUso($empresa_id);
    
    // Busca pneus para rodízio
    $pneusParaRodizio = $pneuRepository->findParaRodizio($empresa_id);
    
    // Busca pneus com alerta
    $pneusComAlerta = $pneuRepository->findComAlerta($empresa_id);
} catch (\Exception $e) {
    $error = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - Gestão Interativa</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/sistema-frotas/css/styles.css">
    <link rel="stylesheet" href="/sistema-frotas/css/theme.css">
    <link rel="stylesheet" href="/sistema-frotas/css/responsive.css">
    <link rel="stylesheet" href="/sistema-frotas/gestao_interativa/assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include __DIR__ . '/../../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Gestão Interativa</h1>
                </div>

                <!-- Cards de Resumo -->
                <div class="dashboard-cards">
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="card-info">
                            <h3>Veículos</h3>
                            <p><?php echo count($veiculos); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-tire"></i>
                        </div>
                        <div class="card-info">
                            <h3>Pneus Disponíveis</h3>
                            <p><?php echo count($pneusDisponiveis); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-tire-rugged"></i>
                        </div>
                        <div class="card-info">
                            <h3>Pneus em Uso</h3>
                            <p><?php echo count($pneusEmUso); ?></p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-icon">
                            <i class="fas fa-sync"></i>
                        </div>
                        <div class="card-info">
                            <h3>Pneus para Rodízio</h3>
                            <p><?php echo count($pneusParaRodizio); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Área de Gestão de Pneus -->
                <div class="gestao-pneus-container">
                    <div class="veiculo-selector">
                        <select id="veiculoSelect" onchange="carregarPneus(this.value)">
                            <option value="">Selecione um veículo</option>
                            <?php foreach ($veiculos as $veiculo): ?>
                                <option value="<?php echo $veiculo->getId(); ?>">
                                    <?php echo htmlspecialchars($veiculo->getPlaca() . ' - ' . $veiculo->getModelo()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Visualização do Veículo -->
                    <div id="componente-veiculo-wrapper">
                        <div id="componente-veiculo"></div>
                    </div>

                    <!-- Legenda -->
                    <div class="legenda-pneus" id="legendaPneus">
                        <div><span class="pneu legenda bom"></span> Bom</div>
                        <div><span class="pneu legenda gasto"></span> Gasto/Alerta</div>
                        <div><span class="pneu legenda furado"></span> Furado/Ruim</div>
                        <div><span class="pneu legenda rodizio"></span> Rodízio Sugerido</div>
                        <div><span class="pneu legenda alerta"><span class="icone-alerta">⚠️</span></span> Alerta</div>
                    </div>
                </div>

                <!-- Histórico de Alocações -->
                <div class="historico-pneus">
                    <h3>Histórico de Alocações</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Pneu</th>
                                    <th>Eixo</th>
                                    <th>Posição</th>
                                    <th>Data Alocação</th>
                                    <th>KM Alocação</th>
                                    <th>Data Desalocação</th>
                                    <th>KM Desalocação</th>
                                    <th>Status</th>
                                    <th>Observações</th>
                                </tr>
                            </thead>
                            <tbody id="historicoPneus">
                                <!-- Será preenchido via JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Footer -->
            <?php include __DIR__ . '/../../includes/footer.php'; ?>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="/sistema-frotas/js/header.js"></script>
    <script src="/sistema-frotas/js/theme.js"></script>
    <script src="/sistema-frotas/js/sidebar.js"></script>
    <script src="/sistema-frotas/gestao_interativa/assets/js/gestao-pneus.js"></script>
</body>
</html> 