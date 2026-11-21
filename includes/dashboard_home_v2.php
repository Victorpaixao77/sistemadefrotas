<?php
/**
 * Layout executivo do dashboard (index.php).
 * Variáveis vêm do index.php + dashboard_home_load_all().
 */
if (!isset($conn) || !isset($empresa_id)) {
    return;
}

$veiculos_ativos = (int) ($veiculos_ativos ?? $total_veiculos ?? 0);
$rotas_hoje = (int) ($rotas_hoje ?? 0);
$manutencoes_30d = (int) ($manutencoes_30d ?? 0);
$home_updated_at = date('d/m/Y \à\s H:i');
$spark_rotas = is_array($spark_rotas ?? null) ? $spark_rotas : [];
$spark_abastecimentos = is_array($spark_abastecimentos ?? null) ? $spark_abastecimentos : [];
$spark_manutencoes = is_array($spark_manutencoes ?? null) ? $spark_manutencoes : [];
$spark_lucro = is_array($spark_lucro ?? null) ? $spark_lucro : [];
$atividades = is_array($atividades_recentes ?? null) ? $atividades_recentes : [];

function dh_delta_short($pct, $suffix = '')
{
    if ($pct === null) {
        return 'Sem comparação';
    }
    $cls = $pct >= 0 ? 'dh-kpi__delta--up' : 'dh-kpi__delta--down';
    $sign = $pct >= 0 ? '+' : '';
    return '<span class="dh-kpi__delta ' . $cls . '">' . $sign . number_format($pct, 1, ',', '.') . '% vs anterior' . ($suffix ? ' · ' . $suffix : '') . '</span>';
}

$margem_lucro_pct = ($can_view_financial_data && ($total_fretes ?? 0) > 0)
    ? round((($lucro_liquido ?? 0) / $total_fretes) * 100, 1)
    : 0;
$custos_totais = ($valor_total_abastecimentos ?? 0) + ($total_desp_viagem ?? 0) + ($total_desp_fixas ?? 0)
    + ($total_contas_pagas ?? 0) + ($total_manutencoes ?? 0) + ($total_pneu_manutencao ?? 0) + ($total_parcelas_financiamento ?? 0);

// Chips da central de alertas
$alert_chips = [];
if ($contas_pend > 0) {
    $alert_chips[] = [
        'icon' => 'file-invoice-dollar',
        'title' => $contas_pend . ' conta(s) vencida(s)',
        'meta' => 'Total R$ ' . number_format($contas_vencidas_valor ?? 0, 2, ',', '.'),
        'href' => 'pages/contas_pagar.php',
        'color' => '#ef4444',
    ];
}
if ($rotas_pend > 0) {
    $alert_chips[] = [
        'icon' => 'route',
        'title' => $rotas_pend . ' rota(s) pendentes',
        'meta' => 'Aguardando análise',
        'href' => 'pages/routes.php',
        'color' => '#f59e0b',
    ];
}
if ($cnh_vencidas > 0) {
    $alert_chips[] = [
        'icon' => 'id-card',
        'title' => $cnh_vencidas . ' CNH vencida(s)',
        'meta' => 'Risco operacional',
        'href' => 'pages/motorists.php',
        'color' => '#ef4444',
    ];
} elseif ($cnh_vencendo > 0) {
    $alert_chips[] = [
        'icon' => 'id-card',
        'title' => $cnh_vencendo . ' CNH vencendo',
        'meta' => 'Próximos 30 dias',
        'href' => 'pages/motorists.php',
        'color' => '#f59e0b',
    ];
}
if ($checklists_pend > 0) {
    $alert_chips[] = [
        'icon' => 'clipboard-check',
        'title' => $checklists_pend . ' checklist(s)',
        'meta' => 'Com problemas',
        'href' => 'pages/checklists.php',
        'color' => '#f59e0b',
    ];
}
if ($despesas_pend > 0) {
    $alert_chips[] = [
        'icon' => 'receipt',
        'title' => $despesas_pend . ' despesa(s)',
        'meta' => 'Pendentes de aprovação',
        'href' => 'pages/routes.php',
        'color' => '#3b82f6',
    ];
}
if (empty($alert_chips)) {
    $alert_chips[] = [
        'icon' => 'circle-check',
        'title' => 'Tudo em dia',
        'meta' => 'Nenhum alerta crítico',
        'href' => '',
        'color' => '#22c55e',
        'ok' => true,
    ];
}

