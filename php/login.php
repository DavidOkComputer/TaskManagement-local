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
    if (preg_match('/^\$2[ayb]\$/', $stored_hash)) {
        return 'bcrypt';
    }
    
    if (preg_match('/^[a-f0-9]{32}$/i', $stored_hash) && strlen($stored_hash) === 32) {
        return 'md5';
    }
    
    return 'plaintext';
}

function verifyPassword($password, $stored_hash) {
    $result = [
        'valid' => false,
        'needs_migration' => false,
        'hash_type' => 'unknown'
    ];
    
    $hash_type = detectHashType($stored_hash);
    $result['hash_type'] = $hash_type;
    
    switch ($hash_type) {
        case 'bcrypt':
            if (password_verify($password, $stored_hash)) {
                $result['valid'] = true;
                
                if (password_needs_rehash($stored_hash, PASSWORD_DEFAULT, ['cost' => 12])) {
                    $result['needs_migration'] = true;
                }
            }
            break;
            
        case 'md5':
            if (md5($password) === $stored_hash) {
                $result['valid'] = true;
                $result['needs_migration'] = true;
            }
            break;
            
        case 'plaintext':
            if ($password === $stored_hash) {
                $result['valid'] = true;
                $result['needs_migration'] = true;
            }
            break;
    }
    
    return $result;
}

function migratePasswordToBcrypt($conn, $user_id, $password, $old_hash_type) {
    try {
        $new_hash = password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
        
        if ($new_hash === false) {
            error_log("Error al generar hash bcrypt para usuario ID: {$user_id}");
            return false;
        }
        
        $stmt = $conn->prepare("UPDATE tbl_usuarios SET acceso = :acceso WHERE id_usuario = :id_usuario");
        $stmt->bindParam(':acceso', $new_hash, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
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
 
$input = file_get_contents('php://input');
$data = json_decode($input, true);
 
if (!isset($data['usuario']) || !isset($data['password'])) {
    sendResponse(false, 'Datos incompletos');
}
 
$usuario = trim($data['usuario']);
$password = $data['password'];
 
if (empty($usuario) || empty($password)) {
    sendResponse(false, 'Por favor completa todos los campos');
}
 
try {
    $conn = getDBConnection();
    
    if ($conn === null) {
        sendResponse(false, 'Error de conexión con la base de datos');
    }
    
    $stmt = $conn->prepare("
        SELECT 
            u.id_usuario, 
            u.usuario, 
            u.acceso, 
            u.nombre,
            u.apellido,
            u.e_mail,
            u.num_empleado,
            u.foto_perfil
        FROM tbl_usuarios u
        WHERE u.usuario = :usuario
        LIMIT 1
    ");
    
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();
    
    $user = $stmt->fetch();
    
    if (!$user) {
        sendResponse(false, 'Usuario o contraseña incorrectos');
    }
    
    // Verificar la contraseña
    $passwordCheck = verifyPassword($password, $user['acceso']);
    
    if ($passwordCheck['valid']) {
        
        // Migrar contraseña si es necesario
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
        
        $stmt_role = $conn->prepare("
            SELECT 
                ur.id_rol,
                ur.id_departamento,
                ur.es_principal,
                r.nombre AS nombre_rol,
                d.nombre AS nombre_departamento
            FROM tbl_usuario_roles ur
            JOIN tbl_roles r ON ur.id_rol = r.id_rol
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
            WHERE ur.id_usuario = :id_usuario 
                AND ur.es_principal = 1 
                AND ur.activo = 1
            LIMIT 1
        ");
        
        $stmt_role->bindParam(':id_usuario', $user['id_usuario'], PDO::PARAM_INT);
        $stmt_role->execute();
        $role_info = $stmt_role->fetch();
        
        // Si no tiene rol principal, buscar cualquier rol activo
        if (!$role_info) {
            $stmt_role = $conn->prepare("
                SELECT 
                    ur.id_rol,
                    ur.id_departamento,
                    ur.es_principal,
                    r.nombre AS nombre_rol,
                    d.nombre AS nombre_departamento
                FROM tbl_usuario_roles ur
                JOIN tbl_roles r ON ur.id_rol = r.id_rol
                JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
                WHERE ur.id_usuario = :id_usuario 
                    AND ur.activo = 1
                ORDER BY ur.id_rol ASC
                LIMIT 1
            ");
            
            $stmt_role->bindParam(':id_usuario', $user['id_usuario'], PDO::PARAM_INT);
            $stmt_role->execute();
            $role_info = $stmt_role->fetch();
        }
        
        // Si aún no tiene rol, error
        if (!$role_info) {
            sendResponse(false, 'Usuario sin rol asignado. Contacte al administrador.');
        }
        
        $stmt_all_roles = $conn->prepare("
            SELECT 
                ur.id_usuario_roles,
                ur.id_rol,
                ur.id_departamento,
                ur.es_principal,
                r.nombre AS nombre_rol,
                d.nombre AS nombre_departamento
            FROM tbl_usuario_roles ur
            JOIN tbl_roles r ON ur.id_rol = r.id_rol
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
            WHERE ur.id_usuario = :id_usuario 
                AND ur.activo = 1
            ORDER BY ur.es_principal DESC, d.nombre ASC
        ");
        
        $stmt_all_roles->bindParam(':id_usuario', $user['id_usuario'], PDO::PARAM_INT);
        $stmt_all_roles->execute();
        $all_roles = $stmt_all_roles->fetchAll();
        
        // Obtener departamentos donde es gerente
        $managed_departments = [];
        foreach ($all_roles as $r) {
            if ($r['id_rol'] == 2) {
                $managed_departments[] = [
                    'id_departamento' => (int)$r['id_departamento'],
                    'nombre_departamento' => $r['nombre_departamento']
                ];
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
        $_SESSION['e_mail'] = $user['e_mail'];
        $_SESSION['num_empleado'] = $user['num_empleado'];
        $_SESSION['foto_perfil'] = $user['foto_perfil'];
        $_SESSION['login_time'] = time();
        
        // Rol y departamento principal (desde tbl_usuario_roles)
        $_SESSION['id_rol'] = $role_info['id_rol'];
        $_SESSION['nombre_rol'] = $role_info['nombre_rol'] ?? getRoleName($role_info['id_rol']);
        $_SESSION['id_departamento'] = $role_info['id_departamento'];
        $_SESSION['departamento_nombre'] = $role_info['nombre_departamento'];
        
        // NUEVO: Información de roles múltiples
        $_SESSION['all_roles'] = $all_roles;
        $_SESSION['managed_departments'] = $managed_departments;
        $_SESSION['has_multiple_roles'] = count($all_roles) > 1;
        $_SESSION['is_manager_anywhere'] = !empty($managed_departments);
        $_SESSION['is_admin'] = ($role_info['id_rol'] == 1);
        
        // Compatibilidad con código antiguo
        $_SESSION['user_department'] = $role_info['id_departamento'];
        $_SESSION['rol_nombre'] = $role_info['nombre_rol'];
        
        // Obtener la URL de redirección según el rol principal
        $redirectUrl = getRedirectByRole($role_info['id_rol']);
        
        sendResponse(
            true, 
            'Inicio de sesión exitoso', 
            $redirectUrl,
            [
                'rol' => $role_info['nombre_rol'] ?? getRoleName($role_info['id_rol']),
                'nombre' => $user['nombre'] . ' ' . $user['apellido'],
                'departamento' => $role_info['nombre_departamento'],
                'has_multiple_roles' => count($all_roles) > 1,
                'total_roles' => count($all_roles)
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