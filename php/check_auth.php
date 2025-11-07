<?php
session_start();

// revisar si esta loggeado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$session_timeout = 7200; // cerrar sesion despues de dos horas

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // sesion expiro
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit;
}

$_SESSION['last_activity'] = time();
?>