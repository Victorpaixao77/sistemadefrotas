<?php
require_once 'config.php';

// Registra o logout
if (isset($_SESSION['motorista_id'])) {
    error_log('Motorista ID ' . $_SESSION['motorista_id'] . ' realizou logout');
}

// Realiza o logout
logout_motorista(); 