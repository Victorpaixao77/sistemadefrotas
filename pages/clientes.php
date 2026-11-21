<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Clientes';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <link rel="stylesheet" href="../css/responsive.css">
    <link rel="stylesheet" href="../css/fornc-modern-page.css">
    <link rel="stylesheet" href="../css/sf-premium.css?v=1.0.3">
    <?php sf_page_help_css('..'); ?>
    <link rel="icon" type="image/png" href="../logo.png">
    <style>
        html, body { overflow-x: hidden; max-width: 100%; }
        .cl-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .cl-sc { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 13px 15px; }
        .cl-sc .n { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); } .cl-sc .l { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-top: 2px; }
        .cl-sc.green .n { color: #16a34a; } .cl-sc.blue .n { color: #3b82f6; } .cl-sc.red .n { color: #dc2626; }
        .cl-table .nome { font-weight: 700; color: var(--text-primary); }
        .cl-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; }
        .b-ativo { background: rgba(16,185,129,.16); color: #16a34a; }
        .b-prospect { background: rgba(59,130,246,.16); color: #3b82f6; }
        .b-inativo { background: rgba(239,68,68,.16); color: #dc2626; }
        .cl-ot { display: inline-block; min-width: 26px; text-align: center; background: var(--bg-tertiary); border-radius: 999px; padding: 2px 8px; font-weight: 700; color: var(--text-primary); }
        .cl-act { background: none; border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 8px; padding: 5px 9px; cursor: pointer; font-size: 12px; margin-right: 4px; }
        .cl-act:hover { border-color: var(--accent-primary); color: var(--text-primary); } .cl-act.danger:hover { border-color: #dc2626; color: #dc2626; }
        .cl-empty { padding: 40px; text-align: center; color: var(--text-muted); }
        .cl-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1090; display: none; } .cl-overlay.active { display: block; }
        .cl-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); width: min(680px,96vw); max-height: 92vh; z-index: 1091; display: none; flex-direction: column; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; } .cl-modal.active { display: flex; }
        .cl-mhead { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color); } .cl-mhead h2 { margin: 0; font-size: 1.05rem; color: var(--text-primary); }
        .cl-mclose { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; }
        .cl-mbody { padding: 18px 20px; overflow: auto; } .cl-mfoot { padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .cl-row { display: flex; gap: 12px; } .cl-row > * { flex: 1; } @media (max-width:640px){ .cl-row{flex-direction:column;} }
        .cl-field { margin-bottom: 12px; } .cl-field label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .cl-field input, .cl-field select, .cl-field textarea { width: 100%; padding: 9px 11px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 14px; }
        .cl-save { padding: 10px 18px; border: none; border-radius: 9px; background: var(--accent-primary); color: #fff; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p style="color:var(--text-secondary);margin:0 0 16px;">Cadastro de <strong>clientes / embarcadores</strong> (tomadores de frete). Integra com as Ordens de Transporte — a coluna "OTs" mostra quantas ordens cada cliente já teve.</p>

                <div class="cl-sum" id="clSum"></div>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="clBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="clBusca" placeholder="Buscar por nome/CNPJ...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="clStatusFilter">Status</label>
                            <select id="clStatusFilter">
                                <option value="">Todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="prospect">Prospects</option>
                                <option value="inativo">Inativos</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="clPerPage">Por página</label>
                            <select id="clPerPage" class="filter-per-page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button class="fornc-btn fornc-btn--primary" onclick="abrirCl()"><i class="fas fa-plus"></i> Novo cliente</button>
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="fornc-empty-state" id="clEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum cliente encontrado</p>
                    <p class="fornc-empty-state-hint">Ajuste a busca ou o filtro de status. Cadastre seus clientes/embarcadores para vinculá-los às Ordens de Transporte.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" onclick="abrirCl()"><i class="fas fa-plus"></i> Novo cliente</button>
                </div>

                <div class="fornc-table-wrap" id="clTableWrap">
                    <table class="fornc-table cl-table">
                        <thead><tr><th>Cliente</th><th>CNPJ/CPF</th><th>Contato</th><th>Cidade/UF</th><th>OTs</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody id="clBody"><tr><td colspan="7" class="cl-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
                <div class="pagination" id="clPag" style="display:none;"></div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <div class="cl-overlay" id="clOverlay" onclick="fecharCl()"></div>
    <div class="cl-modal" id="clModal" role="dialog" aria-modal="true">
        <div class="cl-mhead"><h2 id="clTitle">Novo cliente</h2><button class="cl-mclose" onclick="fecharCl()"><i class="fas fa-times"></i></button></div>
        <div class="cl-mbody">
            <form id="clForm" onsubmit="return salvarCl(event)">
                <input type="hidden" id="cl_id">
                <div class="cl-row">
                    <div class="cl-field" style="max-width:130px"><label>Tipo</label><select id="tipo_pessoa"><option value="PJ">PJ</option><option value="PF">PF</option></select></div>
                    <div class="cl-field"><label>Razão social / Nome *</label><input type="text" id="razao_social"></div>
                </div>
                <div class="cl-row">
                    <div class="cl-field"><label>Nome fantasia</label><input type="text" id="nome_fantasia"></div>
                    <div class="cl-field"><label>CNPJ / CPF</label><input type="text" id="cnpj_cpf"></div>
                    <div class="cl-field" style="max-width:150px"><label>Inscr. Estadual</label><input type="text" id="inscricao_estadual"></div>
                </div>
                <div class="cl-row">
                    <div class="cl-field"><label>Contato</label><input type="text" id="contato_nome"></div>
                    <div class="cl-field"><label>Telefone</label><input type="text" id="telefone"></div>
                    <div class="cl-field"><label>E-mail</label><input type="email" id="email"></div>
                </div>
                <div class="cl-row">
                    <div class="cl-field"><label>Endereço</label><input type="text" id="endereco"></div>
                    <div class="cl-field"><label>Cidade</label><input type="text" id="cidade"></div>
                    <div class="cl-field" style="max-width:100px"><label>UF</label><input type="text" id="uf" maxlength="2"></div>
                </div>
                <div class="cl-field" style="max-width:180px"><label>Status</label><select id="status"><option value="ativo">Ativo</option><option value="prospect">Prospect</option><option value="inativo">Inativo</option></select></div>
                <div class="cl-field"><label>Observações</label><textarea id="observacoes" rows="2"></textarea></div>
            </form>
        </div>
        <div class="cl-mfoot"><span id="clMsg" style="font-size:12px;"></span><button class="cl-save" onclick="salvarCl(event)"><i class="fas fa-save"></i> Salvar</button></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var CL_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    var clStatus = '', clBusca = '', clAll = [], clPage = 1, CL_PER = 10;
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function api(p){ return fetch('../api/clientes.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(p).toString()}).then(function(r){return r.json();}); }
    var CL_FIELDS=['tipo_pessoa','razao_social','nome_fantasia','cnpj_cpf','inscricao_estadual','contato_nome','telefone','email','endereco','cidade','uf','status','observacoes'];

    function carregar(){
        var url='../api/clientes.php?action=list'+(clStatus?('&status='+clStatus):'')+(clBusca?('&busca='+encodeURIComponent(clBusca)):'');
        fetch(url).then(function(r){return r.json();}).then(function(d){
            clAll = (d.success && d.clientes) ? d.clientes : [];
            clPage = 1; renderResumo(); render();
        });
    }
    function renderResumo(){
        var at=0,pr=0,ina=0; clAll.forEach(function(o){ if(o.status==='ativo')at++; else if(o.status==='prospect')pr++; else if(o.status==='inativo')ina++; });
        document.getElementById('clSum').innerHTML=
            '<div class="cl-sc"><div class="n">'+clAll.length+'</div><div class="l">Clientes</div></div>'+
            '<div class="cl-sc green"><div class="n">'+at+'</div><div class="l">Ativos</div></div>'+
            '<div class="cl-sc blue"><div class="n">'+pr+'</div><div class="l">Prospects</div></div>'+
            '<div class="cl-sc red"><div class="n">'+ina+'</div><div class="l">Inativos</div></div>';
    }
    function render(){
        var empty=document.getElementById('clEmptyState'), wrap=document.getElementById('clTableWrap'), b=document.getElementById('clBody');
        if(!clAll.length){ empty.classList.add('visible'); wrap.style.display='none'; document.getElementById('clPag').style.display='none';
            empty.querySelector('.fornc-empty-state-hint').textContent = (clBusca||clStatus) ? 'Nenhum cliente para esse filtro. Ajuste a busca ou o status.' : 'Cadastre seus clientes/embarcadores para vinculá-los às Ordens de Transporte.';
            return; }
        empty.classList.remove('visible'); wrap.style.display='';
        var tp=Math.max(1, Math.ceil(clAll.length/CL_PER)); if(clPage>tp)clPage=tp;
        var slice=clAll.slice((clPage-1)*CL_PER, clPage*CL_PER);
        b.innerHTML=slice.map(function(o){
            var acts='<button class="btn-icon edit-btn" onclick="editarCl('+o.id+')" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></button>'+
                     '<button class="btn-icon delete-btn" onclick="excluirCl('+o.id+')" title="Excluir" aria-label="Excluir"><i class="fas fa-trash"></i></button>';
            return '<tr><td class="nome">'+esc(o.razao_social)+(o.nome_fantasia?('<br><small style="color:var(--text-muted)">'+esc(o.nome_fantasia)+'</small>'):'')+'</td>'+
                   '<td>'+esc(o.cnpj_cpf||'—')+'</td><td>'+esc(o.contato_nome||'—')+(o.telefone?('<br><small style="color:var(--text-muted)">'+esc(o.telefone)+'</small>'):'')+'</td>'+
                   '<td>'+esc((o.cidade||'—'))+(o.uf?('/'+esc(o.uf)):'')+'</td>'+
                   '<td><span class="cl-ot">'+(o.ots||0)+'</span></td>'+
                   '<td><span class="cl-badge b-'+o.status+'">'+o.status+'</span></td><td class="actions">'+acts+'</td></tr>';
        }).join('');
        renderPag();
    }
    function renderPag(){
        var pag=document.getElementById('clPag');
        var tp=Math.max(1, Math.ceil(clAll.length/CL_PER)); if(clPage>tp)clPage=tp;
        var info = tp>1 ? ('Página '+clPage+' de '+tp+' ('+clAll.length+' registros)') : (clAll.length+' registro'+(clAll.length===1?'':'s'));
        pag.innerHTML =
            '<a href="#" class="pagination-btn pagination-prev'+(clPage<=1?' disabled':'')+'" onclick="return clGo('+(clPage-1)+')"><i class="fas fa-chevron-left"></i></a>'+
            '<span class="pagination-info">'+info+'</span>'+
            '<a href="#" class="pagination-btn pagination-next'+(clPage>=tp?' disabled':'')+'" onclick="return clGo('+(clPage+1)+')"><i class="fas fa-chevron-right"></i></a>';
        pag.style.display='flex';
    }
    function clGo(p){ var tp=Math.max(1,Math.ceil(clAll.length/CL_PER)); if(p<1||p>tp)return false; clPage=p; render(); window.scrollTo({top:0,behavior:'smooth'}); return false; }
    document.getElementById('clPerPage').addEventListener('change', function(e){ CL_PER=parseInt(e.target.value,10)||10; clPage=1; render(); });
    document.getElementById('clStatusFilter').addEventListener('change', function(e){ clStatus=e.target.value; carregar(); });
    document.getElementById('clBusca').addEventListener('input', function(e){ clBusca=e.target.value.trim(); clearTimeout(window._clT); window._clT=setTimeout(carregar,350); });

    function setV(id,v){ var el=document.getElementById(id); if(el) el.value=(v==null?'':v); }
    function abrirCl(){ document.getElementById('clTitle').textContent='Novo cliente'; setV('cl_id',''); CL_FIELDS.forEach(function(f){setV(f,'');}); setV('tipo_pessoa','PJ'); setV('status','ativo'); document.getElementById('clMsg').textContent=''; document.getElementById('clOverlay').classList.add('active'); document.getElementById('clModal').classList.add('active'); }
    function editarCl(id){ fetch('../api/clientes.php?action=get&id='+id).then(function(r){return r.json();}).then(function(d){ if(!d.success)return; var o=d.cliente; document.getElementById('clTitle').textContent='Editar '+o.razao_social; setV('cl_id',o.id); CL_FIELDS.forEach(function(f){setV(f,o[f]);}); document.getElementById('clMsg').textContent=''; document.getElementById('clOverlay').classList.add('active'); document.getElementById('clModal').classList.add('active'); }); }
    function fecharCl(){ document.getElementById('clOverlay').classList.remove('active'); document.getElementById('clModal').classList.remove('active'); }
    function salvarCl(ev){ if(ev)ev.preventDefault(); var msg=document.getElementById('clMsg'); msg.style.color='var(--text-muted)'; msg.textContent='Salvando…';
        var f={action:'save',csrf_token:CL_CSRF,id:document.getElementById('cl_id').value}; CL_FIELDS.forEach(function(k){ f[k]=document.getElementById(k).value; });
        api(f).then(function(d){ if(d.success){ msg.style.color='#16a34a'; msg.textContent='Salvo!'; setTimeout(function(){fecharCl();carregar();},500); } else { msg.style.color='#dc2626'; msg.textContent=d.error||'Erro.'; } }); return false; }
    function excluirCl(id){ if(!confirm('Excluir este cliente?'))return; api({action:'delete',id:id,csrf_token:CL_CSRF}).then(carregar); }

    document.addEventListener('DOMContentLoaded', carregar);
    </script>
    <?php $sf_help_key = 'clientes'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
