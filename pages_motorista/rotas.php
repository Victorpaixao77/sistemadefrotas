<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['motorista_id'])) {
    header('Location: login.php');
    exit;
}

$motorista_id = $_SESSION['motorista_id'];
$empresa_id = $_SESSION['empresa_id'];

// Buscar veículos disponíveis
$conn = getConnection();
$stmt = $conn->prepare('
    SELECT v.* 
    FROM veiculos v 
    WHERE v.empresa_id = :empresa_id
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar estados
$stmt = $conn->query('SELECT * FROM estados ORDER BY nome');
$estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Cadastro de Rotas';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <style>
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
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-content">
            <div class="dashboard-header">
                <div>
                    <h1>Rotas</h1>
                    <p>Motorista: <?php echo htmlspecialchars($_SESSION['motorista_nome']); ?></p>
                </div>
            </div>
            <div class="dashboard-content">
                <div class="dashboard-grid">
                    <div class="dashboard-card" style="grid-column: 1 / -1;">
                        <div class="card-header">
                            <h3>Nova Rota</h3>
                        </div>
                        <div class="card-body">
                            <form id="rotaForm" method="post" action="api/rotas.php">
                                <input type="hidden" name="action" value="create">
                                <input type="hidden" name="motorista_id" value="<?php echo $motorista_id; ?>">
                                <input type="hidden" name="empresa_id" value="<?php echo $empresa_id; ?>">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="data_rota" class="required">Data da Rota*</label>
                                        <input type="date" id="data_rota" name="data_rota" class="form-control" required>
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
                                </div>

                                <h4>Origem e Destino</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="estado_origem" class="required">Estado de Origem*</label>
                                        <select id="estado_origem" name="estado_origem" class="form-control" required>
                                            <option value="">Selecione o estado</option>
                                            <?php foreach ($estados as $estado): ?>
                                            <option value="<?php echo $estado['uf']; ?>">
                                                <?php echo htmlspecialchars($estado['nome']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="cidade_origem_id" class="required">Cidade de Origem*</label>
                                        <select id="cidade_origem_id" name="cidade_origem_id" class="form-control" required>
                                            <option value="">Selecione primeiro o estado</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="estado_destino" class="required">Estado de Destino*</label>
                                        <select id="estado_destino" name="estado_destino" class="form-control" required>
                                            <option value="">Selecione o estado</option>
                                            <?php foreach ($estados as $estado): ?>
                                            <option value="<?php echo $estado['uf']; ?>">
                                                <?php echo htmlspecialchars($estado['nome']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="cidade_destino_id" class="required">Cidade de Destino*</label>
                                        <select id="cidade_destino_id" name="cidade_destino_id" class="form-control" required>
                                            <option value="">Selecione primeiro o estado</option>
                                        </select>
                                    </div>
                                </div>

                                <h4>Dados da Viagem</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="data_saida" class="required">Data/Hora Saída*</label>
                                        <input type="datetime-local" id="data_saida" name="data_saida" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="data_chegada">Data/Hora Chegada</label>
                                        <input type="datetime-local" id="data_chegada" name="data_chegada" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="km_saida">KM Saída</label>
                                        <input type="number" id="km_saida" name="km_saida" class="form-control" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label for="km_chegada">KM Chegada</label>
                                        <input type="number" id="km_chegada" name="km_chegada" class="form-control" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label for="distancia_km">Distância (km)</label>
                                        <input type="number" id="distancia_km" name="distancia_km" class="form-control" step="0.01" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="km_vazio">KM Vazio</label>
                                        <input type="number" id="km_vazio" name="km_vazio" class="form-control" step="0.01" value="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="total_km">Total KM</label>
                                        <input type="number" id="total_km" name="total_km" class="form-control" step="0.01" readonly>
                                    </div>
                                </div>

                                <h4>Dados Financeiros e Eficiência</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="frete">Valor do Frete (R$)</label>
                                        <input type="number" id="frete" name="frete" class="form-control" step="0.01" value="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="comissao">Comissão (R$)</label>
                                        <input type="number" id="comissao" name="comissao" class="form-control" step="0.01" value="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="percentual_vazio">Percentual Vazio (%)</label>
                                        <input type="number" id="percentual_vazio" name="percentual_vazio" class="form-control" step="0.01" value="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="eficiencia_viagem">Eficiência da Viagem (%)</label>
                                        <input type="number" id="eficiencia_viagem" name="eficiencia_viagem" class="form-control" step="0.01" value="0.00">
                                    </div>
                                    <div class="form-group">
                                        <label for="no_prazo">Entrega no Prazo</label>
                                        <select id="no_prazo" name="no_prazo" class="form-control">
                                            <option value="1">Sim</option>
                                            <option value="0">Não</option>
                                        </select>
                                    </div>
                                </div>

                                <h4>Dados da Carga</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="peso_carga">Peso da Carga (kg)</label>
                                        <input type="number" id="peso_carga" name="peso_carga" class="form-control" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label for="descricao_carga">Descrição da Carga</label>
                                        <textarea id="descricao_carga" name="descricao_carga" class="form-control" rows="3"></textarea>
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
                                        <i class="fas fa-save"></i> Salvar Rota
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
    // Carregar cidades ao selecionar estado
    function carregarCidades(estadoSelect, cidadeSelect) {
        const estado = estadoSelect.value;
        if (!estado) {
            cidadeSelect.innerHTML = '<option value="">Selecione primeiro o estado</option>';
            return;
        }

        fetch(`api/cidades.php?estado=${estado}`)
            .then(response => response.json())
            .then(cidades => {
                cidadeSelect.innerHTML = '<option value="">Selecione a cidade</option>';
                cidades.forEach(cidade => {
                    cidadeSelect.innerHTML += `<option value="${cidade.id}">${cidade.nome}</option>`;
                });
            })
            .catch(error => {
                console.error('Erro ao carregar cidades:', error);
                cidadeSelect.innerHTML = '<option value="">Erro ao carregar cidades</option>';
            });
    }

    // Adicionar eventos para carregar cidades
    document.getElementById('estado_origem').addEventListener('change', function() {
        carregarCidades(this, document.getElementById('cidade_origem_id'));
    });

    document.getElementById('estado_destino').addEventListener('change', function() {
        carregarCidades(this, document.getElementById('cidade_destino_id'));
    });

    // Função para calcular a distância
    function calcularDistancia() {
        const kmSaida = parseFloat(document.getElementById('km_saida').value) || 0;
        const kmChegada = parseFloat(document.getElementById('km_chegada').value) || 0;
        const distancia = kmChegada - kmSaida;
        document.getElementById('distancia_km').value = distancia.toFixed(2);
        calcularTotalKM();
    }

    // Função para calcular o Total KM
    function calcularTotalKM() {
        const distancia = parseFloat(document.getElementById('distancia_km').value) || 0;
        const kmVazio = parseFloat(document.getElementById('km_vazio').value) || 0;
        const totalKM = distancia + kmVazio;
        document.getElementById('total_km').value = totalKM.toFixed(2);
        calcularPercentualVazio();
    }

    // Função para calcular o Percentual Vazio
    function calcularPercentualVazio() {
        const kmVazio = parseFloat(document.getElementById('km_vazio').value) || 0;
        const totalKM = parseFloat(document.getElementById('total_km').value) || 0;
        const percentualVazio = totalKM > 0 ? (kmVazio / totalKM) * 100 : 0;
        document.getElementById('percentual_vazio').value = percentualVazio.toFixed(2);
        calcularEficienciaViagem();
    }

    // Função para calcular a Eficiência da Viagem
    function calcularEficienciaViagem() {
        const percentualVazio = parseFloat(document.getElementById('percentual_vazio').value) || 0;
        const eficienciaViagem = 100 - percentualVazio;
        document.getElementById('eficiencia_viagem').value = eficienciaViagem.toFixed(2);
    }

    // Adicionar event listeners para os campos
    document.getElementById('km_saida').addEventListener('input', calcularDistancia);
    document.getElementById('km_chegada').addEventListener('input', calcularDistancia);
    document.getElementById('km_vazio').addEventListener('input', calcularTotalKM);

    // Cálculo automático do frete e comissão
    document.getElementById('frete').addEventListener('input', calcularComissao);
    document.getElementById('comissao').addEventListener('input', calcularComissao);

    function calcularComissao() {
        const frete = parseFloat(document.getElementById('frete').value) || 0;
        const comissao = frete * 0.1; // 10% do frete
        const comissaoInput = document.getElementById('comissao');
        if (comissaoInput) {
            comissaoInput.value = comissao.toFixed(2);
        }
    }

    // Validação do formulário
    document.getElementById('rotaForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        
        // Log dos dados do formulário
        console.log('Enviando dados do formulário:', Object.fromEntries(formData));
        
        fetch('api/rotas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Erro ao fazer parse do JSON:', text);
                    throw new Error('Resposta inválida do servidor');
                }
            });
        })
        .then(data => {
            if (data.success) {
                alert('Rota cadastrada com sucesso!');
                window.location.reload();
            } else {
                console.error('Erro retornado pelo servidor:', data);
                alert(data.error || 'Erro ao cadastrar rota');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            alert('Erro ao cadastrar rota: ' + error.message);
        });
    });
    </script>
</body>
</html> 