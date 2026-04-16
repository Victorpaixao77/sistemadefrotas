<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../includes/sf_paths.php';

configure_session();
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: ' . sf_app_url('login.php'));
    exit;
}

// Dashboard fiscal desativado por enquanto.
header('Location: ' . sf_app_url('fiscal/pages/nfe.php'));
exit;
