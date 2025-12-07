<?php
/*login.php para iniciar sesion*/

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
 
define('DB_HOST', 'localhost');
define('DB_NAME', 'task_management_db');
define('DB_USER', 'root');
define('DB_PASS', '');
 

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
 
function sendResponse($success, $message, $redirect = null, $additionalData = []) {
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if ($redirect !== null) {
        $response['redirect'] = $redirect;
    }
    
    foreach ($additionalData as $key => $value) {
        $response[$key] = $value;
    }
    
    echo json_encode($response);
    exit;
}


function getRedirectByRole($id_rol) {
    $redirects = [
        1 => '/taskManagement/adminDashboard/',    // Administrador
        2 => '/taskManagement/managerDashboard/',  // Gerente
        3 => '/taskManagement/userDashboard/'      // Usuario
    ];
    
    return $redirects[$id_rol] ?? '/taskManagement/userDashboard/';
}

function getRoleName($id_rol) {
    $roles = [
        1 => 'Administrador',
        2 => 'Gerente',
        3 => 'Usuario'
    ];
    
    return $roles[$id_rol] ?? 'Usuario';
}

function detectHashType($stored_hash) {
    // Bcrypt: comienza con $2y$, $2a$, o $2b$ (60 caracteres típicamente)
    if (preg_match('/^\$2[ayb]\$/', $stored_hash)) {
        return 'bcrypt';
    }
    
    // MD5: exactamente 32 caracteres hexadecimales
    if (preg_match('/^[a-f0-9]{32}$/i', $stored_hash) && strlen($stored_hash) === 32) {
        return 'md5';
    }
    
    // Todo lo demás se considera texto plano
    return 'plaintext';
}

function verifyPassword($password, $stored_hash) {
    $result = [
        'valid' => false,
        'needs_migration' => false,
        'hash_type' => 'unknown'
    ];
    
    // Detectar el tipo de hash
    $hash_type = detectHashType($stored_hash);
    $result['hash_type'] = $hash_type;
    
    switch ($hash_type) {
        case 'bcrypt':
            // Verificación segura con password_verify()
            if (password_verify($password, $stored_hash)) {
                $result['valid'] = true;
                
                // Verificar si necesita actualización (cost cambió o hay mejor algoritmo)
                if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT, ['cost' => 12])) {
                    $result['needs_migration'] = true;
                }
            }
            break;
            
        case 'md5':
            // Verificación MD5 legacy
            if (md5($password) === $stored_hash) {
                $result['valid'] = true;
                $result['needs_migration'] = true; // Siempre migrar MD5 a bcrypt
            }
            break;
            
        case 'plaintext':
            // Verificación de texto plano (comparación directa)
            if ($password === $stored_hash) {
                $result['valid'] = true;
                $result['needs_migration'] = true; // Siempre migrar texto plano a bcrypt
            }
            break;
    }
    
    return $result;
}

function migratePasswordToBcrypt($conn, $user_id, $password, $old_hash_type) {
    try {
        // Generar hash bcrypt seguro
        $new_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        
        if ($new_hash === false) {
            error_log("Error al generar hash bcrypt para usuario ID: {$user_id}");
            return false;
        }
        
        // Actualizar en la base de datos
        $stmt = $conn->prepare("UPDATE tbl_usuarios SET acceso = :acceso WHERE id_usuario = :id_usuario");
        $stmt->bindParam(':acceso', $new_hash, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Log de la migración exitosa
            $log_message = "Contraseña migrada exitosamente: ";
            $log_message .= "Usuario ID: {$user_id}, ";
            $log_message .= "Tipo anterior: {$old_hash_type}, ";
            $log_message .= "Tipo nuevo: bcrypt";
            error_log($log_message);
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error al migrar contraseña a bcrypt: " . $e->getMessage());
        return false;
    }
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
    $conn = getDBConnection();
    
    if ($conn === null) {
        sendResponse(false, 'Error de conexión con la base de datos');
    }
    
    // Buscar el usuario con información del rol y departamento
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
    
    // Verificar la contraseña (soporta bcrypt, MD5 y texto plano)
    $passwordCheck = verifyPassword($password, $user['acceso']);
    
    if ($passwordCheck['valid']) {
        
        // Si necesita migración, actualizar a bcrypt automáticamente
        if ($passwordCheck['needs_migration']) {
            $migrated = migratePasswordToBcrypt(
                $conn, 
                $user['id_usuario'], 
                $password, 
                $passwordCheck['hash_type']
            );
            
            if ($migrated) {
                error_log("Usuario '{$usuario}': Contraseña actualizada de {$passwordCheck['hash_type']} a bcrypt");
            }
        }
        
        session_start();
        
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
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
} catch(PDOException $e) {
    error_log("Error en login: " . $e->getMessage());
    sendResponse(false, 'Error al procesar la solicitud');
}
?>