[← Índice](README.md)

# A2 · Manual do Desenvolvedor

Como estender o sistema **seguindo os padrões existentes**. Base: [13-modulos/padroes-ui.md](13-modulos/padroes-ui.md),
[07-apis.md](07-apis.md), [11-seguranca.md](11-seguranca.md).

## Regras de ouro (não negociáveis)

1. Toda query filtra `empresa_id` (`$_SESSION['empresa_id']`).
2. Prepared statements com placeholders nomeados (nunca concatenar entrada).
3. `require_authentication()` + CSRF em escrita.
4. Nunca vazar `$e->getMessage()`; nunca fabricar dado no `catch`.
5. Validar com `php -l` e `node --check`; testar o fluxo afetado.

## Nova página (tela do gestor)

```php
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
configure_session(); session_start(); require_authentication();
$page_title = 'Minha Tela';
?>
<!-- head: FontAwesome + styles/theme/responsive + fornc-modern-page + sf-premium + sf_page_help_css -->
<body class="sf-premium">
  <div class="app-container">
    <?php include '../includes/sidebar_pages.php'; ?>
    <div class="main-content">
      <?php include '../includes/header.php'; ?>
      <div class="dashboard-content"><!-- fornc-toolbar + fornc-table + pagination --></div>
      <?php include '../includes/footer.php'; ?>
    </div>
  </div>
  <script src="jquery"></script><script src="../js/theme.js"></script><script src="../js/sidebar.js"></script>
  <?php $sf_help_key='minha_tela'; include '../includes/sf_page_help_bundle.php'; sf_page_help_js('..'); ?>
</body>
```
Depois: adicionar item no menu em `includes/sidebar_pages.php` (com `if (can_...())` se
tiver permissão), e conteúdo de ajuda em `includes/page_help/*.php`.

## Nova API

```php
<?php
require_once '../includes/config.php'; require_once '../includes/functions.php';
require_once '../includes/csrf.php';
configure_session(); session_start(); require_authentication();
header('Content-Type: application/json');
$empresa_id = (int)($_SESSION['empresa_id'] ?? 0);
try {
    $conn = getConnection();
    $action = $_GET['action'] ?? $_POST['action'] ?? 'list';
    if ($action === 'list') {
        $st = $conn->prepare('SELECT * FROM minha_tabela WHERE empresa_id=:e ORDER BY id DESC');
        $st->execute([':e'=>$empresa_id]);
        echo json_encode(['success'=>true,'itens'=>$st->fetchAll(PDO::FETCH_ASSOC)]); return;
    }
    if ($action === 'save') {
        csrf_token_validate($_POST['csrf_token'] ?? '');
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($nome==='') throw new InvalidArgumentException('Informe o nome.');
        // INSERT/UPDATE com :e ...
        echo json_encode(['success'=>true]); return;
    }
} catch (InvalidArgumentException $e) {
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('minha_api: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Erro ao processar.']);
}
```

## Novo CRUD (checklist)
- [ ] Migração em `sql/minha_tabela.sql` (com `empresa_id`, índices, `created_at/updated_at`).
- [ ] API `api/minha_tabela.php` (list/get/save/delete + CSRF + `empresa_id`).
- [ ] Página `pages/minha_tabela.php` (padrão UI acima).
- [ ] Item no menu + permissão (`can_...`).
- [ ] Ajuda em `includes/page_help/`.
- [ ] `php -l`, `node --check`, teste manual.

## Novo Dashboard / Gráfico
- Endpoint de dados (JSON agregado por `empresa_id`/período, sem N+1).
- Front com **Chart.js** (padrão do Centro de Operações); *polling* se "tempo real".

## Novo Relatório
- Registrar tipo na allow-list de `includes/relatorios_helpers.php`.
- Preview/CSV/Excel/PDF via `api/auto_reports.php`; agendamento por `api/relatorios_agendar.php`.

## Novo Cron Job
- Criar `cron/meu_job.php` com cabeçalho (objetivo + frequência sugerida) e resumo no stdout.
- Registrar no Agendador (Windows) / crontab. Documentar em [10-cronjobs.md](10-cronjobs.md).

## Nova Integração / Webhook
- Segredos por **env** (`SF_*`) com fallback; nunca commitar.
- Chamadas externas com timeout; erros em `error_log`. Ver [08-webhooks.md](08-webhooks.md).

## Novo Helper / Serviço
- Colocar em `includes/` com prefixo `sf_` e responsabilidade única; reusar `getConnection()`.

## Ajuda por página (obrigatório em telas novas)
Ver [13-modulos/padroes-ui.md](13-modulos/padroes-ui.md#ajuda-por-página).
