<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_post_guard.php';
require_once __DIR__ . '/lib/adm_events.php';

$page_title = 'Roadmap';
$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!adm_csrf_valid_post()) {
        $mensagem = 'Sessão expirada. Recarregue a página.';
    } else {
        $action = (string) $_POST['action'];
        try {
            if ($action === 'add') {
                $stmt = $pdo->prepare('INSERT INTO adm_roadmap (versao, titulo, descricao, status, progresso, ordem) VALUES (?,?,?,?,?,?)');
                $stmt->execute([
                    trim((string) $_POST['versao']),
                    trim((string) $_POST['titulo']),
                    trim((string) ($_POST['descricao'] ?? '')),
                    (string) ($_POST['status'] ?? 'planejado'),
                    min(100, max(0, (int) ($_POST['progresso'] ?? 0))),
                    (int) ($_POST['ordem'] ?? 0),
                ]);
                adm_event_log($pdo, 'roadmap', 'criar', 'Item roadmap: ' . $_POST['titulo'], 'info');
                $mensagem = 'Item adicionado.';
            } elseif ($action === 'edit') {
                $id = (int) $_POST['id'];
                $stmt = $pdo->prepare('UPDATE adm_roadmap SET versao=?, titulo=?, descricao=?, status=?, progresso=?, ordem=? WHERE id=?');
                $stmt->execute([
                    trim((string) $_POST['versao']),
                    trim((string) $_POST['titulo']),
                    trim((string) ($_POST['descricao'] ?? '')),
                    (string) ($_POST['status'] ?? 'planejado'),
                    min(100, max(0, (int) ($_POST['progresso'] ?? 0))),
                    (int) ($_POST['ordem'] ?? 0),
                    $id,
                ]);
                adm_event_log($pdo, 'roadmap', 'atualizar', 'Roadmap #' . $id, 'info');
                $mensagem = 'Item atualizado.';
            } elseif ($action === 'delete') {
                $id = (int) $_POST['id'];
                $pdo->prepare('DELETE FROM adm_roadmap WHERE id = ?')->execute([$id]);
                adm_event_log($pdo, 'roadmap', 'excluir', 'Roadmap #' . $id, 'aviso');
                $mensagem = 'Item removido.';
            }
        } catch (Throwable $e) {
            error_log('roadmap: ' . $e->getMessage());
            $mensagem = 'Erro ao processar.';
        }
    }
}

$items = $pdo->query('SELECT * FROM adm_roadmap ORDER BY ordem ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/layout_start.php';
$cc_title = 'Roadmap do produto';
$cc_subtitle = 'Versões · progresso · planejamento';
include __DIR__ . '/components/page_header.php';
?>
<?php if ($mensagem): ?><p class="adm-cc-support-banner"><?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

<div class="adm-cc-page-stack">
<div class="adm-panel">
    <div class="adm-panel__head"><h2>Novo item</h2></div>
    <div class="adm-panel__body">
        <form method="post" class="adm-cc-filters adm-cc-filters--flat">
            <input type="hidden" name="action" value="add">
            <?php echo adm_csrf_field(); ?>
            <input type="text" name="versao" placeholder="Versão (ex. 2.8.0)" required style="min-width:100px">
            <input type="text" name="titulo" placeholder="Título" required style="min-width:160px">
            <input type="text" name="descricao" placeholder="Descrição" style="min-width:200px;flex:1">
            <select name="status">
                <option value="planejado">Planejado</option>
                <option value="desenvolvimento">Desenvolvimento</option>
                <option value="beta">Beta</option>
                <option value="concluido">Concluído</option>
            </select>
            <input type="number" name="progresso" min="0" max="100" value="0" placeholder="%" style="width:70px">
            <input type="number" name="ordem" value="0" placeholder="Ordem" style="width:70px">
            <button type="submit" class="adm-cc-btn adm-cc-btn--primary">Adicionar</button>
        </form>
    </div>
</div>

<div class="adm-panel">
    <div class="adm-panel__body">
        <?php if (!$items): ?>
        <div class="adm-cc-empty">Nenhum item no roadmap.</div>
        <?php else: foreach ($items as $item): ?>
        <form method="post" style="margin-bottom:1.25rem;padding-bottom:1rem;border-bottom:1px solid var(--border-color)">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
            <?php echo adm_csrf_field(); ?>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;margin-bottom:0.5rem">
                <input type="text" name="versao" value="<?php echo htmlspecialchars($item['versao'], ENT_QUOTES, 'UTF-8'); ?>" style="width:90px">
                <input type="text" name="titulo" value="<?php echo htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8'); ?>" style="flex:1;min-width:140px">
                <select name="status">
                    <?php foreach (['planejado', 'desenvolvimento', 'beta', 'concluido'] as $st): ?>
                    <option value="<?php echo $st; ?>"<?php echo $item['status'] === $st ? ' selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="progresso" min="0" max="100" value="<?php echo (int) $item['progresso']; ?>" style="width:60px">%
                <input type="number" name="ordem" value="<?php echo (int) $item['ordem']; ?>" style="width:60px" title="Ordem">
                <button type="submit" class="adm-cc-btn">Salvar</button>
            </div>
            <textarea name="descricao" rows="2" style="width:100%;margin-bottom:0.5rem"><?php echo htmlspecialchars((string) ($item['descricao'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            <div style="background:var(--border-color);border-radius:999px;height:8px"><div style="width:<?php echo (int) $item['progresso']; ?>%;background:var(--accent-primary,#3b82f6);height:100%;border-radius:999px"></div></div>
        </form>
        <form method="post" style="margin-top:-0.75rem;margin-bottom:1.5rem" onsubmit="return confirm('Excluir este item?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
            <?php echo adm_csrf_field(); ?>
            <button type="submit" class="adm-cc-btn" style="font-size:0.8rem;opacity:0.85">Excluir</button>
        </form>
        <?php endforeach; endif; ?>
    </div>
</div>
</div>
<?php require_once __DIR__ . '/includes/layout_end.php'; ?>
