<?php
/*
 * login.php
 * Maneja la autenticación de usuarios y redirecciona según el rol
 */

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
function sendResponse($success, $message, $redirect = null, $additionalData = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect !== null) {
        $response['redirect'] = $redirect;
    }
    
    // Agregar datos adicionales a la respuesta
    foreach ($additionalData as $key => $value) {
        $response[$key] = $value;
    }
    
    echo json_encode($response);
    exit;
}

// Función para obtener la URL de redirección según el rol
function getRedirectByRole($id_rol) {
    $redirects = [
        1 => '/taskManagement/adminDashboard/',    // Administrador
        2 => '/taskManagement/managerDashboard/',  // Gerente
        3 => '/taskManagement/userDashboard/'      // Usuario
    ];
    
    // Retorna la URL correspondiente o la de usuario por defecto
    return $redirects[$id_rol] ?? '/taskManagement/userDashboard/';
}

// Función para obtener el nombre del rol
function getRoleName($id_rol) {
    $roles = [
        1 => 'Administrador',
        2 => 'Gerente',
        3 => 'Usuario'
    ];
    
    return $roles[$id_rol] ?? 'Usuario';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
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
    
    // Preparar la consulta para buscar el usuario con información del rol y departamento
    $stmt = $conn->prepare("
        SELECT 
            u.id_usuario, 
            u.usuario, 
            u.acceso, 
            u.nombre,
            u.apellido,
            u.id_departamento,
            u.id_rol,
            u.e_mail,
            u.num_empleado,
            r.nombre AS nombre_rol,
            d.nombre AS nombre_departamento
        FROM tbl_usuarios u
        LEFT JOIN tbl_roles r ON u.id_rol = r.id_rol
        LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
        WHERE u.usuario = :usuario
        LIMIT 1
    ");
    
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    // Verificar si el usuario existe
    if (!$user) {
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
    // Verificar la contraseña
    // Nota: En producción, usar password_verify() con contraseñas hasheadas
    if ($password === $user['acceso']) {
        // Contraseña correcta - iniciar sesión
        session_start();
        
        // Regenerar el ID de sesión por seguridad
        session_regenerate_id(true);
        
        // Guardar información del usuario en la sesión
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['usuario'] = $user['usuario'];
        $_SESSION['logged_in'] = true;
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['apellido'] = $user['apellido'];
        $_SESSION['user_department'] = $user['id_departamento'];
        $_SESSION['nombre_departamento'] = $user['nombre_departamento'];
        $_SESSION['id_rol'] = $user['id_rol'];
        $_SESSION['nombre_rol'] = $user['nombre_rol'] ?? getRoleName($user['id_rol']);
        $_SESSION['e_mail'] = $user['e_mail'];
        $_SESSION['num_empleado'] = $user['num_empleado'];
        $_SESSION['login_time'] = time();
        
        // Obtener la URL de redirección según el rol del usuario
        $redirectUrl = getRedirectByRole($user['id_rol']);
        
        sendResponse(
            true, 
            'Inicio de sesión exitoso', 
            $redirectUrl,
            [
                'rol' => $user['nombre_rol'] ?? getRoleName($user['id_rol']),
                'nombre' => $user['nombre'] . ' ' . $user['apellido']
            ]
        );
        
    } else {
        // Contraseña incorrecta
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
} catch(PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    sendResponse(false, 'Error al procesar la solicitud');
}
?>