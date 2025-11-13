<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
 
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');
 
// Función para conectar a la base de datos
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        error_log("Error de conexión: " . $e->getMessage());
        return null;
    }
}
 
// Función para enviar respuesta JSON
function sendResponse($success, $message, $redirect = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect !== null) {
        $response['redirect'] = $redirect;
    }
    
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
// Verificar que sea una petición POST
    sendResponse(false, 'Método no permitido');
}
 
// Obtener los datos JSON del cuerpo de la petición
$input = file_get_contents('php://input');
$data = json_decode($input, true);
 
// Validar que se recibieron los datos
if (!isset($data['usuario']) || !isset($data['password'])) {
    sendResponse(false, 'Datos incompletos');
}
 
$usuario = trim($data['usuario']);
$password = $data['password'];
 
// Validar que los campos no estén vacíos
if (empty($usuario) || empty($password)) {
    sendResponse(false, 'Por favor completa todos los campos');
}
 
 
try {
    // Conectar a la base de datos
    $conn = getDBConnection();
    
    if ($conn === null) {
        sendResponse(false, 'Error de conexión con la base de datos');
    }
    
    // Preparar la consulta para buscar el usuario
    $stmt = $conn->prepare("
        SELECT id_usuario, usuario, acceso
        FROM tbl_usuarios
        WHERE usuario = :usuario
        LIMIT 1
    ");
    
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    // Verificar si el usuario existe
    if (!$user) {
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
    // Verificar la contraseña (comparación directa - sin hash)
    if ($password === $user['acceso']) {
        // Contraseña correcta - iniciar sesión
        session_start();
        
        // Regenerar el ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Guardar información del usuario en la sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['num_empleado'] = $user['usuario'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        sendResponse(true, 'Inicio de sesión exitoso', '/taskManagement/adminDashboard');
        
    } else {
        // Contraseña incorrecta
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
} catch(PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    sendResponse(false, 'Error al procesar la solicitud');
}
?>