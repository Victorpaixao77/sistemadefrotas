<?php
require_once __DIR__ . '/lib/adm_phase2_page.php';
require_once __DIR__ . '/includes/adm_paths.php';
sf_project_include('includes/sf_paths.php');
$leaflet = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>';
adm_phase2_page('Mapa de clientes', 'Mapa mundial', 'Clientes por cidade — online e offline', 'clients_geo.php', 'mapa', 0, $leaflet);
