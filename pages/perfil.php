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
$stmt = $conn->prepare('SELECT nome, email, foto_perfil FROM usuarios WHERE id = :id AND empresa_id = :empresa_id');
$stmt->bindValue(':id', $usuario_id);
$stmt->bindValue(':empresa_id', $empresa_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$page_title = 'Meu Perfil';

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
    <?php sf_render_api_scripts(); ?>
</head>
<body>
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
        <?php include '../includes/header.php'; ?>
        <div class="dashboard-content">
            <div class="dashboard-header">
                <h1><i class="fas fa-user"></i> <?php echo $page_title; ?></h1>
            </div>
            <div class="dashboard-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 32px;">
                <!-- Card: Foto de Perfil -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-camera kpi-card-icon" aria-hidden="true"></i>
                        <h3>Foto de Perfil</h3>
                    </div>
                    <div class="card-body text-center">
                        <form method="post" enctype="multipart/form-data" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="foto">
                            <label for="foto_perfil">
                                <img src="<?php echo $user['foto_perfil'] ? '../uploads/perfil/' . htmlspecialchars($user['foto_perfil']) : '../img/user-default.png'; ?>" alt="Foto de Perfil" class="profile-avatar" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ccc;" onerror="this.src='../img/user-default.png'">
                            </label>
                            <input type="file" id="foto_perfil" name="foto_perfil" accept="image/*" style="display:none;">
                            <div style="margin-top: 8px;">
                                <button type="button" class="btn btn-secondary" onclick="document.getElementById('foto_perfil').click();"><i class="fas fa-camera"></i> Selecionar Foto</button>
                            </div>
                            <div class="form-actions text-center" style="margin-top: 12px;">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Foto</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Alterar Nome -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-user-pen kpi-card-icon" aria-hidden="true"></i>
                        <h3>Alterar Nome</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="nome">
                            <div class="form-group">
                                <label for="nome">Nome</label>
                                <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars($user['nome']); ?>" required>
                            </div>
                            <div class="form-actions text-center">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Nome</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Alterar E-mail -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-envelope kpi-card-icon" aria-hidden="true"></i>
                        <h3>Alterar E-mail</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="../includes/perfil_update.php">
                            <input type="hidden" name="acao" value="email">
                            <div class="form-group">
                                <label for="email">E-mail</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            <div class="form-actions text-center">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar E-mail</button>
                            </div>
                        </form>
                    </div>
                </div>
                <!-- Card: Trocar Senha -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-key kpi-card-icon" aria-hidden="true"></i>
                        <h3>Trocar Senha</h3>
                    </div>
                    <div class="card-body">
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
                            <div class="form-actions text-center">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Senha</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="dashboard-card perfil-notif-card" style="margin-top: 1rem;">
                <div class="card-header">
                    <i class="fas fa-bell kpi-card-icon" aria-hidden="true"></i>
                    <h3>Notificações</h3>
                </div>
                <div class="card-body">
                    <p class="config-card-lead">Escolha quais alertas <strong>você</strong> deseja receber no sininho (operacionais) e no painel de IA. As preferências são individuais.</p>
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
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar preferências</button>
                    </form>
                    <div id="configNotifPrefsMsg" class="perfil-inline-msg" role="status"></div>
                </div>
            </div>
        </div>
        <?php include '../includes/footer.php'; ?>
    </div>
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
    </script>
</body>
</html> 