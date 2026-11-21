<?php
require_once __DIR__ . '/lib/adm_phase2_page.php';
adm_phase2_page('SLA', 'SLA e disponibilidade', 'Uptime, tempo de resposta e pico de usuários', 'sla_metrics.php', 'sla', 30000, '', 'Calculado internamente — use monitor externo em produção.');
