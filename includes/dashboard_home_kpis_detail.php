<?php
/** KPIs detalhados (colapsável) — variáveis do index.php */
?>
                <div class="dash-customize-row no-print" style="margin:0.25rem 0 0.5rem;">
                    <button type="button" class="fornc-btn fornc-btn--ghost" id="toggleLayoutBtn" title="Alternar entre cards normais e compactos"><i class="fas fa-compress"></i> Compactar</button>
                    <button type="button" class="fornc-btn fornc-btn--muted" id="restoreLayoutBtn" title="Restaurar a ordem padrão dos cards"><i class="fas fa-rotate-left"></i> Restaurar ordem</button>
                </div>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-truck-fast"></i> Operação</div>
                    <div class="dashboard-grid dashboard-print-hidden" id="dashboardGrid" data-dash-section="operacao">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-veiculos" data-href="pages/vehicles.php" tabindex="0" role="link" aria-label="Ver veículos">
                        <div class="card-header">
                            <i class="fas fa-truck kpi-card-icon" aria-hidden="true"></i>
                            <h3>Total de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_veiculos; ?></span>
                                <span class="metric-subtitle">Veículos cadastrados</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-motoristas" data-href="pages/motorists.php" tabindex="0" role="link" aria-label="Ver motoristas">
                        <div class="card-header">
                            <i class="fas fa-users kpi-card-icon" aria-hidden="true"></i>
                            <h3>Motoristas/Colaboradores</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_motoristas; ?></span>
                                <span class="metric-subtitle">Motoristas ativos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-rotas" data-href="pages/routes.php" tabindex="0" role="link" aria-label="Ver rotas">
                        <div class="card-header">
                            <i class="fas fa-route kpi-card-icon" aria-hidden="true"></i>
                            <h3>Rotas Realizadas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value"><?php echo $total_rotas_concluidas; ?></span>
                                <span class="metric-subtitle">De <?php echo $total_rotas; ?> rotas<?php echo $home_period !== 'all' ? ' no período' : ''; ?></span>
                            </div>
                            <?php if ($home_compare_available && $delta_rotas_concl_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_rotas_concl_pct), ENT_QUOTES, 'UTF-8'); ?> (concluídas)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-abastecimentos" data-href="pages/abastecimentos.php" tabindex="0" role="link" aria-label="Ver abastecimentos">
                        <div class="card-header">
                            <i class="fas fa-gas-pump kpi-card-icon" aria-hidden="true"></i>
                            <h3>Abastecimentos</h3>
                        </div>
                        <div class="card-body">
                            <div class="kpi-abast-dual">
                                <div class="metric kpi-abast-item">
                                    <span class="metric-value"><?php echo (int) $total_abastecimentos; ?></span>
                                    <span class="metric-subtitle">Registros no período</span>
                                </div>
                                <?php if ($can_view_financial_data): ?>
                                <div class="metric kpi-abast-item kpi-abast-item--valor">
                                    <span class="metric-value">R$ <?php echo number_format($valor_total_abastecimentos, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Total gasto</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($home_compare_available && $delta_abast_valor_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.45rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_abast_valor_pct), ENT_QUOTES, 'UTF-8'); ?> (valor)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
                </div>
                <?php if ($can_view_financial_data): ?>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-coins"></i> Custos</div>
                    <div class="dashboard-grid dashboard-print-hidden" data-dash-section="custos">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-desp-viagem" data-href="pages/routes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-suitcase-rolling kpi-card-icon" aria-hidden="true"></i>
                            <h3>Despesas de Viagem</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_viagem, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Custos variáveis</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-desp-fixas" data-href="pages/despesas_fixas.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-file-invoice-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Despesas Fixas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_desp_fixas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-contas" data-href="pages/contas_pagar.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-money-check-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Contas Pagas</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_contas_pagas, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Outros pagamentos</span>
                            </div>
                        </div>
                    </div>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-manut-veiculos" data-href="pages/manutencoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-screwdriver-wrench kpi-card-icon" aria-hidden="true"></i>
                            <h3>Manutenções de Veículos</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_manutencoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Veículos</span>
                            </div>
                        </div>
                    </div>
                    <?php if ($can_access_pneus): ?>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-manut-pneus" data-href="pages/manutencao_pneus.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-circle-dot kpi-card-icon" aria-hidden="true"></i>
                            <h3>Manutenções de Pneus</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_pneu_manutencao, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pneus</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-financiamento" data-href="pages/financiamento.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-landmark kpi-card-icon" aria-hidden="true"></i>
                            <h3>Parcelas de Financiamento</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_parcelas_financiamento, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas no período</span>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="dash-section">
                    <div class="dash-section-title"><i class="fas fa-chart-line"></i> Financeiro</div>
                    <div class="dashboard-grid dashboard-print-hidden" data-dash-section="financeiro">
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-fretes" data-href="<?php echo $can_view_financial_data ? 'pages/lucratividade.php' : 'pages/routes.php'; ?>" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas <?php echo $can_view_financial_data ? 'fa-sack-dollar' : 'fa-route'; ?> kpi-card-icon" aria-hidden="true"></i>
                            <h3><?php echo $can_view_financial_data ? 'Faturamento (Fretes)' : 'Rotas no período'; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php if ($can_view_financial_data): ?>
                                    <span class="metric-value">R$ <?php echo number_format($total_fretes, 2, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Receita bruta</span>
                                    <?php if ($home_compare_available && $delta_fretes_pct !== null): ?>
                                    <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_fretes_pct), ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="metric-value"><?php echo number_format($total_rotas, 0, ',', '.'); ?></span>
                                    <span class="metric-subtitle">Total de rotas (período selecionado)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-comissoes" data-href="pages/comissoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-hand-holding-dollar kpi-card-icon" aria-hidden="true"></i>
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value">R$ <?php echo number_format($total_comissoes, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Pagas a motoristas</span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="dashboard-card dashboard-card--link" data-card-id="kpi-comissoes-pct" data-href="pages/comissoes.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-percent kpi-card-icon" aria-hidden="true"></i>
                            <h3>Comissões</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <?php
                                $percentual_comissao = $total_fretes > 0 ? ($total_comissoes / $total_fretes) * 100 : 0;
                                ?>
                                <span class="metric-value"><?php echo number_format($percentual_comissao, 1, ',', '.'); ?>%</span>
                                <span class="metric-subtitle">Percentual médio (rotas aprovadas no período)</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($can_view_financial_data): ?>
                    <div class="dashboard-card index-kpi-lucro dashboard-card--link" data-card-id="kpi-lucro" data-href="pages/lucratividade.php" tabindex="0" role="link">
                        <div class="card-header">
                            <i class="fas fa-chart-line kpi-card-icon" aria-hidden="true"></i>
                            <h3>Lucro líquido geral</h3>
                        </div>
                        <div class="card-body">
                            <div class="metric">
                                <span class="metric-value index-kpi-lucro__value">R$ <?php echo number_format($lucro_liquido, 2, ',', '.'); ?></span>
                                <span class="metric-subtitle">Lucro no período selecionado</span>
                            </div>
                            <?php if ($home_compare_available && $delta_lucro_pct !== null): ?>
                            <p class="index-kpi-delta" style="margin:0.5rem 0 0;"><?php echo htmlspecialchars(dashboard_home_format_delta($delta_lucro_pct), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>

<?php if (!empty($proximas_rotas)): ?>
<div class="card index-proximas-rotas" style="margin-top:1rem">
    <div class="card-header">
        <h2><i class="fas fa-calendar-week" aria-hidden="true"></i> Próximas rotas (7 dias)</h2>
    </div>
    <div class="card-body">
        <ul class="index-proximas-list">
            <?php foreach ($proximas_rotas as $pr):
                $prData = $pr['data_saida'] ?? $pr['data_rota'] ?? '';
                $prOrigem = trim(($pr['cidade_origem'] ?? '') . ($pr['estado_origem'] ? '/' . $pr['estado_origem'] : ''));
                $prDestino = trim(($pr['cidade_destino'] ?? '') . ($pr['estado_destino'] ? '/' . $pr['estado_destino'] : ''));
                if ($prOrigem === '') { $prOrigem = (string) ($pr['estado_origem'] ?? '—'); }
                if ($prDestino === '') { $prDestino = (string) ($pr['estado_destino'] ?? '—'); }
            ?>
            <li>
                <a href="pages/routes.php" class="index-proximas-item">
                    <span class="index-proximas-data"><?php echo $prData ? htmlspecialchars(date('d/m', strtotime($prData)), ENT_QUOTES, 'UTF-8') : '—'; ?></span>
                    <span class="index-proximas-rota"><?php echo htmlspecialchars($prOrigem . ' → ' . $prDestino, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="index-proximas-meta"><?php echo htmlspecialchars(($pr['motorista'] ?? '') . ' · ' . ($pr['placa'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php if ($can_view_financial_data): ?>
<div class="analytics-section" id="dashboardAnalyticsExtra" style="margin-top:1rem">
    <div class="section-header"><h2>Gráficos adicionais</h2></div>
    <div class="analytics-grid">
        <div class="analytics-card">
            <div class="card-header"><h3>Comissões pagas</h3></div>
            <div class="card-body" style="min-height:220px"><canvas id="commissionsChart"></canvas></div>
        </div>
        <div class="analytics-card">
            <div class="card-header"><h3>Lucro líquido</h3></div>
            <div class="card-body" style="min-height:220px"><canvas id="netRevenueChart"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>
