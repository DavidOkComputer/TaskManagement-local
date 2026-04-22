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
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
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