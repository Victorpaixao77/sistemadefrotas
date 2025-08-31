<?php
/**
 * 🖨️ IMPRESSÃO DE NOTA FISCAL ELETRÔNICA
 * 📋 Sistema de Gestão de Frotas
 */

require_once '../../includes/config.php';
require_once '../../includes/functions.php';

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
$total_icms = 0;
$total_pis = 0;
$total_cofins = 0;

foreach ($itens as $item) {
    $total_itens += $item['valor_total_item'];
    $total_icms += $item['icms_valor'] ?? 0;
    $total_pis += $item['pis_valor'] ?? 0;
    $total_cofins += $item['cofins_valor'] ?? 0;
}

$total_geral = $total_itens + ($nfe['valor_total'] - $total_itens);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NF-e #<?php echo $nfe['numero_nfe']; ?> - Impressão</title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; padding: 20px; }
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .nfe-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        
        .nfe-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .nfe-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .nfe-subtitle {
            font-size: 14px;
            color: #666;
        }
        
        .nfe-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            border-bottom: 1px solid #ccc;
            padding-bottom: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 120px;
        }
        
        .info-value {
            text-align: right;
        }
        
        .itens-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .itens-table th,
        .itens-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .itens-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .totais-section {
            border-top: 2px solid #333;
            padding-top: 20px;
            text-align: right;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .total-final {
            font-size: 18px;
            font-weight: bold;
            border-top: 1px solid #333;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .chave-acesso {
            text-align: center;
            margin-top: 30px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .chave-text {
            font-family: monospace;
            font-size: 10px;
            letter-spacing: 1px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-rascunho { background: #fff3cd; color: #856404; }
        .status-pendente { background: #d1ecf1; color: #0c5460; }
        .status-autorizada { background: #d4edda; color: #155724; }
        .status-cancelada { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Imprimir NF-e
    </button>
    
    <div class="nfe-container">
        <!-- Cabeçalho da NF-e -->
        <div class="nfe-header">
            <div class="nfe-title">NOTA FISCAL ELETRÔNICA</div>
            <div class="nfe-subtitle">Nº <?php echo $nfe['numero_nfe']; ?> - Série <?php echo $nfe['serie_nfe']; ?></div>
            <div class="nfe-subtitle">
                Status: 
                <span class="status-badge status-<?php echo $nfe['status'] ?? 'rascunho'; ?>">
                    <?php echo ucfirst($nfe['status'] ?? 'rascunho'); ?>
                </span>
            </div>
        </div>
        
        <!-- Informações da NF-e -->
        <div class="nfe-info">
            <!-- Dados da Empresa -->
            <div class="info-section">
                <h3>DADOS DO EMITENTE</h3>
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
                <h3>DADOS DO DESTINATÁRIO</h3>
                <div class="info-row">
                    <span class="info-label">Nome/Razão Social:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['cliente_razao_social'] ?? 'Cliente'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CNPJ/CPF:</span>
                    <span class="info-value"><?php echo $nfe['cliente_cnpj'] ?? '00.000.000/0001-00'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Inscrição Estadual:</span>
                    <span class="info-value"><?php echo $nfe['cliente_ie'] ?? '000000000'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Endereço:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['destinatario_endereco'] ?? 'Endereço do Cliente'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cidade/UF:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['destinatario_cidade'] ?? 'Cidade'); ?>/<?php echo $nfe['destinatario_uf'] ?? 'UF'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">CEP:</span>
                    <span class="info-value"><?php echo $nfe['destinatario_cep'] ?? '00000-000'; ?></span>
                </div>
            </div>
        </div>
        
        <!-- Informações da NF-e -->
        <div class="nfe-info">
            <div class="info-section">
                <h3>INFORMAÇÕES DA NF-e</h3>
                <div class="info-row">
                    <span class="info-label">Data de Emissão:</span>
                    <span class="info-value"><?php echo date('d/m/Y', strtotime($nfe['data_emissao'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Natureza da Operação:</span>
                    <span class="info-value"><?php echo htmlspecialchars($nfe['natureza_operacao'] ?? 'Venda de mercadoria'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tipo de Operação:</span>
                    <span class="info-value"><?php echo ucfirst($nfe['tipo_operacao'] ?? 'saida'); ?></span>
                </div>
            </div>
            
            <div class="info-section">
                <h3>DADOS FISCAIS</h3>
                <div class="info-row">
                    <span class="info-label">Protocolo de Autorização:</span>
                    <span class="info-value"><?php echo $nfe['protocolo_autorizacao'] ?? 'Pendente'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Data de Autorização:</span>
                    <span class="info-value"><?php echo $nfe['data_autorizacao'] ? date('d/m/Y H:i:s', strtotime($nfe['data_autorizacao'])) : 'Pendente'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Ambiente:</span>
                    <span class="info-value"><?php echo ucfirst($nfe['ambiente'] ?? 'homologacao'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Tabela de Itens -->
        <table class="itens-table">
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
        
        <!-- Totais -->
        <div class="totais-section">
            <div class="total-row">
                <span>Total dos Itens:</span>
                <span>R$ <?php echo number_format($total_itens, 2, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>Total ICMS:</span>
                <span>R$ <?php echo number_format($total_icms, 2, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>Total PIS:</span>
                <span>R$ <?php echo number_format($total_pis, 2, ',', '.'); ?></span>
            </div>
            <div class="total-row">
                <span>Total COFINS:</span>
                <span>R$ <?php echo number_format($total_cofins, 2, ',', '.'); ?></span>
            </div>
            <div class="total-row total-final">
                <span>VALOR TOTAL DA NF-e:</span>
                <span>R$ <?php echo number_format($nfe['valor_total'], 2, ',', '.'); ?></span>
            </div>
        </div>
        
        <!-- Chave de Acesso -->
        <div class="chave-acesso">
            <div style="margin-bottom: 10px; font-weight: bold;">CHAVE DE ACESSO</div>
            <div class="chave-text"><?php echo $nfe['chave_acesso']; ?></div>
        </div>
        
        <!-- Observações -->
        <?php if (!empty($nfe['observacoes'])): ?>
            <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 5px;">
                <h4 style="margin: 0 0 10px 0;">OBSERVAÇÕES</h4>
                <p style="margin: 0;"><?php echo nl2br(htmlspecialchars($nfe['observacoes'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Rodapé -->
        <div style="margin-top: 40px; text-align: center; color: #666; font-size: 10px; border-top: 1px solid #ddd; padding-top: 20px;">
            <p>Documento eletrônico - Sistema de Gestão de Frotas</p>
            <p>Impresso em: <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        // Auto-print quando a página carregar (opcional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
