<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db_connect.php';
configure_session();
session_start();
require_authentication();
$conn = getConnection();
$page_title = 'Planos de Manutenção';

// Se tabela não existir, redireciona para manutenções
try {
    $conn->query("SELECT 1 FROM planos_manutencao LIMIT 1");
} catch (Exception $e) {
    header('Location: manutencoes.php');
    exit;
}

// Buscar veículos, componentes e tipos para os selects
$veiculos = [];
$componentes = [];
$tipos = [];
$stmt = $conn->prepare("SELECT id, placa FROM veiculos WHERE empresa_id = ? ORDER BY placa");
$stmt->execute([$_SESSION['empresa_id']]);
$veiculos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare("SELECT id, nome FROM componentes_manutencao ORDER BY nome");
$stmt->execute();
$componentes = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt = $conn->prepare("SELECT id, nome FROM tipos_manutencao ORDER BY nome");
$stmt->execute();
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <link rel="stylesheet" href="../css/maintenance.css">
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content" style="margin-left: var(--sidebar-width); min-height: 100vh; background: var(--bg-primary);">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content" style="padding: 20px;">
                <div class="dashboard-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h1><?php echo $page_title; ?></h1>
                    <div>
                        <a href="manutencoes.php" class="btn-restore-layout"><i class="fas fa-arrow-left"></i> Voltar</a>
                        <button type="button" class="btn-add-widget" id="btnNovoPlano"><i class="fas fa-plus"></i> Novo plano</button>
                    </div>
                </div>
                <p class="text-muted">Cadastre planos por veículo/componente/tipo com intervalo em km ou dias. Ao concluir uma preventiva, o sistema atualiza a última execução automaticamente.</p>
                <div class="data-table-container" style="margin-top: 16px;">
                    <table class="data-table" id="tablePlanos">
                        <thead>
                            <tr>
                                <th>Veículo</th>
                                <th>Componente</th>
                                <th>Tipo</th>
                                <th>Intervalo KM</th>
                                <th>Intervalo dias</th>
                                <th>Último km</th>
                                <th>Última data</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <p id="msgVazio" class="text-muted" style="display:none;">Nenhum plano cadastrado. Clique em "Novo plano".</p>
            </div>

            <footer class="footer">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Gestão de Frotas - Todos os direitos reservados.</p>
            </footer>
        </div>
    </div>

    <div class="modal" id="modalPlano">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalPlanoTitulo">Novo plano</h2>
                <button type="button" class="close-modal" id="btnFecharModalPlano" aria-label="Fechar"><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <form id="formPlano">
                    <input type="hidden" id="plano_id" name="id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="plano_veiculo_id">Veículo *</label>
                            <select id="plano_veiculo_id" name="veiculo_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($veiculos as $v): ?>
                                <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($v['placa']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="plano_componente_id">Componente *</label>
                            <select id="plano_componente_id" name="componente_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($componentes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="plano_tipo_manutencao_id">Tipo *</label>
                            <select id="plano_tipo_manutencao_id" name="tipo_manutencao_id" required>
                                <option value="">Selecione</option>
                                <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="plano_intervalo_km">Intervalo (km)</label>
                            <input type="number" id="plano_intervalo_km" name="intervalo_km" min="0" placeholder="Ex: 10000">
                        </div>
                        <div class="form-group">
                            <label for="plano_intervalo_dias">Intervalo (dias)</label>
                            <input type="number" id="plano_intervalo_dias" name="intervalo_dias" min="0" placeholder="Ex: 180">
                        </div>
                        <div class="form-group">
                            <label for="plano_ultimo_km">Último km</label>
                            <input type="number" id="plano_ultimo_km" name="ultimo_km" min="0">
                        </div>
                        <div class="form-group">
                            <label for="plano_ultima_data">Última data</label>
                            <input type="date" id="plano_ultima_data" name="ultima_data">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" id="btnCancelarPlano">Cancelar</button>
                <button type="button" class="btn-primary" id="btnSalvarPlano"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </div>
    </div>

    <script>
(function() {
    const tableBody = document.querySelector('#tablePlanos tbody');
    const msgVazio = document.getElementById('msgVazio');
    const modal = document.getElementById('modalPlano');
    const form = document.getElementById('formPlano');

    function listar() {
        fetch('../api/planos_manutencao.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) { console.error(data.error); return; }
                const rows = data.data || [];
                tableBody.innerHTML = rows.map(p => `
                    <tr>
                        <td>${escapeHtml(p.placa)}</td>
                        <td>${escapeHtml(p.componente_nome)}</td>
                        <td>${escapeHtml(p.tipo_nome)}</td>
                        <td>${p.intervalo_km != null ? p.intervalo_km : '-'}</td>
                        <td>${p.intervalo_dias != null ? p.intervalo_dias : '-'}</td>
                        <td>${p.ultimo_km != null ? p.ultimo_km : '-'}</td>
                        <td>${p.ultima_data ? p.ultima_data : '-'}</td>
                        <td>
                            <button type="button" class="btn-icon edit-btn" data-id="${p.id}" title="Editar"><i class="fas fa-edit"></i></button>
                            <button type="button" class="btn-icon delete-btn" data-id="${p.id}" title="Desativar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `).join('');
                msgVazio.style.display = rows.length ? 'none' : 'block';
                tableBody.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', () => editar(btn.dataset.id)));
                tableBody.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', () => excluir(btn.dataset.id)));
            });
    }

    function escapeHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function abrirNovo() {
        document.getElementById('modalPlanoTitulo').textContent = 'Novo plano';
        form.reset();
        document.getElementById('plano_id').value = '';
        modal.classList.add('active');
    }

    function editar(id) {
        fetch('../api/planos_manutencao.php', { credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                const p = (data.data || []).find(x => x.id == id);
                if (!p) return;
                document.getElementById('modalPlanoTitulo').textContent = 'Editar plano';
                document.getElementById('plano_id').value = p.id;
                document.getElementById('plano_veiculo_id').value = p.veiculo_id;
                document.getElementById('plano_componente_id').value = p.componente_id;
                document.getElementById('plano_tipo_manutencao_id').value = p.tipo_manutencao_id;
                document.getElementById('plano_intervalo_km').value = p.intervalo_km || '';
                document.getElementById('plano_intervalo_dias').value = p.intervalo_dias || '';
                document.getElementById('plano_ultimo_km').value = p.ultimo_km || '';
                document.getElementById('plano_ultima_data').value = p.ultima_data || '';
                modal.classList.add('active');
            });
    }

    function fecharModal() { modal.classList.remove('active'); }

    function salvar() {
        const id = document.getElementById('plano_id').value;
        const payload = {
            veiculo_id: document.getElementById('plano_veiculo_id').value,
            componente_id: document.getElementById('plano_componente_id').value,
            tipo_manutencao_id: document.getElementById('plano_tipo_manutencao_id').value,
            intervalo_km: document.getElementById('plano_intervalo_km').value || null,
            intervalo_dias: document.getElementById('plano_intervalo_dias').value || null,
            ultimo_km: document.getElementById('plano_ultimo_km').value || null,
            ultima_data: document.getElementById('plano_ultima_data').value || null
        };
        if (!payload.intervalo_km && !payload.intervalo_dias) {
            alert('Informe pelo menos um intervalo (km ou dias).');
            return;
        }
        const method = id ? 'PUT' : 'POST';
        if (id) payload.id = id;
        fetch('../api/planos_manutencao.php', {
            method,
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { fecharModal(); listar(); alert(data.message || 'Salvo.'); }
            else alert('Erro: ' + (data.error || ''));
        });
    }

    function excluir(id) {
        if (!confirm('Desativar este plano?')) return;
        fetch('../api/planos_manutencao.php?id=' + id, { method: 'DELETE', credentials: 'include' })
            .then(r => r.json())
            .then(data => {
                if (data.success) listar();
                else alert('Erro: ' + (data.error || ''));
            });
    }

    document.getElementById('btnNovoPlano').addEventListener('click', abrirNovo);
    document.getElementById('btnSalvarPlano').addEventListener('click', salvar);
    var btnFechar = document.getElementById('btnFecharModalPlano');
    if (btnFechar) btnFechar.addEventListener('click', fecharModal);
    var btnCancelar = document.getElementById('btnCancelarPlano');
    if (btnCancelar) btnCancelar.addEventListener('click', fecharModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) fecharModal(); });
    listar();
})();
    </script>
</body>
</html>
