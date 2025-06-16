<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

// Log da sessão para debug
error_log("=== Página de checklists ===");
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

// Buscar checklists do motorista
$conn = getConnection();
error_log('Iniciando busca de checklists para empresa_id: ' . $empresa_id . ' e motorista_id: ' . $motorista_id);

// Buscar todos os checklists do motorista
$stmt = $conn->prepare('
    SELECT cv.*, 
           v.placa, v.modelo,
           r.cidade_origem_id, r.cidade_destino_id,
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
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->bindParam(':motorista_id', $motorista_id, PDO::PARAM_INT);
$stmt->execute();
$checklists = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log('Checklists encontrados: ' . print_r($checklists, true));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - Checklists</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- JavaScript Files -->
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
        
        .checklist-section {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .checklist-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .checklist-group {
            margin-bottom: 15px;
        }
        
        .checklist-group label {
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
        
        .checklists-pendentes {
            background: var(--bg-secondary);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .checklists-pendentes h2 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .checklists-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .checklists-table th,
        .checklists-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .checklists-table th {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .checklists-table tr:hover {
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
            
            .checklist-section,
            .checklists-pendentes {
                padding: 1rem;
            }
            
            .checklists-table {
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
                    <h1>Checklists</h1>
                    <p>Motorista: <?php echo htmlspecialchars($_SESSION['motorista_nome']); ?></p>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Novo Checklist</h3>
                        </div>
                        <div class="card-body">
                            <form id="checklistForm" method="post" action="api/checklist.php" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="motorista_id" value="<?php echo $motorista_id; ?>">
                                <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="data_rota" class="required">Data da Rota*</label>
                                        <input type="date" id="data_rota" name="data_rota" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="data_checklist" class="required">Data do Checklist*</label>
                                        <input type="datetime-local" id="data_checklist" name="data_checklist" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="veiculo_id" class="required">Veículo*</label>
                                        <select id="veiculo_id" name="veiculo_id" class="form-control" required>
                                            <option value="">Selecione um veículo</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="rota_id" class="required">Rota*</label>
                                        <select id="rota_id" name="rota_id" class="form-control" required>
                                            <option value="">Selecione primeiro a data e o veículo</option>
                                        </select>
                                    </div>
                                </div>

                                <h4>Verificações do Veículo</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="oleo_motor" value="1">
                                            Óleo do Motor
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="agua_radiador" value="1">
                                            Água do Radiador
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="fluido_freio" value="1">
                                            Fluido de Freio
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="fluido_direcao" value="1">
                                            Fluido de Direção
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="combustivel" value="1">
                                            Combustível
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="pneus" value="1">
                                            Pneus
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="estepe" value="1">
                                            Estepe
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="luzes" value="1">
                                            Luzes
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="buzina" value="1">
                                            Buzina
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="limpador_para_brisa" value="1">
                                            Limpador de Para-brisa
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="agua_limpador" value="1">
                                            Água do Limpador
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="freios" value="1">
                                            Freios
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="vazamentos" value="1">
                                            Vazamentos
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="rastreador" value="1">
                                            Rastreador
                                        </label>
                                    </div>
                                </div>

                                <h4>Equipamentos de Segurança</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="triangulo" value="1">
                                            Triângulo
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="extintor" value="1">
                                            Extintor
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="chave_macaco" value="1">
                                            Chave de Macaco
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="cintas" value="1">
                                            Cintas
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="primeiros_socorros" value="1">
                                            Kit de Primeiros Socorros
                                        </label>
                                    </div>
                                </div>

                                <h4>Documentação</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="doc_veiculo" value="1">
                                            Documentação do Veículo
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="cnh" value="1">
                                            CNH
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="licenciamento" value="1">
                                            Licenciamento
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="seguro" value="1">
                                            Seguro
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="manifesto_carga" value="1">
                                            Manifesto de Carga
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="doc_empresa" value="1">
                                            Documentação da Empresa
                                        </label>
                                    </div>
                                </div>

                                <h4>Carga e Motorista</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="carga_amarrada" value="1">
                                            Carga Amarrada
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="peso_correto" value="1">
                                            Peso Correto
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="motorista_descansado" value="1">
                                            Motorista Descansado
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="motorista_sobrio" value="1">
                                            Motorista Sóbrio
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="celular_carregado" value="1">
                                            Celular Carregado
                                        </label>
                                    </div>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" name="epi" value="1">
                                            EPI
                                        </label>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="observacoes">Observações</label>
                                    <textarea id="observacoes" name="observacoes" class="form-control" rows="3"></textarea>
                                </div>

                                <div class="form-actions">
                                    <a href="index.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Voltar
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Salvar Checklist
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

        // Evento de submit do formulário
        document.getElementById('checklistForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api/checklist.php', {
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
                            document.getElementById('checklistForm').reset();
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
                        text: data.message || 'Erro ao registrar checklist',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                Swal.fire({
                    title: 'Erro!',
                    text: 'Erro ao registrar checklist',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        });
    });
    </script>
</body>
</html> 