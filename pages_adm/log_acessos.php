<?php
require_once __DIR__ . '/includes/adm_auth.php';

$page_title = 'Log de acessos';

// Parâmetros de filtro e paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Filtros
$filtro_empresa = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : null;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$filtro_status = isset($_GET['status']) ? $_GET['status'] : null;
$data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;

// Se veio via iframe, aplicar filtro automaticamente
$is_iframe = isset($_GET['empresa_id']) || (isset($_SERVER['HTTP_SEC_FETCH_DEST']) && $_SERVER['HTTP_SEC_FETCH_DEST'] === 'iframe');

// Construir query com filtros
$where = [];
$params = [];

if ($filtro_empresa) {
    $where[] = "l.empresa_id = ?";
    $params[] = $filtro_empresa;
}

if ($filtro_tipo) {
    $where[] = "l.tipo_acesso = ?";
    $params[] = $filtro_tipo;
}

if ($filtro_status) {
    $where[] = "l.status = ?";
    $params[] = $filtro_status;
}

if ($data_inicio) {
    $where[] = "DATE(l.data_acesso) >= ?";
    $params[] = $data_inicio;
}

if ($data_fim) {
    $where[] = "DATE(l.data_acesso) <= ?";
    $params[] = $data_fim;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Contar total de registros
try {
    $count_sql = "SELECT COUNT(*) as total FROM log_acessos l $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch()['total'];
    $total_pages = ceil($total / $limit);

    // Buscar logs
    $sql = "SELECT l.*, 
                   u.nome as usuario_nome, 
                   u.email as usuario_email,
                   e.razao_social as empresa_nome,
                   DATE_FORMAT(l.data_acesso, '%d/%m/%Y %H:%i:%s') as data_formatada
            FROM log_acessos l
            LEFT JOIN usuarios u ON l.usuario_id = u.id
            LEFT JOIN empresa_clientes e ON l.empresa_id = e.id
            $where_clause
            ORDER BY l.data_acesso DESC
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Buscar estatísticas gerais
    $stats_sql = "SELECT 
                    COUNT(*) as total_acessos,
                    COUNT(DISTINCT l.usuario_id) as total_usuarios,
                    COUNT(DISTINCT l.empresa_id) as total_empresas,
                    SUM(CASE WHEN l.tipo_acesso = 'login' AND l.status = 'sucesso' THEN 1 ELSE 0 END) as logins_sucesso,
                    SUM(CASE WHEN l.tipo_acesso = 'tentativa_login_falha' THEN 1 ELSE 0 END) as tentativas_falha,
                    SUM(CASE WHEN l.tipo_acesso = 'logout' THEN 1 ELSE 0 END) as logouts,
                    SUM(CASE WHEN DATE(l.data_acesso) = CURDATE() THEN 1 ELSE 0 END) as acessos_hoje
                  FROM log_acessos l";
    
    $stats_stmt = $pdo->query($stats_sql);
    $estatisticas = $stats_stmt->fetch();

    // Buscar empresas para o filtro
    $empresas_stmt = $pdo->query("SELECT id, razao_social FROM empresa_clientes ORDER BY razao_social");
    $empresas = $empresas_stmt->fetchAll();

} catch (PDOException $e) {
    error_log('log_acessos load: ' . $e->getMessage());
    $mensagem = "Erro ao carregar logs.";
    $tipo_mensagem = "error";
    $logs = [];
    $total = 0;
    $total_pages = 0;
    $estatisticas = [
        'total_acessos' => 0,
        'total_usuarios' => 0,
        'total_empresas' => 0,
        'logins_sucesso' => 0,
        'tentativas_falha' => 0,
        'logouts' => 0,
        'acessos_hoje' => 0
    ];
    $empresas = [];
}

$adm_iframe_mode = !empty($is_iframe);
$adm_extra_head = <<<'HTML'
<style>
.adm-log .table-container { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto; }
.adm-log table { width: 100%; border-collapse: collapse; min-width: 1000px; }
.adm-log th, .adm-log td { padding: 12px 15px; text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-color); }
.adm-log th { background: var(--bg-tertiary); font-weight: 600; position: sticky; top: 0; }
.adm-log .badge { padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; display: inline-block; color: #fff; }
.adm-log .badge-success { background: #28a745; } .adm-log .badge-danger { background: #dc3545; }
.adm-log .badge-info { background: #17a2b8; } .adm-log .badge-warning { background: #ffc107; color: #212529; }
.adm-log .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 1rem; padding: 1rem; flex-wrap: wrap; }
.adm-log .pagination a, .adm-log .pagination span { padding: 8px 12px; border-radius: 8px; text-decoration: none; color: var(--text-color); background: var(--bg-secondary); border: 1px solid var(--border-color); }
.adm-log .pagination .current { background: var(--accent-primary,#3b82f6); color: #fff; border-color: transparent; }
.adm-log .text-small { font-size: 0.85rem; color: var(--text-secondary); }
</style>
HTML;

require_once __DIR__ . '/includes/layout_start.php';
?>
<div class="adm-log adm-cc-page-stack">
        <?php if ($is_iframe && $filtro_empresa): ?>
        <p class="text-small">Filtrado para empresa específica</p>
        <?php endif; ?>

        <?php if (isset($mensagem)): ?>
            <div class="alert alert-<?php echo $tipo_mensagem; ?>">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <section class="adm-cc-kpi-band">
            <h2 class="adm-cc-section__title">Resumo</h2>
            <div class="adm-cc-kpi-grid">
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Total de acessos</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['total_acessos']); ?></div></div>
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Logins com sucesso</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['logins_sucesso']); ?></div></div>
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Tentativas falhas</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['tentativas_falha']); ?></div></div>
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Acessos hoje</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['acessos_hoje']); ?></div></div>
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Total de usuários</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['total_usuarios']); ?></div></div>
                <div class="adm-cc-kpi"><div class="adm-cc-kpi__label">Total de empresas</div><div class="adm-cc-kpi__value"><?php echo number_format($estatisticas['total_empresas']); ?></div></div>
            </div>
        </section>

        <?php if (!$is_iframe): ?>
        <form method="GET" action="" class="adm-cc-filters" id="adm-log-filters-form">
            <select name="empresa_id" aria-label="Empresa">
                <option value="">Todas empresas</option>
                <?php foreach ($empresas as $empresa): ?>
                <option value="<?php echo (int) $empresa['id']; ?>" <?php echo ($filtro_empresa == $empresa['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($empresa['razao_social'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select name="tipo" aria-label="Tipo de acesso">
                <option value="">Todos tipos</option>
                <option value="login" <?php echo ($filtro_tipo === 'login') ? 'selected' : ''; ?>>Login</option>
                <option value="logout" <?php echo ($filtro_tipo === 'logout') ? 'selected' : ''; ?>>Logout</option>
                <option value="tentativa_login_falha" <?php echo ($filtro_tipo === 'tentativa_login_falha') ? 'selected' : ''; ?>>Tentativa falha</option>
                <option value="sessao_expirada" <?php echo ($filtro_tipo === 'sessao_expirada') ? 'selected' : ''; ?>>Sessão expirada</option>
            </select>
            <select name="status" aria-label="Status">
                <option value="">Todos status</option>
                <option value="sucesso" <?php echo ($filtro_status === 'sucesso') ? 'selected' : ''; ?>>Sucesso</option>
                <option value="falha" <?php echo ($filtro_status === 'falha') ? 'selected' : ''; ?>>Falha</option>
            </select>
            <input type="date" name="data_inicio" value="<?php echo htmlspecialchars((string) $data_inicio, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Data início" title="Data início">
            <input type="date" name="data_fim" value="<?php echo htmlspecialchars((string) $data_fim, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Data fim" title="Data fim">
            <?php if ($filtro_empresa || $filtro_tipo || $filtro_status || $data_inicio || $data_fim): ?>
            <a href="log_acessos.php" class="adm-cc-filters__clear" title="Limpar filtros"><i class="fas fa-xmark" aria-hidden="true"></i> Limpar</a>
            <?php endif; ?>
            <span class="adm-cc-filters__meta"><?php echo number_format($total); ?> registro(s)</span>
        </form>
        <?php endif; ?>

        <!-- Tabela de Logs -->
        <div class="adm-panel">
            <div class="adm-panel__head"><h2>Registros</h2></div>
            <div class="adm-panel__body table-container">
            <table>
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Empresa</th>
                        <th>Usuário</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>IP</th>
                        <th>Descrição</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px; color: #666;">
                                <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                                Nenhum log encontrado
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['data_formatada']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($log['empresa_nome'] ?? 'N/A'); ?>
                                    <br><span class="text-small">ID: <?php echo $log['empresa_id']; ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log['usuario_nome'] ?? 'N/A'); ?>
                                    <?php if ($log['usuario_email']): ?>
                                        <br><span class="text-small"><?php echo htmlspecialchars($log['usuario_email']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $tipo_badges = [
                                        'login' => '<span class="badge badge-info">Login</span>',
                                        'logout' => '<span class="badge badge-warning">Logout</span>',
                                        'tentativa_login_falha' => '<span class="badge badge-danger">Tentativa Falha</span>',
                                        'sessao_expirada' => '<span class="badge badge-warning">Sessão Expirada</span>'
                                    ];
                                    echo $tipo_badges[$log['tipo_acesso']] ?? '<span class="badge">' . htmlspecialchars($log['tipo_acesso']) . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php if ($log['status'] == 'sucesso'): ?>
                                        <span class="badge badge-success">Sucesso</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Falha</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-small"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span></td>
                                <td><span class="text-small"><?php echo htmlspecialchars($log['descricao'] ?? '-'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_filter(['empresa_id' => $filtro_empresa, 'tipo' => $filtro_tipo, 'status' => $filtro_status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])); ?>">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Anterior</span>
                <?php endif; ?>

                <span>Página <?php echo $page; ?> de <?php echo $total_pages; ?> (<?php echo number_format($total); ?> registros)</span>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_filter(['empresa_id' => $filtro_empresa, 'tipo' => $filtro_tipo, 'status' => $filtro_status, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim])); ?>">
                        Próxima <i class="fas fa-chevron-right"></i>
                    </a>
                <?php else: ?>
                    <span class="disabled">Próxima <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
</div>
<?php
$adm_extra_scripts = <<<'JS'
<script>
(function () {
    var form = document.getElementById('adm-log-filters-form');
    if (!form) return;
    form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
        el.addEventListener('change', function () { form.submit(); });
    });
})();
</script>
JS;
require_once __DIR__ . '/includes/layout_end.php';

