<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/csrf.php';

configure_session();
session_start();

// Verificar se está logado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

// Verificar se tem acesso a todas as empresas
if (empty($_SESSION['acesso_todas_empresas']) || $_SESSION['acesso_todas_empresas'] !== true) {
    header('Location: index.php');
    exit;
}

$error = '';

// Se uma empresa foi selecionada via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!csrf_token_validate(is_string($csrf) ? $csrf : null)) {
        $error = 'Sessão expirada ou token inválido. Recarregue a página e tente novamente.';
    } elseif (isset($_POST['empresa_id'])) {
        $empresa_id = (int) $_POST['empresa_id'];

        // Verificar se a empresa existe e está ativa
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT id, razao_social FROM empresa_clientes WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$empresa_id]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($empresa) {
            $_SESSION['empresa_id'] = $empresa_id;
            $_SESSION['empresa_nome'] = $empresa['razao_social'];

            require_once __DIR__ . '/includes/notificacoes_helpers.php';
            sf_notif_flush_novo_acesso_sessao($conn, $empresa_id);

            registrarLogAcesso($_SESSION['usuario_id'], $empresa_id, 'trocar_empresa', 'sucesso', 'Empresa alterada para: ' . $empresa['razao_social']);

            header('Location: index.php');
            exit;
        }

        $error = 'Empresa não encontrada ou inativa.';
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
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> — Frotec</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/login-frotec.css?v=<?php echo htmlspecialchars(sf_asset_v(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="login-page login-page--empresa">
    <div class="login-page__bg" aria-hidden="true"></div>

    <div class="login-page__quotes" aria-hidden="true">
        <div class="login-bg-phrase login-bg-phrase--1">Simples para quem dirige.<br>Poderoso para quem gerencia.</div>
        <div class="login-bg-phrase login-bg-phrase--2">Chega de planilhas!<br>Gestão de frotas simples, visual e eficiente.</div>
        <div class="login-bg-phrase login-bg-phrase--3">Ideal para pequenas e médias empresas.<br>Organize veículos, motoristas, rotas e despesas sem complicações.</div>
        <div class="login-bg-phrase login-bg-phrase--4">Mais Segurança</div>
        <div class="login-bg-phrase login-bg-phrase--5">Aumente a Eficiência</div>
        <div class="login-bg-phrase login-bg-phrase--6">Reduza Custos</div>
        <div class="login-bg-phrase login-bg-phrase--7">Tempo economizado</div>
        <div class="login-bg-phrase login-bg-phrase--8">Redução de custos</div>
        <div class="login-bg-phrase login-bg-phrase--9">Aumento médio de eficiência</div>
        <div class="login-bg-phrase login-bg-phrase--10">Transforme dados em decisões inteligentes.</div>
        <div class="login-bg-phrase login-bg-phrase--11">Tenha sua frota sob controle em qualquer lugar.</div>
        <div class="login-bg-phrase login-bg-phrase--12">Automatize processos e reduza retrabalho.</div>
        <div class="login-bg-phrase login-bg-phrase--13">Mais tempo para focar no crescimento do seu negócio.</div>
        <div class="login-bg-phrase login-bg-phrase--14">Relatórios claros e completos em poucos cliques.</div>
        <div class="login-bg-phrase login-bg-phrase--15">Do cadastro ao controle financeiro, tudo em um só sistema.</div>
        <div class="login-bg-phrase login-bg-phrase--16">Tecnologia feita para simplificar a gestão de frotas.</div>
        <div class="login-bg-phrase login-bg-phrase--17">Gestão inteligente que cresce junto com a sua empresa.</div>
        <div class="login-bg-phrase login-bg-phrase--18">Monitoramento ágil, decisões rápidas.</div>
    </div>

    <a href="#empresas-form" class="login-skip visually-hidden">Ir para o formulário</a>

    <main class="login-page__main login-page__main--empresa">
        <a href="logout.php" class="login-page__back"><i class="fas fa-right-from-bracket" aria-hidden="true"></i> Sair da conta</a>

        <div class="login-card">
            <div class="login-brand">
                <div class="login-brand__logo-wrap">
                    <img src="logo.png" alt="" class="login-brand__logo" width="90" height="90" decoding="async">
                </div>
                <h1 class="login-brand__name">Frotec</h1>
                <p class="login-brand__tagline">Selecione a empresa para continuar<?php
                    $n_emp = count($empresas);
                    if ($n_emp > 0) {
                        echo ' · ' . (int) $n_emp . ' ' . ($n_emp === 1 ? 'empresa ativa' : 'empresas ativas');
                    }
                ?></p>
            </div>

            <div class="login-user-strip">
                <p><strong>Usuário:</strong> <?php echo htmlspecialchars($_SESSION['nome'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                <p><strong>E-mail:</strong> <?php echo htmlspecialchars($_SESSION['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-alert login-alert--error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <form method="post" id="empresas-form" class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] ?? 'selecionar_empresa.php', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token_get(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="empresa_id" id="empresa_id_selected" value="">

                <div class="login-empresa-list" role="listbox" aria-label="Empresas disponíveis">
                    <?php if (empty($empresas)): ?>
                        <p class="login-empresa-empty">Nenhuma empresa ativa encontrada.</p>
                    <?php else: ?>
                        <?php foreach ($empresas as $empresa): ?>
                            <div
                                class="login-empresa-item"
                                role="option"
                                tabindex="0"
                                aria-selected="false"
                                data-empresa-id="<?php echo (int) $empresa['id']; ?>"
                                onclick="selecionarEmpresa(<?php echo (int) $empresa['id']; ?>, this)"
                                onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();selecionarEmpresa(<?php echo (int) $empresa['id']; ?>, this);}"
                            >
                                <h3><?php echo htmlspecialchars($empresa['razao_social'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php if (!empty($empresa['nome_fantasia'])): ?>
                                    <p><?php echo htmlspecialchars($empresa['nome_fantasia'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <button type="submit" class="login-submit" id="btnSelecionar" disabled>
                    Acessar empresa selecionada
                </button>
            </form>
        </div>

        <p class="login-fine">Gestão de frotas em um só lugar. Em caso de dúvida, fale com o suporte da sua empresa.</p>
    </main>

    <script>
        function selecionarEmpresa(empresaId, element) {
            document.querySelectorAll('.login-empresa-item').forEach(function (item) {
                item.classList.remove('is-selected');
                item.setAttribute('aria-selected', 'false');
            });
            element.classList.add('is-selected');
            element.setAttribute('aria-selected', 'true');
            document.getElementById('empresa_id_selected').value = String(empresaId);
            document.getElementById('btnSelecionar').disabled = false;
        }

        document.getElementById('empresas-form').addEventListener('submit', function () {
            var submitButton = this.querySelector('button[type="submit"]');
            if (!submitButton) return;
            submitButton.disabled = true;
            submitButton.textContent = 'Acessando…';
        });
    </script>
</body>
</html>
