
<?php 

/*Session Helper para funciones para manejo de sesión y control de acceso */ 

// Iniciar sesión si no está iniciada 
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

function isLoggedIn() { 
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true; 
} 

function requireLogin() { 
    if (!isLoggedIn()) { 
        header('Location: /taskManagement/index.php'); 
        exit; 
    } 
} 

function getUserType() { 
    return $_SESSION['user_type'] ?? 'guest'; 
} 

function getUserId() { 
    return $_SESSION['user_id'] ?? null; 
} 

function getAccessLevel() { 
    return $_SESSION['nivel_acceso'] ?? 0; 
} 

function hasPermission($permission) { 
    if (!isLoggedIn()) { 
        return false; 
    }     
    $permissions = $_SESSION['user_permissions'] ?? []; 
    return isset($permissions[$permission]) && $permissions[$permission] === true; 
} 

function isAdmin() { 
    return getUserType() === 'admin'; 
} 
 
function isManager() { 
    return getUserType() === 'manager' || isAdmin(); 
} 

function isProjectLeader() { 
    return getUserType() === 'project_leader' || isManager(); 
} 

function isTeamMember() { 
    return getUserType() === 'team_member'; 
} 

function requireAccessLevel($minLevel) { 
    requireLogin(); 
   
    if (getAccessLevel() < $minLevel) { 
        header('HTTP/1.1 403 Forbidden'); 
        die('Acceso denegado: No tienes permisos suficientes'); 
    } 
} 

function requirePermission($permission) { 
    requireLogin(); 
     
    if (!hasPermission($permission)) { 
        header('HTTP/1.1 403 Forbidden'); 
        die('Acceso denegado: No tienes el permiso requerido'); 
    } 
} 

function requireAdmin() { 
    requireLogin(); 
     
    if (!isAdmin()) { 
        header('HTTP/1.1 403 Forbidden'); 
        die('Acceso denegado: Solo administradores'); 
    } 
} 

function requireManager() { 
    requireLogin(); 
     
    if (!isManager()) { 
        header('HTTP/1.1 403 Forbidden'); 
        die('Acceso denegado: Se requiere nivel de manager'); 
    } 
} 

function getCurrentUser() { 
    if (!isLoggedIn()) { 
        return null; 
    } 
     
    return [ 
        'id_usuario' => $_SESSION['user_id'], 
        'usuario' => $_SESSION['usuario'], 
        'nombre' => $_SESSION['nombre'], 
        'apellido' => $_SESSION['apellido'], 
        'nombre_completo' => $_SESSION['nombre_completo'], 
        'email' => $_SESSION['e_mail'], 
        'id_departamento' => $_SESSION['user_department'], 
        'nombre_departamento' => $_SESSION['nombre_departamento'], 
        'id_rol' => $_SESSION['id_rol'], 
        'nombre_rol' => $_SESSION['nombre_rol'], 
        'user_type' => $_SESSION['user_type'], 
        'nivel_acceso' => $_SESSION['nivel_acceso'], 
        'permissions' => $_SESSION['user_permissions'] ?? [] 
    ]; 
} 

function getUserPermissions() { 
    return $_SESSION['user_permissions'] ?? []; 
} 

function logout() { 
    session_start(); 
    session_unset(); 
    session_destroy(); 
    header('Location: /taskManagement/index.php'); 
    exit; 
} 

function getWelcomeMessage() { 
    if (!isLoggedIn()) { 
        return 'Bienvenido'; 
    } 
     
    $nombre = $_SESSION['nombre'] ?? 'Usuario'; 
    $userType = getUserType();    
    $messages = [ 
        'admin' => "Bienvenido Administrador, $nombre", 
        'manager' => "Bienvenido Manager, $nombre", 
        'project_leader' => "Bienvenido Líder de Proyecto, $nombre", 
        'team_member' => "Bienvenido, $nombre", 
        'user' => "Bienvenido, $nombre" 
    ]; 
    
    return $messages[$userType] ?? "Bienvenido, $nombre"; 
} 

function checkSessionTimeout($timeoutSeconds = 3600) { 
    if (isLoggedIn()) { 
        $loginTime = $_SESSION['login_time'] ?? time(); 
        $currentTime = time(); 
         
        if (($currentTime - $loginTime) > $timeoutSeconds) { 
            logout(); 
        } 
    } 
} 
?>