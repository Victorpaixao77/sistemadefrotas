<?php
// Include configuration and functions first
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Configure session before starting it
configure_session();

// Start the session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header("location: login.php");
    exit;
}

// Get user data from session
$nome_usuario = $_SESSION['nome'] ?? 'Usuário';
$empresa_id = $_SESSION['empresa_id'];

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id AND status = 'ativo'");
    $stmt->bindParam(':empresa_id', $empresa_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0 || $stmt->fetch()['status'] !== 'ativo') {
        session_unset();
        session_destroy();
        header("location: login.php?error=empresa_inativa");
        exit;
    }
} catch(PDOException $e) {
    // Log error but don't show to user
    error_log("Erro ao verificar status da empresa: " . $e->getMessage());
}

// Get company data
$empresa = getCompanyData();

// Set page title
$page_title = "Dashboard";

// Verificar permissões do usuário
require_once 'includes/permissions.php';
$can_view_financial_data = can_view_financial_data();

// ====== INDICADORES ACUMULADOS ======
try {
    // Conexão já criada: $conn
    // 1. Total de Veículos
    $total_veiculos = $conn->query("SELECT COUNT(*) FROM veiculos WHERE empresa_id = $empresa_id")->fetchColumn();

    // 2. Total de Motoristas/Colaboradores
    $total_motoristas = $conn->query("SELECT COUNT(*) FROM motoristas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 3. Total de Rotas Realizadas
    $total_rotas = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 4. Total de Abastecimentos
    $total_abastecimentos = $conn->query("SELECT COUNT(*) FROM abastecimentos WHERE empresa_id = $empresa_id")->fetchColumn();

    // 5. Despesas de Viagem
    $total_desp_viagem = $conn->query("SELECT COALESCE(SUM(
        COALESCE(arla,0) + COALESCE(pedagios,0) + COALESCE(caixinha,0) + 
        COALESCE(estacionamento,0) + COALESCE(lavagem,0) + COALESCE(borracharia,0) + 
        COALESCE(eletrica_mecanica,0) + COALESCE(adiantamento,0)
    ),0) FROM despesas_viagem WHERE empresa_id = $empresa_id")->fetchColumn();

    // 6. Despesas Fixas
    $total_desp_fixas = $conn->query("SELECT COALESCE(SUM(valor),0) FROM despesas_fixas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 7. Contas Pagas
    $total_contas_pagas = $conn->query("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE empresa_id = $empresa_id AND status_id = 2")->fetchColumn();

    // 8. Manutenções de Veículos
    $total_manutencoes = $conn->query("SELECT COALESCE(SUM(valor),0) FROM manutencoes WHERE empresa_id = $empresa_id")->fetchColumn();

    // 9. Manutenções de Pneus
    $total_pneu_manutencao = $conn->query("SELECT COALESCE(SUM(custo),0) FROM pneu_manutencao WHERE empresa_id = $empresa_id")->fetchColumn();

    // 10. Parcelas de Financiamento
    $total_parcelas_financiamento = $conn->query("SELECT COALESCE(SUM(valor),0) FROM parcelas_financiamento WHERE empresa_id = $empresa_id AND status_id = 2")->fetchColumn();

    // 11. Total de Faturamento (Fretes)
    $total_fretes = $conn->query("SELECT COALESCE(SUM(frete),0) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 12. Total de Comissões
    $total_comissoes = $conn->query("SELECT COALESCE(SUM(comissao),0) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();

    // 13. Lucro Líquido Geral
    $lucro_liquido = $total_fretes
        - $total_comissoes
        - $total_desp_viagem
        - $total_desp_fixas
        - $total_parcelas_financiamento
        - $total_contas_pagas
        - $total_manutencoes
        - $total_pneu_manutencao;

} catch (Exception $e) {
    $total_veiculos = $total_motoristas = $total_rotas = $total_abastecimentos = 0;
    $total_desp_viagem = $total_desp_fixas = $total_contas_pagas = 0;
    $total_manutencoes = $total_pneu_manutencao = $total_parcelas_financiamento = 0;
    $total_fretes = $total_comissoes = $lucro_liquido = 0;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Principal - <?php echo APP_NAME; ?></title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <link rel="stylesheet" href="css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Sortable.js for drag-and-drop -->
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style>
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left var(--transition-speed) ease;
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            background: var(--bg-primary);
        }
        
        .sidebar-collapsed .main-content {
            margin-left: var(--sidebar-collapsed-width);
            width: calc(100% - var(--sidebar-collapsed-width));
        }
        
        .dashboard-content {
            padding: 20px;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include 'includes/header.php'; ?>
            
            <!-- Dashboard Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Bem-vindo, <?php echo htmlspecialchars($nome_usuario); ?>!</h1>
                </div>
                
                <!-- Alertas Inteligentes -->
                <div class="alertas-inteligentes mb-4" style="display: flex; gap: 16px; margin-bottom: 24px;">
                    <?php
                    // Buscar alertas reais do banco de dados
                    $alertas = [];
                    
                    try {
                        // 1. Manutenção urgente (veículos sem manutenção há mais de 90 dias)
                        $sql_manutencao = "SELECT v.placa, DATEDIFF(CURRENT_DATE, ultima_manutencao.data_manutencao) as dias_sem_manutencao
                                           FROM veiculos v 
                                           LEFT JOIN (
                                               SELECT veiculo_id, MAX(data_manutencao) as data_manutencao
                                               FROM manutencoes 
                                               WHERE empresa_id = :empresa_id
                                               GROUP BY veiculo_id
                                           ) ultima_manutencao ON v.id = ultima_manutencao.veiculo_id
                                           WHERE v.empresa_id = :empresa_id2 
                                           AND (ultima_manutencao.data_manutencao IS NULL OR DATEDIFF(CURRENT_DATE, ultima_manutencao.data_manutencao) > 90)
                                           LIMIT 1";
                        
                        $stmt = $conn->prepare($sql_manutencao);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->bindParam(':empresa_id2', $empresa_id);
                        $stmt->execute();
                        $manutencao_urgente = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($manutencao_urgente) {
                            $alertas[] = [
                                'tipo' => 'Manutenção Urgente',
                                'mensagem' => "Veículo {$manutencao_urgente['placa']} precisa de manutenção preventiva há " . $manutencao_urgente['dias_sem_manutencao'] . " dias.",
                                'cor' => '#e74c3c'
                            ];
                        }
                        
                        // 2. Pneus antigos (baseado na idade do DOT - data de fabricação)
                        $sql_pneu_antigo = "SELECT p.id, p.marca, p.modelo, p.dot,
                                           DATEDIFF(CURRENT_DATE, STR_TO_DATE(p.dot, '%m/%y')) as dias_fabricacao
                                           FROM pneus p 
                                           WHERE p.empresa_id = :empresa_id 
                                           AND p.dot IS NOT NULL 
                                           AND p.dot != ''
                                           AND p.dot REGEXP '^[0-9]{2}/[0-9]{2}$'
                                           AND DATEDIFF(CURRENT_DATE, STR_TO_DATE(p.dot, '%m/%y')) > 1460
                                           AND p.status_id IN (SELECT id FROM status_pneus WHERE nome = 'em_uso')
                                           LIMIT 1";
                        
                        $stmt = $conn->prepare($sql_pneu_antigo);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->execute();
                        $pneu_antigo = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($pneu_antigo) {
                            $alertas[] = [
                                'tipo' => 'Pneu Antigo',
                                'mensagem' => "Pneu {$pneu_antigo['marca']} {$pneu_antigo['modelo']} com DOT {$pneu_antigo['dot']} tem mais de 4 anos.",
                                'cor' => '#f39c12'
                            ];
                        }
                        
                        // 3. Despesa alta (combustível acima da média do mês)
                        $sql_despesa_alta = "SELECT AVG(a.valor_total) as media_mensal, 
                                           (SELECT SUM(valor_total) FROM abastecimentos 
                                            WHERE empresa_id = :empresa_id 
                                            AND MONTH(data_abastecimento) = MONTH(CURRENT_DATE)
                                            AND YEAR(data_abastecimento) = YEAR(CURRENT_DATE)) as total_mes
                                           FROM abastecimentos a 
                                           WHERE a.empresa_id = :empresa_id2 
                                           AND a.data_abastecimento >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)";
                        
                        $stmt = $conn->prepare($sql_despesa_alta);
                        $stmt->bindParam(':empresa_id', $empresa_id);
                        $stmt->bindParam(':empresa_id2', $empresa_id);
                        $stmt->execute();
                        $despesa_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($despesa_data && $despesa_data['total_mes'] > ($despesa_data['media_mensal'] * 1.2)) {
                            $alertas[] = [
                                'tipo' => 'Despesa Alta',
                                'mensagem' => "Despesa de combustível 20% acima da média dos últimos 3 meses.",
                                'cor' => '#2980b9'
                            ];
                        }
                        
                    } catch (Exception $e) {
                        error_log("Erro ao buscar alertas: " . $e->getMessage());
                    }
                    
                    // Se não houver alertas reais, mostrar alertas padrão
                    if (empty($alertas)) {
                        $alertas = [
                            ['tipo' => 'Sistema OK', 'mensagem' => 'Todos os sistemas funcionando normalmente.', 'cor' => '#27ae60'],
                        ];
                    }
                    ?>
                    
                    <?php foreach ($alertas as $alerta): ?>
                        <div class="alerta-card" style="background: <?= $alerta['cor'] ?>; padding: 16px 24px; border-radius: 8px; color: #fff; font-weight: 500; box-shadow: 0 2px 8px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-<?= $alerta['cor'] == '#27ae60' ? 'check-circle' : 'exclamation-triangle' ?>"></i> 
                            <span><?= $alerta['tipo'] ?>: <?= $alerta['mensagem'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Dashboard KPIs -->
                <div class="dashboard-grid mb-4">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Motoristas/Colaboradores</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Rotas Realizadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_rotas; ?></span>
                                <span class="metric-subtitle">Total de rotas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_abastecimentos; ?></span>
                                <span class="metric-subtitle">Total de abastecimentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Despesas de Viagem</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_viagem, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custos variáveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Despesas Fixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_fixas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas acumuladas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Contas Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_contas_pagas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Outros pagamentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Manutenções de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_manutencoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Veículos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Manutenções de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_pneu_manutencao, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pneus</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Parcelas de Financiamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_parcelas_financiamento, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas acumuladas</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><?php echo $can_view_financial_data ? 'Faturamento (Fretes)' : 'Fretes Realizados'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php if ($can_view_financial_data): ?>
                                    <span class="metric-value">R$ <?php echo number_format($total_fretes, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Receita bruta</span>
                                <?php else: ?>
                                    <?php
                                    // Para gestores, mostrar apenas o número de fretes
                                    $num_fretes = $conn->query("SELECT COUNT(*) FROM rotas WHERE empresa_id = $empresa_id")->fetchColumn();
                                    ?>
                                    <span class="metric-value"><?php echo number_format($num_fretes, 0, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Fretes realizados</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_comissoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas a motoristas</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php
                                // Para gestores, mostrar apenas percentual médio de comissão
                                $percentual_comissao = $total_fretes > 0 ? ($total_comissoes / $total_fretes) * 100 : 0;
                                ?>
                                <span class="metric-value"><?php echo number_format($percentual_comissao, 1, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Percentual médio</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card" style="background: #e6f9ed; border: 2px solid #2ecc40;">
                        <div class="card-header">
                            <h3 style="color: #218838;">Lucro Líquido Geral</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value" style="color: #218838; font-size: 2rem; font-weight: bold;">R$ <?php echo number_format($lucro_liquido, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Lucro acumulado</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Gráfico Financeiro -->
                <?php if ($can_view_financial_data): ?>
                <div class="analytics-section">
                    <div class="section-header">
                        <h2>Análise Financeira</h2>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Análise Financeira Geral</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="financialChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Distribuição de Despesas (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                                <canvas id="expensesDistributionChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Comissões Pagas (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="commissionsChart"></canvas>
                    </div>
                </div>

                        <div class="analytics-card">
                    <div class="card-header">
                                <h3>Faturamento Líquido (<?= date('Y') ?>)</h3>
                    </div>
                    <div class="card-body">
                        <canvas id="netRevenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2>Atividades Recentes</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        // Buscar atividades reais do banco de dados
                        try {
                            $atividades = [];
                            
                            // Últimas rotas
                            $sql_rotas = "SELECT r.id, r.estado_origem, r.estado_destino, r.data_saida, m.nome as motorista, v.placa,
                                         co.nome as cidade_origem, cd.nome as cidade_destino
                                         FROM rotas r 
                                         JOIN motoristas m ON r.motorista_id = m.id 
                                         JOIN veiculos v ON r.veiculo_id = v.id 
                                         LEFT JOIN cidades co ON r.cidade_origem_id = co.id
                                         LEFT JOIN cidades cd ON r.cidade_destino_id = cd.id
                                         WHERE r.empresa_id = :empresa_id 
                                         ORDER BY r.data_saida DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_rotas);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $rotas_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($rotas_recentes as $rota) {
                                $origem = $rota['cidade_origem'] ? $rota['cidade_origem'] . '/' . $rota['estado_origem'] : $rota['estado_origem'];
                                $destino = $rota['cidade_destino'] ? $rota['cidade_destino'] . '/' . $rota['estado_destino'] : $rota['estado_destino'];
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($rota['data_saida'])),
                                    'descricao' => "Rota: {$origem} → {$destino} ({$rota['motorista']})",
                                    'tipo' => 'rota',
                                    'cor' => '#3498db'
                                ];
                            }
                            
                            // Últimos abastecimentos
                            $sql_abastecimentos = "SELECT a.id, a.data_abastecimento, a.litros, a.valor_total, m.nome as motorista, v.placa 
                                                  FROM abastecimentos a 
                                                  JOIN motoristas m ON a.motorista_id = m.id 
                                                  JOIN veiculos v ON a.veiculo_id = v.id 
                                                  WHERE a.empresa_id = :empresa_id 
                                                  ORDER BY a.data_abastecimento DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_abastecimentos);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $abastecimentos_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($abastecimentos_recentes as $abastecimento) {
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($abastecimento['data_abastecimento'])),
                                    'descricao' => "Abastecimento: {$abastecimento['placa']} - {$abastecimento['litros']}L (R$ " . number_format($abastecimento['valor_total'], 2, ',', '.') . ")",
                                    'tipo' => 'abastecimento',
                                    'cor' => '#e67e22'
                                ];
                            }
                            
                            // Últimas manutenções
                            $sql_manutencoes = "SELECT m.id, m.data_manutencao, m.descricao, m.valor, v.placa 
                                               FROM manutencoes m 
                                               JOIN veiculos v ON m.veiculo_id = v.id 
                                               WHERE m.empresa_id = :empresa_id 
                                               ORDER BY m.data_manutencao DESC LIMIT 3";
                            $stmt = $conn->prepare($sql_manutencoes);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $manutencoes_recentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($manutencoes_recentes as $manutencao) {
                                $atividades[] = [
                                    'data' => date('d/m/Y', strtotime($manutencao['data_manutencao'])),
                                    'descricao' => "Manutenção: {$manutencao['placa']} - " . substr($manutencao['descricao'], 0, 50) . "... (R$ " . number_format($manutencao['valor'], 2, ',', '.') . ")",
                                    'tipo' => 'manutencao',
                                    'cor' => '#f39c12'
                                ];
                            }
                            
                            // Ordenar por data (mais recentes primeiro)
                            usort($atividades, function($a, $b) {
                                return strtotime($b['data']) - strtotime($a['data']);
                            });
                            
                            // Pegar apenas as 4 mais recentes
                            $atividades = array_slice($atividades, 0, 4);
                            
                        } catch (Exception $e) {
                            error_log("Erro ao buscar atividades recentes: " . $e->getMessage());
                            $atividades = [];
                        }
                        ?>
                        
                        <?php if (empty($atividades)): ?>
                            <p style="color: #666; font-style: italic;">Nenhuma atividade recente encontrada.</p>
                        <?php else: ?>
                            <ul style="list-style: none; padding: 0; margin: 0;">
                                <?php foreach ($atividades as $atividade): ?>
                                    <li style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 12px; padding: 16px 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; gap: 15px; transition: all 0.2s ease;">
                                        <span style="font-size: 1.3rem; color: <?= $atividade['cor'] ?>; min-width: 24px;">
                                            <i class="fas fa-<?= $atividade['tipo']=='rota'?'road':($atividade['tipo']=='abastecimento'?'gas-pump':($atividade['tipo']=='manutencao'?'wrench':'money-bill')) ?>"></i>
                                        </span>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;"><?= $atividade['descricao'] ?></div>
                                            <div style="font-size: 0.9rem; color: #7f8c8d;"><?= $atividade['data'] ?></div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Insights Inteligentes -->
                    <div class="card">
                        <div class="card-header">
                        <h2>Insights Inteligentes</h2>
                        </div>
                        <div class="card-body">
                        <?php
                        // Buscar insights reais do banco de dados
                        $insights = [];
                        
                        try {
                            // 1. Análise de custos de manutenção por fornecedor
                            $sql_custos_fornecedor = "SELECT f.nome as fornecedor, AVG(m.valor) as custo_medio, COUNT(*) as total_manutencoes
                                                     FROM manutencoes m 
                                                     JOIN fornecedores f ON m.fornecedor_id = f.id 
                                                     WHERE m.empresa_id = :empresa_id 
                                                     AND m.data_manutencao >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                     GROUP BY f.id 
                                                     HAVING total_manutencoes >= 3
                                                     ORDER BY custo_medio DESC 
                                                     LIMIT 1";
                            
                            $stmt = $conn->prepare($sql_custos_fornecedor);
                            $stmt->bindParam(':empresa_id', $empresa_id);
                            $stmt->execute();
                            $fornecedor_caro = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($fornecedor_caro) {
                                echo "Reduza custos de manutenção trocando fornecedor {$fornecedor_caro['fornecedor']} (média R$ " . number_format($fornecedor_caro['custo_medio'], 2, ',', '.') . ").";
                            } else {
                                // Se não há dados suficientes, buscar fornecedor com maior custo médio
                                $sql_fornecedor_alt = "SELECT f.nome as fornecedor, AVG(m.valor) as custo_medio
                                                      FROM manutencoes m 
                                                      JOIN fornecedores f ON m.fornecedor_id = f.id 
                                                      WHERE m.empresa_id = :empresa_id 
                                                      AND m.data_manutencao >= DATE_SUB(CURRENT_DATE, INTERVAL 12 MONTH)
                                                      GROUP BY f.id 
                                                      ORDER BY custo_medio DESC 
                                                      LIMIT 1";
                                $stmt = $conn->prepare($sql_fornecedor_alt);
                                $stmt->bindParam(':empresa_id', $empresa_id);
                                $stmt->execute();
                                $fornecedor_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($fornecedor_alt) {
                                    echo "Reduza custos de manutenção trocando fornecedor {$fornecedor_alt['fornecedor']} (média R$ " . number_format($fornecedor_alt['custo_medio'], 2, ',', '.') . ").";
                                } else {
                                    echo "Analise os custos de manutenção por fornecedor para otimizar gastos.";
                                }
                            }
                            
                        } catch (Exception $e) {
                            echo "Analise os custos de manutenção por fornecedor para otimizar gastos.";
                        }
                        ?>
                        
                        <ul style="list-style: disc inside; color: #2d3436; font-size: 1.1rem;">
                            <li><?php
                                try {
                                    // 2. Motorista com melhor desempenho de consumo
                                    $sql_motorista_consumo = "SELECT m.nome as motorista, 
                                                             AVG(a.litros / NULLIF(r.distancia_km, 0)) as consumo_medio,
                                                             COUNT(DISTINCT r.id) as total_rotas
                                                             FROM motoristas m 
                                                             JOIN rotas r ON m.id = r.motorista_id 
                                                             JOIN abastecimentos a ON r.id = a.rota_id 
                                                             WHERE m.empresa_id = :empresa_id 
                                                             AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
                                                             AND r.distancia_km > 0
                                                             AND a.litros > 0
                                                             GROUP BY m.id 
                                                             HAVING total_rotas >= 3
                                                             ORDER BY consumo_medio ASC 
                                                             LIMIT 1";
                                    
                                    $stmt = $conn->prepare($sql_motorista_consumo);
                                    $stmt->bindParam(':empresa_id', $empresa_id);
                                    $stmt->execute();
                                    $melhor_motorista = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($melhor_motorista) {
                                        $consumo_l_100km = $melhor_motorista['consumo_medio'] * 100;
                                        echo "Motorista {$melhor_motorista['motorista']} tem melhor desempenho de consumo (" . number_format($consumo_l_100km, 1) . "L/100km).";
                                    } else {
                                        // Se não há dados suficientes, buscar motorista com mais rotas
                                        $sql_motorista_alt = "SELECT m.nome as motorista, COUNT(r.id) as total_rotas
                                                             FROM motoristas m 
                                                             LEFT JOIN rotas r ON m.id = r.motorista_id 
                                                             WHERE m.empresa_id = :empresa_id 
                                                             AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                             GROUP BY m.id 
                                                             HAVING total_rotas > 0
                                                             ORDER BY total_rotas DESC 
                                                             LIMIT 1";
                                        $stmt = $conn->prepare($sql_motorista_alt);
                                        $stmt->bindParam(':empresa_id', $empresa_id);
                                        $stmt->execute();
                                        $motorista_alt = $stmt->fetch(PDO::FETCH_ASSOC);
                                        
                                        if ($motorista_alt) {
                                            echo "Motorista {$motorista_alt['motorista']} realizou {$motorista_alt['total_rotas']} rotas nos últimos 6 meses.";
                                        } else {
                                            echo "Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.";
                                        }
                                    }
                                    
                                } catch (Exception $e) {
                                    echo "Monitore o desempenho de consumo dos motoristas para otimizar gastos com combustível.";
                                }
                            ?></li>
                            <li><?php
                                try {
                                    // 3. Análise de lucratividade por horário de rota
                                    $sql_lucratividade_horario = "SELECT 
                                                                 CASE 
                                                                     WHEN HOUR(r.data_saida) BETWEEN 22 AND 6 THEN 'Noturno'
                                                                     WHEN HOUR(r.data_saida) BETWEEN 6 AND 18 THEN 'Diurno'
                                                                     ELSE 'Vespertino'
                                                                 END as horario,
                                                                 AVG(r.frete - r.comissao) as lucro_medio,
                                                                 COUNT(*) as total_rotas
                                                                 FROM rotas r 
                                                                 WHERE r.empresa_id = :empresa_id 
                                                                 AND r.data_saida >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                                                                 GROUP BY horario 
                                                                 HAVING total_rotas >= 3
                                                                 ORDER BY lucro_medio DESC 
                                                                 LIMIT 1";
                                    
                                    $stmt = $conn->prepare($sql_lucratividade_horario);
                                    $stmt->bindParam(':empresa_id', $empresa_id);
                                    $stmt->execute();
                                    $melhor_horario = $stmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($melhor_horario) {
                                        echo "Rotas {$melhor_horario['horario']} apresentam maior lucratividade média (R$ " . number_format($melhor_horario['lucro_medio'], 2, ',', '.') . ").";
                                    } else {
                                        echo "Analise a lucratividade por horário de rotas para otimizar operações.";
                                    }
                                    
                                } catch (Exception $e) {
                                    echo "Analise a lucratividade por horário de rotas para otimizar operações.";
                                }
                            ?></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="js/dashboard.js"></script>
    <script src="js/theme.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
        // Função para inicializar o gráfico de distribuição de despesas
        async function initExpensesDistributionChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('expensesDistributionChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch('/sistema-frotas/api/expenses_distribution.php');
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de distribuição de despesas');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('expensesDistributionChart').getContext('2d');
                new Chart(ctx, {
                    type: 'doughnut',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        return `${context.label}: ${new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value)}`;
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de distribuição de despesas:', error);
            }
        }

        // Função para inicializar o gráfico de comissões
        async function initCommissionsChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('commissionsChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch('/sistema-frotas/api/commissions_analytics.php');
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de comissões');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('commissionsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        return `${context.dataset.label}: ${new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value)}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de comissões:', error);
            }
        }

        // Função para inicializar o gráfico de faturamento líquido
        async function initNetRevenueChart() {
            try {
                // Destroy existing chart if it exists
                const existingChart = Chart.getChart('netRevenueChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                const response = await fetch('/sistema-frotas/api/net_revenue_analytics.php');
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados de faturamento líquido');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('netRevenueChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: data,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        const formattedValue = new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value);
                                        
                                        // Adicionar cor ao valor baseado se é positivo ou negativo
                                        const color = value >= 0 ? '#2ecc40' : '#e74c3c';
                                        return `${context.dataset.label}: <span style="color: ${color}">${formattedValue}</span>`;
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico de faturamento líquido:', error);
            }
        }

        // Função para inicializar o gráfico financeiro (Faturamento x Despesas)
        // Variáveis globais para controlar o gráfico financeiro
        let financialChart = null;
        let financialChartLoading = false;
        
        async function initFinancialChart() {
            // Evitar múltiplas inicializações simultâneas
            if (financialChartLoading) {
                console.log('Gráfico financeiro já está sendo carregado...');
                return;
            }
            
            financialChartLoading = true;
            try {
                // Destroy existing chart if it exists
                if (financialChart) {
                    financialChart.destroy();
                    financialChart = null;
                }
                
                // Double check with Chart.js registry
                const existingChart = Chart.getChart('financialChart');
                if (existingChart) {
                    existingChart.destroy();
                }

                // Verificar se o canvas existe
                const canvas = document.getElementById('financialChart');
                if (!canvas) {
                    console.error('Canvas financialChart não encontrado');
                    return;
                }

                const response = await fetch('/sistema-frotas/api/financial_analytics.php');
                if (!response.ok) {
                    throw new Error('Erro ao carregar dados financeiros');
                }
                
                const data = await response.json();
                
                // Criar o gráfico
                const ctx = document.getElementById('financialChart').getContext('2d');
                financialChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: data.labels || ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: data.faturamento || [],
                                backgroundColor: 'rgba(46, 204, 64, 0.8)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                                borderSkipped: false,
                            },
                            {
                                label: 'Despesas',
                                data: data.despesas || [],
                                backgroundColor: 'rgba(231, 76, 60, 0.8)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                                borderSkipped: false,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 12,
                                        weight: '500'
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#fff',
                                bodyColor: '#fff',
                                borderColor: 'rgba(255, 255, 255, 0.1)',
                                borderWidth: 1,
                                cornerRadius: 6,
                                displayColors: true,
                                callbacks: {
                                    label: function(context) {
                                        const value = context.raw;
                                        const formattedValue = new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(value);
                                        return `${context.dataset.label}: ${formattedValue}`;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)',
                                    lineWidth: 1
                                },
                                ticks: {
                                    font: {
                                        size: 11
                                    },
                                    callback: function(value) {
                                        return new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL',
                                            maximumFractionDigits: 0
                                        }).format(value);
                                    }
                                }
                            }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
                
            } catch (error) {
                console.error('Erro ao carregar gráfico financeiro:', error);
                
                // Criar gráfico com dados padrão em caso de erro
                const ctx = document.getElementById('financialChart').getContext('2d');
                financialChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                        datasets: [
                            {
                                label: 'Faturamento',
                                data: [<?php echo $total_fretes; ?>, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                backgroundColor: 'rgba(46, 204, 64, 0.8)',
                                borderColor: 'rgba(46, 204, 64, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                            },
                            {
                                label: 'Despesas',
                                data: [<?php echo ($total_desp_viagem + $total_desp_fixas + $total_contas_pagas + $total_manutencoes + $total_pneu_manutencao + $total_parcelas_financiamento); ?>, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                                backgroundColor: 'rgba(231, 76, 60, 0.8)',
                                borderColor: 'rgba(231, 76, 60, 1)',
                                borderWidth: 2,
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            } finally {
                financialChartLoading = false;
            }
        }

        // Inicializar os gráficos quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            initFinancialChart();
            initExpensesDistributionChart();
            initCommissionsChart();
            initNetRevenueChart();
        });
    </script>
</body>
</html>
