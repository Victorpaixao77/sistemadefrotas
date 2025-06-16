<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

configure_session();
session_start();

$page_title = 'Configurações do Sistema';
$empresa_id = $_SESSION['empresa_id'];

// Busca as configurações atuais
$conn = getConnection();
$stmt = $conn->prepare('SELECT nome_personalizado, logo_empresa FROM configuracoes WHERE empresa_id = :empresa_id LIMIT 1');
$stmt->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$nome_personalizado = $row ? $row['nome_personalizado'] : 'Desenvolvimento';
$logo_path = $row ? $row['logo_empresa'] : null;
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
        .logo-preview {
            max-width: 200px;
            max-height: 200px;
            margin: 10px 0;
            display: none;
        }
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .logo-upload {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <h1><?php echo $page_title; ?></h1>
                </div>
                <div class="dashboard-grid" style="max-width: 600px; margin: 0 auto;">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Personalização do Menu Lateral</h3>
                        </div>
                        <div class="card-body">
                            <form id="configForm">
                                <div class="form-group">
                                    <label for="nome_personalizado">Título do Menu Lateral</label>
                                    <input type="text" id="nome_personalizado" name="nome_personalizado" value="<?php echo htmlspecialchars($nome_personalizado); ?>" maxlength="255" required>
                                </div>
                                <button type="submit" class="btn-primary" id="saveConfigBtn">Salvar</button>
                            </form>
                            <div id="configMsg" style="margin-top:10px;"></div>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3>Logo da Empresa</h3>
                        </div>
                        <div class="card-body">
                            <form id="logoForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="logo">Selecione uma imagem (JPG, PNG ou GIF, máx. 5MB)</label>
                                    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/gif" required>
                                </div>
                                <div class="logo-preview" id="logoPreview">
                                    <img src="" alt="Preview do logo">
                                </div>
                                <button type="submit" class="btn-primary" id="uploadLogoBtn">Enviar Logo</button>
                            </form>
                            <div id="logoMsg" style="margin-top:10px;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    document.getElementById('configForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const nome = document.getElementById('nome_personalizado').value.trim();
        const msg = document.getElementById('configMsg');
        msg.textContent = '';
        fetch('../api/configuracoes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'update_nome', nome_personalizado: nome })
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Título atualizado com sucesso!';
                msg.style.color = 'green';
                // Atualiza o menu lateral dinamicamente
                const menuTitle = document.querySelector('.sidebar .app-name');
                if (menuTitle) menuTitle.textContent = nome;
            } else {
                msg.textContent = res.error || 'Erro ao salvar.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao salvar.';
            msg.style.color = 'red';
        });
    });

    // Preview da imagem antes do upload
    document.getElementById('logo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('logoPreview');
                preview.style.display = 'block';
                preview.querySelector('img').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Upload do logo
    document.getElementById('logoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'upload_logo');
        
        const msg = document.getElementById('logoMsg');
        msg.textContent = '';
        
        fetch('../api/configuracoes.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                msg.textContent = 'Logo atualizado com sucesso!';
                msg.style.color = 'green';
                // Atualiza o logo no menu lateral
                const menuLogo = document.querySelector('.sidebar .logo img');
                if (menuLogo) {
                    menuLogo.src = '../' + res.logo_path;
                }
            } else {
                msg.textContent = res.error || 'Erro ao fazer upload do logo.';
                msg.style.color = 'red';
            }
        })
        .catch(() => {
            msg.textContent = 'Erro ao fazer upload do logo.';
            msg.style.color = 'red';
        });
    });
    </script>
</body>
</html> 