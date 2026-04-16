<?php
/**
 * Visualização de NF-e recebida
 * Exibe dados da nota e links para download de XML e PDF quando disponíveis.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/db_connect.php';

configure_session();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_authentication();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$empresa_id = $_SESSION['empresa_id'] ?? 0;

if ($id <= 0 || $empresa_id <= 0) {
    header('Location: pages/nfe.php');
    exit;
}

$conn = getConnection();

// Buscar colunas disponíveis (xml_nfe pode não existir em bases antigas)
$columns = [];
try {
    $stmt = $conn->query("SHOW COLUMNS FROM fiscal_nfe_clientes");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $row['Field'];
    }
} catch (Throwable $e) {
    $columns = ['id', 'numero_nfe', 'serie_nfe', 'chave_acesso', 'data_emissao', 'cliente_razao_social', 'valor_total', 'status', 'protocolo_autorizacao', 'observacoes', 'tipo_operacao'];
}

$select = array_intersect($columns, [
    'id', 'numero_nfe', 'serie_nfe', 'chave_acesso', 'data_emissao', 'data_entrada',
    'cliente_razao_social', 'cliente_cnpj', 'cliente_nome_fantasia', 'valor_total',
    'status', 'protocolo_autorizacao', 'observacoes', 'tipo_operacao',
    'xml_nfe', 'pdf_nfe', 'created_at', 'updated_at'
]);
$select = array_values($select);
$sql = "SELECT " . implode(", ", $select) . " FROM fiscal_nfe_clientes WHERE id = :id AND empresa_id = :empresa_id LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->execute([':id' => $id, ':empresa_id' => $empresa_id]);
$nfe = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$nfe) {
    header('Location: pages/nfe.php');
    exit;
}

$tem_xml = !empty($nfe['xml_nfe']);
$chave_nfe_digits = preg_replace('/\D/', '', $nfe['chave_acesso'] ?? '');
$chave_ok_download = strlen($chave_nfe_digits) === 44;
/** XML pode ser obtido do banco ou baixado na SEFAZ (mesma lógica da API) — não esconder o botão só porque xml_nfe está vazio */
$pode_baixar_xml = $tem_xml || $chave_ok_download;
$tem_pdf = !empty($nfe['pdf_nfe']) && file_exists(__DIR__ . '/../' . $nfe['pdf_nfe']);
$pode_gerar_pdf = $tem_pdf || $tem_xml || $chave_ok_download;

// URL completa (scheme + host + path) para o download funcionar e o cookie ser enviado
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$fiscal_base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_url = $scheme . '://' . $host . $fiscal_base;
$url_download_xml = $base_url . '/api/download_nfe_xml.php?id=' . (int)$id . '&_=' . time();
$url_download_pdf = $base_url . '/api/download_nfe_pdf.php?id=' . (int)$id;

