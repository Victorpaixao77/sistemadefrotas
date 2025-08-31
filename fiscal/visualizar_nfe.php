<?php
/**
 * 👁️ VISUALIZAÇÃO DE NOTA FISCAL ELETRÔNICA
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configurar sessão
configure_session();
session_start();

// Verificar autenticação
if (!isset($_SESSION['empresa_id'])) {
    die('Acesso negado');
}

$empresa_id = $_SESSION['empresa_id'];
$nfe_id = $_GET['id'] ?? null;

if (!$nfe_id) {
    die('ID da NF-e não fornecido');
}

$conn = getConnection();

// Buscar dados da NF-e
$stmt = $conn->prepare("
    SELECT * FROM fiscal_nfe_clientes 
    WHERE id = ? AND empresa_id = ?
");
$stmt->execute([$nfe_id, $empresa_id]);
$nfe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nfe) {
    die('NF-e não encontrada');
}

// Buscar dados da empresa
$stmt = $conn->prepare("
    SELECT * FROM empresa_clientes WHERE id = ?
");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Se não encontrar empresa, usar dados padrão
if (!$empresa) {
    $empresa = [
        'razao_social' => 'Cliente Padrão LTDA',
        'nome_fantasia' => 'Cliente Padrão',
        'cnpj' => '00.000.000/0001-00',
        'endereco' => 'Endereço do Cliente',
        'cidade' => 'Cidade',
        'estado' => 'RS',
        'cep' => '00000-000',
        'telefone' => '(00) 0000-0000',
        'email' => 'contato@cliente.com'
    ];
}

// Buscar itens da NF-e
$stmt = $conn->prepare("
    SELECT * FROM fiscal_nfe_itens WHERE nfe_id = ?
");
$stmt->execute([$nfe_id]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totais
$total_itens = 0;
foreach ($itens as $item) {
    $total_itens += $item['valor_total_item'];
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NF-e #<?php echo $nfe['numero_nfe']; ?> - Visualização</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <style>
        .nfe-view-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .nfe-header {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid var(--border-color);
        }
        
        .nfe-title {
            font-size: 2rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .nfe-subtitle {
            font-size: 1.1rem;
            color: var(--text-secondary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .status-pendente { background: #d1ecf1; color: #0c5460; }
        .status-autorizada { background: #d4edda; color: #155724; }
        .status-cancelada { background: #f8d7da; color: #721c24; }
        .status-denegada { background: #f8d7da; color: #721c24; }
        .status-inutilizada { background: #e2e3e5; color: #383d41; }
        
        .nfe-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-section {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border-color);
        }
        
        .info-section h3 {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-label {
            font-weight: bold;
            color: var(--text-primary);
            min-width: 150px;
        }
        
        .info-value {
            color: var(--text-secondary);
            text-align: right;
            flex: 1;
        }
        
        .itens-table {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .itens-table h3 {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background: var(--bg-primary);
            color: var(--text-primary);
            font-weight: bold;
        }
        
        .table tbody tr:hover {
            background: var(--bg-primary);
        }
        
        .totais-section {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }
        
        .totais-section h3 {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 0;
            font-size: 1.1rem;
        }
        
        .total-final {
            font-size: 1.3rem;
            font-weight: bold;
            border-top: 2px solid var(--primary-color);
            padding-top: 15px;
            margin-top: 15px;
            color: var(--primary-color);
        }
        
        .chave-acesso {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .chave-acesso h3 {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .chave-text {
            font-family: monospace;
            font-size: 1.2rem;
            letter-spacing: 2px;
            background: var(--bg-primary);
            padding: 15px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .actions-bar {
            background: var(--bg-secondary);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            text-align: center;
        }
        
        .btn {
            padding: 10px 20px;
            margin: 0 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #007bff; color: white; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-danger { background: #dc3545; color: white; }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="nfe-view-container">
        <!-- Link de Voltar -->
        <a href="pages/nfe.php" class="back-link">
            ← Voltar para Lista de NF-e
        </a>
        
        <!-- Cabeçalho da NF-e -->
        <div class="nfe-header">
            <div class="nfe-title">NOTA FISCAL ELETRÔNICA</div>
            <div class="nfe-subtitle">
                Nº <?php echo $nfe['numero_nfe']; ?> - Série <?php echo $nfe['serie_nfe']; ?>
            </div>
            <div class="nfe-subtitle" style="margin-top: 15px;">
                Status: 
                <span class="status-badge status-<?php echo $nfe['status'] ?? 'pendente'; ?>">
                    <?php echo ucfirst($nfe['status'] ?? 'pendente'); ?>
                </span>
            </div>
        </div>
        
        <!-- Barra de Ações -->
        <div class="actions-bar">
            <a href="../impressao/nfe.php?id=<?php echo $nfe['id']; ?>" target="_blank" class="btn btn-primary">
                🖨️ Imprimir NF-e
            </a>
            <button onclick="editarNFE(<?php echo $nfe['id']; ?>)" class="btn btn-info">
                ✏️ Editar NF-e
            </button>
            <button onclick="window.close()" class="btn btn-secondary">
                ❌ Fechar
            </button>
        </div>
        
        <!-- Informações da NF-e -->
        <div class="nfe-info-grid">
            <!-- Dados da Empresa -->
            <div class="info-section">
                <h3>🏢 DADOS DO EMITENTE</h3>
                <div class="info-row">
                    <span class="info-label">Razão Social:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empresa['razao_social'] ?? 'EMPRESA LTDA'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CNPJ:</span>
                    <span class="info-value"><?php echo $empresa['cnpj'] ?? '00.000.000/0001-00'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Inscrição Estadual:</span>
                    <span class="info-value"><?php echo $empresa['inscricao_estadual'] ?? '000000000'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Endereço:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empresa['endereco'] ?? 'Endereço da Empresa'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cidade/UF:</span>
                    <span class="info-value"><?php echo htmlspecialchars($empresa['cidade'] ?? 'Cidade'); ?>/<?php echo $empresa['estado'] ?? 'UF'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CEP:</span>
                    <span class="info-value"><?php echo $empresa['cep'] ?? '00000-000'; ?></span>
                </div>
            </div>
            
            <!-- Dados do Cliente -->
            <div class="info-section">
                <h3>👤 DADOS DO DESTINATÁRIO</h3>
                <div class="info-row">
                    <span class="info-label">Nome/Razão Social:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['cliente_razao_social'] ?? 'Cliente'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CNPJ/CPF:</span>
                    <span class="info-value"><?php echo $nfe['cliente_cnpj'] ?? '00.000.000/0001-00'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Nome Fantasia:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['cliente_nome_fantasia'] ?? 'N/A'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Informações da NF-e -->
        <div class="nfe-info-grid">
            <div class="info-section">
                <h3>📋 INFORMAÇÕES DA NF-e</h3>
                <div class="info-row">
                    <span class="info-label">Data de Emissão:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($nfe['data_emissao'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data de Entrada:</span>
                    <span class="info-value"><?php echo $nfe['data_entrada'] ? date('d/m/Y', strtotime($nfe['data_entrada'])) : 'N/A'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Chave de Acesso:</span>
                    <span class="info-value"><?php echo $nfe['chave_acesso'] ?? 'N/A'; ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>🔐 DADOS FISCAIS</h3>
                <div class="info-row">
                    <span class="info-label">Protocolo de Autorização:</span>
                    <span class="info-value"><?php echo $nfe['protocolo_autorizacao'] ?? 'Pendente'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status da Assinatura:</span>
                    <span class="info-value"><?php echo ucfirst($nfe['status_assinatura'] ?? 'pendente'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hash da Assinatura:</span>
                    <span class="info-value"><?php echo $nfe['hash_assinatura'] ? substr($nfe['hash_assinatura'], 0, 20) . '...' : 'N/A'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Itens -->
        <div class="itens-table">
            <h3>📦 ITENS DA NF-e</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Código</th>
                            <th>Descrição</th>
                            <th>NCM</th>
                            <th>CFOP</th>
                            <th>Unidade</th>
                            <th>Quantidade</th>
                            <th>Valor Unit.</th>
                            <th>Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($itens)): ?>
                            <?php foreach ($itens as $index => $item): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($item['codigo_produto'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($item['descricao_produto']); ?></td>
                                    <td><?php echo $item['ncm'] ?? ''; ?></td>
                                    <td><?php echo $item['cfop'] ?? ''; ?></td>
                                    <td><?php echo $item['unidade_comercial'] ?? 'UN'; ?></td>
                                    <td><?php echo number_format($item['quantidade_comercial'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($item['valor_unitario'], 2, ',', '.'); ?></td>
                                    <td>R$ <?php echo number_format($item['valor_total_item'], 2, ',', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #666;">
                                    Nenhum item cadastrado
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Totais -->
        <div class="totais-section">
            <h3>💰 TOTAIS</h3>
            <div class="total-row">
                <span>Total dos Itens:</span>
                <span>R$ <?php echo number_format($total_itens, 2, ',', '.'); ?></span>
            </div>
            <div class="total-row total-final">
                <span>VALOR TOTAL DA NF-e:</span>
                <span>R$ <?php echo number_format($nfe['valor_total'], 2, ',', '.'); ?></span>
            </div>
        </div>
        
        <!-- Chave de Acesso -->
        <div class="chave-acesso">
            <h3>🔑 CHAVE DE ACESSO</h3>
            <div class="chave-text"><?php echo $nfe['chave_acesso'] ?? 'N/A'; ?></div>
        </div>
        
        <!-- Observações -->
        <?php if (!empty($nfe['observacoes'])): ?>
            <div class="info-section">
                <h3>📝 OBSERVAÇÕES</h3>
                <p style="margin: 0; color: var(--text-secondary);"><?php echo nl2br(htmlspecialchars($nfe['observacoes'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Rodapé -->
        <div style="text-align: center; color: #666; font-size: 0.9rem; margin-top: 40px; padding: 20px; border-top: 1px solid var(--border-color);">
            <p>Documento eletrônico - Sistema de Gestão de Frotas</p>
            <p>Visualizado em: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <!-- JavaScript Files -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editarNFE(id) {
            // Abrir modal de edição na página principal
            if (window.opener && !window.opener.closed) {
                window.opener.editarNFE(id);
                window.close();
            } else {
                // Se não conseguir abrir o modal, redirecionar para a página principal
                window.location.href = `pages/nfe.php#edit-${id}`;
            }
        }
    </script>
</body>
</html>
