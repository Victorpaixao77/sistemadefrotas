<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

// Log da sessão para debug
error_log("=== Página de abastecimento ===");
error_log("Session ID: " . session_id());
error_log("Session Name: " . session_name());
error_log("Session Status: " . session_status());
error_log("Session Data: " . print_r($_SESSION, true));

// Verifica se o motorista está logado
validar_sessao_motorista();

// Obtém dados do motorista
$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Log dos dados do motorista
error_log("Dados do motorista:");
error_log("motorista_id: " . $motorista_id);
error_log("empresa_id: " . $empresa_id);

// Log da sessão
error_log('Dados da sessão: ' . print_r($_SESSION, true));

// Buscar veículos disponíveis
$conn = getConnection();
error_log('Iniciando busca de veículos para empresa_id: ' . $empresa_id . ' e motorista_id: ' . $motorista_id);

// Buscar todos os veículos da empresa
$stmt = $conn->prepare('
    SELECT v.* 
    FROM veiculos v 
    WHERE v.empresa_id = :empresa_id
    AND v.status_id = 1
    ORDER BY v.placa
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log('Veículos encontrados: ' . print_r($veiculos, true));

// Buscar tipos de combustível
$stmt = $conn->query('SELECT * FROM tipos_combustivel ORDER BY nome');
$combustiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log('Tipos de combustível encontrados: ' . print_r($combustiveis, true));

// Buscar formas de pagamento
$stmt = $conn->query('SELECT * FROM formas_pagamento ORDER BY nome');
$formas_pagamento = $stmt->fetchAll(PDO::FETCH_ASSOC);
error_log('Formas de pagamento encontradas: ' . print_r($formas_pagamento, true));

// Processar formulário
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $veiculo_id = $_POST['veiculo_id'] ?? '';
    $data_abastecimento = $_POST['data_abastecimento'] ?? date('Y-m-d');
    $tipo_combustivel = $_POST['tipo_combustivel'] ?? '';
    $quantidade = $_POST['quantidade'] ?? '';
    $valor_litro = $_POST['valor_litro'] ?? '';
    $valor_total = $_POST['valor_total'] ?? '';
    $km_atual = $_POST['km_atual'] ?? '';
    $posto = $_POST['posto'] ?? '';
    $observacoes = $_POST['observacoes'] ?? '';
    
    if (empty($veiculo_id) || empty($tipo_combustivel) || empty($quantidade) || 
        empty($valor_litro) || empty($valor_total) || empty($km_atual)) {
        $error = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        try {
            $sql = "INSERT INTO abastecimentos (
                        veiculo_id, data_abastecimento, tipo_combustivel, 
                        quantidade, valor_litro, valor_total, km_atual, 
                        posto, observacoes, status, fonte
                    ) VALUES (
                        :veiculo_id, :data_abastecimento, :tipo_combustivel,
                        :quantidade, :valor_litro, :valor_total, :km_atual,
                        :posto, :observacoes, 'pendente', 'motorista'
                    )";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':veiculo_id', $veiculo_id);
            $stmt->bindValue(':data_abastecimento', $data_abastecimento);
            $stmt->bindValue(':tipo_combustivel', $tipo_combustivel);
            $stmt->bindValue(':quantidade', $quantidade);
            $stmt->bindValue(':valor_litro', $valor_litro);
            $stmt->bindValue(':valor_total', $valor_total);
            $stmt->bindValue(':km_atual', $km_atual);
            $stmt->bindValue(':posto', $posto);
            $stmt->bindValue(':observacoes', $observacoes);
            
            if ($stmt->execute()) {
                $success = 'Abastecimento registrado com sucesso! Aguardando aprovação.';
            } else {
                $error = 'Erro ao registrar abastecimento. Por favor, tente novamente.';
            }
        } catch (PDOException $e) {
            $error = 'Erro ao registrar abastecimento: ' . $e->getMessage();
        }
    }
}

// Obter abastecimentos pendentes do motorista
$sql = "SELECT a.*, v.placa, v.modelo 
        FROM abastecimentos a 
        JOIN veiculos v ON a.veiculo_id = v.id 
        WHERE v.empresa_id = :empresa_id 
        AND a.status = 'pendente'
        ORDER BY a.data_abastecimento DESC";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':empresa_id', $empresa_id);
