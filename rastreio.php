<?php
/**
 * Rastreio público de frete (Portal do Cliente) — escopo por token de UMA OT.
 * Sem login: o token (32 hex aleatórios) é a credencial. Read-only e sem dados
 * comerciais (não mostra frete/custo/margem). Isolado a uma única ordem.
 */
require_once __DIR__ . '/includes/db_connect.php';

$token = preg_replace('/[^a-f0-9]/i', '', (string) ($_GET['t'] ?? ''));
$ot = null;
if (strlen($token) >= 24) {
    try {
        $conn = getConnection();
        $st = $conn->prepare('SELECT o.numero, o.cliente_nome, o.origem, o.destino, o.estado_origem, o.estado_destino,
            o.status, o.data_coleta, o.data_entrega, o.updated_at, o.descricao_carga, v.placa
            FROM ordens_transporte o LEFT JOIN veiculos v ON v.id = o.veiculo_id
            WHERE o.token_rastreio = :t LIMIT 1');
        $st->execute([':t' => $token]);
        $ot = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        error_log('rastreio.php: ' . $e->getMessage());
    }
}

$fases = ['cotacao' => 'Cotação', 'aprovada' => 'Aprovada', 'em_viagem' => 'Em viagem', 'concluida' => 'Entregue'];
$ordem = array_keys($fases);
$atualIdx = $ot ? array_search($ot['status'], $ordem, true) : -1;
function rfmt($d) { return $d ? date('d/m/Y H:i', strtotime($d)) : '—'; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rastreio de Frete<?php echo $ot ? ' · ' . htmlspecialchars($ot['numero']) : ''; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Arial, sans-serif; background: #0f1824; color: #e8eef7; padding: 24px 16px; }
    .rt-wrap { max-width: 620px; margin: 0 auto; }
    .rt-head { text-align: center; margin-bottom: 22px; }
    .rt-head .logo { width: 54px; height: 54px; border-radius: 12px; background: #3b82f6; display: inline-flex; align-items: center; justify-content: center; font-size: 26px; }
    .rt-head h1 { font-size: 1.15rem; margin: 12px 0 2px; }
    .rt-head p { color: #94a3b8; margin: 0; font-size: .85rem; }
    .rt-card { background: #1a2332; border: 1px solid #2d3748; border-radius: 16px; padding: 22px; margin-bottom: 16px; }
    .rt-num { font-size: 1.4rem; font-weight: 800; }
    .rt-cli { color: #94a3b8; font-size: .9rem; margin-top: 2px; }
    .rt-trecho { display: flex; align-items: center; gap: 10px; margin: 18px 0; font-size: 1.05rem; }
    .rt-trecho .city { font-weight: 700; } .rt-trecho i { color: #3b82f6; }
    .rt-timeline { margin-top: 20px; }
    .rt-step { display: flex; align-items: flex-start; gap: 14px; padding-bottom: 20px; position: relative; }
    .rt-step:not(:last-child)::before { content: ""; position: absolute; left: 15px; top: 32px; bottom: 0; width: 2px; background: #2d3748; }
    .rt-step.done:not(:last-child)::before { background: #16a34a; }
    .rt-dot { width: 32px; height: 32px; border-radius: 50%; background: #243041; border: 2px solid #2d3748; display: flex; align-items: center; justify-content: center; font-size: 13px; flex-shrink: 0; z-index: 1; }
    .rt-step.done .rt-dot { background: #16a34a; border-color: #16a34a; color: #fff; }
    .rt-step.current .rt-dot { background: #3b82f6; border-color: #3b82f6; color: #fff; animation: rtp 2s infinite; }
    @keyframes rtp { 0%,100% { box-shadow: 0 0 0 0 rgba(59,130,246,.5); } 50% { box-shadow: 0 0 0 8px rgba(59,130,246,0); } }
    .rt-step .lbl { font-weight: 600; padding-top: 5px; } .rt-step:not(.done):not(.current) .lbl { color: #64748b; }
    .rt-meta { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 6px; font-size: .85rem; color: #94a3b8; }
    .rt-meta b { color: #e8eef7; font-weight: 600; }
    .rt-cancel { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.4); color: #fca5a5; border-radius: 12px; padding: 14px; text-align: center; }
    .rt-err { text-align: center; padding: 40px 20px; color: #94a3b8; }
    .rt-foot { text-align: center; color: #64748b; font-size: .75rem; margin-top: 10px; }
</style>
</head>
<body>
<div class="rt-wrap">
    <div class="rt-head">
        <div class="logo"><i class="fas fa-truck-fast"></i></div>
        <h1>Rastreio de Frete</h1>
        <p>Acompanhe sua carga em tempo real</p>
    </div>

    <?php if (!$ot): ?>
        <div class="rt-card rt-err">
            <i class="fas fa-circle-question" style="font-size:2rem;color:#475569;"></i>
            <p>Rastreio não encontrado. Confira o link recebido.</p>
        </div>
    <?php else: ?>
        <div class="rt-card">
            <div class="rt-num"><?php echo htmlspecialchars($ot['numero']); ?></div>
            <?php if ($ot['cliente_nome']): ?><div class="rt-cli"><?php echo htmlspecialchars($ot['cliente_nome']); ?></div><?php endif; ?>

            <div class="rt-trecho">
                <span class="city"><?php echo htmlspecialchars($ot['origem'] ?: ($ot['estado_origem'] ?: '—')); ?></span>
                <i class="fas fa-arrow-right-long"></i>
                <span class="city"><?php echo htmlspecialchars($ot['destino'] ?: ($ot['estado_destino'] ?: '—')); ?></span>
            </div>

            <?php if ($ot['status'] === 'cancelada'): ?>
                <div class="rt-cancel"><i class="fas fa-ban"></i> Este frete foi cancelado.</div>
            <?php else: ?>
                <div class="rt-timeline">
                    <?php foreach ($fases as $key => $label):
                        $idx = array_search($key, $ordem, true);
                        $cls = $idx < $atualIdx ? 'done' : ($idx === $atualIdx ? 'current' : '');
                        $ic = $idx < $atualIdx ? 'fa-check' : ($idx === $atualIdx ? 'fa-circle' : 'fa-circle');
                    ?>
                    <div class="rt-step <?php echo $cls; ?>">
                        <div class="rt-dot"><i class="fas <?php echo $ic; ?>"></i></div>
                        <div class="lbl"><?php echo $label; ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="rt-meta">
                <?php if ($ot['placa']): ?><div>Veículo: <b><?php echo htmlspecialchars($ot['placa']); ?></b></div><?php endif; ?>
                <div>Coleta: <b><?php echo rfmt($ot['data_coleta']); ?></b></div>
                <div>Entrega prevista: <b><?php echo rfmt($ot['data_entrega']); ?></b></div>
            </div>
        </div>
        <div class="rt-foot">Atualizado em <?php echo rfmt($ot['updated_at']); ?> · Frotec</div>
    <?php endif; ?>
</div>
</body>
</html>
