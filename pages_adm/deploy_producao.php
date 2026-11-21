<?php
require_once __DIR__ . '/includes/adm_auth.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');
require_once __DIR__ . '/lib/adm_deploy_prod.php';

$page_title = 'Deploy Produção';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$adm_extra_css = [sf_app_url('pages_adm/css/adm-deploy-producao.css?v=' . $v)];

require_once __DIR__ . '/includes/layout_start.php';

$cc_title = 'Central de Deploy Inteligente';
$cc_subtitle = 'Homologação → Produção com backup, migrations e rollback';
$cc_actions = '<button type="button" class="adm-cc-btn adm-cc-btn--ghost" id="dpRefresh"><i class="fas fa-sync-alt"></i> Atualizar</button>';
include __DIR__ . '/components/page_header.php';
?>
<div class="adm-dp-page adm-cc-page-stack" id="dpApp">
    <div class="adm-dp-toolbar">
        <label>
            <span class="adm-dp-meta">Servidor destino</span>
            <select id="dpServerSelect" class="adm-dp-select-server">
                <option value="">Carregando…</option>
            </select>
        </label>
        <span class="adm-dp-meta" id="dpHomologPath"></span>
    </div>

    <div class="adm-dp-grid">
        <!-- Card 1: Configuração -->
        <section class="adm-dp-card adm-dp-card--wide" id="cardConfig">
            <div class="adm-dp-card__head"><h2><i class="fas fa-server"></i> Configuração do servidor</h2></div>
            <div class="adm-dp-card__body">
                <form id="dpServerForm" class="adm-dp-form-grid">
                    <input type="hidden" name="id" id="dpSrvId" value="0">
                    <label>Nome <input name="nome" id="dpSrvNome" required></label>
                    <label>Ambiente
                        <select name="ambiente" id="dpSrvAmbiente">
                            <option value="desenvolvimento">Desenvolvimento</option>
                            <option value="homologacao">Homologação</option>
                            <option value="staging">Staging</option>
                            <option value="producao" selected>Produção</option>
                        </select>
                    </label>
                    <label>IP / Host <input name="host" id="dpSrvHost" placeholder="127.0.0.1 ou IP produção" required></label>
                    <label>Porta SSH <input type="number" name="porta_ssh" id="dpSrvPort" value="22"></label>
                    <label>Usuário SSH <input name="usuario" id="dpSrvUser" value="root"></label>
                    <label>Caminho do projeto <input name="project_path" id="dpSrvPath" placeholder="C:/xampp/htdocs/sistema-frotas" required></label>
                    <label>URL do site (health-check) <input name="site_url" id="dpSrvSite" placeholder="https://app.suaempresa.com.br"></label>
                    <label style="grid-column:1/-1">Arquivos protegidos — nunca enviados (um por linha)
                        <textarea name="sync_exclude" id="dpSrvExclude" rows="3" style="width:100%;font-family:monospace;font-size:.82rem" placeholder="ex.: includes/config_local.php&#10;fiscal/certs/"></textarea>
                    </label>
                    <p class="adm-dp-meta" style="grid-column:1/-1;margin:-6px 0 0">Sempre protegidos (não precisa listar): <code>includes/conexao.php</code>, <code>includes/config.php</code>, <code>includes/db_connect.php</code>, <code>.env*</code>. Esses arquivos têm as credenciais da produção — o deploy nunca os sobrescreve.</p>
                    <label>Caminho Git <input name="git_path" id="dpSrvGitPath"></label>
                    <label>Branch <input name="git_branch" id="dpSrvBranch" value="main"></label>
                    <label>Homologação (origem local) <input name="homolog_path" id="dpSrvHomolog" placeholder="vazio = projeto atual"></label>
                    <label>Método auth
                        <select name="auth_method" id="dpSrvAuth">
                            <option value="ssh_key">Chave SSH</option>
                            <option value="password">Senha</option>
                        </select>
                    </label>
                    <label>Chave SSH (PEM) <textarea name="ssh_key" id="dpSrvKey" placeholder="-----BEGIN…"></textarea></label>
                    <label>Senha SSH <input type="password" name="password" id="dpSrvPass" placeholder="********"></label>
                    <label>DB Host <input name="db_host" id="dpDbHost"></label>
                    <label>DB Porta <input type="number" name="db_port" id="dpDbPort" value="3307"></label>
                    <label>DB Nome <input name="db_name" id="dpDbName"></label>
                    <label>DB Usuário <input name="db_user" id="dpDbUser" value="root"></label>
                    <label>DB Senha <input type="password" name="db_pass" id="dpDbPass"></label>
                    <label style="flex-direction:row;align-items:center;gap:8px;margin-top:8px">
                        <input type="checkbox" name="ativo" id="dpSrvAtivo" value="1" checked> Ativo
                    </label>
                </form>
                <div style="margin-top:12px">
                    <button type="button" class="adm-cc-btn adm-cc-btn--primary" id="dpSaveServer"><i class="fas fa-save"></i> Salvar configuração</button>
                </div>
            </div>
        </section>

        <!-- Card 2: Status -->
        <section class="adm-dp-card" id="cardStatus">
            <div class="adm-dp-card__head"><h2><i class="fas fa-heartbeat"></i> Status do servidor</h2>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" id="dpRefreshStatus">Atualizar</button>
            </div>
            <div class="adm-dp-card__body" id="dpStatusBody"><p class="adm-dp-meta">Selecione um servidor.</p></div>
        </section>

        <!-- Card 3: Arquivos -->
        <section class="adm-dp-card" id="cardFiles">
            <div class="adm-dp-card__head"><h2><i class="fas fa-file-code"></i> Arquivos (H × P)</h2>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" id="dpCompareFiles">Comparar</button>
            </div>
            <div class="adm-dp-card__body" id="dpFilesBody"><p class="adm-dp-meta">Clique em Comparar.</p></div>
        </section>

        <!-- Card 4: Banco -->
        <section class="adm-dp-card" id="cardDb">
            <div class="adm-dp-card__head"><h2><i class="fas fa-database"></i> Banco de dados</h2>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" id="dpCompareDb">Comparar</button>
            </div>
            <div class="adm-dp-card__body" id="dpDbBody"><p class="adm-dp-meta">Configure DB remoto e compare.</p></div>
        </section>

        <!-- Card 5: Migration -->
        <section class="adm-dp-card adm-dp-card--wide" id="cardMigration">
            <div class="adm-dp-card__head"><h2><i class="fas fa-code-branch"></i> Migração automática (SQL)</h2></div>
            <div class="adm-dp-card__body">
                <p class="adm-dp-meta">Comandos destrutivos (DROP/TRUNCATE) são bloqueados. Aprove antes do deploy.</p>
                <div class="adm-dp-sql-preview" id="dpSqlPreview">Execute a comparação de banco primeiro.</div>
                <label style="display:flex;align-items:center;gap:8px;margin-top:10px;font-size:0.88rem">
                    <input type="checkbox" id="dpApproveSql"> Aprovar execução do SQL validado no deploy
                </label>
            </div>
        </section>

        <!-- Card 6: Backup -->
        <section class="adm-dp-card" id="cardBackup">
            <div class="adm-dp-card__head"><h2><i class="fas fa-shield-alt"></i> Backup</h2>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" id="dpBackupNow">Gerar agora</button>
            </div>
            <div class="adm-dp-card__body" id="dpBackupBody"></div>
        </section>

        <!-- Card 7: Git -->
        <section class="adm-dp-card" id="cardGit">
            <div class="adm-dp-card__head"><h2><i class="fab fa-git-alt"></i> Git</h2></div>
            <div class="adm-dp-card__body" id="dpGitBody"></div>
            <div class="adm-dp-git-actions" style="padding:0 16px 16px">
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" data-git="fetch" title="Baixa novidades do repositório remoto, sem alterar arquivos">Fetch</button>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" data-git="pull" title="Atualiza a branch com o que há no remoto">Pull</button>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" data-git="diff" title="Resumo do que mudou em relação ao último commit">Diff</button>
                <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" data-git="push" title="Envia commits locais para o repositório remoto">Push</button>
            </div>
            <p class="adm-dp-git-help">Ações no servidor selecionado · <strong>Fetch</strong> consulta o remoto · <strong>Pull</strong> atualiza a pasta · <strong>Diff</strong> mostra alterações · <strong>Push</strong> publica commits</p>
        </section>

        <!-- Card 8: Deploy -->
        <section class="adm-dp-card adm-dp-card--wide" id="cardDeploy">
            <div class="adm-dp-card__head"><h2><i class="fas fa-rocket"></i> Deploy inteligente</h2></div>
            <div class="adm-dp-card__body">
                <div class="adm-dp-deploy-hero">
                    <p class="adm-dp-meta">Fluxo completo: validação → backup → sync → migrations → testes → tag Git</p>
                    <div style="margin:12px 0">
                        <button type="button" class="adm-cc-btn adm-cc-btn--ghost" id="dpPreflightBtn"><i class="fas fa-clipboard-check"></i> Verificar (pré-voo)</button>
                        <p class="adm-dp-meta" style="margin-top:6px">Roda só as checagens de leitura (não envia nada) e mostra se está pronto para subir.</p>
                    </div>
                    <div class="adm-dp-preflight" id="dpPreflightBox" hidden></div>
                    <label style="display:block;margin:12px 0">Versão / tag <input type="text" id="dpVersao" class="adm-dp-select-server" style="max-width:200px;margin:0 auto" placeholder="v2.6.0"></label>
                    <div class="adm-dp-confirm-box">
                        <label><input type="checkbox" id="dpConfirmDeploy"> Confirmo publicação no servidor selecionado</label>
                        <label style="margin-top:8px"><input type="checkbox" id="dpAutoRollback" checked> Rollback automático se falhar (quando houver backup)</label>
                    </div>
                    <label style="display:block;margin:12px 0" id="dp2faWrap" hidden>
                        Código 2FA (obrigatório em produção)
                        <input type="text" id="dpTotp" maxlength="8" autocomplete="one-time-code" class="adm-dp-select-server" style="max-width:160px;margin:8px auto 0" placeholder="000000">
                    </label>
                    <button type="button" class="adm-cc-btn adm-cc-btn--primary" id="dpDeployBtn" style="margin-top:16px">
                        <i class="fas fa-rocket"></i> Publicar Produção
                    </button>

                    <!-- Progresso do deploy — aparece aqui mesmo, logo abaixo do botão -->
                    <div class="adm-dp-progress-wrap" id="dpProgressWrap" hidden style="text-align:left;margin-top:18px">
                        <div class="adm-dp-progress-top">
                            <div class="adm-dp-meta"><span class="adm-dp-spinner" id="dpProgressSpin"></span> <strong id="dpProgressLabel">Deploy em andamento…</strong> <span id="dpProgressPct">0%</span> · <span id="dpProgressTime">0s</span></div>
                            <button type="button" class="adm-cc-btn adm-cc-btn--ghost adm-cc-btn--sm" id="dpCancelBtn" hidden><i class="fas fa-stop"></i> Cancelar</button>
                        </div>
                        <div class="adm-dp-progress-bar"><div class="adm-dp-progress-fill" id="dpProgressFill"></div></div>
                        <div class="adm-dp-error" id="dpProgressError" hidden></div>
                        <ul class="adm-dp-steps" id="dpStepsList"></ul>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Histórico -->
    <section class="adm-dp-card adm-dp-card--wide">
        <div class="adm-dp-card__head"><h2><i class="fas fa-history"></i> Histórico de deploys</h2></div>
        <div class="adm-dp-card__body adm-dp-table-wrap">
            <table class="adm-dp-table" id="dpHistoryTable">
                <thead><tr>
                    <th>Data</th><th>Servidor</th><th>Versão</th><th>Commit</th><th>Arquivos</th><th>Tempo</th><th>Resultado</th><th>Ações</th>
                </tr></thead>
                <tbody><tr><td colspan="8">Carregando…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>
<?php
$adm_extra_scripts = '<script src="' . htmlspecialchars(sf_app_url('pages_adm/js/adm-deploy-producao.js?v=' . $v)) . '"></script>';
require_once __DIR__ . '/includes/layout_end.php';
