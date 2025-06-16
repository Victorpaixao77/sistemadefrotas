<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

// Verifica se o motorista está logado
validar_sessao_motorista();

// Obtém dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Buscar dados do motorista
$conn = getConnection();
$stmt = $conn->prepare('
    SELECT nome
    FROM motoristas
    WHERE id = :motorista_id
    AND empresa_id = :empresa_id
');
$stmt->execute([
    'motorista_id' => $motorista_id,
    'empresa_id' => $empresa_id
]);
$motorista = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se foi fornecido um ID de rota
if (!isset($_GET['rota_id'])) {
    header('Location: index.php');
    exit;
}

$rota_id = $_GET['rota_id'];

// Buscar dados da rota
$stmt = $conn->prepare('
    SELECT r.*, 
           c1.nome as cidade_origem_nome,
           c2.nome as cidade_destino_nome
    FROM rotas r
    LEFT JOIN cidades c1 ON r.cidade_origem_id = c1.id
    LEFT JOIN cidades c2 ON r.cidade_destino_id = c2.id
    WHERE r.id = :rota_id
    AND r.empresa_id = :empresa_id
    AND r.motorista_id = :motorista_id
');
$stmt->execute([
    'rota_id' => $rota_id,
    'empresa_id' => $empresa_id,
    'motorista_id' => $motorista_id
]);
$rota = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rota) {
    header('Location: index.php');
    exit;
}

// Buscar despesas existentes
$stmt = $conn->prepare('
    SELECT *
    FROM despesas_viagem
    WHERE rota_id = :rota_id
    AND empresa_id = :empresa_id
');
$stmt->execute([
    'rota_id' => $rota_id,
    'empresa_id' => $empresa_id
]);
$despesa = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - Despesas de Viagem</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
</head>
<body>
    <div class="app-container">
        <div class="main-content">
            <div class="content-header">
                <div class="header-left">
                    <h1>Despesas de Viagem</h1>
                    <p class="subtitle">Motorista: <?php echo htmlspecialchars($motorista['nome']); ?></p>
                </div>
            </div>
            
            <div class="content-body">
                <div class="card">
                    <div class="card-header">
                        <h2>Informações da Rota</h2>
                    </div>
                    <div class="card-body">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Data:</label>
                                <span><?php echo date('d/m/Y', strtotime($rota['data_rota'])); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Origem:</label>
                                <span><?php echo htmlspecialchars($rota['cidade_origem_nome']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Destino:</label>
                                <span><?php echo htmlspecialchars($rota['cidade_destino_nome']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Status:</label>
                                <span class="status-badge status-<?php echo $rota['status']; ?>"><?php echo ucfirst($rota['status']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2>Registro de Despesas</h2>
                    </div>
                    <div class="card-body">
                        <form id="despesasForm" method="post" action="api/despesas.php">
                            <input type="hidden" name="action" value="<?php echo $despesa ? 'update' : 'create'; ?>">
                            <input type="hidden" name="rota_id" value="<?php echo $rota_id; ?>">
                            <?php if ($despesa): ?>
                            <input type="hidden" name="id" value="<?php echo $despesa['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="arla">ARLA (R$)</label>
                                    <input type="number" id="arla" name="arla" class="form-control" step="0.01" value="<?php echo $despesa['arla'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="pedagios">Pedágios (R$)</label>
                                    <input type="number" id="pedagios" name="pedagios" class="form-control" step="0.01" value="<?php echo $despesa['pedagios'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="caixinha">Caixinha (R$)</label>
                                    <input type="number" id="caixinha" name="caixinha" class="form-control" step="0.01" value="<?php echo $despesa['caixinha'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="estacionamento">Estacionamento (R$)</label>
                                    <input type="number" id="estacionamento" name="estacionamento" class="form-control" step="0.01" value="<?php echo $despesa['estacionamento'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="lavagem">Lavagem (R$)</label>
                                    <input type="number" id="lavagem" name="lavagem" class="form-control" step="0.01" value="<?php echo $despesa['lavagem'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="borracharia">Borracharia (R$)</label>
                                    <input type="number" id="borracharia" name="borracharia" class="form-control" step="0.01" value="<?php echo $despesa['borracharia'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="eletrica_mecanica">Elétrica/Mecânica (R$)</label>
                                    <input type="number" id="eletrica_mecanica" name="eletrica_mecanica" class="form-control" step="0.01" value="<?php echo $despesa['eletrica_mecanica'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="adiantamento">Adiantamento (R$)</label>
                                    <input type="number" id="adiantamento" name="adiantamento" class="form-control" step="0.01" value="<?php echo $despesa['adiantamento'] ?? '0.00'; ?>" onchange="calcularTotal()">
                                </div>
                                
                                <div class="form-group">
                                    <label for="total">Total (R$)</label>
                                    <input type="number" id="total" name="total" class="form-control" step="0.01" readonly>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="form-actions">
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Voltar
                            </a>
                            <button type="submit" form="despesasForm" class="btn btn-primary">
                                <i class="fas fa-save"></i> Salvar Despesas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    
    <script>
    function calcularTotal() {
        const campos = [
            'arla',
            'pedagios',
            'caixinha',
            'estacionamento',
            'lavagem',
            'borracharia',
            'eletrica_mecanica',
            'adiantamento'
        ];
        
        let total = 0;
        
        campos.forEach(campo => {
            const valor = parseFloat(document.getElementById(campo).value) || 0;
            total += valor;
        });
        
        document.getElementById('total').value = total.toFixed(2);
    }
    
    // Calcular total ao carregar a página
    document.addEventListener('DOMContentLoaded', function() {
        calcularTotal();
        
        // Evento de submit do formulário
        document.getElementById('despesasForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/despesas.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Mostra notificação de sucesso
                    Swal.fire({
                        title: 'Sucesso!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redireciona para a página inicial
                            window.location.href = 'index.php';
                        }
                    });
                } else {
                    // Mostra notificação de erro
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Erro ao salvar despesas',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao salvar despesas',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
    });
    </script>
</body>
</html> 