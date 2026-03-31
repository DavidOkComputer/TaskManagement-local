<?php
 // check_session.php

ob_start();
session_start();
header('Content-Type: application/json');

// Timeout de inactividad: debe coincidir con check_auth.php
$session_timeout = 300; // 5 minutos

// Verificar si hay sesion activa
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'session_expired' => true,
        'message' => 'No hay sesión activa.'
    ]);
    exit;
}

// Verificar si la sesion ya expiro por inactividad
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    session_unset();
    session_destroy();
    ob_clean();
    echo json_encode([
        'success' => false,
        'session_expired' => true,
        'message' => 'Tu sesión ha expirado por inactividad.'
    ]);
    exit;
}

// La sesion esta activa - renovar last_activity
$_SESSION['last_activity'] = time();

$tiempo_restante = $session_timeout - (time() - $_SESSION['last_activity']);

ob_clean();
echo json_encode([
    'success' => true,
    'session_expired' => false,
    'timeout_seconds' => $session_timeout,
    'remaining_seconds' => $session_timeout, // se acaba de renovar
    'message' => 'Sesión activa.'
]);
exit;