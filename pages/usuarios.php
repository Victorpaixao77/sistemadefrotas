<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/permissions.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

if (!isset($_SESSION['empresa_id'])) {
    header('Location: ../login.php');
    exit;
}

$page_title = 'Gerenciamento de Usuários';
$empresa_id = $_SESSION['empresa_id'];

// Obter permissões do usuário atual
$permissions = get_user_permissions();
$can_edit_system_users = $permissions['can_edit_system_users'];
$can_create_system_users = $permissions['can_create_system_users'];
$can_access_lucratividade = $permissions['can_access_lucratividade'];

// Buscar usuários de motoristas
$conn = getConnection();
$stmt = $conn->prepare('
    SELECT um.*, m.nome as nome_motorista, m.cpf, m.email AS email_motorista, "motorista" as tipo_usuario_sistema
    FROM usuarios_motoristas um 
    JOIN motoristas m ON um.motorista_id = m.id 
    WHERE um.empresa_id = :empresa_id 
    ORDER BY um.data_cadastro DESC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios_motoristas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar usuários do sistema (admin/gestor) - excluindo usuários ocultos
$stmt = $conn->prepare('
    SELECT u.*, "sistema" as tipo_usuario_sistema
    FROM usuarios u 
    WHERE u.empresa_id = :empresa_id 
    AND (u.is_oculto IS NULL OR u.is_oculto = 0)
    ORDER BY u.data_cadastro DESC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$usuarios_sistema = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combinar todos os usuários
$usuarios = array_merge($usuarios_motoristas, $usuarios_sistema);

// Motoristas sem usuário do app ainda (qualquer disponibilidade — o app não depende do status operacional)
$stmt = $conn->prepare('
    SELECT m.*
    FROM motoristas m
    LEFT JOIN usuarios_motoristas um ON m.id = um.motorista_id AND um.empresa_id = m.empresa_id
    WHERE m.empresa_id = :empresa_id
    AND um.id IS NULL
    ORDER BY m.nome ASC
');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$motoristas_disponiveis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -------- Indicadores (KPIs) — calculados só com dados reais --------
$kpi_total = count($usuarios);
$kpi_ativos = 0; $kpi_inativos = 0; $kpi_admins = 0; $kpi_gestores = 0;
$kpi_motoristas = 0; $kpi_2fa = 0; $kpi_novos_mes = 0;
$mes_atual = date('Y-m');
foreach ($usuarios as $u) {
    $eh_mot = ($u['tipo_usuario_sistema'] ?? '') === 'motorista';
    if (($u['status'] ?? '') === 'ativo') { $kpi_ativos++; } else { $kpi_inativos++; }
    if ($eh_mot) {
        $kpi_motoristas++;
    } else {
        if (($u['tipo_usuario'] ?? '') === 'admin') { $kpi_admins++; }
        elseif (($u['tipo_usuario'] ?? '') === 'gestor') { $kpi_gestores++; }
        if (!empty($u['two_factor_enabled'])) { $kpi_2fa++; }
    }
    if (!empty($u['data_cadastro']) && strpos((string) $u['data_cadastro'], $mes_atual) === 0) { $kpi_novos_mes++; }
}
$kpi_sistema = $kpi_admins + $kpi_gestores;
$pct = function ($n, $t) { return $t > 0 ? round(($n / $t) * 100) : 0; };
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo function_exists('sf_asset_v') ? sf_asset_v() : '1'; ?>">
    <style>
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-size: 14px;
            margin: 0;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
            transition: all 0.3s ease;
        }
        
        .checkbox-label:hover {
            background: #e9e9e9;
            border-color: #007bff;
        }
        
        .checkbox-label input[type="checkbox"] {
            margin-right: 8px;
            transform: scale(1.2);
        }
        
        .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: #007bff;
            border-color: #007bff;
        }
        
        .permissions-grid {
            max-height: 300px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fafafa;
        }
        
        .permission-item {
            margin: 0;
        }
        
        .permission-item label {
            margin: 0;
            font-weight: normal;
        }

        /* Estilos para tema escuro - checkboxes de permissões */
        body:not(.light-theme) .checkbox-label {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .checkbox-label:hover {
            background: var(--bg-tertiary) !important;
            border-color: var(--accent-primary) !important;
        }
        
        body:not(.light-theme) .permissions-grid {
            background: var(--bg-secondary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .permission-item label {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .checkbox-label input[type="checkbox"] {
            accent-color: var(--accent-primary);
        }
        
        body:not(.light-theme) .checkbox-label input[type="checkbox"]:checked + .checkmark {
            background: var(--accent-primary) !important;
            border-color: var(--accent-primary) !important;
        }

        /* Estilos para tema claro - manter consistência */
        .light-theme .checkbox-label {
            background: #f9f9f9;
            color: #333;
            border-color: #ddd;
        }
        
        .light-theme .checkbox-label:hover {
            background: #e9e9e9;
            border-color: #007bff;
        }
        
        .light-theme .permissions-grid {
            background: #fafafa;
            border-color: #ddd;
        }
        
        .light-theme .permission-item label {
            color: #333;
        }
        
        /* Estilo para checkboxes (mantido para consistência) */
        .permission-item input[type="checkbox"] {
            cursor: pointer;
        }

        /* Estilos para modal no tema escuro */
        body:not(.light-theme) .modal-content {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .modal-header {
            background: var(--bg-tertiary) !important;
            border-bottom-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .modal-header h5 {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .modal-body {
            background: var(--bg-secondary) !important;
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .form-group label {
            color: var(--text-primary) !important;
        }
        
        body:not(.light-theme) .form-control {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .form-control:focus {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
            border-color: var(--accent-primary) !important;
            box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25) !important;
        }
        
        body:not(.light-theme) .btn-secondary {
            background: var(--bg-tertiary) !important;
            color: var(--text-primary) !important;
            border-color: var(--border-color) !important;
        }
        
        body:not(.light-theme) .btn-secondary:hover {
            background: var(--bg-primary) !important;
            color: var(--text-primary) !important;
        }

        /* Barra de busca + filtros */
        .users-filterbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 0.85rem;
        }
        .users-filterbar .uf-search {
            flex: 1 1 220px;
            min-width: 180px;
            position: relative;
        }
        .users-filterbar .uf-search i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted, #94a3b8);
            font-size: 0.85rem;
        }
        .users-filterbar .uf-search input {
            width: 100%;
            box-sizing: border-box;
            padding: 0.5rem 0.6rem 0.5rem 2rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .users-filterbar select {
            padding: 0.5rem 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        .users-filterbar .uf-count {
            font-size: 0.78rem;
            color: var(--text-muted, #94a3b8);
            margin-left: auto;
        }

        /* Linha do próprio usuário */
        .user-row.is-self > td { background: rgba(59, 130, 246, 0.08); }
        .user-self-tag {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            border-radius: 999px;
            background: var(--accent-primary, #3b82f6);
            color: #fff;
            font-size: 9px;
            font-weight: 700;
            vertical-align: middle;
        }

        /* Botões de ação compactos (substitui estilos inline) */
        .table .btn-acao { font-size: 10px; }
        .table .btn-acao + .btn-acao { margin-left: 5px; }

        /* Medidor de senha forte */
        .pwd-meter { margin-top: 6px; font-size: 0.72rem; }
        .pwd-meter ul { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 4px 12px; }
        .pwd-meter li { color: var(--text-muted, #94a3b8); display: flex; align-items: center; gap: 4px; }
        .pwd-meter li::before { content: "\f111"; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: 7px; }
        .pwd-meter li.ok { color: #16a34a; }
        .pwd-meter li.ok::before { content: "\f00c"; }

        /* Toast */
        #uaToastWrap {
            position: fixed; top: 16px; right: 16px; z-index: 9999;
            display: flex; flex-direction: column; gap: 8px; max-width: 340px;
        }
        .ua-toast {
            padding: 0.7rem 0.9rem; border-radius: 8px; color: #fff;
            font-size: 0.85rem; font-weight: 600; box-shadow: 0 6px 20px rgba(0,0,0,0.18);
            opacity: 0; transform: translateX(20px); transition: opacity .25s ease, transform .25s ease;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .ua-toast.show { opacity: 1; transform: translateX(0); }
        .ua-toast.success { background: #16a34a; }
        .ua-toast.error { background: #dc2626; }
        .ua-toast.info { background: #2563eb; }

        /* Modal de confirmação */
        .confirm-modal {
            position: fixed; inset: 0; z-index: 9998; display: none;
            align-items: center; justify-content: center; background: rgba(0,0,0,0.5);
        }
        .confirm-modal.show { display: flex; }
        .confirm-box {
            background: var(--card-bg, var(--bg-secondary)); color: var(--text-primary);
            border-radius: 12px; padding: 1.25rem; max-width: 380px; width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); text-align: center;
        }
        .confirm-box .ci { font-size: 2rem; color: #dc2626; margin-bottom: 0.5rem; }
        .confirm-box h4 { margin: 0 0 0.4rem; font-size: 1.05rem; }
        .confirm-box p { margin: 0 0 1rem; color: var(--text-secondary); font-size: 0.9rem; }
        .confirm-box .confirm-actions { display: flex; gap: 0.5rem; justify-content: center; }
    </style>
    <?php sf_page_help_css('..'); ?>
</head>
<body class="page-usuarios sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <!-- Cabeçalho da página -->
                <div class="u-pagehead">
                    <div class="u-pagehead__title">
                        <h1><i class="fas fa-users-gear" style="color:var(--accent-primary,#3b82f6)"></i> Usuários</h1>
                        <p>Gerencie usuários do sistema e do app de motoristas, permissões e segurança.</p>
                    </div>
                    <div class="u-pagehead__meta">
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda desta tela" aria-label="Ajuda desta tela"><i class="fas fa-question-circle"></i></button>
                        <span class="u-badge <?php echo $permissions['is_admin'] ? 'u-badge--admin' : ($permissions['is_gestor'] ? 'u-badge--gestor' : 'u-badge--muted'); ?>" title="Seu nível de acesso">
                            <i class="fas fa-user-shield"></i> <?php echo ucfirst($permissions['permission_level']); ?>
                        </span>
                        <small class="text-muted" style="font-size:0.76rem;">
                            <?php if ($permissions['is_admin']): ?>Acesso total
                            <?php elseif ($permissions['is_gestor']): ?>Gerencia motoristas
                            <?php else: ?>Somente visualização<?php endif; ?>
                        </small>
                    </div>
                </div>

                <!-- Indicadores (dados reais) -->
                <div class="u-kpis">
                    <div class="u-kpi">
                        <div class="u-kpi__top"><span class="u-kpi__label">Total de usuários</span><span class="u-kpi__icon"><i class="fas fa-users"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_total; ?></div>
                        <div class="u-kpi__hint"><?php echo $kpi_sistema; ?> do sistema · <?php echo $kpi_motoristas; ?> motoristas</div>
                    </div>
                    <div class="u-kpi u-kpi--ok">
                        <div class="u-kpi__top"><span class="u-kpi__label">Ativos</span><span class="u-kpi__icon"><i class="fas fa-circle-check"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_ativos; ?></div>
                        <div class="u-kpi__bar"><i style="width:<?php echo $pct($kpi_ativos, $kpi_total); ?>%"></i></div>
                    </div>
                    <div class="u-kpi u-kpi--danger">
                        <div class="u-kpi__top"><span class="u-kpi__label">Inativos</span><span class="u-kpi__icon"><i class="fas fa-circle-minus"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_inativos; ?></div>
                        <div class="u-kpi__hint"><?php echo $pct($kpi_inativos, $kpi_total); ?>% do total</div>
                    </div>
                    <div class="u-kpi u-kpi--warn">
                        <div class="u-kpi__top"><span class="u-kpi__label">Administradores</span><span class="u-kpi__icon"><i class="fas fa-user-shield"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_admins; ?></div>
                        <div class="u-kpi__hint"><?php echo $kpi_gestores; ?> gestor(es)</div>
                    </div>
                    <div class="u-kpi u-kpi--ok">
                        <div class="u-kpi__top"><span class="u-kpi__label">2FA habilitado</span><span class="u-kpi__icon"><i class="fas fa-shield-halved"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_2fa; ?></div>
                        <div class="u-kpi__hint"><?php echo $kpi_sistema > 0 ? $pct($kpi_2fa, $kpi_sistema) . '% dos usuários do sistema' : 'nenhum usuário do sistema'; ?></div>
                    </div>
                    <div class="u-kpi">
                        <div class="u-kpi__top"><span class="u-kpi__label">Novos este mês</span><span class="u-kpi__icon"><i class="fas fa-user-plus"></i></span></div>
                        <div class="u-kpi__value"><?php echo $kpi_novos_mes; ?></div>
                        <div class="u-kpi__hint">Referência <?php echo date('m/Y'); ?></div>
                    </div>
                </div>

                <!-- Lista de Usuários -->
                <div class="dashboard-grid">
                    <div class="u-panel" style="grid-column: 1 / -1;">
                        <div class="u-panel__head">
                            <span class="u-panel__ic"><i class="fas fa-users" aria-hidden="true"></i></span>
                            <div><h3>Usuários cadastrados</h3><p>Busque, filtre e gerencie o acesso de cada usuário.</p></div>
                        </div>
                        <div class="u-panel__body">
                            <div class="u-toolbar">
                                <div class="u-search">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                    <input type="text" id="ufSearch" placeholder="Buscar por nome, e-mail, CPF, tipo…" autocomplete="off" aria-label="Buscar usuários">
                                </div>
                                <select id="ufTipo" class="u-select" aria-label="Filtrar por tipo">
                                    <option value="">Todos os tipos</option>
                                    <option value="admin">Administrador</option>
                                    <option value="gestor">Gestor</option>
                                    <option value="motorista">Motorista</option>
                                </select>
                                <select id="ufStatus" class="u-select" aria-label="Filtrar por status">
                                    <option value="">Todos os status</option>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                </select>
                                <select id="ufSort" class="u-select" aria-label="Ordenar">
                                    <option value="recent">Mais recentes</option>
                                    <option value="name">Nome (A–Z)</option>
                                </select>
                                <button type="button" id="ufClear" class="u-btn-ghost" title="Limpar filtros"><i class="fas fa-rotate-left"></i> Limpar</button>
                                <span class="u-count" id="ufCount"></span>
                            </div>
                            <div class="u-table-wrap">
                                <table class="u-table" id="usersTable">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th class="u-col-center">Tipo</th>
                                            <th class="u-col-center">Permissões</th>
                                            <th class="u-col-center">Status</th>
                                            <th class="u-col-center">2FA</th>
                                            <th class="u-col-center">Cadastro</th>
                                            <th class="u-col-center">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="usersTbody">
                                        <?php foreach ($usuarios as $usuario):
                                            $is_motorista = $usuario['tipo_usuario_sistema'] === 'motorista';
                                            $row_nome = $is_motorista ? ($usuario['nome_motorista'] ?? '') : ($usuario['nome'] ?? '');
                                            $row_email = $is_motorista ? trim((string) ($usuario['email_motorista'] ?? '')) : ($usuario['email'] ?? '');
                                            $row_tipo = $is_motorista ? 'motorista' : ($usuario['tipo_usuario'] ?? '');
                                            $row_status = $usuario['status'] ?? '';
                                            $row_proprio = !$is_motorista && (int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0);
                                        ?>
                                        <?php
                                            $row_cpf = $is_motorista ? preg_replace('/\D+/', '', (string) ($usuario['cpf'] ?? '')) : '';
                                            $parts = preg_split('/\s+/', trim((string) $row_nome));
                                            $ini = $parts && $parts[0] !== '' ? mb_strtoupper(mb_substr($parts[0], 0, 1)) : '?';
                                            if ($parts && count($parts) > 1) { $ini .= mb_strtoupper(mb_substr(end($parts), 0, 1)); }
                                            $avatar_class = $is_motorista ? 'u-avatar--motorista' : ($row_tipo === 'admin' ? 'u-avatar--admin' : '');
                                            $search_blob = mb_strtolower(trim($row_nome . ' ' . $row_email . ' ' . $row_cpf . ' ' . $row_tipo . ' ' . $row_status));
                                            $ts_cad = !empty($usuario['data_cadastro']) ? strtotime((string) $usuario['data_cadastro']) : 0;
                                        ?>
                                        <tr class="user-row<?php echo $row_proprio ? ' is-self' : ''; ?>"
                                            data-user-id="<?php echo (int) $usuario['id']; ?>"
                                            data-user-tipo="<?php echo htmlspecialchars($usuario['tipo_usuario_sistema'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-nome="<?php echo htmlspecialchars(mb_strtolower($row_nome), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-email="<?php echo htmlspecialchars(mb_strtolower($row_email), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-tipo="<?php echo htmlspecialchars($row_tipo, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-status="<?php echo htmlspecialchars($row_status, ENT_QUOTES, 'UTF-8'); ?>"
                                            data-cadastro="<?php echo (int) $ts_cad; ?>"
                                            data-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8'); ?>">
                                            <td class="u-cell-identity" data-label="Usuário">
                                                <div class="u-identity">
                                                    <span class="u-avatar <?php echo $avatar_class; ?>" aria-hidden="true"><?php echo htmlspecialchars($ini); ?></span>
                                                    <div class="u-identity__txt">
                                                        <div class="u-identity__name" title="<?php echo htmlspecialchars($row_nome); ?>">
                                                            <?php echo htmlspecialchars($row_nome); ?>
                                                            <?php if ($row_proprio): ?><span class="u-self-tag" title="Esta é a sua conta">você</span><?php endif; ?>
                                                        </div>
                                                        <div class="u-identity__mail" title="<?php echo htmlspecialchars($row_email); ?>"><?php echo $row_email !== '' ? htmlspecialchars($row_email) : '—'; ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="u-col-center" data-label="Tipo">
                                                <?php if ($is_motorista): ?>
                                                    <span class="u-badge u-badge--motorista"><i class="fas fa-id-card"></i> Motorista</span>
                                                <?php else: ?>
                                                    <span class="u-badge <?php echo $usuario['tipo_usuario'] === 'admin' ? 'u-badge--admin' : 'u-badge--gestor'; ?>">
                                                        <i class="fas <?php echo $usuario['tipo_usuario'] === 'admin' ? 'fa-user-shield' : 'fa-user-tie'; ?>"></i> <?php echo ucfirst($usuario['tipo_usuario']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="u-col-center" data-label="Permissões">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'sistema'): ?>
                                                    <?php if ($usuario['tipo_usuario'] === 'admin'): ?>
                                                        <span class="u-badge u-badge--ok"><i class="fas fa-check"></i> Todas</span>
                                                    <?php else:
                                                        $rowPerms = [];
                                                        if (!empty($usuario['pode_acessar_lucratividade'])) $rowPerms[] = 'Lucratividade';
                                                        if (!empty($usuario['pode_editar_usuarios_sistema'])) $rowPerms[] = 'Editar Usuários';
                                                        if (!empty($usuario['pode_criar_usuarios_sistema'])) $rowPerms[] = 'Criar Usuários';
                                                        if (!empty($usuario['pode_acessar_relatorios_avancados'])) $rowPerms[] = 'Relatórios';
                                                        if (!empty($usuario['pode_gerenciar_configuracoes'])) $rowPerms[] = 'Configurações';
                                                        if (!empty($usuario['pode_aprovar_abastecimentos'])) $rowPerms[] = 'Gestão de Motoristas';
                                                        if (!empty($usuario['pode_ver_dados_financeiros'])) $rowPerms[] = 'Dados Financeiros';
                                                        if (!empty($usuario['pode_acessar_sistema_fiscal'])) $rowPerms[] = 'Sistema Fiscal';
                                                        if (!empty($usuario['pode_acessar_gestao_pneus'])) $rowPerms[] = 'Gestão de Pneus';
                                                    ?>
                                                        <?php if (empty($rowPerms)): ?>
                                                            <span class="u-badge u-badge--muted">Básicas</span>
                                                        <?php else: ?>
                                                            <div class="u-perms">
                                                                <?php $shown = array_slice($rowPerms, 0, 3); foreach ($shown as $p): ?>
                                                                    <span class="u-badge u-badge--perm"><?php echo htmlspecialchars($p); ?></span>
                                                                <?php endforeach; ?>
                                                                <?php if (count($rowPerms) > 3): ?>
                                                                    <span class="u-badge u-badge--muted" title="<?php echo htmlspecialchars(implode(', ', $rowPerms)); ?>">+<?php echo count($rowPerms) - 3; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="u-col-center" data-label="Status">
                                                <span class="u-badge <?php echo $row_status === 'ativo' ? 'u-badge--ok' : 'u-badge--off'; ?>">
                                                    <span class="u-dot <?php echo $row_status === 'ativo' ? 'u-dot--on' : 'u-dot--off'; ?>"></span> <?php echo ucfirst($row_status); ?>
                                                </span>
                                            </td>
                                            <td class="u-col-center" data-label="2FA">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'sistema'):
                                                    $tfa_on = !empty($usuario['two_factor_enabled']);
                                                    $eh_proprio = ((int) $usuario['id'] === (int) ($_SESSION['usuario_id'] ?? 0));
                                                ?>
                                                    <div style="display:inline-flex;align-items:center;gap:6px;">
                                                        <span class="u-badge <?php echo $tfa_on ? 'u-badge--ok' : 'u-badge--muted'; ?>">
                                                            <i class="fas fa-shield-halved"></i> <?php echo $tfa_on ? 'Ativo' : 'Inativo'; ?>
                                                        </span>
                                                        <?php if ($eh_proprio && !$tfa_on): ?>
                                                            <button class="u-iconbtn u-iconbtn--2fa" title="Configurar 2FA" aria-label="Configurar 2FA" onclick="setup2FA()"><i class="fas fa-qrcode"></i></button>
                                                        <?php elseif ($tfa_on && ($eh_proprio || $can_edit_system_users)): ?>
                                                            <button class="u-iconbtn u-iconbtn--danger" title="Desativar 2FA" aria-label="Desativar 2FA" onclick="disable2FA(<?php echo (int) $usuario['id']; ?>, <?php echo $eh_proprio ? 'true' : 'false'; ?>)"><i class="fas fa-ban"></i></button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="u-col-center" data-label="Cadastro">
                                                <span style="font-size:0.8rem;color:var(--text-secondary);white-space:nowrap;"><?php echo $ts_cad ? date('d/m/Y', $ts_cad) : '—'; ?></span>
                                            </td>
                                            <td class="u-col-center" data-label="Ações">
                                                <?php if ($usuario['tipo_usuario_sistema'] === 'motorista' || $can_edit_system_users): ?>
                                                    <div class="u-actions">
                                                        <button class="u-iconbtn u-iconbtn--edit" title="Editar usuário" aria-label="Editar usuário" onclick="editarUsuario(<?php echo $usuario['id']; ?>, '<?php echo $usuario['tipo_usuario_sistema']; ?>')"><i class="fas fa-pen"></i></button>
                                                        <button class="u-iconbtn u-iconbtn--danger" title="Excluir usuário" aria-label="Excluir usuário" onclick="excluirUsuario(<?php echo $usuario['id']; ?>, '<?php echo $usuario['tipo_usuario_sistema']; ?>')"><i class="fas fa-trash"></i></button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted" style="font-size:0.75rem;">Sem permissão</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($usuarios)): ?>
                                        <tr><td colspan="7"><div class="u-empty"><i class="fas fa-users-slash"></i>Nenhum usuário cadastrado ainda.</div></td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Cadastros (lado a lado) -->
                    <div class="u-forms-row">
                    <!-- Cadastro de Usuário Motorista -->
                    <div class="u-panel">
                        <div class="u-panel__head">
                            <span class="u-panel__ic"><i class="fas fa-id-card" aria-hidden="true"></i></span>
                            <div><h3>Novo usuário motorista</h3><p>Dê acesso ao app a um motorista já cadastrado, usando o e-mail dele.</p></div>
                        </div>
                        <div class="u-panel__body">
                            <form id="cadastroMotoristaForm" method="post" action="../api/usuarios_motoristas.php">
                                <input type="hidden" name="action" value="create">
                                <div class="form-group">
                                    <label for="motorista_id">Motorista</label>
                                    <select id="motorista_id" name="motorista_id" class="form-control" required>
                                        <option value="">Selecione um motorista</option>
                                        <?php foreach ($motoristas_disponiveis as $motorista): ?>
                                        <option value="<?php echo (int) $motorista['id']; ?>" data-email="<?php echo htmlspecialchars($motorista['email'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($motorista['nome'] . ' - ' . $motorista['cpf']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted" style="display:block;margin-top:6px;">Lista apenas motoristas <strong>sem</strong> usuário do app; quem já tem acesso aparece na tabela acima.</small>
                                </div>
                                <div class="form-group">
                                    <label for="email_login_motorista_display">E-mail para login no app</label>
                                    <input type="text" id="email_login_motorista_display" class="form-control" readonly placeholder="Selecione o motorista" autocomplete="off">
                                    <small id="email_login_motorista_hint" class="text-muted" style="display:block;margin-top:6px;">O acesso no app usa o <strong>e-mail cadastrado no motorista</strong> (não o CPF).</small>
                                </div>
                                <div class="form-group">
                                    <label for="senha_motorista">Senha</label>
                                    <input type="password" id="senha_motorista" name="senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha_motorista">Confirmar Senha</label>
                                    <input type="password" id="confirmar_senha_motorista" name="confirmar_senha" class="form-control" required minlength="6">
                                </div>
                                <div class="u-form-actions">
                                    <button type="submit" class="u-btn u-btn--primary">
                                        <i class="fas fa-user-plus u-btn__ic"></i> Cadastrar motorista
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Cadastro de Usuário do Sistema -->
                    <?php if ($can_create_system_users): ?>
                    <div class="u-panel">
                        <div class="u-panel__head">
                            <span class="u-panel__ic"><i class="fas fa-user-gear" aria-hidden="true"></i></span>
                            <div><h3>Novo usuário do sistema</h3><p>Cadastre um administrador ou gestor e defina suas permissões de acesso.</p></div>
                        </div>
                        <div class="u-panel__body">
                            <form id="cadastroSistemaForm" method="post" action="../api/usuarios.php">
                                <input type="hidden" name="action" value="create">
                                <div class="form-group">
                                    <label for="nome_sistema">Nome Completo</label>
                                    <input type="text" id="nome_sistema" name="nome" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="email_sistema">Email</label>
                                    <input type="email" id="email_sistema" name="email" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label for="tipo_usuario">Tipo de Usuário</label>
                                    <select id="tipo_usuario" name="tipo_usuario" class="form-control" required>
                                        <option value="">Selecione o tipo</option>
                                        <option value="admin">Administrador</option>
                                        <option value="gestor">Gestor</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="senha_sistema">Senha</label>
                                    <input type="password" id="senha_sistema" name="senha" class="form-control" required minlength="6">
                                    <div class="pwd-meter" data-target="senha_sistema">
                                        <ul>
                                            <li data-rule="len">8+ caracteres</li>
                                            <li data-rule="upper">Maiúscula</li>
                                            <li data-rule="lower">Minúscula</li>
                                            <li data-rule="num">Número</li>
                                            <li data-rule="special">Especial</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirmar_senha_sistema">Confirmar Senha</label>
                                    <input type="password" id="confirmar_senha_sistema" name="confirmar_senha" class="form-control" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label for="status_sistema">Status</label>
                                    <select id="status_sistema" name="status" class="form-control">
                                        <option value="ativo">Ativo</option>
                                        <option value="inativo">Inativo</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <div class="u-section-label"><i class="fas fa-shield-halved"></i> Permissões especiais</div>
                                    <div class="permissions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_edit_system_users" name="can_edit_system_users" value="1">
                                                <span class="checkmark"></span>
                                                Editar Usuários do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_create_system_users" name="can_create_system_users" value="1">
                                                <span class="checkmark"></span>
                                                Criar Usuários do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_lucratividade" name="can_access_lucratividade" value="1">
                                                <span class="checkmark"></span>
                                                Acessar Lucratividade
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_advanced_reports" name="can_access_advanced_reports" value="1">
                                                <span class="checkmark"></span>
                                                Relatórios Avançados
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_manage_system_settings" name="can_manage_system_settings" value="1">
                                                <span class="checkmark"></span>
                                                Configurações do Sistema
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_approve_refuels" name="can_approve_refuels" value="1">
                                                <span class="checkmark"></span>
                                                Gestão de Motoristas
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_view_financial_data" name="can_view_financial_data" value="1">
                                                <span class="checkmark"></span>
                                                Dados Financeiros
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_fiscal_system" name="can_access_fiscal_system" value="1">
                                                <span class="checkmark"></span>
                                                Sistema Fiscal
                                            </label>
                                        </div>
                                        <div class="permission-item">
                                            <label class="checkbox-label">
                                                <input type="checkbox" id="create_can_access_tire_management" name="can_access_tire_management" value="1">
                                                <span class="checkmark"></span>
                                                Gestão de Pneus
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="u-form-actions">
                                    <button type="submit" class="u-btn u-btn--primary">
                                        <i class="fas fa-user-plus u-btn__ic"></i> Cadastrar usuário do sistema
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div><!-- /.u-forms-row -->
                </div>
            </div>
        </div>
    </div>

    <!-- Botão voltar ao topo -->
    <button type="button" id="uScrollTop" class="u-scrolltop" aria-label="Voltar ao topo" title="Voltar ao topo">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Modal de Edição -->
    <div id="editModal" class="modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-content">
            <div class="modal-header">
                <span class="u-modal-ic"><i class="fas fa-user-pen"></i></span>
                <div class="u-modal-headtext">
                    <h3 id="modalTitle">Editar Usuário</h3>
                    <p>Atualize os dados, o acesso e as permissões deste usuário.</p>
                </div>
                <span class="close" onclick="fecharModal()" role="button" tabindex="0" aria-label="Fechar">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <input type="hidden" id="edit_id" name="id">
                    <input type="hidden" id="edit_tipo" name="tipo_usuario_sistema">
                    <input type="hidden" name="action" value="update">

                    <div class="u-section-label"><i class="fas fa-id-badge"></i> Identificação</div>

                    <div id="edit_motorista_fields" style="display: none;">
                        <div class="form-group">
                            <label for="edit_motorista_id">Motorista</label>
                            <select id="edit_motorista_id" name="motorista_id" class="form-control">
                                <option value="">Selecione um motorista</option>
                                <?php foreach ($motoristas_disponiveis as $motorista): ?>
                                <option value="<?php echo $motorista['id']; ?>">
                                    <?php echo htmlspecialchars($motorista['nome'] . ' - ' . $motorista['cpf']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_nome" id="edit_nome_label">Nome</label>
                        <input type="text" id="edit_nome" name="nome" class="form-control" required>
                    </div>
                    
                    <div id="edit_email_field" style="display: none;">
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" class="form-control">
                        </div>
                    </div>
                    
                    <div id="edit_tipo_usuario_field" style="display: none;">
                        <div class="form-group">
                            <label for="edit_tipo_usuario">Tipo de Usuário</label>
                            <select id="edit_tipo_usuario" name="tipo_usuario" class="form-control">
                                <option value="admin">Administrador</option>
                                <option value="gestor">Gestor</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Permissões Granulares (apenas para gestores) -->
                    <div id="edit_permissions_field" style="display: none;">
                        <div class="form-group">
                            <label>Permissões Especiais</label>
                            <div class="permissions-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_edit_system_users" name="can_edit_system_users" value="1">
                                        <span class="checkmark"></span>
                                        Editar Usuários do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_create_system_users" name="can_create_system_users" value="1">
                                        <span class="checkmark"></span>
                                        Criar Usuários do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_lucratividade" name="can_access_lucratividade" value="1">
                                        <span class="checkmark"></span>
                                        Acessar Lucratividade
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_advanced_reports" name="can_access_advanced_reports" value="1">
                                        <span class="checkmark"></span>
                                        Relatórios Avançados
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_manage_system_settings" name="can_manage_system_settings" value="1">
                                        <span class="checkmark"></span>
                                        Configurações do Sistema
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_approve_refuels" name="can_approve_refuels" value="1">
                                        <span class="checkmark"></span>
                                        Gestão de Motoristas
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_view_financial_data" name="can_view_financial_data" value="1">
                                        <span class="checkmark"></span>
                                        Dados Financeiros
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_fiscal_system" name="can_access_fiscal_system" value="1">
                                        <span class="checkmark"></span>
                                        Sistema Fiscal
                                    </label>
                                </div>
                                <div class="permission-item">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="edit_can_access_tire_management" name="can_access_tire_management" value="1">
                                        <span class="checkmark"></span>
                                        Gestão de Pneus
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="u-section-label"><i class="fas fa-lock"></i> Acesso e segurança</div>

                    <div class="form-group">
                        <label for="edit_senha">Nova Senha (deixe em branco para manter a atual)</label>
                        <input type="password" id="edit_senha" name="senha" class="form-control" minlength="6">
                        <div class="pwd-meter" data-target="edit_senha">
                            <ul>
                                <li data-rule="len">8+ caracteres</li>
                                <li data-rule="upper">Maiúscula</li>
                                <li data-rule="lower">Minúscula</li>
                                <li data-rule="num">Número</li>
                                <li data-rule="special">Especial</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="edit_status">Status</label>
                        <select id="edit_status" name="status" class="form-control">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="u-btn u-btn--secondary" onclick="fecharModal()">
                            <i class="fas fa-times u-btn__ic"></i> Cancelar
                        </button>
                        <button type="submit" class="u-btn u-btn--primary">
                            <i class="fas fa-save u-btn__ic"></i> Salvar alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    function atualizarEmailMotoristaSelecionado() {
        const sel = document.getElementById('motorista_id');
        if (!sel) return;
        const opt = sel.options[sel.selectedIndex];
        const em = opt ? (opt.getAttribute('data-email') || '').trim() : '';
        const disp = document.getElementById('email_login_motorista_display');
        const hint = document.getElementById('email_login_motorista_hint');
        if (disp) disp.value = em;
        if (hint) {
            if (!em) {
                hint.innerHTML = '<span style="color:#c0392b">Este motorista não tem e-mail cadastrado. Cadastre o e-mail em <strong>Motoristas</strong> antes de criar o acesso.</span>';
            } else {
                hint.innerHTML = 'O acesso no app usa o <strong>e-mail cadastrado no motorista</strong> (não o CPF).';
            }
        }
    }
    document.getElementById('motorista_id').addEventListener('change', atualizarEmailMotoristaSelecionado);

    // Validação do formulário de motoristas
    document.getElementById('cadastroMotoristaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const sel = document.getElementById('motorista_id');
        const opt = sel ? sel.options[sel.selectedIndex] : null;
        const em = opt ? (opt.getAttribute('data-email') || '').trim() : '';
        if (!sel || !sel.value || !em) {
            uaToast('Selecione um motorista que possua e-mail cadastrado em Motoristas.', 'error');
            return;
        }
        
        const senha = document.getElementById('senha_motorista').value;
        const confirmarSenha = document.getElementById('confirmar_senha_motorista').value;
        
        if (senha !== confirmarSenha) {
            uaToast('As senhas não coincidem!', 'error');
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../api/usuarios_motoristas.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uaToast('Usuário motorista cadastrado com sucesso!', 'success');
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                uaToast(data.message || data.error || 'Erro ao cadastrar usuário motorista.', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            uaToast('Erro ao cadastrar usuário motorista.', 'error');
        });
    });

    // Validação do formulário de usuários do sistema
    document.getElementById('cadastroSistemaForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('senha_sistema').value;
        const confirmarSenha = document.getElementById('confirmar_senha_sistema').value;
        
        if (senha !== confirmarSenha) {
            uaToast('As senhas não coincidem!', 'error');
            return;
        }
        
        const formData = new FormData(this);
        
        fetch('../api/usuarios.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uaToast('Usuário do sistema cadastrado com sucesso!', 'success');
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                uaToast(data.message || data.error || 'Erro ao cadastrar usuário do sistema.', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            uaToast('Erro ao cadastrar usuário do sistema.', 'error');
        });
    });

    // Validação do formulário de edição
    document.getElementById('editForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const senha = document.getElementById('edit_senha').value;
        const confirmarSenha = document.getElementById('edit_confirmar_senha') ? document.getElementById('edit_confirmar_senha').value : senha;
        
        if (senha && senha !== confirmarSenha) {
            uaToast('As senhas não coincidem!', 'error');
            return;
        }
        
        const formData = new FormData(this);
        const tipoUsuario = document.getElementById('edit_tipo').value;
        const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';
        
        fetch(apiUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uaToast('Usuário atualizado com sucesso!', 'success');
                fecharModal();
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                uaToast(data.message || data.error || 'Erro ao atualizar usuário.', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            uaToast('Erro ao atualizar usuário.', 'error');
        });
    });

    // Função para editar usuário
    function editarUsuario(id, tipoUsuario) {
        // Verificar permissões
        const canEditSystemUsers = <?php echo $can_edit_system_users ? 'true' : 'false'; ?>;
        
        if (tipoUsuario === 'sistema' && !canEditSystemUsers) {
            uaToast('Você não tem permissão para editar usuários do sistema.', 'error');
            return;
        }
        
        const modal = document.getElementById('editModal');
        const modalTitle = document.getElementById('modalTitle');
        const editTipo = document.getElementById('edit_tipo');
        const editMotoristaFields = document.getElementById('edit_motorista_fields');
        const editEmailField = document.getElementById('edit_email_field');
        const editTipoUsuarioField = document.getElementById('edit_tipo_usuario_field');
        
        // Configurar campos baseado no tipo de usuário
        const editPermissionsField = document.getElementById('edit_permissions_field');
        
        if (tipoUsuario === 'motorista') {
            modalTitle.textContent = 'Editar Usuário Motorista';
            editTipo.value = 'motorista';
            editMotoristaFields.style.display = 'none';
            editEmailField.style.display = 'none';
            editTipoUsuarioField.style.display = 'none';
            editPermissionsField.style.display = 'none';
            document.getElementById('edit_nome_label').textContent = 'E-mail (login do app)';
            document.getElementById('edit_nome').readOnly = true;
        } else {
            modalTitle.textContent = 'Editar Usuário do Sistema';
            editTipo.value = 'sistema';
            editMotoristaFields.style.display = 'none';
            editEmailField.style.display = 'block';
            editTipoUsuarioField.style.display = 'block';
            editPermissionsField.style.display = 'block';
            document.getElementById('edit_nome_label').textContent = 'Nome';
            document.getElementById('edit_nome').readOnly = false;
        }
        
        // Buscar dados do usuário
        const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';
        
        fetch(`${apiUrl}?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const usuario = data.data;
                document.getElementById('edit_id').value = usuario.id;
                if (tipoUsuario === 'motorista') {
                    document.getElementById('edit_nome').value = usuario.email_motorista || usuario.nome || '';
                } else {
                    document.getElementById('edit_nome').value = usuario.nome;
                }
                document.getElementById('edit_status').value = usuario.status;
                
                if (tipoUsuario === 'sistema') {
                    document.getElementById('edit_email').value = usuario.email;
                    document.getElementById('edit_tipo_usuario').value = usuario.tipo_usuario;
                    
                    // Se for admin, marcar todas as permissões automaticamente e desabilitar
                    if (usuario.tipo_usuario === 'admin') {
                        const adminCheckboxes = [
                            'edit_can_edit_system_users',
                            'edit_can_create_system_users', 
                            'edit_can_access_lucratividade',
                            'edit_can_access_advanced_reports',
                            'edit_can_manage_system_settings',
                            'edit_can_approve_refuels',
                            'edit_can_view_financial_data',
                            'edit_can_access_fiscal_system',
                            'edit_can_access_tire_management'
                        ];
                        
                        adminCheckboxes.forEach(id => {
                            const checkbox = document.getElementById(id);
                            if (checkbox) {
                                checkbox.checked = true;
                                checkbox.disabled = false; // Admin pode desmarcar se quiser
                            }
                        });
                    } else {
                        // Carregar permissões granulares para usuários não-admin e habilitar checkboxes
                        const nonAdminCheckboxes = [
                            'edit_can_edit_system_users',
                            'edit_can_create_system_users', 
                            'edit_can_access_lucratividade',
                            'edit_can_access_advanced_reports',
                            'edit_can_manage_system_settings',
                            'edit_can_approve_refuels',
                            'edit_can_view_financial_data',
                            'edit_can_access_fiscal_system',
                            'edit_can_access_tire_management'
                        ];
                        
                        // Valores das permissões do usuário
                        const permissionValues = {
                            'edit_can_edit_system_users': usuario.pode_editar_usuarios_sistema == 1,
                            'edit_can_create_system_users': usuario.pode_criar_usuarios_sistema == 1,
                            'edit_can_access_lucratividade': usuario.pode_acessar_lucratividade == 1,
                            'edit_can_access_advanced_reports': usuario.pode_acessar_relatorios_avancados == 1,
                            'edit_can_manage_system_settings': usuario.pode_gerenciar_configuracoes == 1,
                            'edit_can_approve_refuels': usuario.pode_aprovar_abastecimentos == 1,
                            'edit_can_view_financial_data': usuario.pode_ver_dados_financeiros == 1,
                            'edit_can_access_fiscal_system': usuario.pode_acessar_sistema_fiscal == 1,
                            'edit_can_access_tire_management': usuario.pode_acessar_gestao_pneus == 1
                        };
                        
                        nonAdminCheckboxes.forEach(id => {
                            const checkbox = document.getElementById(id);
                            if (checkbox) {
                                checkbox.checked = permissionValues[id];
                                checkbox.disabled = false;
                            }
                        });
                    }
                } else {
                    const sel = document.getElementById('edit_motorista_id');
                    if (sel) sel.value = usuario.motorista_id || '';
                }

                modal.style.display = 'flex';
            } else {
                uaToast(data.message || data.error || 'Erro ao carregar dados do usuário.', 'error');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            uaToast('Erro ao carregar dados do usuário.', 'error');
        });
    }

    // Função para excluir usuário
    function excluirUsuario(id, tipoUsuario) {
        // Verificar permissões
        const canEditSystemUsers = <?php echo $can_edit_system_users ? 'true' : 'false'; ?>;
        
        if (tipoUsuario === 'sistema' && !canEditSystemUsers) {
            uaToast('Você não tem permissão para excluir usuários do sistema.', 'error');
            return;
        }

        uaConfirm('Tem certeza que deseja excluir este usuário? Esta ação não pode ser desfeita.').then(function (ok) {
            if (!ok) return;
            const apiUrl = tipoUsuario === 'motorista' ? '../api/usuarios_motoristas.php' : '../api/usuarios.php';

            fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    var row = document.querySelector('tr.user-row[data-user-id="' + id + '"][data-user-tipo="' + tipoUsuario + '"]');
                    if (row) { row.remove(); if (window.uaApplyFilter) window.uaApplyFilter(); }
                    uaToast('Usuário excluído com sucesso!', 'success');
                } else {
                    uaToast(data.message || data.error || 'Erro ao excluir usuário.', 'error');
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                uaToast('Erro ao excluir usuário.', 'error');
            });
        });
    }

    // Função para fechar modal
    function fecharModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Fechar modal ao clicar fora dele
    window.onclick = function(event) {
        const modal = document.getElementById('editModal');
        if (event.target === modal) {
            fecharModal();
        }
    }

    // Controlar exibição de permissões baseado no tipo de usuário
    document.addEventListener('DOMContentLoaded', function() {
        const tipoUsuarioSelect = document.getElementById('edit_tipo_usuario');
        const permissionsField = document.getElementById('edit_permissions_field');
        
        if (tipoUsuarioSelect && permissionsField) {
            tipoUsuarioSelect.addEventListener('change', function() {
                if (this.value === 'gestor') {
                    permissionsField.style.display = 'block';
                    // Habilitar todos os checkboxes para gestor
                    const checkboxes = permissionsField.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                } else if (this.value === 'admin') {
                    permissionsField.style.display = 'block';
                    // Marcar todas as permissões para admin mas manter habilitado (pode desmarcar se quiser)
                    const checkboxes = permissionsField.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = true;
                        checkbox.disabled = false;
                    });
                }
            });
        }
    });
    </script>
    
    <!-- Modal de configuração do 2FA -->
    <div id="modal2FA" class="modal" style="display:none;">
        <div class="modal-content" style="max-width:420px;">
            <div class="modal-header">
                <h3><i class="fas fa-shield-halved"></i> Autenticação em dois fatores</h3>
                <button type="button" class="modal-close" onclick="close2FAModal()" aria-label="Fechar">&times;</button>
            </div>
            <div class="modal-body" style="text-align:center;">
                <p style="font-size:13px;">Escaneie o QR Code no <strong>Google Authenticator</strong> ou <strong>Microsoft Authenticator</strong> e digite o código gerado.</p>
                <div id="qr2fa" style="display:flex;justify-content:center;margin:14px 0;"></div>
                <p style="font-size:12px;opacity:.8;">Ou insira manualmente esta chave:</p>
                <code id="secret2fa" style="display:block;word-break:break-all;font-size:12px;margin-bottom:12px;"></code>
                <div class="form-group" style="text-align:left;">
                    <label for="code2fa">Código de 6 dígitos</label>
                    <input type="text" id="code2fa" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" style="width:100%;">
                </div>
                <div id="msg2fa" style="font-size:12px;color:#c0392b;min-height:16px;"></div>
                <button type="button" class="btn btn-primary" onclick="confirm2FA()" style="width:100%;margin-top:8px;">Ativar 2FA</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    var TFA_API = '../api/usuarios_2fa.php';
    function setup2FA() {
        var fd = new FormData(); fd.append('action', 'start');
        fetch(TFA_API, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.success) { uaToast(res.message || res.error || 'Erro ao iniciar 2FA.', 'error'); return; }
            var qr = document.getElementById('qr2fa'); qr.innerHTML = '';
            new QRCode(qr, { text: res.otpauth, width: 180, height: 180 });
            document.getElementById('secret2fa').textContent = res.secret;
            document.getElementById('code2fa').value = '';
            document.getElementById('msg2fa').textContent = '';
            document.getElementById('modal2FA').style.display = 'flex';
        }).catch(function () { uaToast('Erro de comunicação.', 'error'); });
    }
    function confirm2FA() {
        var code = (document.getElementById('code2fa').value || '').trim();
        var fd = new FormData(); fd.append('action', 'confirm'); fd.append('code', code);
        fetch(TFA_API, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) { uaToast('2FA ativado com sucesso!', 'success'); setTimeout(function(){ location.reload(); }, 900); }
            else { document.getElementById('msg2fa').textContent = res.message || res.error || 'Código inválido.'; }
        }).catch(function () { document.getElementById('msg2fa').textContent = 'Erro de comunicação.'; });
    }
    function disable2FA(id, isSelf) {
        if (!confirm('Desativar a autenticação em dois fatores deste usuário?')) return;
        var fd = new FormData(); fd.append('action', 'disable'); fd.append('usuario_id', id);
        fetch(TFA_API, { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.success) { location.reload(); } else { uaToast(res.message || res.error || 'Erro ao desativar.', 'error'); }
        }).catch(function () { uaToast('Erro de comunicação.', 'error'); });
    }
    function close2FAModal() { document.getElementById('modal2FA').style.display = 'none'; }
    </script>

    <!-- Toast + modal de confirmação -->
    <div id="uaToastWrap" aria-live="polite"></div>
    <div class="confirm-modal" id="uaConfirmModal" role="dialog" aria-modal="true" aria-labelledby="uaConfirmTitle">
        <div class="confirm-box">
            <div class="ci"><i class="fas fa-triangle-exclamation"></i></div>
            <h4 id="uaConfirmTitle">Confirmar exclusão</h4>
            <p id="uaConfirmMsg">Tem certeza?</p>
            <div class="confirm-actions">
                <button type="button" class="btn btn-secondary" id="uaConfirmCancel"><i class="fas fa-times"></i> Cancelar</button>
                <button type="button" class="btn btn-danger" id="uaConfirmOk"><i class="fas fa-trash"></i> Excluir</button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        // ---- Toast ----
        window.uaToast = function (msg, type) {
            var wrap = document.getElementById('uaToastWrap');
            if (!wrap) { alert(msg); return; }
            var t = document.createElement('div');
            t.className = 'ua-toast ' + (type || 'info');
            var icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
            t.innerHTML = '<i class="fas ' + icon + '"></i><span></span>';
            t.querySelector('span').textContent = msg;
            wrap.appendChild(t);
            requestAnimationFrame(function () { t.classList.add('show'); });
            setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 300); }, 3500);
        };

        // ---- Confirmação (Promise) ----
        var modal = document.getElementById('uaConfirmModal');
        var msgEl = document.getElementById('uaConfirmMsg');
        var resolver = null;
        function closeConfirm(val) { if (modal) modal.classList.remove('show'); if (resolver) { resolver(val); resolver = null; } }
        var ok = document.getElementById('uaConfirmOk');
        var cancel = document.getElementById('uaConfirmCancel');
        if (ok) ok.addEventListener('click', function () { closeConfirm(true); });
        if (cancel) cancel.addEventListener('click', function () { closeConfirm(false); });
        if (modal) modal.addEventListener('click', function (e) { if (e.target === modal) closeConfirm(false); });
        window.uaConfirm = function (message) {
            return new Promise(function (resolve) {
                resolver = resolve;
                if (msgEl) msgEl.textContent = message || 'Tem certeza?';
                if (modal) modal.classList.add('show');
            });
        };

        // ---- Busca instantânea + filtros + ordenação ----
        function applyFilter() {
            var s = document.getElementById('ufSearch');
            var q = (s ? s.value : '').trim().toLowerCase();
            var tipo = (document.getElementById('ufTipo') || {}).value || '';
            var status = (document.getElementById('ufStatus') || {}).value || '';
            var rows = Array.prototype.slice.call(document.querySelectorAll('tr.user-row'));
            var visible = 0;
            rows.forEach(function (r) {
                var blob = r.getAttribute('data-search') || ((r.getAttribute('data-nome') || '') + ' ' + (r.getAttribute('data-email') || ''));
                var okQ = !q || blob.indexOf(q) >= 0;
                var okT = !tipo || r.getAttribute('data-tipo') === tipo;
                var okS = !status || r.getAttribute('data-status') === status;
                var show = okQ && okT && okS;
                r.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            var c = document.getElementById('ufCount');
            if (c) c.textContent = visible + ' de ' + rows.length + ' usuário(s)';
            // Ordenação (reordena as linhas visíveis no tbody)
            var sort = (document.getElementById('ufSort') || {}).value || 'recent';
            var body = document.getElementById('usersTbody');
            if (body) {
                rows.sort(function (a, b) {
                    if (sort === 'name') {
                        return (a.getAttribute('data-nome') || '').localeCompare(b.getAttribute('data-nome') || '');
                    }
                    return (parseInt(b.getAttribute('data-cadastro'), 10) || 0) - (parseInt(a.getAttribute('data-cadastro'), 10) || 0);
                });
                rows.forEach(function (r) { body.appendChild(r); });
            }
        }
        window.uaApplyFilter = applyFilter;
        ['ufSearch', 'ufTipo', 'ufStatus', 'ufSort'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener(id === 'ufSearch' ? 'input' : 'change', applyFilter);
        });
        var clearBtn = document.getElementById('ufClear');
        if (clearBtn) clearBtn.addEventListener('click', function () {
            var s = document.getElementById('ufSearch'); if (s) s.value = '';
            var t = document.getElementById('ufTipo'); if (t) t.value = '';
            var st = document.getElementById('ufStatus'); if (st) st.value = '';
            var so = document.getElementById('ufSort'); if (so) so.value = 'recent';
            applyFilter();
            if (s) s.focus();
        });
        applyFilter();

        // ESC fecha qualquer modal aberto (editModal / modal2FA)
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            ['editModal', 'modal2FA'].forEach(function (mid) {
                var m = document.getElementById(mid);
                if (m && (m.style.display === 'flex' || m.style.display === 'block')) { m.style.display = 'none'; }
            });
        });
        // Fechar 2FA clicando fora
        var m2fa = document.getElementById('modal2FA');
        if (m2fa) m2fa.addEventListener('click', function (e) { if (e.target === m2fa) m2fa.style.display = 'none'; });

        // ---- Botão "voltar ao topo" ----
        var topBtn = document.getElementById('uScrollTop');
        if (topBtn) {
            // Detecta o container que realmente rola (window ou .main-content/.dashboard-content)
            var scroller = null;
            ['.main-content', '.dashboard-content'].forEach(function (sel) {
                var el = document.querySelector(sel);
                if (!scroller && el && el.scrollHeight > el.clientHeight + 40) { scroller = el; }
            });
            function scrollPos() { return scroller ? scroller.scrollTop : (window.pageYOffset || document.documentElement.scrollTop || 0); }
            function onScroll() { topBtn.classList.toggle('show', scrollPos() > 260); }
            (scroller || window).addEventListener('scroll', onScroll, { passive: true });
            window.addEventListener('resize', onScroll, { passive: true });
            topBtn.addEventListener('click', function () {
                if (scroller) { scroller.scrollTo({ top: 0, behavior: 'smooth' }); }
                else { window.scrollTo({ top: 0, behavior: 'smooth' }); }
            });
            onScroll();
        }

        // ---- Medidor de senha forte ----
        document.querySelectorAll('.pwd-meter').forEach(function (meter) {
            var input = document.getElementById(meter.getAttribute('data-target'));
            if (!input) return;
            function run() {
                var v = input.value || '';
                var rules = {
                    len: v.length >= 8, upper: /[A-Z]/.test(v), lower: /[a-z]/.test(v),
                    num: /[0-9]/.test(v), special: /[^A-Za-z0-9]/.test(v)
                };
                meter.querySelectorAll('li').forEach(function (li) {
                    li.classList.toggle('ok', !!rules[li.getAttribute('data-rule')]);
                });
            }
            input.addEventListener('input', run);
            run();
        });
    })();
    </script>

    <?php include '../includes/footer.php'; ?>
    <?php $sf_help_key = 'usuarios'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html> 