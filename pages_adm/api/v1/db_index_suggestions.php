<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/lib/adm_api.php';
require_once dirname(__DIR__, 2) . '/lib/adm_db.php';
$pdo = adm_api_boot();
$dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
adm_api_ok(['suggestions' => adm_db_index_suggestions($pdo, $dbName)]);
