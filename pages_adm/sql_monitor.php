<?php
require_once __DIR__ . '/lib/adm_phase2_page.php';
adm_phase2_page('SQL ao vivo', 'Monitor SQL', 'Queries em tempo real e histórico de rotas lentas', 'sql_stream.php', 'sqlMonitor', 15000);
