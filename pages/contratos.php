<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/csrf.php';
require_once '../includes/sf_page_help.php';

configure_session();
session_start();
require_authentication();
if (!isset($_SESSION['empresa_id']) || empty($_SESSION['empresa_id'])) { header('Location: ../login.php'); exit; }

$page_title = 'Contratos';
$v = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$csrf = csrf_token_get();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestão de Frotas - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/png" href="../logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/theme.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/responsive.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/fornc-modern-page.css?v=<?php echo $v; ?>">
    <link rel="stylesheet" href="../css/sf-premium.css?v=<?php echo $v; ?>">
    <?php sf_page_help_css('..'); ?>
    <style>
        .ct-sum { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; margin:6px 0 16px; }
        .ct-s { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:12px; padding:12px 14px; } .ct-s b{display:block;font-size:1.5rem;font-weight:800;} .ct-s small{color:var(--text-secondary);font-size:.76rem;}
        .ct-s.venc b{color:#f59e0b;} .ct-s.exp b{color:#dc2626;}
        .ct-form { background:var(--bg-secondary); border:1px solid var(--border-color); border-radius:14px; padding:16px; margin-bottom:18px; display:none; } .ct-form.open{display:block;}
        .ct-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px; }
        .ct-grid label { display:block; font-size:.78rem; color:var(--text-secondary); margin-bottom:4px; }
        .ct-grid input, .ct-grid select, .ct-grid textarea { width:100%; padding:8px 10px; border:1px solid var(--border-color); border-radius:8px; background:var(--bg-primary); color:var(--text-primary); }
        .ct-table-wrap{overflow-x:auto;} .ct-badge{font-size:.68rem;font-weight:700;padding:2px 8px;border-radius:999px;}
        .ct-b-ok{background:rgba(22,163,74,.15);color:#16a34a;} .ct-b-venc{background:rgba(245,158,11,.15);color:#f59e0b;} .ct-b-exp{background:rgba(220,38,38,.15);color:#dc2626;} .ct-b-off{background:var(--bg-primary);color:var(--text-secondary);}
        .ct-del{background:none;border:none;color:var(--text-secondary);cursor:pointer;}
    </style>
</head>
<body class="sf-premium">
    <div class="app-container">
        <?php include '../includes/sidebar_pages.php'; ?>
        <div class="main-content">
            <?php include '../includes/header.php'; ?>
            <div class="dashboard-content fornc-page">
                <div class="fornc-toolbar">
                    <div class="fornc-search-block"><label for="ctBusca">Busca rápida</label><div class="fornc-search-inner"><i class="fas fa-search"></i><input type="text" id="ctBusca" placeholder="Título, parte, tipo..."></div></div>
                    <div class="fornc-filters-inline"><div class="fg"><label for="ctFiltroTipo">Tipo</label><select id="ctFiltroTipo"><option value="">Todos</option><option>cliente</option><option>fornecedor</option><option>seguro</option><option>locacao</option><option>financiamento</option><option>leasing</option><option>prestador</option><option>outro</option></select></div></div>
                    <div class="fornc-btn-row">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="ctNovo"><i class="fas fa-plus"></i> Novo contrato</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost fornc-btn--icon" id="helpBtn" title="Ajuda" aria-label="Ajuda"><i class="fas fa-question-circle"></i></button>
                    </div>
                </div>

                <div class="ct-sum">
                    <div class="ct-s"><b id="ctAtivos">—</b><small>Ativos</small></div>
                    <div class="ct-s venc"><b id="ctVencendo">—</b><small>Vencem em 30 dias</small></div>
                    <div class="ct-s exp"><b id="ctVencidos">—</b><small>Vencidos</small></div>
                    <div class="ct-s"><b id="ctValor">—</b><small>Valor (ativos)</small></div>
                </div>

                <div class="ct-form" id="ctForm">
                    <div class="ct-grid">
                        <div style="grid-column:1/-1"><label>Título *</label><input type="text" id="cTitulo" placeholder="Ex.: Seguro da frota 2026"></div>
                        <div><label>Tipo</label><select id="cTipo"><option>cliente</option><option>fornecedor</option><option selected>seguro</option><option>locacao</option><option>financiamento</option><option>leasing</option><option>prestador</option><option>outro</option></select></div>
                        <div><label>Contraparte</label><input type="text" id="cParte" placeholder="Ex.: Porto Seguro"></div>
                        <div><label>Valor (R$)</label><input type="number" id="cValor" step="0.01" placeholder="opcional"></div>
                        <div><label>Início</label><input type="date" id="cIni"></div>
                        <div><label>Vencimento</label><input type="date" id="cFim"></div>
                        <div style="grid-column:1/-1"><label>Observações</label><input type="text" id="cObs" placeholder="opcional"></div>
                    </div>
                    <div style="margin-top:12px;display:flex;gap:8px;align-items:center">
                        <button type="button" class="fornc-btn fornc-btn--primary" id="cSalvar"><i class="fas fa-check"></i> Salvar</button>
                        <button type="button" class="fornc-btn fornc-btn--ghost" id="cCancelar">Cancelar</button>
                        <span id="cMsg" style="font-size:.85rem"></span>
                    </div>
                </div>

                <div class="fornc-table-wrap ct-table-wrap">
                    <table class="fornc-table"><thead><tr><th>Contrato</th><th>Tipo</th><th>Contraparte</th><th>Valor</th><th>Vencimento</th><th>Situação</th><th></th></tr></thead>
                    <tbody id="ctTbody"><tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-secondary)">Carregando…</td></tr></tbody></table>
                </div>
            </div>
        </div>
    </div>
    <script src="../js/theme.js?v=<?php echo $v; ?>"></script>
    <script src="../js/sidebar.js?v=<?php echo $v; ?>"></script>
    <script>
    (function () {
        var CSRF='<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
        var esc=function(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;};
        var brl=function(n){return n==null?'—':'R$ '+(Number(n)||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});};
        var contratos=[];
        function fdata(d){ if(!d) return '—'; var x=new Date(d+'T00:00:00'); return x.toLocaleDateString('pt-BR'); }
        function situacao(c){
            if(c.status!=='ativo') return '<span class="ct-badge ct-b-off">'+esc(c.status)+'</span>';
            if(c.dias===null) return '<span class="ct-badge ct-b-ok">ativo</span>';
            if(c.dias<0) return '<span class="ct-badge ct-b-exp">vencido há '+Math.abs(c.dias)+'d</span>';
            if(c.dias<=30) return '<span class="ct-badge ct-b-venc">vence em '+c.dias+'d</span>';
            return '<span class="ct-badge ct-b-ok">em dia ('+c.dias+'d)</span>';
        }
        document.getElementById('ctNovo').addEventListener('click',function(){ document.getElementById('ctForm').classList.toggle('open'); });
        document.getElementById('cCancelar').addEventListener('click',function(){ document.getElementById('ctForm').classList.remove('open'); });
        document.getElementById('cSalvar').addEventListener('click',function(){
            var fd=new FormData();
            ['Titulo','Tipo','Parte','Valor','Ini','Fim','Obs'].forEach(function(k){ });
            fd.append('csrf_token',CSRF); fd.append('titulo',document.getElementById('cTitulo').value);
            fd.append('tipo',document.getElementById('cTipo').value); fd.append('parte',document.getElementById('cParte').value);
            fd.append('valor',document.getElementById('cValor').value); fd.append('data_inicio',document.getElementById('cIni').value);
            fd.append('data_fim',document.getElementById('cFim').value); fd.append('observacoes',document.getElementById('cObs').value);
            fetch('../api/contratos.php?action=criar',{method:'POST',credentials:'same-origin',body:fd}).then(function(r){return r.json();}).then(function(d){
                var m=document.getElementById('cMsg');
                if(d.success){ m.style.color='#16a34a'; m.textContent='Contrato salvo!'; document.getElementById('cTitulo').value=''; setTimeout(function(){document.getElementById('ctForm').classList.remove('open');m.textContent='';},900); carregar(); }
                else { m.style.color='#dc3545'; m.textContent=d.message||'Erro.'; }
            });
        });
        document.getElementById('ctBusca').addEventListener('input',render);
        document.getElementById('ctFiltroTipo').addEventListener('change',render);

        function carregar(){
            fetch('../api/contratos.php',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(!d.success) throw 0; var r=d.resumo;
                document.getElementById('ctAtivos').textContent=r.ativos; document.getElementById('ctVencendo').textContent=r.vencendo;
                document.getElementById('ctVencidos').textContent=r.vencidos; document.getElementById('ctValor').textContent=brl(r.valor_total);
                contratos=d.contratos||[]; render();
            }).catch(function(){ document.getElementById('ctTbody').innerHTML='<tr><td colspan="7" style="text-align:center;padding:20px;color:#dc3545">Erro ao carregar.</td></tr>'; });
        }
        function render(){
            var t=(document.getElementById('ctBusca').value||'').toLowerCase(), ft=document.getElementById('ctFiltroTipo').value;
            var lista=contratos.filter(function(c){ return (!ft||c.tipo===ft) && (!t||(c.titulo+' '+(c.parte||'')+' '+c.tipo).toLowerCase().indexOf(t)>=0); });
            if(!lista.length){ document.getElementById('ctTbody').innerHTML='<tr><td colspan="7" style="text-align:center;padding:20px;color:var(--text-secondary)">Nenhum contrato.</td></tr>'; return; }
            document.getElementById('ctTbody').innerHTML=lista.map(function(c){
                return '<tr><td><strong>'+esc(c.titulo)+'</strong>'+(c.observacoes?'<br><small style="color:var(--text-secondary)">'+esc(c.observacoes)+'</small>':'')+'</td>'+
                    '<td>'+esc(c.tipo)+'</td><td>'+esc(c.parte||'—')+'</td><td>'+brl(c.valor)+'</td><td>'+fdata(c.data_fim)+'</td><td>'+situacao(c)+'</td>'+
                    '<td class="actions"><button class="ct-del" data-id="'+c.id+'" title="Excluir"><i class="fas fa-trash"></i></button></td></tr>';
            }).join('');
            document.querySelectorAll('.ct-del').forEach(function(b){ b.addEventListener('click',function(){
                if(!confirm('Excluir este contrato?')) return;
                var fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('id',b.dataset.id);
                fetch('../api/contratos.php?action=excluir',{method:'POST',credentials:'same-origin',body:fd}).then(function(){ carregar(); });
            });});
        }
        carregar();
    })();
    </script>
    <?php include '../includes/scroll_to_top.php'; ?>
    <?php $sf_help_key = 'contratos'; include __DIR__ . '/../includes/sf_page_help_bundle.php'; ?>
    <?php sf_page_help_js('..'); ?>
</body>
</html>
