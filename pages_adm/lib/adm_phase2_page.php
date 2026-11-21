<?php
declare(strict_types=1);

/**
 * Helper para páginas Phase 2 do Control Center.
 */
function adm_phase2_page(
    string $pageTitle,
    string $ccTitle,
    string $ccSubtitle,
    string $apiEndpoint,
    string $renderMethod,
    int $pollMs = 0,
    string $extraHead = '',
    string $estimatedNote = ''
): void {
    require_once __DIR__ . '/../includes/adm_auth.php';
    require_once __DIR__ . '/../includes/adm_paths.php';
    sf_project_include('includes/sf_paths.php');
    $v = function_exists('sf_asset_v') ? sf_asset_v() : '1';

    $page_title = $pageTitle;
    $adm_extra_head = $extraHead;

    require_once __DIR__ . '/../includes/layout_start.php';
    $cc_title = $ccTitle;
    $cc_subtitle = $ccSubtitle;
    include __DIR__ . '/../components/page_header.php';
    echo '<div id="cc-root" class="adm-cc-dashboard"><div class="adm-cc-support-banner">Carregando…</div></div>';

    $api = json_encode($apiEndpoint, JSON_UNESCAPED_UNICODE);
    $fn = json_encode($renderMethod, JSON_UNESCAPED_UNICODE);
    $poll = (int) $pollMs;
    $note = json_encode($estimatedNote, JSON_UNESCAPED_UNICODE);
    $scriptUrl = htmlspecialchars(sf_app_url('pages_adm/js/adm-phase2-render.js?v=' . $v), ENT_QUOTES, 'UTF-8');

    $boot = "AdmPhase2.load({api:{$api},render:{$fn},poll:{$poll},estimated:{$note},root:document.getElementById('cc-root')});";

    $adm_extra_scripts = '<script src="' . $scriptUrl . '"></script><script>' . $boot . '</script>';
    require_once __DIR__ . '/../includes/layout_end.php';
}
