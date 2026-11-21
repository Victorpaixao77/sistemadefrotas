<?php
require_once __DIR__ . '/adm_paths.php';
sf_project_include('includes/sf_paths.php');
require_once __DIR__ . '/adm_help.php';
$adm_asset_v = $GLOBALS['adm_asset_v'] ?? '1';
$adm_iframe_mode = !empty($adm_iframe_mode);
$adm_help_key_end = $GLOBALS['adm_help_key_resolved'] ?? adm_page_help_key();
?>
<?php if (!$adm_iframe_mode): ?>
        </div><!-- .adm-page-content -->
        <?php sf_project_include('includes/footer.php'); ?>
    </div><!-- .main-content -->
</div><!-- .app-container -->
<?php else: ?>
        </div><!-- .adm-iframe-content -->
<?php endif; ?>

<?php if ($adm_help_key_end && !$adm_iframe_mode): ?>
<?php
    $sf_help_key = (string) $adm_help_key_end;
    $sf_help_modal_extra_class = 'adm-modal-legacy sf-help-modal-adm';
    // require direto no escopo atual: o bundle LÊ $sf_help_key/$sf_help_modal_extra_class,
    // e sf_project_include() incluiria dentro do escopo da função (variáveis não chegariam).
    require SF_PROJECT_ROOT . '/includes/sf_page_help_bundle.php';
?>
<script src="<?php echo htmlspecialchars(sf_app_url('js/sf_page_help.js?v=' . $adm_asset_v)); ?>"></script>
<?php endif; ?>

<?php if (!$adm_iframe_mode): ?>
<script src="<?php echo htmlspecialchars(sf_app_url('js/theme.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('js/sidebar.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-header.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-telemetry.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-sidebar.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-sidebar-search.js?v=' . $adm_asset_v)); ?>"></script>
<script src="<?php echo htmlspecialchars(sf_app_url('pages_adm/js/adm-sidebar-favorites.js?v=' . $adm_asset_v)); ?>"></script>
<?php endif; ?>
<?php
if (!empty($adm_extra_scripts)) {
    echo $adm_extra_scripts;
}
include SF_PROJECT_ROOT . '/includes/scroll_to_top.php';
?>
</body>
</html>