$page_title = "NF-e " . ($nfe['numero_nfe'] ?? $id);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Sistema de Frotas</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="icon" type="image/png" href="../logo.png">
    <style>
        .nfe-view { max-width: 800px; margin: 20px auto; padding: 20px; }
        .nfe-view h1 { margin-bottom: 20px; color: var(--text-primary, #333); }
        .nfe-card { background: var(--bg-secondary, #fff); border: 1px solid var(--border-color, #dee2e6); border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .nfe-card dl { margin: 0; display: grid; grid-template-columns: 200px 1fr; gap: 8px 16px; }
        .nfe-card dt { font-weight: 600; color: var(--text-secondary, #6c757d); }
        .nfe-card dd { margin: 0; }
        .nfe-actions { margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap; }
        .nfe-actions a, .nfe-actions .btn, .nfe-actions button { display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; }
        .nfe-actions button.btn-download { border: none; cursor: pointer; font-size: inherit; }
        .btn-back { background: #6c757d; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        .btn-download { background: #0d6efd; color: #fff; }
        .btn-download:hover { color: #fff; opacity: 0.9; }
        .btn-download.disabled { background: #adb5bd; pointer-events: none; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 4px; font-size: 0.875rem; font-weight: 500; }
        .status-recebida, .status-consultada_sefaz, .status-autorizada { background: #d4edda; color: #155724; }
        .status-pendente { background: #fff3cd; color: #856404; }
        .status-cancelada, .status-denegada { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include __DIR__ . '/../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include __DIR__ . '/../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="nfe-view">
                    <h1><i class="fas fa-file-invoice"></i> NF-e <?php echo htmlspecialchars($nfe['numero_nfe'] ?? $id); ?></h1>

                    <div class="nfe-card">
                        <dl>
                            <dt>Número</dt>
                            <dd><?php echo htmlspecialchars($nfe['numero_nfe'] ?? '-'); ?></dd>
                            <dt>Série</dt>
                            <dd><?php echo htmlspecialchars($nfe['serie_nfe'] ?? '-'); ?></dd>
                            <dt>Chave de acesso</dt>
                            <dd><code><?php echo htmlspecialchars($nfe['chave_acesso'] ?? '-'); ?></code></dd>
                            <dt>Data de emissão</dt>
                            <dd><?php echo !empty($nfe['data_emissao']) ? date('d/m/Y', strtotime($nfe['data_emissao'])) : '-'; ?></dd>
                            <dt>Emitente / Cliente</dt>
                            <dd><?php echo htmlspecialchars($nfe['cliente_razao_social'] ?? $nfe['cliente_nome_fantasia'] ?? 'Cliente'); ?></dd>
                            <?php if (!empty($nfe['cliente_cnpj'])): ?>
                            <dt>CNPJ</dt>
                            <dd><?php echo htmlspecialchars($nfe['cliente_cnpj']); ?></dd>
                            <?php endif; ?>
                            <dt>Valor total</dt>
                            <dd>R$ <?php echo number_format((float)($nfe['valor_total'] ?? 0), 2, ',', '.'); ?></dd>
                            <dt>Status</dt>
                            <dd>
                                <?php
                                $st = isset($nfe['status']) && $nfe['status'] !== '' ? $nfe['status'] : 'recebida';
                                $class = 'status-recebida';
                                if (in_array($st, ['consultada_sefaz'])) $class = 'status-consultada_sefaz';
                                if (in_array($st, ['pendente'])) $class = 'status-pendente';
                                if (in_array($st, ['cancelada', 'denegada', 'inutilizada'])) $class = 'status-cancelada';
                                $st_label = $st === 'consultada_sefaz' ? 'Consultada SEFAZ' : ucfirst(str_replace('_', ' ', $st));
                                ?>
                                <span class="status-badge <?php echo $class; ?>"><?php echo htmlspecialchars($st_label); ?></span>
                            </dd>
                            <?php if (!empty($nfe['tipo_operacao']) && $nfe['tipo_operacao'] !== 'recebida' || $st === 'consultada_sefaz'): ?>
                            <dt>Origem</dt>
                            <dd><?php
                                if ($st === 'consultada_sefaz') echo 'Consulta SEFAZ';
                                elseif (!empty($nfe['tipo_operacao'])) {
                                    $origem = $nfe['tipo_operacao'];
                                    if ($origem === 'recebida_manual') echo 'Digitação manual';
                                    elseif (strpos($origem, 'xml') !== false) echo 'Upload XML';
                                    else echo htmlspecialchars(ucfirst(str_replace('_', ' ', $origem)));
                                } else echo 'Consulta SEFAZ';
                            ?></dd>
                            <?php endif; ?>
                            <?php if (!empty($nfe['protocolo_autorizacao'])): ?>
                            <dt>Protocolo</dt>
                            <dd><code><?php echo htmlspecialchars($nfe['protocolo_autorizacao']); ?></code></dd>
                            <?php endif; ?>
                            <?php if (!empty($nfe['observacoes'])): ?>
                            <dt>Observações</dt>
                            <dd><?php echo nl2br(htmlspecialchars($nfe['observacoes'])); ?></dd>
                            <?php endif; ?>
                        </dl>

                        <div class="nfe-actions">
                            <a href="pages/nfe.php" class="btn-back"><i class="fas fa-arrow-left"></i> Voltar</a>
                            <?php if ($pode_baixar_xml): ?>
                            <a href="<?php echo htmlspecialchars($url_download_xml); ?>" class="btn-download" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-code"></i> Download XML</a>
                            <?php if (!$tem_xml && $chave_ok_download): ?>
                            <p class="form-text" style="margin-top:8px;width:100%;">Se ainda não houver XML salvo, o sistema tentará baixá-lo na SEFAZ (certificado da empresa como destinatário).</p>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="btn-download disabled"><i class="fas fa-file-code"></i> XML indisponível</span>
                            <p class="form-text" style="margin-top:8px;">É necessária a chave de acesso (44 dígitos) ou XML já armazenado.</p>
                            <?php endif; ?>
                            <?php if ($tem_pdf): ?>
                            <a href="<?php echo htmlspecialchars($url_download_pdf); ?>" class="btn-download" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf"></i> Download PDF</a>
                            <?php elseif ($pode_gerar_pdf): ?>
                            <a href="<?php echo htmlspecialchars($url_download_pdf); ?>" class="btn-download" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf"></i> Gerar / Download PDF</a>
                            <p class="form-text" style="margin-top:4px;">DANFE oficial quando houver XML (SEFAZ ou banco).</p>
                            <?php else: ?>
                            <span class="btn-download disabled"><i class="fas fa-file-pdf"></i> PDF não disponível</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/sidebar.js"></script>
    <script src="../js/theme.js"></script>
</body>
</html>
