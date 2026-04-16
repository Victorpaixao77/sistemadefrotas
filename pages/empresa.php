<?php
// Include configuration and functions first
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Configure session before starting it
configure_session();

// Start session
session_start();

// Check if user is logged in and has empresa_id
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['empresa_id'])) {
    // Clear session and redirect to login
    session_unset();
    session_destroy();
    header('location: ' . sf_app_url('login.php'));
    exit;
}

// Verify if empresa is still active
try {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT status FROM empresa_clientes WHERE id = :empresa_id AND status = 'ativo'");
    $stmt->bindParam(':empresa_id', $_SESSION['empresa_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0 || $stmt->fetch()['status'] !== 'ativo') {
        session_unset();
        session_destroy();
        header('location: ' . sf_app_url('login.php?error=empresa_inativa'));
        exit;
    }
} catch(PDOException $e) {
    // Log error but don't show to user
    error_log("Erro ao verificar status da empresa: " . $e->getMessage());
}

// Set page title
$page_title = "Empresa";

// Get company data from database
$companyData = getCompanyData();

// CRT (situação tributária do emitente na NF-e) — coluna fiscal_config_empresa.crt
$fiscal_crt = 1;
$fiscal_crt_editable = false;
try {
    $connF = getConnection();
    $tbl = $connF->query("SHOW TABLES LIKE 'fiscal_config_empresa'");
    if ($tbl && $tbl->rowCount() > 0) {
        $colCrt = $connF->query("SHOW COLUMNS FROM fiscal_config_empresa LIKE 'crt'");
        if ($colCrt && $colCrt->rowCount() > 0) {
            $fiscal_crt_editable = true;
            $st = $connF->prepare('SELECT crt FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1');
            $st->execute([$_SESSION['empresa_id']]);
            $fr = $st->fetch(PDO::FETCH_ASSOC);
            if ($fr && isset($fr['crt'])) {
                $fiscal_crt = max(1, min(3, (int)$fr['crt']));
            }
        }
    }
} catch (Throwable $e) {
    $fiscal_crt = 1;
    $fiscal_crt_editable = false;
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getConnection();
        
        // Prepare update data
        $data = array(
            'razao_social' => $_POST['razao_social'],
            'nome_fantasia' => $_POST['nome_fantasia'],
            'cnpj' => $_POST['cnpj'],
            'inscricao_estadual' => $_POST['inscricao_estadual'],
            'telefone' => $_POST['telefone'],
            'email' => $_POST['email'],
            'endereco' => $_POST['endereco'],
            'cidade' => $_POST['cidade'],
            'estado' => $_POST['estado'],
            'cep' => $_POST['cep'],
            'responsavel' => $_POST['responsavel']
        );
        
        // Update existing company
        $sql = "UPDATE empresa_clientes SET 
                razao_social = :razao_social,
                nome_fantasia = :nome_fantasia,
                cnpj = :cnpj,
                inscricao_estadual = :inscricao_estadual,
                telefone = :telefone,
                email = :email,
                endereco = :endereco,
                cidade = :cidade,
                estado = :estado,
                cep = :cep,
                responsavel = :responsavel
                WHERE id = :id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $_SESSION['empresa_id']);
        
        // Bind all parameters
        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        if ($stmt->execute()) {
            // Atualizar situação tributária (CRT) na configuração fiscal, se disponível
            $fiscal_crt_post = isset($_POST['fiscal_crt']) ? (int)$_POST['fiscal_crt'] : 1;
            if (!in_array($fiscal_crt_post, [1, 2, 3], true)) {
                $fiscal_crt_post = 1;
            }
            try {
                $chkTbl = $conn->query("SHOW TABLES LIKE 'fiscal_config_empresa'");
                $chkCol = $conn->query("SHOW COLUMNS FROM fiscal_config_empresa LIKE 'crt'");
                if ($chkTbl && $chkTbl->rowCount() > 0 && $chkCol && $chkCol->rowCount() > 0) {
                    $stEx = $conn->prepare('SELECT id FROM fiscal_config_empresa WHERE empresa_id = ? LIMIT 1');
                    $stEx->execute([$_SESSION['empresa_id']]);
                    if ($stEx->fetch()) {
                        $up = $conn->prepare('UPDATE fiscal_config_empresa SET crt = ? WHERE empresa_id = ?');
                        $up->execute([$fiscal_crt_post, $_SESSION['empresa_id']]);
                    } else {
                        $cnpjLimpo = preg_replace('/\D/', '', (string)$data['cnpj']);
                        $razao = trim((string)$data['razao_social']);
                        if (strlen($cnpjLimpo) === 14 && $razao !== '') {
                            $ins = $conn->prepare(
                                'INSERT INTO fiscal_config_empresa (empresa_id, cnpj, razao_social, nome_fantasia, ambiente_sefaz, crt) VALUES (?, ?, ?, ?, ?, ?)'
                            );
                            $ins->execute([
                                $_SESSION['empresa_id'],
                                $data['cnpj'],
                                $razao,
                                $data['nome_fantasia'] !== '' ? $data['nome_fantasia'] : null,
                                'homologacao',
                                $fiscal_crt_post
                            ]);
                        }
                    }
                    $fiscal_crt = $fiscal_crt_post;
                }
            } catch (Throwable $e) {
                error_log('empresa.php: falha ao salvar CRT fiscal: ' . $e->getMessage());
            }

            setFlashMessage('success', 'Dados da empresa atualizados com sucesso!');
            $companyData = getCompanyData(); // Refresh data
        } else {
            setFlashMessage('error', 'Erro ao atualizar os dados da empresa.');
        }
    } catch(PDOException $e) {
        setFlashMessage('error', 'Erro ao atualizar: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestão de Frotas</title>
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../logo.png">
    
    <!-- jQuery and jQuery Mask Plugin -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>
    
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

        /* Estilos do Menu de Perfil */
        .profile-dropdown {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            width: 280px;
            background-color: var(--card-bg);
            border-radius: var(--card-border-radius);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            z-index: 1000;
        }

        .profile-dropdown.show {
            display: block;
        }

        .user-profile {
            position: relative;
        }

        .profile-dropdown-icon {
            transition: transform 0.3s ease;
        }

        .user-profile.active .profile-dropdown-icon {
            transform: rotate(180deg);
        }
        
        /* Estilos das seções organizadas */
        .card.mb-4 {
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--card-border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: box-shadow 0.3s ease;
        }
        
        .card.mb-4:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.2rem;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header h2 i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }
        
        .dashboard-content .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1rem 1.25rem;
        }
        .dashboard-content .form-grid .form-group label {
            display: block;
            margin-bottom: 0.35rem;
        }
        .dashboard-content .form-grid .form-group input[type="text"],
        .dashboard-content .form-grid .form-group input[type="email"],
        .dashboard-content .form-grid .form-group select {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
        }
        .dashboard-content .form-grid .form-group textarea.empresa-logradouro {
            width: 100%;
            max-width: 100%;
            box-sizing: border-box;
            min-height: 4.5rem;
            line-height: 1.45;
            resize: vertical;
            font-family: inherit;
            font-size: inherit;
        }
        /* CRT: não ocupar a largura inteira do card */
        .dashboard-content .form-grid .form-group.empresa-fiscal-crt select#fiscal_crt {
            width: 100%;
            max-width: 28rem;
        }

        .form-group-full {
            grid-column: 1 / -1;
        }
        
        .form-actions.text-center {
            text-align: center;
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: var(--card-border-radius);
            border: 1px solid var(--border-color);
        }
        
        .btn-lg {
            padding: 12px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .btn-lg:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .btn-lg i {
            margin-right: 8px;
        }
        .empresa-cnpj-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: flex-start;
        }
        .empresa-cnpj-row input#cnpj {
            flex: 0 0 auto;
            width: 15.75rem;
            max-width: 100%;
            min-width: 11rem;
        }
        .empresa-cnpj-row .btn-buscar-cnpj {
            white-space: nowrap;
            padding: 10px 16px;
            border-radius: 6px;
            border: 1px solid var(--border-color, #dee2e6);
            background: var(--bg-secondary, #f8f9fa);
            color: var(--text-primary, #333);
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s, border-color 0.2s;
        }
        .empresa-cnpj-row .btn-buscar-cnpj:hover:not(:disabled) {
            background: var(--primary-color, #0d6efd);
            color: #fff;
            border-color: var(--primary-color, #0d6efd);
        }
        .empresa-cnpj-row .btn-buscar-cnpj:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }
        #empresaBrasilapiHint {
            display: block;
            width: 100%;
            margin-top: 6px;
            font-size: 0.85rem;
            color: var(--text-secondary, #6c757d);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation -->
        <?php include '../includes/sidebar_pages.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Header -->
            <?php include '../includes/header.php'; ?>
            
            <!-- Page Content -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1>Dados da Empresa</h1>
                </div>
                
                <?php echo displayFlashMessage(); ?>
                
                <form method="POST">
                    <!-- Seção: Informações Básicas -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2><i class="fas fa-building"></i> Informações Básicas da Empresa</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="razaoSocial">Razão Social *</label>
                                    <input type="text" id="razaoSocial" name="razao_social" 
                                           value="<?php echo htmlspecialchars($companyData['razao_social'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="nomeFantasia">Nome Fantasia</label>
                                    <input type="text" id="nomeFantasia" name="nome_fantasia" 
                                           value="<?php echo htmlspecialchars($companyData['nome_fantasia'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="responsavel">Responsável</label>
                                    <input type="text" id="responsavel" name="responsavel" 
                                           value="<?php echo htmlspecialchars($companyData['responsavel'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Documentos Fiscais -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2><i class="fas fa-file-invoice"></i> Documentos Fiscais</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group form-group-full">
                                    <label for="cnpj">CNPJ *</label>
                                    <div class="empresa-cnpj-row">
                                        <input type="text" id="cnpj" name="cnpj" maxlength="18" autocomplete="off"
                                               placeholder="Ex.: 02.603.624/0001-29"
                                               value="<?php echo htmlspecialchars($companyData['cnpj'] ?? ''); ?>" required>
                                        <button type="button" class="btn-buscar-cnpj" id="btnEmpresaBuscarCnpj" title="Consulta gratuita BrasilAPI (mesma do cadastro de fornecedores)">
                                            <i class="fas fa-cloud-download-alt"></i> Buscar CNPJ
                                        </button>
                                    </div>
                                    <small id="empresaBrasilapiHint"></small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="inscricaoEstadual">Inscrição Estadual</label>
                                    <input type="text" id="inscricaoEstadual" name="inscricao_estadual" 
                                           value="<?php echo htmlspecialchars($companyData['inscricao_estadual'] ?? ''); ?>">
                                </div>
                                <?php if (!empty($fiscal_crt_editable)): ?>
                                <div class="form-group form-group-full empresa-fiscal-crt">
                                    <label for="fiscal_crt">Situação tributária (CRT — emitente NF-e)</label>
                                    <select id="fiscal_crt" name="fiscal_crt">
                                        <option value="1" <?php echo (int)$fiscal_crt === 1 ? 'selected' : ''; ?>>1 — Simples Nacional</option>
                                        <option value="2" <?php echo (int)$fiscal_crt === 2 ? 'selected' : ''; ?>>2 — Simples Nacional (excesso de sublimite de receita bruta)</option>
                                        <option value="3" <?php echo (int)$fiscal_crt === 3 ? 'selected' : ''; ?>>3 — Regime normal (Lucro presumido ou real)</option>
                                    </select>
                                </div>
                                <?php else: ?>
                                <div class="form-group form-group-full">
                                    <p class="form-text" style="margin:0;color:var(--text-secondary);font-size:0.9rem;">
                                        <strong>Situação tributária (CRT):</strong> indisponível — instale/atualize o banco fiscal (<code>fiscal_config_empresa.crt</code>) para editar aqui.
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Contato -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2><i class="fas fa-phone"></i> Informações de Contato</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="telefone">Telefone</label>
                                    <input type="text" id="telefone" name="telefone" 
                                           value="<?php echo htmlspecialchars($companyData['telefone'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">E-mail</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($companyData['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Seção: Endereço -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h2><i class="fas fa-map-marker-alt"></i> Endereço</h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group form-group-full">
                                    <label for="endereco">Logradouro</label>
                                    <textarea id="endereco" name="endereco" class="empresa-logradouro" rows="3"
                                              placeholder="Rua, Avenida, número, complemento, bairro..."><?php echo htmlspecialchars($companyData['endereco'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cidade">Cidade</label>
                                    <input type="text" id="cidade" name="cidade" 
                                           value="<?php echo htmlspecialchars($companyData['cidade'] ?? ''); ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="estado">Estado</label>
                                    <select id="estado" name="estado">
                                        <option value="">Selecione...</option>
                                        <?php
                                        $estados = array(
                                            'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
                                            'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal',
                                            'ES' => 'Espírito Santo', 'GO' => 'Goiás', 'MA' => 'Maranhão',
                                            'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul', 'MG' => 'Minas Gerais',
                                            'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná', 'PE' => 'Pernambuco',
                                            'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
                                            'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima',
                                            'SC' => 'Santa Catarina', 'SP' => 'São Paulo', 'SE' => 'Sergipe',
                                            'TO' => 'Tocantins'
                                        );
                                        
                                        foreach ($estados as $uf => $nome) {
                                            $selected = ($companyData['estado'] ?? '') === $uf ? 'selected' : '';
                                            echo "<option value=\"$uf\" $selected>$nome</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="cep">CEP</label>
                                    <input type="text" id="cep" name="cep" 
                                           value="<?php echo htmlspecialchars($companyData['cep'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Botão de Ação -->
                    <div class="form-actions text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>
    
    <!-- JavaScript Files -->
    <script src="../js/doc_validators.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
        (function () {
            function apiCnpjUrl() {
                try {
                    return new URL('../api/cnpj_brasilapi.php', window.location.href).href;
                } catch (e) {
                    return '../api/cnpj_brasilapi.php';
                }
            }
            function onlyDigits(s) {
                return String(s || '').replace(/\D/g, '');
            }
            function fmtCnpjMask(digits) {
                var d = onlyDigits(digits);
                if (d.length !== 14) return digits;
                return d.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            function fmtCep(v) {
                var d = onlyDigits(v);
                if (d.length === 8) return d.replace(/(\d{5})(\d{3})/, '$1-$2');
                return v;
            }
            function montarLogradouro(d) {
                var log = String(d.endereco || '').trim();
                var num = String(d.numero || '').trim();
                var comp = String(d.complemento || '').trim();
                var bai = String(d.bairro || '').trim();
                var parts = [];
                if (log) parts.push(log);
                if (num) parts.push(num);
                var line = parts.join(', ');
                if (comp) line += (line ? ' — ' : '') + comp;
                if (bai) line += (line ? ' — ' : '') + bai;
                return line;
            }
            var FETCH_MS = 35000;
            var _empCnpjTimer = null;
            var _empCnpjLastFetch = '';

            function fetchWithTimeout(url, opts, ms) {
                var c = new AbortController();
                var tid = setTimeout(function () { c.abort(); }, ms || FETCH_MS);
                var o = Object.assign({}, opts || {}, { signal: c.signal, credentials: 'same-origin' });
                return fetch(url, o).finally(function () { clearTimeout(tid); });
            }

            function setHint(t) {
                var el = document.getElementById('empresaBrasilapiHint');
                if (el) el.textContent = t || '';
            }

            function applyBrasilapiEmpresa(d) {
                if (!d) return;
                var set = function (sel, v) {
                    var el = document.querySelector(sel);
                    if (el && v != null && String(v).trim() !== '') el.value = String(v).trim();
                };
                set('#razaoSocial', d.nome);
                set('#nomeFantasia', d.nome_fantasia);
                set('#endereco', montarLogradouro(d));
                set('#cep', fmtCep(d.cep));
                set('#cidade', d.cidade);
                set('#email', d.email);
                set('#telefone', d.telefone);
                if (d.inscricao_estadual != null) {
                    var ieVal = String(d.inscricao_estadual).trim();
                    var $ie = $('#inscricaoEstadual');
                    $ie.unmask();
                    $ie.val(ieVal);
                    var ieDig = onlyDigits(ieVal);
                    if (ieVal !== '' && ieDig.length >= 8 && ieDig.length <= 14 && ieVal.length === ieDig.length) {
                        $ie.mask('000.000.000.000');
                    } else if (ieVal === '') {
                        $ie.mask('000.000.000.000');
                    }
                }
                var uf = String(d.uf || '').toUpperCase().substring(0, 2);
                if (uf.length === 2) {
                    var est = document.getElementById('estado');
                    if (est) est.value = uf;
                }
                $('#cep').trigger('input');
                $('#telefone').trigger('input');
                $('#cnpj').val(fmtCnpjMask(onlyDigits($('#cnpj').val())));
            }

            function buscarCnpjEmpresa(isManual) {
                var cnpj = onlyDigits(document.getElementById('cnpj').value);
                var btn = document.getElementById('btnEmpresaBuscarCnpj');
                if (cnpj.length !== 14) {
                    if (isManual) alert('Informe o CNPJ com 14 dígitos (pode usar a máscara).');
                    return;
                }
                if (window.DocValidators && !window.DocValidators.validarCnpj(cnpj)) {
                    if (isManual) alert('CNPJ inválido (dígitos verificadores).');
                    return;
                }
                if (isManual) setHint('Consultando BrasilAPI...');
                if (btn) btn.disabled = true;
                fetchWithTimeout(apiCnpjUrl() + '?cnpj=' + encodeURIComponent(cnpj), { method: 'GET' }, FETCH_MS)
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (!res.success) {
                            if (isManual) alert(res.message || 'Não foi possível consultar o CNPJ.');
                            else setHint(res.message || '');
                            return;
                        }
                        var d = res.data || {};
                        applyBrasilapiEmpresa(d);
                        _empCnpjLastFetch = cnpj;
                        setHint((d.hint != null ? String(d.hint) : '').trim());
                    })
                    .catch(function (err) {
                        if (isManual) {
                            alert(err && err.name === 'AbortError' ? 'Tempo esgotado ao consultar o CNPJ.' : 'Erro de comunicação ao consultar o CNPJ.');
                        } else {
                            setHint('Falha de rede ao consultar CNPJ.');
                        }
                    })
                    .finally(function () {
                        if (btn) btn.disabled = false;
                    });
            }

            function scheduleEmpresaCnpjAuto() {
                if (_empCnpjTimer) clearTimeout(_empCnpjTimer);
                var cnpj = onlyDigits(document.getElementById('cnpj').value);
                if (cnpj.length !== 14 || cnpj === _empCnpjLastFetch) return;
                _empCnpjTimer = setTimeout(function () {
                    _empCnpjTimer = null;
                    var again = onlyDigits(document.getElementById('cnpj').value);
                    if (again.length === 14 && again !== _empCnpjLastFetch) {
                        buscarCnpjEmpresa(false);
                    }
                }, 1200);
            }

            $(document).ready(function() {
                $('#cnpj').mask('00.000.000/0000-00');
                $('#cep').mask('00000-000');
                $('#telefone').mask('(00) 00000-0000');
                $('#inscricaoEstadual').mask('000.000.000.000');

                var raw = onlyDigits($('#cnpj').val());
                if (raw.length === 14) {
                    $('#cnpj').val(fmtCnpjMask(raw));
                }

                document.getElementById('btnEmpresaBuscarCnpj').addEventListener('click', function () {
                    buscarCnpjEmpresa(true);
                });
                document.getElementById('cnpj').addEventListener('input', function () {
                    var d = onlyDigits(this.value);
                    if (d.length !== 14) _empCnpjLastFetch = '';
                    scheduleEmpresaCnpjAuto();
                });
            });
        })();
    </script>

    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>