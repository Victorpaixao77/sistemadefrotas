<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Implementos';
$tiposCarreta = [];
$cavalos = [];
try {
    $conn = getConnection();
    $eid = (int) ($_SESSION['empresa_id'] ?? 0);
    $tiposCarreta = $conn->query('SELECT id, nome FROM tipos_carretas ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
    $sv = $conn->prepare('SELECT id, placa FROM veiculos WHERE empresa_id = :e ORDER BY placa');
    $sv->execute([':e' => $eid]);
    $cavalos = $sv->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* estados vazios */ }
$tiposImplemento = ['Carreta', 'Carreta Baú', 'Carreta Sider', 'Carreta Graneleira', 'Carreta Tanque', 'Carreta Prancha', 'Dolly', 'Reboque', 'Bitrem', 'Rodotrem'];
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
        .im-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .im-sc { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 13px 15px; }
        .im-sc .n { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); } .im-sc .l { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-top: 2px; }
        .im-sc.green .n { color: #16a34a; } .im-sc.amber .n { color: #f59e0b; } .im-sc.red .n { color: #dc2626; }
        .im-table .placa { font-weight: 700; color: var(--text-primary); }
        .im-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; }
        .b-ativo { background: rgba(16,185,129,.16); color: #16a34a; }
        .b-manutencao { background: rgba(245,158,11,.16); color: #f59e0b; }
        .b-inativo { background: rgba(239,68,68,.16); color: #dc2626; }
        .venc-alerta { color: #dc2626; font-weight: 700; }
        .venc-aviso { color: #f59e0b; font-weight: 600; }
        .im-act { background: none; border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 8px; padding: 5px 9px; cursor: pointer; font-size: 12px; margin-right: 4px; }
        .im-act:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        .im-act.danger:hover { border-color: #dc2626; color: #dc2626; }
        .im-empty { padding: 40px; text-align: center; color: var(--text-muted); }
        .im-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1090; display: none; } .im-overlay.active { display: block; }
        .im-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); width: min(720px,96vw); max-height: 92vh; z-index: 1091; display: none; flex-direction: column; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; } .im-modal.active { display: flex; }
        .im-mhead { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
        .im-mhead h2 { margin: 0; font-size: 1.05rem; color: var(--text-primary); }
        .im-mclose { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; }
        .im-mbody { padding: 18px 20px; overflow: auto; } .im-mfoot { padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .im-row { display: flex; gap: 12px; } .im-row > * { flex: 1; } @media (max-width:640px){ .im-row{flex-direction:column;} }
        .im-field { margin-bottom: 12px; } .im-field label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .im-field input, .im-field select, .im-field textarea { width: 100%; padding: 9px 11px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 14px; }
        .im-save { padding: 10px 18px; border: none; border-radius: 9px; background: var(--accent-primary); color: #fff; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p style="color:var(--text-secondary);margin:0 0 16px;">Gestão dos <strong>implementos</strong> (carretas, reboques, dollys, pranchas…) como ativos próprios — placa, documentação e vencimentos, separados do cavalo.</p>

                <div class="im-sum" id="imSum"></div>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="imBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="imBusca" placeholder="Placa, tipo, marca, cavalo...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="imStatusFilter">Status</label>
                            <select id="imStatusFilter">
                                <option value="">Todos</option>
                                <option value="ativo">Ativos</option>
                                <option value="manutencao">Manutenção</option>
                                <option value="inativo">Inativos</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="imPerPage">Por página</label>
                            <select id="imPerPage" class="filter-per-page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button class="fornc-btn fornc-btn--primary" onclick="abrirImp()"><i class="fas fa-plus"></i> Novo implemento</button>
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="fornc-empty-state" id="imEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhum implemento encontrado</p>
                    <p class="fornc-empty-state-hint">Cadastre carretas, reboques e dollys para controlar documentação e vencimentos separados do cavalo mecânico.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" onclick="abrirImp()"><i class="fas fa-plus"></i> Novo implemento</button>
                </div>

                <div class="fornc-table-wrap" id="imTableWrap">
                    <table class="fornc-table im-table">
                        <thead><tr><th>Placa</th><th>Tipo</th><th>Marca/Ano</th><th>Cavalo</th><th>Licenciamento</th><th>Seguro</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody id="imBody"><tr><td colspan="8" class="im-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
                <div class="pagination" id="imPag" style="display:none;"></div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <div class="im-overlay" id="imOverlay" onclick="fecharImp()"></div>
    <div class="im-modal" id="imModal" role="dialog" aria-modal="true">
        <div class="im-mhead"><h2 id="imTitle">Novo implemento</h2><button class="im-mclose" onclick="fecharImp()"><i class="fas fa-times"></i></button></div>
        <div class="im-mbody">
            <form id="imForm" onsubmit="return salvarImp(event)">
                <input type="hidden" id="im_id">
                <div class="im-row">
                    <div class="im-field"><label>Placa *</label><input type="text" id="placa" maxlength="10"></div>
                    <div class="im-field"><label>Tipo</label><select id="tipo"><?php foreach ($tiposImplemento as $t): ?><option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option><?php endforeach; ?></select></div>
                    <div class="im-field"><label>Subtipo (carreta)</label><select id="tipo_carreta_id"><option value="">—</option><?php foreach ($tiposCarreta as $tc): ?><option value="<?php echo (int) $tc['id']; ?>"><?php echo htmlspecialchars($tc['nome']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="im-row">
                    <div class="im-field"><label>Marca</label><input type="text" id="marca"></div>
                    <div class="im-field" style="max-width:110px"><label>Ano</label><input type="number" id="ano" min="1980" max="2100"></div>
                    <div class="im-field" style="max-width:110px"><label>Eixos</label><input type="number" id="eixos" min="1" max="9"></div>
                    <div class="im-field"><label>Capacidade (kg)</label><input type="number" id="capacidade_kg" min="0" step="1"></div>
                </div>
                <div class="im-row">
                    <div class="im-field"><label>RENAVAM</label><input type="text" id="renavam"></div>
                    <div class="im-field"><label>Chassi</label><input type="text" id="chassi"></div>
                    <div class="im-field"><label>RNTRC</label><input type="text" id="rntrc"></div>
                </div>
                <div class="im-row">
                    <div class="im-field"><label>Cavalo vinculado</label><select id="veiculo_id"><option value="">—</option><?php foreach ($cavalos as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['placa']); ?></option><?php endforeach; ?></select></div>
                    <div class="im-field"><label>Status</label><select id="status"><option value="ativo">Ativo</option><option value="manutencao">Manutenção</option><option value="inativo">Inativo</option></select></div>
                </div>
                <div class="im-row">
                    <div class="im-field"><label>Venc. licenciamento</label><input type="date" id="vencimento_licenciamento"></div>
                    <div class="im-field"><label>Venc. seguro</label><input type="date" id="vencimento_seguro"></div>
                </div>
                <div class="im-field"><label>Observações</label><textarea id="observacoes" rows="2"></textarea></div>
            </form>
        </div>
        <div class="im-mfoot"><span id="imMsg" style="font-size:12px;"></span><button class="im-save" onclick="salvarImp(event)"><i class="fas fa-save"></i> Salvar</button></div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var IM_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    var imStatus = '', imBusca = '', imAll = [], imPage = 1, IM_PER = 10;
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    function api(p){ return fetch('../api/implementos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(p).toString()}).then(function(r){return r.json();}); }
    function fmtData(d){ if(!d) return '—'; var p=d.substring(0,10).split('-'); return p[2]+'/'+p[1]+'/'+p[0]; }
    function vencClass(d){ if(!d) return ''; var hoje=new Date(); var dt=new Date(d.substring(0,10)+'T00:00'); var dias=(dt-hoje)/86400000; if(dias<0) return 'venc-alerta'; if(dias<=30) return 'venc-aviso'; return ''; }

    function carregar(){
        fetch('../api/implementos.php?action=list'+(imStatus?('&status='+imStatus):'')).then(function(r){return r.json();}).then(function(d){
            imAll = (d.success && d.implementos) ? d.implementos : [];
            imPage = 1; renderResumo(); renderImp();
        });
    }
    function renderResumo(){
        var at=0,mn=0,ina=0; imAll.forEach(function(o){ if(o.status==='ativo')at++; else if(o.status==='manutencao')mn++; else if(o.status==='inativo')ina++; });
        document.getElementById('imSum').innerHTML=
            '<div class="im-sc"><div class="n">'+imAll.length+'</div><div class="l">Implementos</div></div>'+
            '<div class="im-sc green"><div class="n">'+at+'</div><div class="l">Ativos</div></div>'+
            '<div class="im-sc amber"><div class="n">'+mn+'</div><div class="l">Manutenção</div></div>'+
            '<div class="im-sc red"><div class="n">'+ina+'</div><div class="l">Inativos</div></div>';
    }
    function renderImp(){
        var b=document.getElementById('imBody'), empty=document.getElementById('imEmptyState'), wrap=document.getElementById('imTableWrap');
        var rows = imBusca ? imAll.filter(function(o){
            return ((o.placa||'')+' '+(o.tipo||'')+' '+(o.marca||'')+' '+(o.cavalo_placa||'')).toLowerCase().indexOf(imBusca)>-1;
        }) : imAll;
        if(!rows.length){ empty.classList.add('visible'); wrap.style.display='none'; document.getElementById('imPag').style.display='none';
            empty.querySelector('.fornc-empty-state-hint').textContent = (imStatus||imBusca) ? 'Nenhum implemento para esse filtro. Ajuste a busca ou o status.' : 'Cadastre carretas, reboques e dollys para controlar documentação e vencimentos separados do cavalo mecânico.';
            return; }
        empty.classList.remove('visible'); wrap.style.display='';
        var tp=Math.max(1, Math.ceil(rows.length/IM_PER)); if(imPage>tp)imPage=tp;
        var slice=rows.slice((imPage-1)*IM_PER, imPage*IM_PER);
        b.innerHTML=slice.map(function(o){
            var lic=fmtData(o.vencimento_licenciamento), seg=fmtData(o.vencimento_seguro);
            var acts='<button class="btn-icon edit-btn" onclick="editarImp('+o.id+')" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></button>'+
                     '<button class="btn-icon delete-btn" onclick="excluirImp('+o.id+')" title="Excluir" aria-label="Excluir"><i class="fas fa-trash"></i></button>';
            return '<tr><td class="placa">'+esc(o.placa)+'</td><td>'+esc(o.tipo)+'</td><td>'+esc((o.marca||'—'))+(o.ano?(' · '+o.ano):'')+'</td>'+
                   '<td>'+esc(o.cavalo_placa||'—')+'</td>'+
                   '<td class="'+vencClass(o.vencimento_licenciamento)+'">'+lic+'</td><td class="'+vencClass(o.vencimento_seguro)+'">'+seg+'</td>'+
                   '<td><span class="im-badge b-'+o.status+'">'+o.status+'</span></td><td class="actions">'+acts+'</td></tr>';
        }).join('');
        renderPag(rows.length);
    }
    function imFiltradosLen(){ return (imBusca ? imAll.filter(function(o){ return ((o.placa||'')+' '+(o.tipo||'')+' '+(o.marca||'')+' '+(o.cavalo_placa||'')).toLowerCase().indexOf(imBusca)>-1; }) : imAll).length; }
    function renderPag(tot){
        var pag=document.getElementById('imPag');
        var tp=Math.max(1, Math.ceil(tot/IM_PER)); if(imPage>tp)imPage=tp;
        var info = tp>1 ? ('Página '+imPage+' de '+tp+' ('+tot+' registros)') : (tot+' registro'+(tot===1?'':'s'));
        pag.innerHTML =
            '<a href="#" class="pagination-btn pagination-prev'+(imPage<=1?' disabled':'')+'" onclick="return imGo('+(imPage-1)+')"><i class="fas fa-chevron-left"></i></a>'+
            '<span class="pagination-info">'+info+'</span>'+
            '<a href="#" class="pagination-btn pagination-next'+(imPage>=tp?' disabled':'')+'" onclick="return imGo('+(imPage+1)+')"><i class="fas fa-chevron-right"></i></a>';
        pag.style.display='flex';
    }
    function imGo(p){ var tp=Math.max(1,Math.ceil(imFiltradosLen()/IM_PER)); if(p<1||p>tp)return false; imPage=p; renderImp(); window.scrollTo({top:0,behavior:'smooth'}); return false; }
    document.getElementById('imPerPage').addEventListener('change', function(e){ IM_PER=parseInt(e.target.value,10)||10; imPage=1; renderImp(); });
    document.getElementById('imStatusFilter').addEventListener('change', function(e){ imStatus=e.target.value; carregar(); });
    document.getElementById('imBusca').addEventListener('input', function(e){ imBusca=e.target.value.trim().toLowerCase(); imPage=1; renderImp(); });

    var IM_FIELDS=['placa','tipo','tipo_carreta_id','marca','ano','eixos','capacidade_kg','renavam','chassi','rntrc','veiculo_id','status','vencimento_licenciamento','vencimento_seguro','observacoes'];
    function setV(id,v){ var el=document.getElementById(id); if(el) el.value=(v==null?'':v); }
    function abrirImp(){ document.getElementById('imTitle').textContent='Novo implemento'; setV('im_id',''); IM_FIELDS.forEach(function(f){setV(f,'');}); setV('tipo','Carreta'); setV('status','ativo'); document.getElementById('imMsg').textContent=''; document.getElementById('imOverlay').classList.add('active'); document.getElementById('imModal').classList.add('active'); }
    function editarImp(id){ fetch('../api/implementos.php?action=get&id='+id).then(function(r){return r.json();}).then(function(d){ if(!d.success)return; var o=d.implemento; document.getElementById('imTitle').textContent='Editar '+o.placa; setV('im_id',o.id); IM_FIELDS.forEach(function(f){ setV(f, f.indexOf('vencimento')===0 && o[f] ? o[f].substring(0,10) : o[f]); }); document.getElementById('imMsg').textContent=''; document.getElementById('imOverlay').classList.add('active'); document.getElementById('imModal').classList.add('active'); }); }
    function fecharImp(){ document.getElementById('imOverlay').classList.remove('active'); document.getElementById('imModal').classList.remove('active'); }
    function salvarImp(ev){ if(ev)ev.preventDefault(); var msg=document.getElementById('imMsg'); msg.style.color='var(--text-muted)'; msg.textContent='Salvando…';
        var f={action:'save',csrf_token:IM_CSRF,id:document.getElementById('im_id').value}; IM_FIELDS.forEach(function(k){ f[k]=document.getElementById(k).value; });
        api(f).then(function(d){ if(d.success){ msg.style.color='#16a34a'; msg.textContent='Salvo!'; setTimeout(function(){fecharImp();carregar();},500); } else { msg.style.color='#dc2626'; msg.textContent=d.error||'Erro.'; } }); return false; }
    function excluirImp(id){ if(!confirm('Excluir este implemento?'))return; api({action:'delete',id:id,csrf_token:IM_CSRF}).then(carregar); }

    document.addEventListener('DOMContentLoaded', carregar);
    </script>
    <?php $sf_help_key = 'implementos'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
