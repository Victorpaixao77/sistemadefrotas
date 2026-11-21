<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once __DIR__ . '/../includes/sf_page_help.php';
require_once '../includes/sf_antt_frete.php';

configure_session();
session_start();
require_authentication();

$page_title = 'Ordens de Transporte';
$tiposCarga = sf_antt_tipos_carga();

$veiculos = [];
$motoristas = [];
try {
    $conn = getConnection();
    $eid = (int) ($_SESSION['empresa_id'] ?? 0);
    $sv = $conn->prepare('SELECT id, placa, modelo FROM veiculos WHERE empresa_id = :e ORDER BY placa');
    $sv->execute([':e' => $eid]);
    $veiculos = $sv->fetchAll(PDO::FETCH_ASSOC);
    $sm = $conn->prepare('SELECT id, nome FROM motoristas WHERE empresa_id = :e ORDER BY nome');
    $sm->execute([':e' => $eid]);
    $motoristas = $sm->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { /* estados vazios */ }
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
        .ot-sum { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .ot-sc { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 12px; padding: 13px 15px; }
        .ot-sc .n { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); } .ot-sc .l { font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: .03em; margin-top: 2px; }
        .ot-sc.blue .n { color: #3b82f6; } .ot-sc.amber .n { color: #f59e0b; } .ot-sc.green .n { color: #16a34a; }
        .ot-table .num { font-weight: 700; color: var(--text-primary); }
        .ot-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .02em; }
        .st-cotacao { background: rgba(100,116,139,.18); color: #94a3b8; }
        .st-aprovada { background: rgba(59,130,246,.16); color: #3b82f6; }
        .st-em_viagem { background: rgba(245,158,11,.16); color: #f59e0b; }
        .st-concluida { background: rgba(16,185,129,.16); color: #16a34a; }
        .st-cancelada { background: rgba(239,68,68,.16); color: #dc2626; }
        .ot-act { background: none; border: 1px solid var(--border-color); color: var(--text-secondary); border-radius: 8px; padding: 5px 9px; cursor: pointer; font-size: 12px; margin-right: 4px; }
        .ot-act:hover { border-color: var(--accent-primary); color: var(--text-primary); }
        .ot-act.danger:hover { border-color: #dc2626; color: #dc2626; }
        .ot-empty { padding: 40px; text-align: center; color: var(--text-muted); }
        /* modal */
        .ot-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 1090; display: none; }
        .ot-overlay.active { display: block; }
        .ot-modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); width: min(760px,96vw); max-height: 92vh; z-index: 1091; display: none; flex-direction: column; background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 14px; overflow: hidden; }
        .ot-modal.active { display: flex; }
        .ot-mhead { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid var(--border-color); }
        .ot-mhead h2 { margin: 0; font-size: 1.05rem; color: var(--text-primary); }
        .ot-mclose { background: none; border: none; color: var(--text-muted); font-size: 1.1rem; cursor: pointer; }
        .ot-mbody { padding: 18px 20px; overflow: auto; }
        .ot-mfoot { padding: 14px 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .ot-row { display: flex; gap: 12px; } .ot-row > * { flex: 1; }
        @media (max-width: 640px) { .ot-row { flex-direction: column; } }
        .ot-field { margin-bottom: 12px; }
        .ot-field label { display: block; font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .ot-field input, .ot-field select, .ot-field textarea { width: 100%; padding: 9px 11px; border-radius: 9px; border: 1px solid var(--border-color); background: var(--bg-tertiary); color: var(--text-primary); font-size: 14px; }
        .ot-sub { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--text-muted); margin: 14px 0 8px; }
        .ot-save { padding: 10px 18px; border: none; border-radius: 9px; background: var(--accent-primary); color: #fff; font-weight: 700; cursor: pointer; }
        .ot-msg { font-size: 12px; }
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content">
                <p style="color:var(--text-secondary);margin:0 0 16px;">Pedido → Cotação → Ordem de Transporte → Rota. A OT aprovada <strong>gera a rota</strong> existente (sem retrabalho). Use o <a href="<?php echo htmlspecialchars(sf_app_url('pages/simulador_rota.php')); ?>" style="color:var(--accent-primary);">Simulador de Rota</a> para custo/ANTT.</p>

                <div class="ot-sum" id="otSum"></div>

                <div class="fornc-toolbar">
                    <div class="fornc-search-block">
                        <label for="otBusca">Busca rápida</label>
                        <div class="fornc-search-inner">
                            <i class="fas fa-search"></i>
                            <input type="text" id="otBusca" placeholder="Nº, cliente, origem, destino...">
                        </div>
                    </div>
                    <div class="fornc-filters-inline">
                        <div class="fg">
                            <label for="otStatusFilter">Status</label>
                            <select id="otStatusFilter">
                                <option value="">Todas</option>
                                <option value="cotacao">Cotação</option>
                                <option value="aprovada">Aprovadas</option>
                                <option value="em_viagem">Em viagem</option>
                                <option value="concluida">Concluídas</option>
                                <option value="cancelada">Canceladas</option>
                            </select>
                        </div>
                        <div class="fg">
                            <label for="otPerPage">Por página</label>
                            <select id="otPerPage" class="filter-per-page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                    <div class="fornc-btn-row">
                        <button class="fornc-btn fornc-btn--primary" onclick="abrirOT()"><i class="fas fa-plus"></i> Nova OT</button>
                        <button type="button" id="helpBtn" class="fornc-btn fornc-btn--ghost fornc-btn--icon" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="fornc-empty-state" id="otEmptyState" role="status" aria-live="polite">
                    <p class="fornc-empty-state-title">Nenhuma ordem de transporte encontrada</p>
                    <p class="fornc-empty-state-hint">Crie uma cotação para começar o fluxo comercial: OT → aprovação → planejamento → rota → CT-e.</p>
                    <button type="button" class="fornc-btn fornc-btn--primary" onclick="abrirOT()"><i class="fas fa-plus"></i> Nova OT</button>
                </div>

                <div class="fornc-table-wrap" id="otTableWrap">
                    <table class="fornc-table ot-table">
                        <thead><tr><th>Nº</th><th>Cliente</th><th>Trecho</th><th>Veículo / Motorista</th><th>Frete</th><th>Status</th><th>Ações</th></tr></thead>
                        <tbody id="otBody"><tr><td colspan="7" class="ot-empty">Carregando…</td></tr></tbody>
                    </table>
                </div>
                <div class="pagination" id="otPag" style="display:none;"></div>
            </div>
            <?php include '../includes/footer.php'; ?>
        </div>
    </div>

    <!-- Modal criar/editar -->
    <div class="ot-overlay" id="otOverlay" onclick="fecharOT()"></div>
    <div class="ot-modal" id="otModal" role="dialog" aria-modal="true">
        <div class="ot-mhead"><h2 id="otTitle">Nova Ordem de Transporte</h2><button class="ot-mclose" onclick="fecharOT()"><i class="fas fa-times"></i></button></div>
        <div class="ot-mbody">
            <form id="otForm" onsubmit="return salvarOT(event)">
                <input type="hidden" id="ot_id">
                <div class="ot-sub">Cliente</div>
                <div class="ot-field"><label>Cliente cadastrado (opcional)</label><select id="cliente_id_sel"><option value="">— selecionar do cadastro —</option></select></div>
                <div class="ot-row">
                    <div class="ot-field"><label>Nome do cliente</label><input type="text" id="cliente_nome"></div>
                    <div class="ot-field"><label>CNPJ/CPF</label><input type="text" id="cliente_doc"></div>
                </div>
                <div class="ot-sub">Trecho e carga</div>
                <div class="ot-row">
                    <div class="ot-field"><label>Origem</label><input type="text" id="origem" placeholder="Cidade"></div>
                    <div class="ot-field" style="max-width:110px"><label>UF orig.</label><input type="text" id="estado_origem" maxlength="2"></div>
                    <div class="ot-field"><label>Destino</label><input type="text" id="destino" placeholder="Cidade"></div>
                    <div class="ot-field" style="max-width:110px"><label>UF dest.</label><input type="text" id="estado_destino" maxlength="2"></div>
                </div>
                <div class="ot-row">
                    <div class="ot-field"><label>Distância (km)</label><input type="number" id="distancia_km" min="0" step="1" value="0"></div>
                    <div class="ot-field"><label>Tipo de carga</label><select id="tipo_carga"><?php foreach ($tiposCarga as $k => $r): ?><option value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($r); ?></option><?php endforeach; ?></select></div>
                    <div class="ot-field" style="max-width:120px"><label>Eixos</label><select id="eixos"><?php for ($e = 2; $e <= 9; $e++): ?><option value="<?php echo $e; ?>" <?php echo $e === 5 ? 'selected' : ''; ?>><?php echo $e; ?></option><?php endfor; ?></select></div>
                    <div class="ot-field"><label>Peso (kg)</label><input type="number" id="peso_carga" min="0" step="1" value="0"></div>
                </div>
                <div class="ot-field"><label>Descrição da carga</label><input type="text" id="descricao_carga"></div>
                <div class="ot-sub">Valores e execução</div>
                <div class="ot-row">
                    <div class="ot-field"><label>Frete (R$)</label><input type="number" id="valor_frete" min="0" step="0.01" value="0"></div>
                    <div class="ot-field"><label>Custo estimado (R$)</label><input type="number" id="valor_custo_estimado" min="0" step="0.01" value="0"></div>
                </div>
                <div class="ot-row">
                    <div class="ot-field"><label>Veículo</label><select id="veiculo_id"><option value="">—</option><?php foreach ($veiculos as $v): ?><option value="<?php echo (int) $v['id']; ?>"><?php echo htmlspecialchars($v['placa'] . ($v['modelo'] ? ' · ' . $v['modelo'] : '')); ?></option><?php endforeach; ?></select></div>
                    <div class="ot-field"><label>Motorista</label><select id="motorista_id"><option value="">—</option><?php foreach ($motoristas as $m): ?><option value="<?php echo (int) $m['id']; ?>"><?php echo htmlspecialchars($m['nome']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="ot-row">
                    <div class="ot-field"><label>Coleta</label><input type="datetime-local" id="data_coleta"></div>
                    <div class="ot-field"><label>Entrega</label><input type="datetime-local" id="data_entrega"></div>
                </div>
                <div class="ot-field"><label>Observações</label><textarea id="observacoes" rows="2"></textarea></div>
            </form>
        </div>
        <div class="ot-mfoot">
            <span class="ot-msg" id="otMsg"></span>
            <button class="ot-save" onclick="salvarOT(event)"><i class="fas fa-save"></i> Salvar</button>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../js/theme.js"></script>
    <script src="../js/sidebar.js"></script>
    <script>
    var OT_CSRF = '<?php echo htmlspecialchars(csrf_token_get()); ?>';
    var otStatus = '', otBusca = '', otAll = [], otPage = 1, OT_PER = 10;
    function moeda(v){ return 'R$ ' + Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2}); }
    function esc(s){ var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
    var STLBL = { cotacao:'Cotação', aprovada:'Aprovada', em_viagem:'Em viagem', concluida:'Concluída', cancelada:'Cancelada' };

    function api(params){ return fetch('../api/ordens_transporte.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(params).toString()}).then(function(r){return r.json();}); }

    function carregar(){
        fetch('../api/ordens_transporte.php?action=list' + (otStatus?('&status='+otStatus):'')).then(function(r){return r.json();}).then(function(d){
            otAll = (d.success && d.ordens) ? d.ordens : [];
            otPage = 1; renderResumo(); renderOT();
        });
    }
    function renderResumo(){
        var cot=0,apr=0,viag=0,frete=0; otAll.forEach(function(o){
            if(o.status==='cotacao')cot++; else if(o.status==='aprovada')apr++; else if(o.status==='em_viagem')viag++;
            if(o.status!=='cancelada') frete+=Number(o.valor_frete||0);
        });
        document.getElementById('otSum').innerHTML=
            '<div class="ot-sc"><div class="n">'+otAll.length+'</div><div class="l">Ordens</div></div>'+
            '<div class="ot-sc blue"><div class="n">'+cot+'</div><div class="l">Em cotação</div></div>'+
            '<div class="ot-sc amber"><div class="n">'+(apr+viag)+'</div><div class="l">Aprov./viagem</div></div>'+
            '<div class="ot-sc green"><div class="n">'+moeda(frete)+'</div><div class="l">Frete (não canc.)</div></div>';
    }
    function renderOT(){
            var b = document.getElementById('otBody'), empty=document.getElementById('otEmptyState'), wrap=document.getElementById('otTableWrap');
            var lista = otBusca ? otAll.filter(function(o){
                return ((o.numero||'')+' '+(o.cliente_nome||'')+' '+(o.origem||'')+' '+(o.destino||'')+' '+(o.estado_origem||'')+' '+(o.estado_destino||'')).toLowerCase().indexOf(otBusca)>-1;
            }) : otAll;
            if(!lista.length){ empty.classList.add('visible'); wrap.style.display='none'; document.getElementById('otPag').style.display='none';
                empty.querySelector('.fornc-empty-state-hint').textContent = (otStatus||otBusca) ? 'Nenhuma ordem para esse filtro. Ajuste a busca ou o status.' : 'Crie uma cotação para começar o fluxo comercial: OT → aprovação → planejamento → rota → CT-e.';
                return; }
            empty.classList.remove('visible'); wrap.style.display='';
            var totFiltro=lista.length;
            var tp=Math.max(1, Math.ceil(totFiltro/OT_PER)); if(otPage>tp)otPage=tp;
            lista = lista.slice((otPage-1)*OT_PER, otPage*OT_PER);
            b.innerHTML = lista.map(function(o){
                var vm = (o.placa||'—') + (o.motorista_nome?(' · '+esc(o.motorista_nome)):'');
                var trecho = (esc(o.origem||o.estado_origem||'—')) + ' → ' + (esc(o.destino||o.estado_destino||'—'));
                var acts = '<button class="btn-icon edit-btn" onclick="editarOT('+o.id+')" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></button>';
                acts += '<button class="btn-icon view-btn" onclick="linkRastreio('+o.id+')" title="Link de rastreio p/ o cliente" aria-label="Link de rastreio"><i class="fas fa-share-nodes"></i></button>';
                if(o.status==='aprovada' || o.status==='em_viagem') acts += '<button class="btn-icon" onclick="emitirCte('+o.id+')" title="Emitir CT-e (pré-preenchido)" aria-label="Emitir CT-e"><i class="fas fa-file-invoice"></i></button>';
                if(o.status==='cotacao') acts += '<button class="btn-icon" onclick="mudar('+o.id+',\'aprovada\')" title="Aprovar" aria-label="Aprovar"><i class="fas fa-check"></i></button>';
                if(o.status==='aprovada' && !o.rota_id) acts += '<button class="btn-icon" onclick="gerarRota('+o.id+')" title="Gerar rota" aria-label="Gerar rota"><i class="fas fa-route"></i></button>';
                if(o.status==='em_viagem') acts += '<button class="btn-icon" onclick="mudar('+o.id+',\'concluida\')" title="Concluir" aria-label="Concluir"><i class="fas fa-flag-checkered"></i></button>';
                if(o.status!=='cancelada' && o.status!=='concluida') acts += '<button class="btn-icon delete-btn" onclick="mudar('+o.id+',\'cancelada\')" title="Cancelar" aria-label="Cancelar"><i class="fas fa-ban"></i></button>';
                acts += '<button class="btn-icon delete-btn" onclick="excluir('+o.id+')" title="Excluir" aria-label="Excluir"><i class="fas fa-trash"></i></button>';
                return '<tr><td class="num">'+esc(o.numero)+'</td><td>'+esc(o.cliente_nome||'—')+'</td><td>'+trecho+'</td><td>'+vm+'</td><td>'+moeda(o.valor_frete)+'</td>'+
                       '<td><span class="ot-badge st-'+o.status+'">'+(STLBL[o.status]||o.status)+'</span>'+(o.rota_id?(' <small style="color:var(--text-muted)">rota #'+o.rota_id+'</small>'):'')+'</td><td class="actions">'+acts+'</td></tr>';
            }).join('');
            renderPag(totFiltro);
    }
    function otFiltradosLen(){ return (otBusca ? otAll.filter(function(o){ return ((o.numero||'')+' '+(o.cliente_nome||'')+' '+(o.origem||'')+' '+(o.destino||'')+' '+(o.estado_origem||'')+' '+(o.estado_destino||'')).toLowerCase().indexOf(otBusca)>-1; }) : otAll).length; }
    function renderPag(tot){
        var pag=document.getElementById('otPag');
        var tp=Math.max(1, Math.ceil(tot/OT_PER)); if(otPage>tp)otPage=tp;
        var info = tp>1 ? ('Página '+otPage+' de '+tp+' ('+tot+' registros)') : (tot+' registro'+(tot===1?'':'s'));
        pag.innerHTML =
            '<a href="#" class="pagination-btn pagination-prev'+(otPage<=1?' disabled':'')+'" onclick="return otGo('+(otPage-1)+')"><i class="fas fa-chevron-left"></i></a>'+
            '<span class="pagination-info">'+info+'</span>'+
            '<a href="#" class="pagination-btn pagination-next'+(otPage>=tp?' disabled':'')+'" onclick="return otGo('+(otPage+1)+')"><i class="fas fa-chevron-right"></i></a>';
        pag.style.display='flex';
    }
    function otGo(p){ var tp=Math.max(1,Math.ceil(otFiltradosLen()/OT_PER)); if(p<1||p>tp)return false; otPage=p; renderOT(); window.scrollTo({top:0,behavior:'smooth'}); return false; }

    document.getElementById('otPerPage').addEventListener('change', function(e){ OT_PER=parseInt(e.target.value,10)||10; otPage=1; renderOT(); });
    document.getElementById('otStatusFilter').addEventListener('change', function(e){ otStatus=e.target.value; carregar(); });
    document.getElementById('otBusca').addEventListener('input', function(e){ otBusca=e.target.value.trim().toLowerCase(); otPage=1; renderOT(); });

    function setV(id,v){ document.getElementById(id).value = (v==null?'':v); }
    function abrirOT(){
        document.getElementById('otTitle').textContent='Nova Ordem de Transporte';
        ['ot_id','cliente_nome','cliente_doc','origem','estado_origem','destino','estado_destino','descricao_carga','observacoes','data_coleta','data_entrega'].forEach(function(f){setV(f,'');});
        setV('distancia_km',0); setV('peso_carga',0); setV('valor_frete',0); setV('valor_custo_estimado',0);
        setV('tipo_carga','geral'); setV('eixos',5); setV('veiculo_id',''); setV('motorista_id',''); document.getElementById('otMsg').textContent='';
        document.getElementById('otOverlay').classList.add('active'); document.getElementById('otModal').classList.add('active');
    }
    function editarOT(id){
        fetch('../api/ordens_transporte.php?action=get&id='+id).then(function(r){return r.json();}).then(function(d){
            if(!d.success){ return; } var o=d.ordem;
            document.getElementById('otTitle').textContent='Editar '+o.numero;
            setV('ot_id',o.id);
            ['cliente_nome','cliente_doc','origem','estado_origem','destino','estado_destino','distancia_km','tipo_carga','eixos','peso_carga','descricao_carga','valor_frete','valor_custo_estimado','veiculo_id','motorista_id','observacoes'].forEach(function(f){setV(f,o[f]);});
            setV('data_coleta', o.data_coleta?o.data_coleta.replace(' ','T').substring(0,16):'');
            setV('data_entrega', o.data_entrega?o.data_entrega.replace(' ','T').substring(0,16):'');
            document.getElementById('otMsg').textContent='';
            document.getElementById('otOverlay').classList.add('active'); document.getElementById('otModal').classList.add('active');
        });
    }
    function fecharOT(){ document.getElementById('otOverlay').classList.remove('active'); document.getElementById('otModal').classList.remove('active'); }

    function salvarOT(ev){ if(ev) ev.preventDefault();
        var msg=document.getElementById('otMsg'); msg.style.color='var(--text-muted)'; msg.textContent='Salvando…';
        var f={ action:'save', csrf_token:OT_CSRF, id:document.getElementById('ot_id').value };
        ['cliente_nome','cliente_doc','origem','estado_origem','destino','estado_destino','distancia_km','tipo_carga','eixos','peso_carga','descricao_carga','valor_frete','valor_custo_estimado','veiculo_id','motorista_id','data_coleta','data_entrega','observacoes'].forEach(function(k){ f[k]=document.getElementById(k).value; });
        api(f).then(function(d){ if(d.success){ msg.style.color='#16a34a'; msg.textContent='Salvo!'; setTimeout(function(){fecharOT();carregar();},500); } else { msg.style.color='#dc2626'; msg.textContent=d.error||'Erro.'; } });
        return false;
    }
    function mudar(id,status){ if(status==='cancelada' && !confirm('Cancelar esta OT?')) return; api({action:'status',id:id,status:status,csrf_token:OT_CSRF}).then(carregar); }
    function gerarRota(id){ if(!confirm('Gerar a rota desta OT? Ela entrará em "Em viagem".')) return;
        api({action:'gerar_rota',id:id,csrf_token:OT_CSRF}).then(function(d){ if(d.success){ alert('Rota #'+d.rota_id+' gerada.'); carregar(); } else { alert(d.error||'Erro ao gerar rota.'); } }); }
    function excluir(id){ if(!confirm('Excluir esta OT?')) return; api({action:'delete',id:id,csrf_token:OT_CSRF}).then(carregar); }
    var CTE_BASE = '<?php echo htmlspecialchars(sf_app_url('fiscal/pages/cte.php')); ?>';
    function emitirCte(id){ window.location.href = CTE_BASE + '?ot=' + id; }
    var RASTREIO_BASE = '<?php echo htmlspecialchars(sf_app_url('rastreio.php')); ?>';
    function linkRastreio(id){
        api({action:'gerar_token',id:id,csrf_token:OT_CSRF}).then(function(d){
            if(!d.success){ alert(d.error||'Erro.'); return; }
            var url = window.location.origin + RASTREIO_BASE + '?t=' + d.token;
            if(navigator.clipboard){ navigator.clipboard.writeText(url).then(function(){ alert('Link de rastreio copiado:\n\n'+url); }, function(){ prompt('Link de rastreio (copie):', url); }); }
            else { prompt('Link de rastreio (copie):', url); }
        });
    }

    // Integração com o cadastro de Clientes: popula o select e preenche nome/CNPJ.
    function carregarClientesOT(){
        var sel=document.getElementById('cliente_id_sel'); if(!sel) return;
        fetch('../api/clientes.php?action=options').then(function(r){return r.json();}).then(function(d){
            if(!d.success || !d.clientes) return;
            d.clientes.forEach(function(c){
                var o=document.createElement('option'); o.value=c.id;
                o.textContent=(c.nome_fantasia||c.razao_social)+(c.cnpj_cpf?(' · '+c.cnpj_cpf):'');
                o.dataset.nome=c.razao_social; o.dataset.doc=c.cnpj_cpf||'';
                sel.appendChild(o);
            });
        }).catch(function(){});
        sel.addEventListener('change', function(){
            var op=sel.options[sel.selectedIndex];
            if(op && op.value){ document.getElementById('cliente_nome').value=op.dataset.nome||''; document.getElementById('cliente_doc').value=op.dataset.doc||''; }
        });
    }
    document.addEventListener('DOMContentLoaded', function(){ carregar(); carregarClientesOT(); });
    </script>
    <?php $sf_help_key = 'ordens_transporte'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
    <?php include '../includes/scroll_to_top.php'; ?>
</body>
</html>
