<?php
/** KPI card: $kpi_id, $kpi_label, $kpi_icon, $kpi_value, $kpi_delta, $kpi_href */
?>
<div class="adm-cc-kpi"<?php echo !empty($kpi_id) ? ' id="' . htmlspecialchars($kpi_id, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
    <?php if (!empty($kpi_href)): ?><a href="<?php echo htmlspecialchars($kpi_href, ENT_QUOTES, 'UTF-8'); ?>" class="adm-cc-kpi__link"><?php endif; ?>
    <div class="adm-cc-kpi__top">
        <span class="adm-cc-kpi__icon"><i class="fas <?php echo htmlspecialchars($kpi_icon ?? 'fa-chart-line', ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i></span>
        <span class="adm-cc-kpi__label"><?php echo htmlspecialchars($kpi_label, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="adm-cc-kpi__value adm-cc-skeleton-target"><?php echo htmlspecialchars((string) ($kpi_value ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if (isset($kpi_delta) && $kpi_delta !== ''): ?>
    <div class="adm-cc-kpi__delta"><?php echo $kpi_delta; ?></div>
    <?php endif; ?>
    <?php if (!empty($kpi_href)): ?></a><?php endif; ?>
</div>
