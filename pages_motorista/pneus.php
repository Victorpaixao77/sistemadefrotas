<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'db.php';

validar_sessao_motorista();

$motorista_id = (int) $_SESSION['motorista_id'];
$empresa_id = (int) $_SESSION['empresa_id'];
$page_title = 'Pneus — Inspeção';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../css/theme.css">
    <style>
        .pm-card { background: #fff; border-radius: 12px; padding: 16px; margin: 12px 0; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .pm-scan { display: flex; gap: 8px; }
        .pm-scan input { flex: 1; padding: 12px; font-size: 16px; border: 1px solid #ddd; border-radius: 8px; }
        .pm-field { margin: 10px 0; }
        .pm-field label { display: block; font-weight: 600; margin-bottom: 4px; }
        .pm-field input, .pm-field select, .pm-field textarea { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #ccc; }
        .pm-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
        .btn-pm { padding: 12px 16px; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; }
        .btn-pm-primary { background: #2563eb; color: #fff; }
        .btn-pm-secondary { background: #e5e7eb; color: #111; }
        #pmInfo { font-size: 0.9rem; color: #374151; }
    </style>
</head>
<body>
<div class="app-container">
    <div class="main-content" style="padding:12px;max-width:560px;margin:0 auto">
    <p><a href="index.php"><i class="fas fa-arrow-left"></i> Voltar</a></p>
    <h1><i class="fas fa-circle"></i> Pneus</h1>
    <p>Escanear QR, registrar inspeção e anexar foto pelo celular.</p>

    <div class="pm-card">
        <h2>Buscar pneu</h2>
        <div class="pm-scan">
            <input type="text" id="qrInput" placeholder="QR / código" inputmode="text" autocomplete="off">
            <button type="button" class="btn-pm btn-pm-primary" id="btnBuscar"><i class="fas fa-search"></i></button>
        </div>
        <div id="pmInfo" style="margin-top:12px"></div>
    </div>

    <div class="pm-card" id="formInspecao" style="display:none">
        <h2>Checklist de inspeção</h2>
        <input type="hidden" id="pneuId">
        <div class="pm-field">
            <label>Veículo (opcional)</label>
            <select id="veiculoId"><option value="">—</option></select>
        </div>
        <div class="pm-field">
            <label>Pressão (PSI)</label>
            <input type="number" id="pressao" step="0.1" min="0">
        </div>
        <div class="pm-field">
            <label>Sulco (mm)</label>
            <input type="number" id="sulco" step="0.1" min="0">
        </div>
        <div class="pm-field">
            <label><input type="checkbox" id="temCorte"> Corte visível</label>
        </div>
        <div class="pm-field">
            <label><input type="checkbox" id="temBolha"> Bolha</label>
        </div>
        <div class="pm-field">
            <label>Observações</label>
            <textarea id="obs" rows="2"></textarea>
        </div>
        <div class="pm-field">
            <label>Foto</label>
            <input type="file" id="foto" accept="image/*" capture="environment">
        </div>
        <div class="pm-actions">
            <button type="button" class="btn-pm btn-pm-primary" id="btnSalvar">Salvar inspeção</button>
            <a href="index.php" class="btn-pm btn-pm-secondary">Voltar</a>
        </div>
    </div>
    </div>
</div>
</div>
<script>
(function () {
    const api = 'api/pneus.php';
    let pneuAtual = null;

    fetch(api + '?action=veiculos').then(r => r.json()).then(function (d) {
        const sel = document.getElementById('veiculoId');
        (d.veiculos || []).forEach(function (v) {
            const o = document.createElement('option');
            o.value = v.id;
            o.textContent = v.placa + ' — ' + (v.modelo || '');
            sel.appendChild(o);
        });
    });

    function buscar() {
        const qr = document.getElementById('qrInput').value.trim();
        if (!qr) return;
        fetch(api + '?action=buscar_qr&qr=' + encodeURIComponent(qr))
            .then(r => r.json())
            .then(function (d) {
                if (!d.success) {
                    document.getElementById('pmInfo').innerHTML = '<span style="color:#b91c1c">' + (d.error || 'Não encontrado') + '</span>';
                    document.getElementById('formInspecao').style.display = 'none';
                    return;
                }
                pneuAtual = d.pneu;
                const c = d.carcaca || {};
                document.getElementById('pneuId').value = d.pneu.id;
                document.getElementById('pmInfo').innerHTML =
                    '<strong>' + d.pneu.numero_serie + '</strong> — ' + (d.pneu.marca || '') + ' ' + (d.pneu.medida || '') +
                    '<br>Recapagens: ' + (c.recapagens || 0) + ' · KM: ' + (c.km_acumulados || '—');
                document.getElementById('formInspecao').style.display = 'block';
            });
    }

    document.getElementById('btnBuscar').addEventListener('click', buscar);
    document.getElementById('qrInput').addEventListener('keydown', function (e) { if (e.key === 'Enter') buscar(); });

    document.getElementById('btnSalvar').addEventListener('click', function () {
        if (!pneuAtual) return;
        const payload = {
            pneu_id: parseInt(document.getElementById('pneuId').value, 10),
            veiculo_id: document.getElementById('veiculoId').value || null,
            pressao_psi: document.getElementById('pressao').value || null,
            sulco_mm: document.getElementById('sulco').value || null,
            tem_corte: document.getElementById('temCorte').checked ? 1 : 0,
            tem_bolha: document.getElementById('temBolha').checked ? 1 : 0,
            observacoes: document.getElementById('obs').value
        };
        fetch(api + '?action=inspecao', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json()).then(function (d) {
            if (!d.success) {
                alert(d.error || 'Erro ao salvar');
                return;
            }
            const foto = document.getElementById('foto').files[0];
            if (foto) {
                const fd = new FormData();
                fd.append('pneu_id', payload.pneu_id);
                fd.append('tipo', 'inspecao');
                fd.append('foto', foto);
                fetch(api + '?action=upload_foto', { method: 'POST', body: fd })
                    .then(function () { alert('Inspeção registrada com foto!'); })
                    .catch(function () { alert('Inspeção salva; falha no envio da foto.'); });
            } else {
                alert('Inspeção registrada!');
            }
        });
    });
})();
</script>
</body>
</html>