// Insights (resumidos em 3 cards)
$insights_cards = [];
try {
    if ($can_view_financial_data && ($delta_abast_valor_pct ?? null) !== null && $delta_abast_valor_pct < -5) {
        $insights_cards[] = ['type' => 'opp', 'tag' => 'Oportunidade', 'text' => 'Consumo de combustível caiu ' . number_format(abs($delta_abast_valor_pct), 1, ',', '.') . '% vs período anterior.'];
    }
    if ($cnh_total_pend > 0) {
        $insights_cards[] = ['type' => 'warn', 'tag' => 'Atenção', 'text' => $cnh_total_pend . ' motorista(s) com CNH vencida ou vencendo — regularize para evitar multas.'];
    }
    if ($rotas_pend > 0) {
        $insights_cards[] = ['type' => 'tip', 'tag' => 'Recomendação', 'text' => 'Analise ' . $rotas_pend . ' rota(s) pendente(s) para liberar fechamento do período.'];
    }
} catch (Exception $e) { /* ignore */ }
if (empty($insights_cards)) {
    $insights_cards[] = ['type' => 'tip', 'tag' => 'Recomendação', 'text' => 'Acompanhe o BI Frota para tendências de custo e lucratividade por veículo.'];
}
$insights_cards = array_slice($insights_cards, 0, 3);