$stmt->execute();
$abastecimentos_pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - Cadastro de Abastecimento</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    
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
        
        .form-section {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .form-control:focus {
            border-color: var(--accent-primary);
            outline: none;
        }
        
        .required::after {
            content: " *";
            color: red;
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
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .abastecimentos-pendentes {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .abastecimentos-pendentes h2 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .abastecimentos-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .abastecimentos-table th,
        .abastecimentos-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .abastecimentos-table th {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .abastecimentos-table tr:hover {
            background: var(--bg-primary);
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .status-pendente {
            background: var(--warning-color);
            color: var(--warning-color-dark);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: var(--success-color);
            color: var(--success-color-dark);
        }
        
        .alert-error {
            background: var(--danger-color);
            color: var(--danger-color-dark);
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .form-section,
            .abastecimentos-pendentes {
                padding: 1rem;
            }
            
            .abastecimentos-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-content">
            <div class="dashboard-header">
                <div>
                    <h1>Abastecimento</h1>
                    <p>Motorista: <?php echo htmlspecialchars($_SESSION['motorista_nome']); ?></p>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Novo Abastecimento</h3>
                        </div>
                        <div class="card-body">
                            <form id="abastecimentoForm" method="post" action="api/abastecimento.php" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="motorista_id" value="<?php echo $motorista_id; ?>">
                                <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="data_rota" class="required">Data da Rota*</label>
                                        <input type="date" id="data_rota" name="data_rota" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="data_abastecimento" class="required">Data do Abastecimento*</label>
                                        <input type="datetime-local" id="data_abastecimento" name="data_abastecimento" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="veiculo_id" class="required">Veículo*</label>
                                        <select id="veiculo_id" name="veiculo_id" class="form-control" required>
                                            <option value="">Selecione um veículo</option>
                                            <?php foreach ($veiculos as $veiculo): ?>
                                            <option value="<?php echo $veiculo['id']; ?>">
                                                <?php echo htmlspecialchars($veiculo['placa'] . ' - ' . $veiculo['modelo']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="rota_id" class="required">Rota*</label>
                                        <select id="rota_id" name="rota_id" class="form-control" required>
                                            <option value="">Selecione primeiro a data e o veículo</option>
                                        </select>
                                    </div>
                                </div>

                                <h4>Dados do Abastecimento</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="tipo_combustivel_id" class="required">Tipo de Combustível*</label>
                                        <select id="tipo_combustivel_id" name="tipo_combustivel" class="form-control" required>
                                            <option value="">Selecione o combustível</option>
                                            <?php foreach ($combustiveis as $combustivel): ?>
                                            <option value="<?php echo htmlspecialchars($combustivel['nome']); ?>">
                                                <?php echo htmlspecialchars($combustivel['nome']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="posto" class="required">Posto de Combustível*</label>
                                        <input type="text" id="posto" name="posto" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="quantidade" class="required">Quantidade (Litros)*</label>
                                        <input type="number" id="quantidade" name="quantidade" class="form-control" step="0.01" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="preco_litro" class="required">Preço por Litro (R$)*</label>
                                        <input type="number" id="preco_litro" name="preco_litro" class="form-control" step="0.01" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="valor_total" class="required">Valor Total (R$)*</label>
                                        <input type="number" id="valor_total" name="valor_total" class="form-control" step="0.01" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="km_atual" class="required">Quilometragem Atual*</label>
                                        <input type="number" id="km_atual" name="km_atual" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="forma_pagamento_id" class="required">Forma de Pagamento*</label>
                                        <select id="forma_pagamento_id" name="forma_pagamento" class="form-control" required>
                                            <option value="">Selecione a forma de pagamento</option>
                                            <?php foreach ($formas_pagamento as $forma): ?>
                                            <option value="<?php echo htmlspecialchars($forma['nome']); ?>">
                                                <?php echo htmlspecialchars($forma['nome']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="comprovante">Comprovante</label>
                                        <input type="file" id="comprovante" name="comprovante" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                                    </div>
                                    <div class="form-group">
                                        <label for="observacoes">Observações</label>
                                        <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Voltar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Salvar Abastecimento
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
    document.addEventListener('DOMContentLoaded', function() {
        // Carregar tipos de combustível e formas de pagamento ao iniciar
        carregarTiposCombustivel();
        carregarFormasPagamento();

        // Evento para carregar veículos quando a data da rota for selecionada
        document.getElementById('data_rota').addEventListener('change', function() {
            const data = this.value;
            console.log('Data selecionada:', data);
            if (data) {
                carregarVeiculos(data);
            } else {
                document.getElementById('veiculo_id').innerHTML = '<option value="">Selecione um veículo</option>';
                document.getElementById('rota_id').innerHTML = '<option value="">Selecione uma rota</option>';
            }
        });

        // Evento para carregar rotas quando o veículo for selecionado
        document.getElementById('veiculo_id').addEventListener('change', function() {
            const veiculoId = this.value;
            const dataRota = document.getElementById('data_rota').value;
            console.log('Veículo selecionado:', veiculoId);
            console.log('Data da rota:', dataRota);
            if (veiculoId && dataRota) {
                carregarRotas(veiculoId, dataRota);
            } else {
                document.getElementById('rota_id').innerHTML = '<option value="">Selecione uma rota</option>';
            }
        });

        // Eventos para cálculo do valor total
        document.getElementById('quantidade').addEventListener('input', calcularValorTotal);
        document.getElementById('preco_litro').addEventListener('input', calcularValorTotal);

        // Evento de submit do formulário
        document.getElementById('abastecimentoForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/abastecimento.php', {
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
                            // Limpa o formulário
                            document.getElementById('abastecimentoForm').reset();
                            // Limpa os selects
                            document.getElementById('veiculo_id').innerHTML = '<option value="">Selecione um veículo</option>';
                            document.getElementById('rota_id').innerHTML = '<option value="">Selecione uma rota</option>';
                            // Desabilita o select de rotas
                            document.getElementById('rota_id').disabled = true;
                        }
                    });
                } else {
                    // Mostra notificação de erro
                    Swal.fire({
                        title: 'Erro!',
                        text: data.message || 'Erro ao registrar abastecimento',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao registrar abastecimento',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });

        // Função para carregar veículos
        function carregarVeiculos(data) {
            console.log('Carregando veículos para data:', data);
            
            // Limpa o select de veículos
            const veiculoSelect = document.getElementById('veiculo_id');
            veiculoSelect.innerHTML = '<option value="">Selecione um veículo</option>';
            
            // Faz a requisição para a API
            fetch(`api/veiculos.php?action=list&data=${data}`, {
                method: 'GET',
                credentials: 'same-origin', // Inclui cookies na requisição
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Cache-Control': 'no-cache',
                    'Pragma': 'no-cache'
                }
            })
            .then(response => {
                console.log('Status da resposta:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Resposta da API de veículos:', data);
                
                if (data.success) {
                    if (data.data && data.data.length > 0) {
                        // Adiciona os veículos ao select
                        data.data.forEach(veiculo => {
                            const option = document.createElement('option');
                            option.value = veiculo.id;
                            option.textContent = `${veiculo.placa} - ${veiculo.modelo}`;
                            veiculoSelect.appendChild(option);
                        });
                    } else {
                        console.log('Nenhum veículo encontrado para a data selecionada');
                        alert('Nenhum veículo encontrado para a data selecionada');
                    }
                } else {
                    console.error('Erro ao carregar veículos:', data.message);
                    alert(data.message || 'Erro ao carregar veículos');
                }
            })
            .catch(error => {
                console.error('Erro ao carregar veículos:', error);
                alert('Erro ao carregar veículos: ' + error.message);
            });
        }

        // Função para carregar rotas
        function carregarRotas(veiculoId, data) {
            console.log('Carregando rotas para veículo:', veiculoId, 'e data:', data);
            
            // Limpar select de rotas
            const selectRotas = document.getElementById('rota_id');
            selectRotas.innerHTML = '<option value="">Selecione uma rota</option>';
            selectRotas.disabled = true;
            
            // Fazer requisição para a API
            fetch(`api/rotas.php?action=list&veiculo_id=${veiculoId}&data=${data}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Resposta da API de rotas:', data);
                    
                    if (data.success) {
                        const rotas = data.data;
                        
                        if (rotas.length === 0) {
                            selectRotas.innerHTML = '<option value="">Nenhuma rota disponível</option>';
                        } else {
                            rotas.forEach(rota => {
                                const option = document.createElement('option');
                                option.value = rota.id;
                                option.textContent = `${rota.cidade_origem_nome} → ${rota.cidade_destino_nome}`;
                                selectRotas.appendChild(option);
                            });
                            selectRotas.disabled = false;
                        }
                    } else {
                        throw new Error(data.message || 'Erro ao carregar rotas');
                    }
                })
                .catch(error => {
                    console.error('Erro ao carregar rotas:', error);
                    selectRotas.innerHTML = '<option value="">Erro ao carregar rotas</option>';
                });
        }

        function calcularValorTotal() {
            const quantidade = parseFloat(document.getElementById('quantidade').value) || 0;
            const precoLitro = parseFloat(document.getElementById('preco_litro').value) || 0;
            const valorTotal = quantidade * precoLitro;
            document.getElementById('valor_total').value = valorTotal.toFixed(2);
        }

        // Função para carregar tipos de combustível
        function carregarTiposCombustivel() {
            fetch('api/tipos_combustivel.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('tipo_combustivel_id');
                        select.innerHTML = '<option value="">Selecione o Tipo de Combustível</option>';
                        data.tipos.forEach(tipo => {
                            const option = document.createElement('option');
                            option.value = tipo.nome;
                            option.textContent = tipo.nome;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Erro ao carregar tipos de combustível:', error));
        }

        // Função para carregar formas de pagamento
        function carregarFormasPagamento() {
            fetch('api/formas_pagamento.php?action=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const select = document.getElementById('forma_pagamento_id');
                        select.innerHTML = '<option value="">Selecione a Forma de Pagamento</option>';
                        data.formas.forEach(forma => {
                            const option = document.createElement('option');
                            option.value = forma.nome;
                            option.textContent = forma.nome;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => console.error('Erro ao carregar formas de pagamento:', error));
        }
    });
    </script>
</body>
</html> 