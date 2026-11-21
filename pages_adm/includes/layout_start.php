<?php
/**
 * Abertura do layout padrão do painel ADM.
 * Variáveis: $page_title, $adm_body_class, $adm_extra_css (array), $adm_extra_head (string HTML), $adm_iframe_mode (bool)
 */
if (!isset($page_title)) {
    $page_title = 'Painel ADM';
}
if (!isset($adm_iframe_mode)) {
    $adm_iframe_mode = false;
}
if (!isset($adm_extra_css) || !is_array($adm_extra_css)) {
    $adm_extra_css = [];
}

require_once __DIR__ . '/adm_paths.php';
sf_project_include('includes/sf_paths.php');
require_once __DIR__ . '/adm_help.php';
$adm_help_key_resolved = adm_page_help_bootstrap();
$GLOBALS['adm_asset_v'] = function_exists('sf_asset_v') ? sf_asset_v() : '1';
$adm_asset_v = $GLOBALS['adm_asset_v'];

$body_classes = ['adm-app'];
if (!empty($adm_body_class)) {
    $body_classes[] = trim((string) $adm_body_class);
}
if ($adm_iframe_mode) {
    $body_classes[] = 'adm-iframe-mode';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> — Frotec ADM</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(sf_app_url('logo.png')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/styles.css?v=' . $adm_asset_v)); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/theme.css?v=' . $adm_asset_v)); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/responsive.css?v=' . $adm_asset_v)); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/fornc-modern-page.css?v=' . $adm_asset_v)); ?>">
<?php foreach ($adm_extra_css as $cssHref): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssHref, ENT_QUOTES, 'UTF-8'); ?>">
<?php endforeach; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/adm-pages.css?v=' . $adm_asset_v)); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/adm-control-center.css?v=' . $adm_asset_v)); ?>">
<?php if (!empty($adm_help_key_resolved)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(sf_app_url('css/sf-page-help.css?v=' . $adm_asset_v)); ?>">
<?php endif; ?>
<?php if (!empty($adm_extra_head)) {
    echo $adm_extra_head;
} ?>
<?php if (!$adm_iframe_mode): ?>
<?php
if (!function_exists('csrf_token_get')) {
    sf_project_include('includes/csrf.php');
}
?>
<script>window.__SF_APP_BASE__=<?php echo json_encode(sf_app_web_base(), JSON_UNESCAPED_UNICODE); ?>;window.__ADM_API_BASE__=<?php echo json_encode(sf_app_web_base() . '/pages_adm/api/v1', JSON_UNESCAPED_SLASHES); ?>;window.__ADM_CSRF__=<?php echo json_encode(csrf_token_get(), JSON_UNESCAPED_UNICODE); ?>;window.__SF_CHARTJS_URL__=<?php echo json_encode(sf_chartjs_cdn_url(), JSON_UNESCAPED_SLASHES); ?>;</script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-core.js?v=' . $adm_asset_v)); ?>"></script>
<?php endif; ?>
</head>
<body class="<?php echo htmlspecialchars(implode(' ', $body_classes), ENT_QUOTES, 'UTF-8'); ?>">
<?php if (!$adm_iframe_mode): ?>
<div class="app-container">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/header_adm.php'; ?>
        <div class="dashboard-content fornc-page adm-page-content" id="conteudo-principal">
<?php else: ?>
        <div class="adm-iframe-content" id="conteudo-principal">
<?php endif; ?>
