<?php
/**
 * Acesso às APIs GPS do painel web (mapa da frota).
 */
require_once __DIR__ . '/permissions.php';

if (!function_exists('gps_require_web_panel_access')) {
    function gps_require_web_panel_access(): void
    {
        if (!function_exists('require_authentication')) {
            require_once __DIR__ . '/config.php';
        }
        require_authentication();
        if (!can_view_fleet_map()) {
            if (!function_exists('api_json_error')) {
                require_once __DIR__ . '/api_json.php';
            }
            api_json_error('Sem permissão para visualizar o mapa GPS.', 403);
        }
    }
}
