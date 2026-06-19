<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
configure_session();
session_start();
require_authentication();
$page_title = 'Importar Rotas (Excel) - POC';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
<?php include '../includes/header.php'; ?>
<main style="padding:20px;">
    <h1><?php echo $page_title; ?></h1>
    <p>Envie um arquivo Excel (.xlsx). Cada aba deve conter os registros de uma placa (nome da aba = placa).</p>
    <p class="text-muted" style="font-size:0.9em;">Só entram linhas com <strong>data</strong> e <strong>logística</strong> válidas. Datas só com dia e mês usam o ano abaixo; se vazio, o ano no nome do arquivo (ex.: <code>Envelopes 2025.xlsx</code>); se não houver, o ano anterior ao atual (para não gravar em 2026 planilhas de 2025).</p>
    <form id="uploadForm" action="../api/import_rotas.php" method="post" enctype="multipart/form-data">
        <label>Arquivo Excel (.xlsx): <input type="file" name="file" accept=".xlsx,.xls" required></label>
        <br><br>
        <label>Ano para datas sem ano (opcional): <input type="number" name="ano_referencia" min="1990" max="2100" step="1" placeholder="ex.: 2025" style="width:7em;"></label>
        <br><br>
        <label><input type="checkbox" name="commit" value="1"> Efetuar inserção no sistema (desmarque para apenas preview)</label>
        <br><br>
        <button type="submit">Enviar e processar</button>
    </form>
    <div id="result" style="margin-top:20px;"></div>
</main>
<script>
document.getElementById('uploadForm').addEventListener('submit', function(e){
    e.preventDefault();
    var f = e.target;
    var data = new FormData(f);
    var result = document.getElementById('result');
    result.innerHTML = 'Processando...';
    fetch(f.action, { method: 'POST', body: data, credentials: 'same-origin' })
    .then(function (r) {
        return r.text().then(function (t) {
            var ct = (r.headers.get('content-type') || '').toLowerCase();
            if (!t || !t.trim()) {
                throw new Error('Resposta vazia do servidor (HTTP ' + r.status + ').');
            }
            if (ct.indexOf('application/json') === -1 && t.trim().charAt(0) !== '{') {
                throw new Error('Resposta não é JSON (HTTP ' + r.status + '). Início: ' + t.slice(0, 200));
            }
            try {
                return JSON.parse(t);
            } catch (e) {
                throw new Error('JSON inválido (HTTP ' + r.status + '): ' + (e && e.message ? e.message : e));
            }
        });
    })
    .then(function (j) {
        if (j.error) {
            result.innerHTML = '<pre style="color:red;">' + (j.message ? (j.error + '\n' + j.message) : j.error) + '</pre>';
            return;
        }
        result.innerHTML = '<h3>Resultado</h3><pre>' + JSON.stringify(j, null, 2) + '</pre>';
    }).catch(function (err) {
        result.innerHTML = '<pre style="color:red;">' + (err && err.message ? err.message : String(err)) + '</pre>';
    });
});
</script>
</body>
</html>
