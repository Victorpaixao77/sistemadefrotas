<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Verificar se está logado
verificarLogin();

// Obter dados básicos da sessão
$usuario_sessao = obterUsuarioLogado();
$empresa_id = obterEmpresaId();

// Buscar dados completos do usuário no banco
try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT * FROM seguro_usuarios 
        WHERE id = ? AND seguro_empresa_id = ?
    ");
    $stmt->execute([$usuario_sessao['id'], $empresa_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        die("Erro: Usuário não encontrado");
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do usuário: " . $e->getMessage());
    die("Erro ao carregar dados do perfil");
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Seguro - Meu Perfil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/menu-responsivo.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .header {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: white;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .form-section {
            margin-bottom: 30px;
        }
        .section-title {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="p-3">
            <h4 class="text-white text-center mb-4">
                <i class="fas fa-shield-alt me-2"></i>
                Sistema Seguro
            </h4>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="empresa.php">
                    <i class="fas fa-building me-2"></i>
                    Empresa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="clientes.php">
                    <i class="fas fa-users me-2"></i>
                    Clientes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="financeiro.php">
                    <i class="fas fa-chart-line me-2"></i>
                    Financeiro
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="atendimento.php">
                    <i class="fas fa-headset me-2"></i>
                    Atendimento
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle me-3" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div>
                        <h2 class="mb-1">
                            <i class="fas fa-user me-2"></i>
                            Meu Perfil
                        </h2>
                        <p class="text-muted mb-0">Gerencie suas informações pessoais</p>
                    </div>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            Perfil
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item active" href="perfil.php"><i class="fas fa-user me-2"></i>Meu Perfil</a></li>
                            <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Configurações</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair do Sistema</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mensagens de Feedback -->
        <div id="mensagemFeedback"></div>

        <div class="row">
            <!-- Sidebar de Perfil -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-body text-center">
                        <!-- Avatar -->
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        
                        <h4><?php echo htmlspecialchars($usuario['nome']); ?></h4>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($usuario['email']); ?></p>
                        
                        <div class="mb-3">
                            <?php
                            $niveis = [
                                'admin' => '<span class="badge bg-danger">Administrador</span>',
                                'gerente' => '<span class="badge bg-warning">Gerente</span>',
                                'operador' => '<span class="badge bg-primary">Operador</span>',
                                'visualizador' => '<span class="badge bg-secondary">Visualizador</span>'
                            ];
                            $nivel = isset($usuario['nivel_acesso']) ? $usuario['nivel_acesso'] : 'operador';
                            echo $niveis[$nivel] ?? '<span class="badge bg-secondary">Usuário</span>';
                            ?>
                        </div>
                        
                        <hr>
                        
                        <div class="text-start">
                            <p class="mb-2">
                                <i class="fas fa-building me-2 text-primary"></i>
                                <strong>Empresa:</strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['seguro_empresa_nome'] ?? 'N/A'); ?></small>
                            </p>
                            <p class="mb-2">
                                <i class="fas fa-phone me-2 text-success"></i>
                                <strong>Telefone:</strong><br>
                                <small class="text-muted">
                                    <?php 
                                    $telExibir = $usuario['telefone'] ?? '';
                                    if (!empty($telExibir) && !strpos($telExibir, '(')) {
                                        // Aplicar máscara
                                        $telExibir = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telExibir);
                                    }
                                    echo $telExibir ?: 'Não informado';
                                    ?>
                                </small>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-calendar me-2 text-warning"></i>
                                <strong>Último Acesso:</strong><br>
                                <small class="text-muted">
                                    <?php 
                                    if (isset($usuario['ultimo_acesso']) && $usuario['ultimo_acesso']) {
                                        echo date('d/m/Y H:i', strtotime($usuario['ultimo_acesso']));
                                    } else {
                                        echo 'Primeiro acesso';
                                    }
                                    ?>
                                </small>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulários de Edição -->
            <div class="col-md-8">
                <!-- Dados Pessoais -->
                <div class="card mb-4">
                    <div class="card-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                        <h5 class="mb-0">
                            <i class="fas fa-user-edit me-2"></i>
                            Dados Pessoais
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="formDadosPessoais">
                            <div class="mb-3">
                                <label for="nome" class="form-label">Nome Completo <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                                <small class="text-muted">Usado para fazer login no sistema</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="telefone" value="<?php 
                                    $tel = $usuario['telefone'] ?? '';
                                    // Aplicar máscara se o telefone estiver sem formatação
                                    if (!empty($tel) && !strpos($tel, '(')) {
                                        $tel = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $tel);
                                    }
                                    echo htmlspecialchars($tel); 
                                ?>" placeholder="(00) 00000-0000">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary-custom">
                                    <i class="fas fa-save me-2"></i>
                                    Salvar Dados Pessoais
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Alterar Senha -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">
                            <i class="fas fa-key me-2"></i>
                            Alterar Senha
                        </h5>
                    </div>
                    <div class="card-body">
                        <form id="formAlterarSenha">
                            <div class="mb-3">
                                <label for="senhaAtual" class="form-label">Senha Atual <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="senhaAtual" required>
                                    <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('senhaAtual')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="novaSenha" class="form-label">Nova Senha <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="novaSenha" required minlength="6">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('novaSenha')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Mínimo de 6 caracteres</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmarSenha" class="form-label">Confirmar Nova Senha <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmarSenha" required minlength="6">
                                    <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword('confirmarSenha')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Dica:</strong> Guarde sua nova senha em local seguro. Você continuará logado após a alteração.
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-lock me-2"></i>
                                    Alterar Senha
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/menu-responsivo.js"></script>
    <script>
        // ===== TOGGLE PASSWORD =====
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // ===== SALVAR DADOS PESSOAIS =====
        document.getElementById('formDadosPessoais').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nome = document.getElementById('nome').value;
            const email = document.getElementById('email').value;
            const telefone = document.getElementById('telefone').value;
            
            try {
                const response = await fetch('api/atualizar_perfil.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        acao: 'dados_pessoais',
                        nome: nome,
                        email: email,
                        telefone: telefone
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    console.log('Debug da atualização:', resultado.debug);
                    
                    let msg = '✅ Dados pessoais atualizados com sucesso!';
                    if (resultado.debug) {
                        msg += `<br><small>Telefone salvo: ${resultado.debug.telefone_salvo || 'vazio'}</small>`;
                    }
                    mostrarMensagem(msg, 'success');
                    
                    // Atualizar nome no header e recarregar após 2 segundos
                    setTimeout(() => {
                        location.reload(true); // Force reload
                    }, 2000);
                } else {
                    mostrarMensagem('❌ Erro: ' + (resultado.mensagem || 'Erro desconhecido'), 'danger');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarMensagem('❌ Erro ao salvar dados pessoais!', 'danger');
            }
        });
        
        // ===== ALTERAR SENHA =====
        document.getElementById('formAlterarSenha').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const senhaAtual = document.getElementById('senhaAtual').value;
            const novaSenha = document.getElementById('novaSenha').value;
            const confirmarSenha = document.getElementById('confirmarSenha').value;
            
            // Validar senhas
            if (novaSenha !== confirmarSenha) {
                mostrarMensagem('❌ As senhas não conferem!', 'danger');
                return;
            }
            
            if (novaSenha.length < 6) {
                mostrarMensagem('❌ A senha deve ter no mínimo 6 caracteres!', 'danger');
                return;
            }
            
            try {
                const response = await fetch('api/atualizar_perfil.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        acao: 'alterar_senha',
                        senha_atual: senhaAtual,
                        nova_senha: novaSenha
                    })
                });
                
                const resultado = await response.json();
                
                if (resultado.sucesso) {
                    mostrarMensagem('✅ Senha alterada com sucesso! Você continua logado no sistema.', 'success');
                    
                    // Limpar formulário
                    document.getElementById('formAlterarSenha').reset();
                } else {
                    mostrarMensagem('❌ Erro: ' + (resultado.mensagem || 'Erro desconhecido'), 'danger');
                }
            } catch (error) {
                console.error('Erro:', error);
                mostrarMensagem('❌ Erro ao alterar senha!', 'danger');
            }
        });
        
        // ===== MOSTRAR MENSAGEM =====
        function mostrarMensagem(mensagem, tipo) {
            const container = document.getElementById('mensagemFeedback');
            container.innerHTML = `
                <div class="alert alert-${tipo} alert-dismissible fade show" role="alert">
                    ${mensagem}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            // Scroll para o topo
            window.scrollTo({ top: 0, behavior: 'smooth' });
            
            // Auto-fechar após 5 segundos
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        container.innerHTML = '';
                    }, 150);
                }
            }, 5000);
        }
        
        // Máscara para telefone
        document.getElementById('telefone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/^(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
    </script>
</body>
</html>

