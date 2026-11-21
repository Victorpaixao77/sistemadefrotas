<?php
declare(strict_types=1);

/**
 * Ajuda contextual do Control Center (pages_adm).
 * Chave automática: adm_<arquivo.php> (index → adm_dashboard).
 * Override: $adm_help_key = 'adm_empresas'; ou $adm_help_disabled = true;
 */
function adm_page_help_key(): ?string
{
    if (!empty($GLOBALS['adm_iframe_mode'])) {
        return null;
    }
    if (!empty($GLOBALS['adm_help_disabled'])) {
        return null;
    }
    if (!empty($GLOBALS['adm_help_key'])) {
        return (string) $GLOBALS['adm_help_key'];
    }
    $base = basename($_SERVER['PHP_SELF'] ?? '', '.php');
    static $skip = ['login', 'logout', 'licencas'];
    if (in_array($base, $skip, true)) {
        return null;
    }
    return $base === 'index' ? 'adm_dashboard' : ('adm_' . $base);
}

function adm_page_help_bootstrap(): ?string
{
    $key = adm_page_help_key();
    if (!$key) {
        return null;
    }
    sf_project_include('includes/sf_page_help.php');
    $GLOBALS['adm_help_key_resolved'] = $key;
    return $key;
}