$gauge_deg = max(0, min(100, $margem_lucro_pct)) * 3.6;
?>
<div class="dh-home dashboard-print-hidden">

    <header class="dh-hero">
        <div>
            <h1 class="dh-hero__title"><?php echo htmlspecialchars($saudacao . ($primeiro_nome !== '' ? ', ' . $primeiro_nome : '') . '! 👋', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="dh-hero__sub">Aqui está o resumo da sua frota<?php echo $nome_empresa !== '' ? ' · ' . htmlspecialchars($nome_empresa, ENT_QUOTES, 'UTF-8') : ''; ?> · <?php echo htmlspecialchars(ucfirst($data_br), ENT_QUOTES, 'UTF-8'); ?>.</p>
        </div>
        <div class="dh-hero__actions">
            <form method="get" action="" class="index-period-form dh-toolbar">
                <label for="dashPeriod" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0">Período</label>
                <select name="period" id="dashPeriod" onchange="this.form.submit()" title="Filtrar indicadores">
                    <option value="year" <?php echo $home_period === 'year' ? 'selected' : ''; ?>>Ano atual</option>
                    <option value="month" <?php echo $home_period === 'month' ? 'selected' : ''; ?>>Mês atual</option>
                    <option value="quarter" <?php echo $home_period === 'quarter' ? 'selected' : ''; ?>>Trimestre</option>
                    <option value="all" <?php echo $home_period === 'all' ? 'selected' : ''; ?>>Todo período</option>
                </select>
            </form>
            <div class="dh-toolbar">
                <a href="<?php echo htmlspecialchars(sf_app_url('pages/centro_operacoes.php'), ENT_QUOTES, 'UTF-8'); ?>" class="fornc-btn fornc-btn--ghost" title="Centro de Operações (tempo real)"><i class="fas fa-satellite-dish"></i></a>
                <a href="?period=<?php echo urlencode($home_period); ?>&amp;refresh=1" class="fornc-btn fornc-btn--ghost" title="Atualizar"><i class="fas fa-rotate"></i></a>
                <button type="button" class="fornc-btn fornc-btn--muted" id="btnDashboardPrint" title="Imprimir"><i class="fas fa-print"></i></button>
                <button type="button" class="fornc-btn fornc-btn--ghost" id="btnDashboardCsv" title="Exportar CSV"><i class="fas fa-file-csv"></i></button>
                <button type="button" class="fornc-btn fornc-btn--ghost" id="helpBtn" title="Ajuda desta tela" aria-label="Ajuda desta tela"><i class="fas fa-question-circle"></i></button>
            </div>
            <div class="dh-novo-wrap">
                <button type="button" class="dh-novo-btn" id="dhNovoBtn" aria-expanded="false" aria-haspopup="true">
                    <i class="fas fa-plus" aria-hidden="true"></i> Novo registro <i class="fas fa-chevron-down" style="font-size:0.65rem;opacity:0.85" aria-hidden="true"></i>
                </button>
                <div class="dh-novo-menu" id="dhNovoMenu" role="menu">
                    <a href="pages/routes.php" role="menuitem"><i class="fas fa-route"></i> Nova rota</a>
                    <a href="pages/abastecimentos.php" role="menuitem"><i class="fas fa-gas-pump"></i> Abastecimento</a>
                    <a href="pages/manutencoes.php" role="menuitem"><i class="fas fa-wrench"></i> Manutenção</a>
                    <a href="pages/motorists.php" role="menuitem"><i class="fas fa-user"></i> Motorista</a>
                    <a href="pages/vehicles.php" role="menuitem"><i class="fas fa-truck"></i> Veículo</a>
                </div>
            </div>
        </div>
    </header>

    <section class="dh-kpis" aria-label="Indicadores principais">
        <a href="pages/vehicles.php" class="dh-kpi" style="--dh-accent:#3b82f6">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-truck" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Veículos ativos</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $veiculos_ativos; ?></div>
            <span class="dh-kpi__delta"><?php echo (int) $total_veiculos; ?> cadastrados</span>
        </a>
        <a href="pages/motorists.php" class="dh-kpi" style="--dh-accent:#22c55e">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Motoristas</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $total_motoristas; ?></div>
            <span class="dh-kpi__delta">Colaboradores ativos</span>
        </a>
        <a href="pages/routes.php" class="dh-kpi" style="--dh-accent:#8b5cf6">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Rotas hoje</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $rotas_hoje; ?></div>
            <?php echo $home_compare_available ? dh_delta_short($delta_rotas_concl_pct ?? null, (int) $total_rotas_concluidas . ' no período') : '<span class="dh-kpi__delta">' . (int) $total_rotas_concluidas . ' concluídas no período</span>'; ?>
            <?php echo dashboard_home_spark_svg($spark_rotas, '#8b5cf6'); ?>
        </a>
        <a href="pages/manutencoes.php" class="dh-kpi" style="--dh-accent:#f59e0b">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-wrench" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Manutenções</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $manutencoes_30d; ?></div>
            <span class="dh-kpi__delta">Últimos 30 dias</span>
            <?php echo dashboard_home_spark_svg($spark_manutencoes, '#f59e0b'); ?>
        </a>
        <a href="pages/abastecimentos.php" class="dh-kpi" style="--dh-accent:#06b6d4">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-gas-pump" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Abastecimentos</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $total_abastecimentos; ?></div>
            <?php echo $home_compare_available && isset($delta_abast_valor_pct) ? dh_delta_short($delta_abast_valor_pct) : '<span class="dh-kpi__delta">No período selecionado</span>'; ?>
            <?php echo dashboard_home_spark_svg($spark_abastecimentos, '#06b6d4'); ?>
        </a>
        <?php if ($can_view_financial_data): ?>
        <a href="pages/lucratividade.php" class="dh-kpi" style="--dh-accent:#10b981">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-sack-dollar" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Lucro líquido</span>
            </div>
            <div class="dh-kpi__value" style="font-size:1.25rem">R$ <?php echo number_format($lucro_liquido, 0, ',', '.'); ?></div>
            <?php echo $home_compare_available ? dh_delta_short($delta_lucro_pct ?? null) : '<span class="dh-kpi__delta">' . htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8') . '</span>'; ?>
            <?php echo dashboard_home_spark_svg($spark_lucro, '#10b981'); ?>
        </a>
        <?php else: ?>
        <a href="pages/routes.php" class="dh-kpi" style="--dh-accent:#10b981">
            <div class="dh-kpi__head">
                <span class="dh-kpi__icon"><i class="fas fa-route" aria-hidden="true"></i></span>
                <span class="dh-kpi__label">Rotas período</span>
            </div>
            <div class="dh-kpi__value"><?php echo (int) $total_rotas; ?></div>
            <span class="dh-kpi__delta"><?php echo (int) $total_rotas_concluidas; ?> concluídas</span>
        </a>
        <?php endif; ?>
    </section>

    <section class="dh-alerts" aria-label="Central de alertas">
        <div class="dh-alerts__head">
            <h2><i class="fas fa-shield-halved" aria-hidden="true"></i> Central de Alertas</h2>
            <?php if (count($alert_chips) > 1 || empty($alert_chips[0]['ok'])): ?>
            <span class="dh-alerts__badge"><?php echo count(array_filter($alert_chips, fn($c) => empty($c['ok']))); ?> ativo(s)</span>
            <?php endif; ?>
            <a href="pages/notificacoes.php" class="dh-alerts__link">Ver todos os alertas</a>
        </div>
        <div class="dh-alerts__grid">
            <?php foreach (array_slice($alert_chips, 0, 4) as $chip):
                $tag = !empty($chip['ok']) ? 'div' : 'a';
                $href = ($tag === 'a' && !empty($chip['href'])) ? ' href="' . htmlspecialchars($chip['href'], ENT_QUOTES, 'UTF-8') . '"' : '';
                $okCls = !empty($chip['ok']) ? ' dh-alert-chip--ok' : '';
            ?>
            <<?php echo $tag; ?> class="dh-alert-chip<?php echo $okCls; ?>" style="--dh-alert:<?php echo htmlspecialchars($chip['color'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $href; ?>>
                <span class="dh-alert-chip__icon"><i class="fas fa-<?php echo htmlspecialchars($chip['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
                <span>
                    <div class="dh-alert-chip__title"><?php echo htmlspecialchars($chip['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="dh-alert-chip__meta"><?php echo htmlspecialchars($chip['meta'], ENT_QUOTES, 'UTF-8'); ?></div>
                </span>
            </<?php echo $tag; ?>>
            <?php endforeach; ?>
        </div>
    </section>

    <?php if ($can_view_financial_data): ?>
    <div class="dh-grid dh-grid--3">
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-chart-line" style="color:#22c55e"></i> Receita × Custos</h3>
                <span class="dh-panel__meta"><?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="dh-panel__body dh-panel__body--chart">
                <canvas id="financialChart"></canvas>
            </div>
        </div>
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-chart-pie" style="color:#8b5cf6"></i> Distribuição de despesas</h3>
                <span class="dh-panel__meta">Período</span>
            </div>
            <div class="dh-panel__body dh-panel__body--chart">
                <canvas id="expensesDistributionChart"></canvas>
            </div>
        </div>
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-map-location-dot" style="color:#3b82f6"></i> Mapa da frota</h3>
                <span class="dh-panel__meta">Ao vivo</span>
            </div>
            <div class="dh-panel__body">
                <a href="pages/mapa_frota.php" class="dh-map-preview">
                    <div class="dh-map-preview__bg"><i class="fas fa-map" aria-hidden="true"></i></div>
                    <div class="dh-map-preview__foot">
                        <span class="dh-map-preview__live"><?php echo (int) ($gps_veiculos_sinal_recente ?? 0); ?> veículo(s) online</span>
                        <span>Abrir mapa <i class="fas fa-arrow-right" aria-hidden="true"></i></span>
                    </div>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="dh-grid dh-grid--3eq">
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-clock-rotate-left" style="color:#64748b"></i> Atividades recentes</h3>
                <span class="dh-panel__meta">Últimas operações</span>
            </div>
            <div class="dh-panel__body">
                <?php if (empty($atividades)): ?>
                <p style="color:var(--text-muted);font-size:0.85rem;margin:0">Nenhuma atividade recente.</p>
                <?php else: ?>
                <?php foreach ($atividades as $act): ?>
                <div class="dh-activity">
                    <span class="dh-activity__icon" style="color:<?php echo htmlspecialchars($act['color'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-<?php echo htmlspecialchars($act['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <div>
                        <div class="dh-activity__title"><?php echo htmlspecialchars($act['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="dh-activity__meta"><?php echo htmlspecialchars($act['meta'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                    <?php if ($act['value'] !== ''): ?><span class="dh-activity__value"><?php echo htmlspecialchars($act['value'], ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-wand-magic-sparkles" style="color:#8b5cf6"></i> Insights inteligentes</h3>
                <span class="dh-panel__meta">Sugestões</span>
            </div>
            <div class="dh-panel__body">
                <div class="dh-insights">
                    <?php foreach ($insights_cards as $ins): ?>
                    <div class="dh-insight dh-insight--<?php echo $ins['type'] === 'opp' ? 'opp' : ($ins['type'] === 'warn' ? 'warn' : 'tip'); ?>">
                        <div class="dh-insight__tag"><?php echo htmlspecialchars($ins['tag'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <p class="dh-insight__text"><?php echo htmlspecialchars($ins['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($can_view_financial_data): ?>
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-wallet" style="color:#10b981"></i> Resumo financeiro</h3>
                <span class="dh-panel__meta"><?php echo htmlspecialchars($home_period_label, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="dh-panel__body">
                <div class="dh-fin-summary">
                    <div class="dh-fin-item">
                        <div class="dh-fin-item__label">Faturamento</div>
                        <div class="dh-fin-item__val">R$ <?php echo number_format($total_fretes, 0, ',', '.'); ?></div>
                    </div>
                    <div class="dh-fin-item">
                        <div class="dh-fin-item__label">Custos totais</div>
                        <div class="dh-fin-item__val">R$ <?php echo number_format($custos_totais, 0, ',', '.'); ?></div>
                    </div>
                    <div class="dh-fin-item">
                        <div class="dh-fin-item__label">Comissões</div>
                        <div class="dh-fin-item__val">R$ <?php echo number_format($total_comissoes, 0, ',', '.'); ?></div>
                    </div>
                    <div class="dh-fin-item">
                        <div class="dh-fin-item__label">Lucro líquido</div>
                        <div class="dh-fin-item__val" style="color:<?php echo $lucro_liquido >= 0 ? '#22c55e' : '#ef4444'; ?>">R$ <?php echo number_format($lucro_liquido, 0, ',', '.'); ?></div>
                    </div>
                    <div class="dh-fin-gauge-wrap">
                        <div class="dh-fin-gauge" style="background:conic-gradient(#22c55e 0deg <?php echo $gauge_deg; ?>deg, var(--bg-tertiary) <?php echo $gauge_deg; ?>deg 360deg)">
                            <div class="dh-fin-gauge__inner">
                                <span><?php echo number_format($margem_lucro_pct, 1, ',', '.'); ?>%</span>
                            </div>
                        </div>
                        <div class="dh-fin-gauge__label">Margem de lucro no período</div>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="dh-panel">
            <div class="dh-panel__head">
                <h3><i class="fas fa-map-location-dot"></i> Mapa da frota</h3>
            </div>
            <div class="dh-panel__body">
                <a href="pages/mapa_frota.php" class="dh-map-preview">
                    <div class="dh-map-preview__bg"><i class="fas fa-map"></i></div>
                    <div class="dh-map-preview__foot">
                        <span class="dh-map-preview__live"><?php echo (int) ($gps_veiculos_sinal_recente ?? 0); ?> online</span>
                        <span>Abrir <i class="fas fa-arrow-right"></i></span>
                    </div>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <details class="dh-more no-print">
        <summary><i class="fas fa-table-columns" aria-hidden="true"></i> Indicadores detalhados e gráficos adicionais</summary>
        <div class="dh-more__body">
            <?php include __DIR__ . '/dashboard_home_kpis_detail.php'; ?>
        </div>
    </details>

    <p class="index-home-updated no-print" style="margin:0;font-size:0.72rem;color:var(--text-muted)">Atualizado em <?php echo htmlspecialchars($home_updated_at, ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('dhNovoBtn');
    var menu = document.getElementById('dhNovoMenu');
    if (!btn || !menu) return;
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = menu.classList.toggle('show');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function () {
        menu.classList.remove('show');
        btn.setAttribute('aria-expanded', 'false');
    });
});
</script>
