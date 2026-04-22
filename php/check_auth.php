<?php
session_start();

// revisar si esta loggeado
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Para peticiones AJAX, devolver JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Sesión no iniciada',
            'session_expired' => true
        ]);
        exit;
    }
    header('Location: ../index.html');
    exit;
}

//INACTIVIDAD  5 MINUTOS
$session_timeout = 300; // 5 minutos en segundos

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    $was_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

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

    if ($was_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Tu sesión ha expirado por inactividad.',
            'session_expired' => true
        ]);
        exit;
    }

    header('Location: ../index.html');
    exit;
}

// Actualizar timestamp de ultima actividad
$_SESSION['last_activity'] = time();