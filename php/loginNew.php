<?php
 
session_start();
 
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
 
require_once 'db_config.php';
 
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos
 
function isRateLimited() {
    $ip = $_SERVER['REMOTE_ADDR'];
    
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    
    $_SESSION['login_attempts'] = array_filter(
        $_SESSION['login_attempts'],
        function($timestamp) {
            return (time() - $timestamp) < LOCKOUT_TIME;
        }
    );
    
    return count($_SESSION['login_attempts']) >= MAX_LOGIN_ATTEMPTS;
}
 
function recordFailedAttempt() {
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = [];
    }
    $_SESSION['login_attempts'][] = time();
}
 
function clearLoginAttempts() {
    $_SESSION['login_attempts'] = [];
}
 
function sendResponse($success, $message, $redirect = null, $rol = null, $usuario = null, $id_usuario = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect !== null) {
        $response['redirect'] = $redirect;
    }
    
    if ($rol !== null) {
        $response['rol'] = $rol;
    }
    
    if ($usuario !== null) {
        $response['usuario'] = $usuario;
    }
    
    if ($id_usuario !== null) {
        $response['id_usuario'] = $id_usuario;
    }
    
    echo json_encode($response);
    exit;
}
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Método no permitido');
}
 
if (isRateLimited()) {
    $remaining = LOCKOUT_TIME - (time() - min($_SESSION['login_attempts']));
    $minutes = ceil($remaining / 60);
    sendResponse(false, "Demasiados intentos fallidos. Intente nuevamente en $minutes minutos");
}
 
$input = file_get_contents('php://input');
$data = json_decode($input, true);
 
if (!isset($data['usuario']) || !isset($data['password'])) {//validar informacion de input
    sendResponse(false, 'Datos incompletos');
}
 
$usuario = trim($data['usuario']);
$password = $data['password'];
 
if (empty($usuario) || empty($password)) {//validar campos vacios
    sendResponse(false, 'Por favor completa todos los campos');
}
 
try {
    $conn = getDBConnection();
    
    if ($conn === null) {
        sendResponse(false, 'Error de conexión con la base de datos');
    }
    
    //obtener ususario desde la base de datos incluido rol
    $stmt = $conn->prepare("
        SELECT id_usuario, num_empleado, contrasenia, rol
        FROM tbl_usuarios
        WHERE num_empleado = ?
        LIMIT 1
    ");
    
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        recordFailedAttempt();
        sendResponse(false, 'Número de empleado o contraseña incorrectos');
    }
    
    $passwordCorrect = false;
    $needsRehash = false;
    
    if (preg_match('/^\$2[ayb]\$.{56}$/', $user['contrasenia'])) {
        $passwordCorrect = password_verify($password, $user['contrasenia']);
    } else {
        if ($password === $user['contrasenia']) {
            $passwordCorrect = true;
            $needsRehash = true;
        }
    }
    
    if ($passwordCorrect) {
        // Actualizar hasheado si es necesario
        if ($needsRehash) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("
                UPDATE tbl_usuarios
                SET contrasenia = ?
                WHERE id_usuario = ?
            ");
            $updateStmt->bind_param("si", $hashedPassword, $user['id_usuario']);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        clearLoginAttempts();
        session_regenerate_id(true);
        
        // Guardar informacion de la sesion
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['num_empleado'] = $user['num_empleado'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Determinar redireccion de usuario basado en su rol
        $redirect = '';
        switch ($user['rol']) {
            case 'admin':
                $redirect = 'dashboardAdmin.php';
                break;
            case 'usuario':
                $redirect = 'dashboard.php';
                break;
            default:
                $redirect = 'dashboard.php';
        }
        
        sendResponse(
            true, 
            'Inicio de sesión exitoso', 
            $redirect, 
            $user['rol'],
            $user['num_empleado'],
            $user['id_usuario']
        );
        
    } else {
        // Contraseña incorrecta
        recordFailedAttempt();
        $remaining_attempts = MAX_LOGIN_ATTEMPTS - count($_SESSION['login_attempts']);
        
        if ($remaining_attempts > 0) {
            sendResponse(false, "Número de empleado o contraseña incorrectos. Intentos restantes: $remaining_attempts");
        } else {
            sendResponse(false, 'Número de empleado o contraseña incorrectos');
        }
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Error en login: " . $e->getMessage());
    sendResponse(false, 'Error al procesar la solicitud');
}
?>
