<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

configure_session();
session_start();

// Verificar se está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Verificar se tem acesso a todas as empresas
if (empty($_SESSION['acesso_todas_empresas']) || $_SESSION['acesso_todas_empresas'] !== true) {
    // Se não tem acesso global, redirecionar para o index
    header('Location: index.php');
    exit;
}

// Se uma empresa foi selecionada via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['empresa_id'])) {
    $empresa_id = (int)$_POST['empresa_id'];
    
    // Verificar se a empresa existe e está ativa
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT id, razao_social FROM empresa_clientes WHERE id = ? AND status = 'ativo'");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($empresa) {
        // Atualizar sessão com a empresa selecionada
        $_SESSION['empresa_id'] = $empresa_id;
        $_SESSION['empresa_nome'] = $empresa['razao_social'];
        
        // Registrar log
        registrarLogAcesso($_SESSION['usuario_id'], $empresa_id, 'trocar_empresa', 'sucesso', 'Empresa alterada para: ' . $empresa['razao_social']);
        
        // Redirecionar para o index
        header('Location: index.php');
        exit;
    } else {
        $error = "Empresa não encontrada ou inativa.";
    }
}

// Buscar todas as empresas ativas
$conn = getConnection();
$stmt = $conn->query("SELECT id, razao_social, nome_fantasia FROM empresa_clientes WHERE status = 'ativo' ORDER BY razao_social");
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Selecionar Empresa';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Gestão de Frotas</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/theme.css">
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="logo.png">

    <style>
        body {
            background: #101522;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .login-container {
            width: 100%;
            max-width: 500px;
            background: #181f2f;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            padding: 40px 32px 32px 32px;
            margin: 40px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        .login-logo {
            width: 90px;
            margin-bottom: 18px;
        }
        .login-title {
            color: #3fa6ff;
            font-size: 1.7em;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 1px;
            text-align: center;
        }
        .login-greeting {
            color: #b0b8c9;
            font-size: 1.1em;
            margin-bottom: 28px;
            text-align: center;
        }
        .user-info {
            width: 100%;
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #26304a;
        }
        .user-info p {
            margin: 5px 0;
            color: #b0b8c9;
            font-size: 0.95em;
        }
        .user-info strong {
            color: #3fa6ff;
        }
        .empresa-list {
            width: 100%;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .empresa-item {
            border: 1px solid #26304a;
            border-radius: 8px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.3s;
            background: #141a29;
        }
        .empresa-item:hover {
            border-color: #3fa6ff;
            background: #1a2332;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(63,166,255,0.15);
        }
        .empresa-item.selected {
            border-color: #3fa6ff;
            background: linear-gradient(90deg, #3fa6ff 0%, #1e7ecb 100%);
            color: #fff;
        }
        .empresa-item h3 {
            margin: 0 0 4px 0;
            font-size: 1em;
            font-weight: 600;
            color: #eaf1fb;
        }
        .empresa-item.selected h3 {
            color: #fff;
        }
        .empresa-item p {
            margin: 0;
            font-size: 0.9em;
            opacity: 0.8;
            color: #b0b8c9;
        }
        .empresa-item.selected p {
            color: #fff;
            opacity: 1;
        }
        .btn-selecionar {
            width: 100%;
            padding: 12px;
            background: linear-gradient(90deg, #3fa6ff 0%, #1e7ecb 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(63,166,255,0.10);
            transition: background 0.2s, box-shadow 0.2s;
        }
        .btn-selecionar:hover:not(:disabled) {
            background: linear-gradient(90deg, #1e7ecb 0%, #3fa6ff 100%);
            box-shadow: 0 4px 16px rgba(63,166,255,0.18);
        }
        .btn-selecionar:disabled {
            background: #26304a;
            color: #6b7280;
            cursor: not-allowed;
            box-shadow: none;
        }
        .error-message {
            color: #fff;
            background: #dc3545;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 18px;
            width: 100%;
            text-align: center;
            font-size: 1em;
        }
        .login-background-texts {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }
        .bg-phrase {
            position: absolute;
            color: #3fa6ff;
            opacity: 0.18;
            font-weight: 700;
            font-family: 'Segoe UI', Arial, sans-serif;
            text-shadow: 0 2px 12px #000, 0 1px 0 #fff1;
            user-select: none;
            line-height: 1.3;
            max-width: 320px;
            transition: opacity 0.3s;
        }
        .phrase1 {
            top: 7%; left: 7%; font-size: 1.3em; color: #3fa6ff; opacity: 0.22;
        }
        .phrase2 {
            top: 18%; right: 8%; font-size: 1.1em; color: #fff; opacity: 0.16;
        }
        .phrase3 {
            bottom: 10%; left: 6%; font-size: 1.05em; color: #3fa6ff; opacity: 0.19;
        }
        .phrase4 {
            top: 38%; left: 2%; font-size: 1.7em; color: #1e7ecb; opacity: 0.13;
        }
        .phrase5 {
            bottom: 18%; right: 7%; font-size: 1.3em; color: #3fa6ff; opacity: 0.18;
        }
        .phrase6 {
            top: 60%; right: 3%; font-size: 1.2em; color: #fff; opacity: 0.15;
        }
        .phrase7 {
            bottom: 5%; left: 40%; font-size: 1.1em; color: #3fa6ff; opacity: 0.16;
        }
        .phrase8 {
            top: 90%; right: 18%; font-size: 1.1em; color: #1e7ecb; opacity: 0.13;
        }
        .phrase9 {
            top: 10%; right: 35%; font-size: 1.05em; color: #fff; opacity: 0.13;
        }
        .phrase10 {
            top: 25%; left: 8%; font-size: 1.2em; color: #3fa6ff; opacity: 0.17;
        }
        .phrase11 {
            top: 45%; right: 5%; font-size: 1.1em; color: #fff; opacity: 0.15;
        }
        .phrase12 {
            bottom: 35%; left: 4%; font-size: 1.15em; color: #1e7ecb; opacity: 0.14;
        }
        .phrase13 {
            top: 70%; left: 12%; font-size: 1.0em; color: #3fa6ff; opacity: 0.16;
        }
        .phrase14 {
            top: 32%; right: 15%; font-size: 1.05em; color: #fff; opacity: 0.13;
        }
        .phrase15 {
            bottom: 25%; right: 20%; font-size: 1.1em; color: #3fa6ff; opacity: 0.18;
        }
        .phrase16 {
            top: 15%; left: 25%; font-size: 1.0em; color: #1e7ecb; opacity: 0.15;
        }
        .phrase17 {
            bottom: 50%; right: 65%; font-size: 1.05em; color: #fff; opacity: 0.14;
        }
        .phrase18 {
            top: 85%; left: 25%; font-size: 1.1em; color: #3fa6ff; opacity: 0.17;
        }
        
        /* Media query para tablets/iPad (768px - 1024px) */
        @media (min-width: 701px) and (max-width: 1024px) {
            .bg-phrase { 
                font-size: 0.8em !important; 
                max-width: 180px; 
                opacity: 0.1 !important;
            }
            .login-background-texts { 
                position: fixed; 
                inset: 0;
                z-index: 0;
            }
            .login-container {
                position: relative;
                z-index: 1;
            }
            .phrase4, .phrase7, .phrase9, .phrase14, .phrase16, .phrase17 {
                display: none !important;
            }
            .phrase1 { top: 5% !important; left: 5% !important; }
            .phrase2 { top: 10% !important; right: 5% !important; }
            .phrase3 { bottom: 5% !important; left: 5% !important; }
            .phrase5 { bottom: 10% !important; right: 5% !important; }
            .phrase6 { top: 15% !important; right: 3% !important; }
            .phrase8 { display: none !important; }
            .phrase10 { top: 8% !important; left: 6% !important; }
            .phrase11 { top: 20% !important; right: 6% !important; }
            .phrase12 { bottom: 8% !important; left: 6% !important; }
            .phrase13 { top: 25% !important; left: 7% !important; }
            .phrase15 { bottom: 12% !important; right: 8% !important; }
            .phrase18 { bottom: 8% !important; left: 30% !important; }
        }
        
        @media (max-width: 700px) {
            .bg-phrase { 
                font-size: 0.75em !important; 
                max-width: 140px; 
                opacity: 0.1 !important;
            }
            .login-background-texts { 
                position: fixed; 
                inset: 0;
                z-index: 0;
            }
            .login-container {
                position: relative;
                z-index: 1;
            }
            .phrase1 { top: 2% !important; left: 2% !important; }
            .phrase2 { top: 5% !important; right: 2% !important; }
            .phrase3 { bottom: 2% !important; left: 2% !important; }
            .phrase4 { top: 8% !important; left: 1% !important; font-size: 0.9em !important; }
            .phrase5 { bottom: 5% !important; right: 2% !important; }
            .phrase6 { top: 12% !important; right: 1% !important; }
            .phrase7 { bottom: 8% !important; left: 50% !important; transform: translateX(-50%); }
            .phrase8 { display: none; }
            .phrase9 { top: 3% !important; right: 30% !important; }
            .phrase10 { top: 6% !important; left: 3% !important; }
            .phrase11 { top: 10% !important; right: 3% !important; }
            .phrase12 { bottom: 3% !important; left: 3% !important; }
            .phrase13 { top: 15% !important; left: 4% !important; }
            .phrase14 { top: 9% !important; right: 8% !important; }
            .phrase15 { bottom: 6% !important; right: 10% !important; }
            .phrase16 { top: 4% !important; left: 20% !important; }
            .phrase17 { display: none; }
            .phrase18 { bottom: 4% !important; left: 20% !important; }
        }
        
        @media (max-width: 500px) {
            .bg-phrase { 
                font-size: 0.65em !important; 
                max-width: 120px;
                opacity: 0.08 !important;
            }
            .phrase4, .phrase6, .phrase9, .phrase10, .phrase11, .phrase13, .phrase14, .phrase15, .phrase16, .phrase18 {
                display: none;
            }
            .login-container {
                padding: 24px 8px 18px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="login-background-texts">
        <div class="bg-phrase phrase1">Simples para quem dirige.<br>Poderoso para quem gerencia.</div>
        <div class="bg-phrase phrase2">Chega de planilhas!<br>Gestão de frotas simples, visual e eficiente.</div>
        <div class="bg-phrase phrase3">Ideal para pequenas e médias empresas.<br>Organize veículos, motoristas, rotas e despesas sem complicações.</div>
        <div class="bg-phrase phrase4">Mais Segurança</div>
        <div class="bg-phrase phrase5">Aumente a Eficiência</div>
        <div class="bg-phrase phrase6">Reduza Custos</div>
        <div class="bg-phrase phrase7">Tempo economizado</div>
        <div class="bg-phrase phrase8">Redução de custos</div>
        <div class="bg-phrase phrase9">Aumento médio de eficiência</div>
        <div class="bg-phrase phrase10">Transforme dados em decisões inteligentes.</div>
        <div class="bg-phrase phrase11">Tenha sua frota sob controle em qualquer lugar.</div>
        <div class="bg-phrase phrase12">Automatize processos e reduza retrabalho.</div>
        <div class="bg-phrase phrase13">Mais tempo para focar no crescimento do seu negócio.</div>
        <div class="bg-phrase phrase14">Relatórios claros e completos em poucos cliques.</div>
        <div class="bg-phrase phrase15">Do cadastro ao controle financeiro, tudo em um só sistema.</div>
        <div class="bg-phrase phrase16">Tecnologia feita para simplificar a gestão de frotas.</div>
        <div class="bg-phrase phrase17">Gestão inteligente que cresce junto com a sua empresa.</div>
        <div class="bg-phrase phrase18">Monitoramento ágil, decisões rápidas.</div>
    </div>
    
    <div class="login-container">
        <img src="logo.png" alt="Logo" class="login-logo">
        <div class="login-title">FROTEC</div>
        <div class="login-greeting">Selecionar Empresa</div>

        <div class="user-info">
            <p><strong>Usuário:</strong> <?php echo htmlspecialchars($_SESSION['nome']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($_SESSION['email']); ?></p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" id="empresaForm" style="width: 100%;">
            <input type="hidden" name="empresa_id" id="empresa_id_selected">
            
            <div class="empresa-list">
                <?php if (empty($empresas)): ?>
                    <p style="text-align: center; color: #b0b8c9; padding: 20px;">Nenhuma empresa ativa encontrada.</p>
                <?php else: ?>
                    <?php foreach ($empresas as $empresa): ?>
                        <div class="empresa-item" onclick="selecionarEmpresa(<?php echo $empresa['id']; ?>, this)">
                            <h3><?php echo htmlspecialchars($empresa['razao_social']); ?></h3>
                            <?php if (!empty($empresa['nome_fantasia'])): ?>
                                <p><?php echo htmlspecialchars($empresa['nome_fantasia']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="btn-selecionar" id="btnSelecionar" disabled>
                Acessar Empresa Selecionada
            </button>
        </form>
    </div>
    
    <script>
        function selecionarEmpresa(empresaId, element) {
            // Remover seleção anterior
            document.querySelectorAll('.empresa-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Selecionar item atual
            element.classList.add('selected');
            
            // Definir empresa selecionada
            document.getElementById('empresa_id_selected').value = empresaId;
            
            // Habilitar botão
            document.getElementById('btnSelecionar').disabled = false;
        }
        
        // Prevenir múltiplos envios do formulário
        document.getElementById('empresaForm').addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            submitButton.disabled = true;
            submitButton.textContent = 'Acessando...';
        });
    </script>
</body>
</html>
