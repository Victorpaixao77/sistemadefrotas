<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
require_once '../includes/sf_api_base.php';

configure_session();
session_start();
require_authentication();

if (!isset($_SESSION['empresa_id'])) {
    header('Location: ../selecionar_empresa.php');
    exit;
}

$usuario_id = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? 0);
$empresa_id = (int) $_SESSION['empresa_id'];

// Buscar dados atuais do usuário
$conn = getConnection();
$stmt = $conn->prepare('SELECT nome, email, foto_perfil, tipo_usuario, status, two_factor_enabled, data_cadastro FROM usuarios WHERE id = :id AND empresa_id = :empresa_id');
$stmt->bindValue(':id', $usuario_id);
$stmt->bindValue(':empresa_id', $empresa_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$page_title = 'Meu Perfil';

// Dados reais para o resumo da conta
$pf_nome = (string) ($user['nome'] ?? '');
$pf_email = (string) ($user['email'] ?? '');
$pf_tipo = (string) ($user['tipo_usuario'] ?? '');
$pf_status = (string) ($user['status'] ?? 'ativo');
$pf_2fa = !empty($user['two_factor_enabled']);
$pf_desde = !empty($user['data_cadastro']) ? date('m/Y', strtotime((string) $user['data_cadastro'])) : '—';
$pf_parts = preg_split('/\s+/', trim($pf_nome));
$pf_ini = $pf_parts && $pf_parts[0] !== '' ? mb_strtoupper(mb_substr($pf_parts[0], 0, 1)) : '?';
if ($pf_parts && count($pf_parts) > 1) { $pf_ini .= mb_strtoupper(mb_substr(end($pf_parts), 0, 1)); }
$pf_tipo_label = $pf_tipo === 'admin' ? 'Administrador' : ($pf_tipo === 'gestor' ? 'Gestor' : ucfirst($pf_tipo ?: 'Usuário'));

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
    <link rel="stylesheet" href="../css/notif-preferencias.css?v=2">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo function_exists('sf_asset_v') ? sf_asset_v() : '1'; ?>">
    <?php sf_render_api_scripts(); ?>
</head>
<body class="page-perfil sf-premium">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content">
            <!-- Cabeçalho da página -->
            <div class="u-pagehead">
                <div class="u-pagehead__title">
                    <h1><i class="fas fa-user-gear" style="color:var(--accent-primary,#3b82f6)"></i> Meu perfil</h1>
                    <p>Atualize seus dados, sua foto, sua senha e suas preferências de notificação.</p>
                </div>
            </div>

            <!-- Resumo da conta (dados reais) -->
            <div class="u-account">
                <div class="u-account__avatar">
                    <?php if (!empty($user['foto_perfil'])): ?>
                        <img src="../uploads/perfil/<?php echo htmlspecialchars($user['foto_perfil']); ?>" alt="Foto de perfil" onerror="this.style.display='none';this.parentNode.classList.add('is-initials');">
                    <?php else: ?><span></span><?php endif; ?>
                    <b><?php echo htmlspecialchars($pf_ini); ?></b>
                </div>
                <div class="u-account__info">
                    <h2><?php echo htmlspecialchars($pf_nome !== '' ? $pf_nome : 'Usuário'); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($pf_email !== '' ? $pf_email : '—'); ?></p>
                    <div class="u-account__badges">
                        <span class="u-badge <?php echo $pf_tipo === 'admin' ? 'u-badge--admin' : ($pf_tipo === 'gestor' ? 'u-badge--gestor' : 'u-badge--muted'); ?>"><i class="fas fa-user-shield"></i> <?php echo htmlspecialchars($pf_tipo_label); ?></span>
                        <span class="u-badge <?php echo $pf_status === 'ativo' ? 'u-badge--ok' : 'u-badge--off'; ?>"><span class="u-dot <?php echo $pf_status === 'ativo' ? 'u-dot--on' : 'u-dot--off'; ?>"></span> <?php echo ucfirst($pf_status); ?></span>
                        <span class="u-badge <?php echo $pf_2fa ? 'u-badge--ok' : 'u-badge--muted'; ?>"><i class="fas fa-shield-halved"></i> 2FA <?php echo $pf_2fa ? 'ativo' : 'inativo'; ?></span>
                        <span class="u-badge u-badge--muted"><i class="fas fa-calendar-day"></i> Desde <?php echo htmlspecialchars($pf_desde); ?></span>
                    </div>
                </div>
            </div>

            <div class="u-forms-row" style="margin-bottom:1.25rem;">
                <!-- Card: Foto de Perfil -->
                <div class="u-panel">
                    <div class="u-panel__head">
                        <span class="u-panel__ic"><i class="fas fa-camera" aria-hidden="true"></i></span>
                        <div><h3>Foto de perfil</h3><p>Envie uma imagem para personalizar sua conta.</p></div>
                    </div>
                    <div class="u-panel__body text-center">
                        <form method="post" enctype="multipart/form-data" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="foto">
                            <label for="foto_perfil">
                                <img src="<?php echo $user['foto_perfil'] ? '../uploads/perfil/' . htmlspecialchars($user['foto_perfil']) : '../img/user-default.png'; ?>" alt="Foto de Perfil" class="profile-avatar" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;" onerror="this.src='../img/user-default.png'">
                            </label>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display:none;">
                            <div style="margin-top: 8px;">
                                <button type="button" class="u-btn u-btn--secondary" onclick="document.getElementById('foto_perfil').click();"><i class="fas fa-camera"></i> Selecionar Foto</button>
                            </div>
                            <div class="u-form-actions" style="margin-top: 12px;">
                                <button type="submit" class="u-btn u-btn--primary"><i class="fas fa-save"></i> Salvar Foto</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Alterar Nome -->
                <div class="u-panel">
                    <div class="u-panel__head">
                        <span class="u-panel__ic"><i class="fas fa-user-pen" aria-hidden="true"></i></span>
                        <div><h3>Alterar nome</h3><p>Como seu nome aparece no sistema.</p></div>
                    </div>
                    <div class="u-panel__body">
                        <form method="post" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="nome">
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
                            </div>
                            <div class="u-form-actions">
                                <button type="submit" class="u-btn u-btn--primary"><i class="fas fa-save"></i> Salvar Nome</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Alterar E-mail -->
                <div class="u-panel">
                    <div class="u-panel__head">
                        <span class="u-panel__ic"><i class="fas fa-envelope" aria-hidden="true"></i></span>
                        <div><h3>Alterar e-mail</h3><p>E-mail usado para acesso e comunicações.</p></div>
                    </div>
                    <div class="u-panel__body">
                        <form method="post" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="email">
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="u-form-actions">
                                <button type="submit" class="u-btn u-btn--primary"><i class="fas fa-save"></i> Salvar E-mail</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Trocar Senha -->
                <div class="u-panel">
                    <div class="u-panel__head">
                        <span class="u-panel__ic"><i class="fas fa-key" aria-hidden="true"></i></span>
                        <div><h3>Trocar senha</h3><p>Use uma senha forte e única.</p></div>
                    </div>
                    <div class="u-panel__body">
                        <form method="post" action="../includes/perfil_update.php" autocomplete="off">
                            <input type="hidden" name="acao" value="senha">
                            <div class="form-group">
                                <label for="nova_senha">Nova Senha</label>
                                <input type="password" id="nova_senha" name="nova_senha" class="form-control" minlength="6" autocomplete="new-password">
                            </div>
                            <div class="form-group">
                                <label for="confirmar_senha">Confirmar Nova Senha</label>
                                <input type="password" id="confirmar_senha" name="confirmar_senha" class="form-control" minlength="6" autocomplete="new-password">
                            </div>
                            <div class="u-form-actions">
                                <button type="submit" class="u-btn u-btn--primary"><i class="fas fa-save"></i> Salvar Senha</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="u-panel perfil-notif-card">
                <div class="u-panel__head">
                    <span class="u-panel__ic"><i class="fas fa-bell" aria-hidden="true"></i></span>
                    <div><h3>Notificações</h3><p>Escolha quais alertas você recebe. As preferências são individuais.</p></div>
                </div>
                <div class="u-panel__body">
                    <form id="configNotifPrefsForm">
                        <div class="notif-prefs-grid">
                            <div class="notif-prefs-col">
                                <div class="notif-prefs-col-head">
                                    <h6><i class="fas fa-bell" aria-hidden="true"></i> Operacionais</h6>
                                    <div class="notif-prefs-actions">
                                        <button type="button" class="btn-link-sm" data-prefs-grupo="operacional" data-prefs-acao="all">Marcar todos</button>
                                        <span aria-hidden="true">·</span>
                                        <button type="button" class="btn-link-sm" data-prefs-grupo="operacional" data-prefs-acao="none">Desmarcar todos</button>
                                    </div>
                                </div>
                                <div id="notifPrefsOperacional" class="notif-prefs-tipos"><p class="notif-prefs-loading">Carregando…</p></div>
                            </div>
                            <div class="notif-prefs-col">
                                <div class="notif-prefs-col-head">
                                    <h6><i class="fas fa-robot" aria-hidden="true"></i> Inteligência Artificial</h6>
                                    <div class="notif-prefs-actions">
                                        <button type="button" class="btn-link-sm" data-prefs-grupo="ia" data-prefs-acao="all">Marcar todos</button>
                                        <span aria-hidden="true">·</span>
                                        <button type="button" class="btn-link-sm" data-prefs-grupo="ia" data-prefs-acao="none">Desmarcar todos</button>
                                    </div>
                                </div>
                                <div id="notifPrefsIa" class="notif-prefs-tipos"><p class="notif-prefs-loading">Carregando…</p></div>
                            </div>
                        </div>
                        <div class="notif-prefs-digest">
                            <label class="notif-pref-check">
                                <input type="checkbox" id="notifPrefDigest" name="email_digest">
                                Receber resumo diário por e-mail
                            </label>
                            <label class="notif-pref-time">
                                Hora do resumo
                                <input type="time" id="notifPrefDigestHora" value="08:00">
                            </label>
                        </div>
                        <button type="submit" class="u-btn u-btn--primary"><i class="fas fa-save"></i> Salvar preferências</button>
                    </form>
                    <div id="configNotifPrefsMsg" class="perfil-inline-msg" role="status"></div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Botão voltar ao topo -->
    <button type="button" id="uScrollTop" class="u-scrolltop" aria-label="Voltar ao topo" title="Voltar ao topo">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script src="../js/notificacoes-preferencias-config.js?v=2"></script>
    <script>
    // Preview da foto de perfil
    document.getElementById('foto_perfil').addEventListener('change', function(e) {
        const [file] = e.target.files;
        if (file) {
            const img = document.querySelector('.profile-avatar');
            img.src = URL.createObjectURL(file);
        }
    });
    // Validação de senha
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (form.querySelector('[name=acao]') && form.querySelector('[name=acao]').value === 'senha') {
                const senha = form.querySelector('#nova_senha').value;
                const confirmar = form.querySelector('#confirmar_senha').value;
                if (senha.length < 6) {
                    alert('A senha deve ter pelo menos 6 caracteres.');
                    e.preventDefault();
                    return;
                }
                if (senha !== confirmar) {
                    alert('As senhas não coincidem.');
                    e.preventDefault();
                    return;
                }
            }
        });
    });

    // Botão "voltar ao topo" (detecta o container que realmente rola)
    (function () {
        var topBtn = document.getElementById('uScrollTop');
        if (!topBtn) return;
        var scroller = null;
        ['.main-content', '.dashboard-content'].forEach(function (sel) {
            var el = document.querySelector(sel);
            if (!scroller && el && el.scrollHeight > el.clientHeight + 40) { scroller = el; }
        });
        function pos() { return scroller ? scroller.scrollTop : (window.pageYOffset || document.documentElement.scrollTop || 0); }
        function onScroll() { topBtn.classList.toggle('show', pos() > 260); }
        (scroller || window).addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });
        topBtn.addEventListener('click', function () {
            (scroller || window).scrollTo({ top: 0, behavior: 'smooth' });
        });
        onScroll();
    })();
    </script>
</body>
</html> 