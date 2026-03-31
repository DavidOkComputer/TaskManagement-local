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

// TIMEOUT POR INACTIVIDAD - 5 MINUTOS (300s)
$session_timeout = 300; // 5 minutos en segundos

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $session_timeout)) {
    // sesion expiro por inactividad
    $was_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    session_unset();
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