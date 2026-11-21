<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_post_guard.php';
require_once __DIR__ . '/lib/adm_events.php';

$page_title = 'Planos';

// Visual via adm-pages.css — sem Bootstrap CDN global (conflitava com o menu lateral).

// Criar tabela se não existir
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS adm_planos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL COMMENT 'Nome do plano',
        descricao TEXT NULL,
        tipo ENUM('avulso', 'pacote') NOT NULL DEFAULT 'pacote',
        limite_veiculos INT NULL COMMENT 'Limite máximo de veículos (NULL = ilimitado)',
        valor_por_veiculo DECIMAL(10,2) NOT NULL,
        valor_maximo DECIMAL(10,2) NULL COMMENT 'Valor máximo do plano',
        ordem INT DEFAULT 0,
        status ENUM('ativo', 'inativo') DEFAULT 'ativo',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tipo (tipo),
        INDEX idx_status (status),
        INDEX idx_ordem (ordem)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
} catch (PDOException $e) {
    // Tabela já existe
}

$mensagem = '';
$tipo_mensagem = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!adm_csrf_valid_post()) {
        $mensagem = 'Sessão expirada. Recarregue a página.';
        $tipo_mensagem = 'error';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                try {
                    $nome = $_POST['nome'];
                    $descricao = $_POST['descricao'] ?? null;
                    $tipo = $_POST['tipo'];
                    $limite_veiculos = !empty($_POST['limite_veiculos']) ? (int)$_POST['limite_veiculos'] : null;
                    $valor_por_veiculo = (float)$_POST['valor_por_veiculo'];
                    $valor_maximo = !empty($_POST['valor_maximo']) ? (float)$_POST['valor_maximo'] : null;
                    $ordem = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
                    $status = $_POST['status'] ?? 'ativo';
                    
                    $stmt = $pdo->prepare("INSERT INTO adm_planos (nome, descricao, tipo, limite_veiculos, valor_por_veiculo, valor_maximo, ordem, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$nome, $descricao, $tipo, $limite_veiculos, $valor_por_veiculo, $valor_maximo, $ordem, $status]);
                    
                    adm_event_log($pdo, 'planos', 'criar', 'Plano criado: ' . $nome, 'info');
                    $mensagem = "Plano cadastrado com sucesso!";
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    error_log('planos add: ' . $e->getMessage());
                    $mensagem = "Erro ao cadastrar plano. Tente novamente.";
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'edit':
                try {
                    $id = (int)$_POST['id'];
                    $nome = $_POST['nome'];
                    $descricao = $_POST['descricao'] ?? null;
                    $tipo = $_POST['tipo'];
                    $limite_veiculos = !empty($_POST['limite_veiculos']) ? (int)$_POST['limite_veiculos'] : null;
                    $valor_por_veiculo = (float)$_POST['valor_por_veiculo'];
                    $valor_maximo = !empty($_POST['valor_maximo']) ? (float)$_POST['valor_maximo'] : null;
                    $ordem = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 0;
                    $status = $_POST['status'] ?? 'ativo';
                    
                    $stmt = $pdo->prepare("UPDATE adm_planos SET nome = ?, descricao = ?, tipo = ?, limite_veiculos = ?, valor_por_veiculo = ?, valor_maximo = ?, ordem = ?, status = ? WHERE id = ?");
                    $stmt->execute([$nome, $descricao, $tipo, $limite_veiculos, $valor_por_veiculo, $valor_maximo, $ordem, $status, $id]);
                    
                    adm_event_log($pdo, 'planos', 'atualizar', 'Plano atualizado: ' . $nome, 'info');
                    $mensagem = "Plano atualizado com sucesso!";
                    $tipo_mensagem = "success";
                } catch (Exception $e) {
                    error_log('planos edit: ' . $e->getMessage());
                    $mensagem = "Erro ao atualizar plano. Tente novamente.";
                    $tipo_mensagem = "error";
                }
                break;
                
            case 'delete':
                try {
                    $id = (int)$_POST['id'];
                    
                    // Verificar se há empresas usando este plano
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresa_adm WHERE plano_id = ?");
                    $stmt->execute([$id]);
                    $count = $stmt->fetchColumn();
                    
                    if ($count > 0) {
                        throw new Exception("Não é possível excluir este plano pois existem empresas utilizando-o.");
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM adm_planos WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    adm_event_log($pdo, 'planos', 'excluir', 'Plano excluído ID ' . $id, 'aviso');
                    $mensagem = "Plano excluído com sucesso!";
                    $tipo_mensagem = "success";
                } catch (PDOException $e) {
                    error_log('planos delete: ' . $e->getMessage());
                    $mensagem = "Erro ao excluir plano. Tente novamente.";
                    $tipo_mensagem = "error";
                } catch (Exception $e) {
                    $mensagem = $e->getMessage();
                    $tipo_mensagem = "error";
                }
                break;
        }
    }
}

// Buscar planos
try {
    $stmt = $pdo->query("SELECT * FROM adm_planos ORDER BY ordem ASC, nome ASC");
    $planos = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('planos load: ' . $e->getMessage());
    $mensagem = "Erro ao carregar planos.";
    $tipo_mensagem = "error";
    $planos = [];
}
require_once __DIR__ . '/includes/layout_start.php';
?>

        <div class="adm-toolbar">
            <p class="text-muted mb-0" style="margin:0">Configure os planos de cobrança por quantidade de veículos</p>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNovoPlano">
                <i class="fas fa-plus me-1"></i> Novo Plano
            </button>
        </div>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?php echo $tipo_mensagem === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($mensagem); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th width="80">Ordem</th>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Limite Veículos</th>
                            <th>Valor/Veículo</th>
                            <th>Valor Máximo</th>
                            <th>Status</th>
                            <th width="120">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planos)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3 d-block"></i>
                                    <p class="text-muted">Nenhum plano cadastrado</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($planos as $plano): ?>
                                <tr>
                                    <td><?php echo $plano['ordem']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($plano['nome']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-<?php echo $plano['tipo'] === 'pacote' ? 'info' : 'warning'; ?>">
                                            <?php echo ucfirst($plano['tipo']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $plano['limite_veiculos'] ? $plano['limite_veiculos'] . ' veículos' : 'Ilimitado'; ?>
                                    </td>
                                    <td>R$ <?php echo number_format($plano['valor_por_veiculo'], 2, ',', '.'); ?></td>
                                    <td>
                                        <?php echo $plano['valor_maximo'] ? 'R$ ' . number_format($plano['valor_maximo'], 2, ',', '.') : 'Sem limite'; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $plano['status'] === 'ativo' ? 'success' : 'secondary'; ?>">
                                            <?php echo ucfirst($plano['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-icons">
                                            <div class="action-icon icon-edit" title="Editar" onclick="abrirEditar(<?php echo htmlspecialchars(json_encode($plano)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-icon icon-delete" title="Excluir" onclick="excluirPlano(<?php echo (int) $plano['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- Modal Novo Plano -->
    <div class="modal fade" id="modalNovoPlano" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered adm-modal-dialog adm-modal-dialog--form">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Novo Plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body adm-modal-form-grid">
                        <input type="hidden" name="action" value="add">
                        <?php echo adm_csrf_field(); ?>
                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome do Plano *</label>
                            <input type="text" class="form-control" id="nome" name="nome" required placeholder="Ex: Básico, Profissional">
                        </div>
                        <div class="mb-3 adm-modal-field--full">
                            <label for="descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="descricao" name="descricao" rows="2" placeholder="Descrição do plano"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="tipo" name="tipo" required>
                                <option value="pacote">Pacote</option>
                                <option value="avulso">Avulso</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="limite_veiculos" class="form-label">Limite de Veículos</label>
                            <input type="number" class="form-control" id="limite_veiculos" name="limite_veiculos" min="1" placeholder="Deixe vazio para ilimitado">
                            <small class="text-muted">Deixe vazio para planos ilimitados</small>
                        </div>
                        <div class="mb-3">
                            <label for="valor_por_veiculo" class="form-label">Valor por Veículo (R$) *</label>
                            <input type="number" class="form-control" id="valor_por_veiculo" name="valor_por_veiculo" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="valor_maximo" class="form-label">Valor Máximo (R$)</label>
                            <input type="number" class="form-control" id="valor_maximo" name="valor_maximo" step="0.01" min="0" placeholder="Deixe vazio para sem limite">
                            <small class="text-muted">Valor máximo que pode ser cobrado neste plano</small>
                        </div>
                        <div class="mb-3">
                            <label for="ordem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="ordem" name="ordem" value="0" min="0">
                            <small class="text-muted">Ordem para exibição na lista (menor número aparece primeiro)</small>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Plano -->
    <div class="modal fade" id="modalEditarPlano" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered adm-modal-dialog adm-modal-dialog--form">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Plano</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body adm-modal-form-grid">
                        <input type="hidden" name="action" value="edit">
                        <?php echo adm_csrf_field(); ?>
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_nome" class="form-label">Nome do Plano *</label>
                            <input type="text" class="form-control" id="edit_nome" name="nome" required>
                        </div>
                        <div class="mb-3 adm-modal-field--full">
                            <label for="edit_descricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="edit_descricao" name="descricao" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_tipo" class="form-label">Tipo *</label>
                            <select class="form-select" id="edit_tipo" name="tipo" required>
                                <option value="pacote">Pacote</option>
                                <option value="avulso">Avulso</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="edit_limite_veiculos" class="form-label">Limite de Veículos</label>
                            <input type="number" class="form-control" id="edit_limite_veiculos" name="limite_veiculos" min="1">
                            <small class="text-muted">Deixe vazio para ilimitado</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_valor_por_veiculo" class="form-label">Valor por Veículo (R$) *</label>
                            <input type="number" class="form-control" id="edit_valor_por_veiculo" name="valor_por_veiculo" step="0.01" min="0" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_valor_maximo" class="form-label">Valor Máximo (R$)</label>
                            <input type="number" class="form-control" id="edit_valor_maximo" name="valor_maximo" step="0.01" min="0">
                            <small class="text-muted">Deixe vazio para sem limite</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_ordem" class="form-label">Ordem de Exibição</label>
                            <input type="number" class="form-control" id="edit_ordem" name="ordem" min="0">
                        </div>
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status *</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Atualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Formulário de Exclusão -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <?php echo adm_csrf_field(); ?>
        <input type="hidden" name="id" id="delete_id">
    </form>

<?php
$adm_extra_scripts = <<<'HTML'
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirEditar(plano) {
            document.getElementById('edit_id').value = plano.id;
            document.getElementById('edit_nome').value = plano.nome;
            document.getElementById('edit_descricao').value = plano.descricao || '';
            document.getElementById('edit_tipo').value = plano.tipo;
            document.getElementById('edit_limite_veiculos').value = plano.limite_veiculos || '';
            document.getElementById('edit_valor_por_veiculo').value = plano.valor_por_veiculo;
            document.getElementById('edit_valor_maximo').value = plano.valor_maximo || '';
            document.getElementById('edit_ordem').value = plano.ordem;
            document.getElementById('edit_status').value = plano.status;
            new bootstrap.Modal(document.getElementById('modalEditarPlano')).show();
        }

        function excluirPlano(id) {
            if (confirm('Tem certeza que deseja excluir este plano?')) {
                document.getElementById('delete_id').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
HTML;
require_once __DIR__ . '/includes/layout_end.php';

