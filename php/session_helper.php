
<?php 

/** 

 * Session Helper - Funciones para manejo de sesión y control de acceso 

 */ 

 

// Iniciar sesión si no está iniciada 

if (session_status() === PHP_SESSION_NONE) { 

    session_start(); 

} 

 

/** 

 * Verifica si el usuario está autenticado 

 */ 

function isLoggedIn() { 

    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true; 

} 

 

/** 

 * Requiere que el usuario esté autenticado 

 * Redirige al login si no lo está 

 */ 

function requireLogin() { 

    if (!isLoggedIn()) { 

        header('Location: /taskManagement/index.php'); 

        exit; 

    } 

} 

 

/** 

 * Obtiene el tipo de usuario actual 

 */ 

function getUserType() { 

    return $_SESSION['user_type'] ?? 'guest'; 

} 

 

/** 

 * Obtiene el ID del usuario actual 

 */ 

function getUserId() { 

    return $_SESSION['user_id'] ?? null; 

} 

 

/** 

 * Obtiene el nivel de acceso del usuario 

 */ 

function getAccessLevel() { 

    return $_SESSION['nivel_acceso'] ?? 0; 

} 

 

/** 

 * Verifica si el usuario tiene un permiso específico 

 */ 

function hasPermission($permission) { 

    if (!isLoggedIn()) { 

        return false; 

    } 

     

    $permissions = $_SESSION['user_permissions'] ?? []; 

    return isset($permissions[$permission]) && $permissions[$permission] === true; 

} 

 

/** 

 * Verifica si el usuario es administrador 

 */ 

function isAdmin() { 

    return getUserType() === 'admin'; 

} 

 

/** 

 * Verifica si el usuario es manager 

 */ 

function isManager() { 

    return getUserType() === 'manager' || isAdmin(); 

} 

 

/** 

 * Verifica si el usuario es líder de proyecto 

 */ 

function isProjectLeader() { 

    return getUserType() === 'project_leader' || isManager(); 

} 

 

/** 

 * Verifica si el usuario es miembro del equipo 

 */ 

function isTeamMember() { 

    return getUserType() === 'team_member'; 

} 

 

/** 

 * Requiere un nivel de acceso mínimo 

 */ 

function requireAccessLevel($minLevel) { 

    requireLogin(); 

     

    if (getAccessLevel() < $minLevel) { 

        header('HTTP/1.1 403 Forbidden'); 

        die('Acceso denegado: No tienes permisos suficientes'); 

    } 

} 

 

/** 

 * Requiere un permiso específico 

 */ 

function requirePermission($permission) { 

    requireLogin(); 

     

    if (!hasPermission($permission)) { 

        header('HTTP/1.1 403 Forbidden'); 

        die('Acceso denegado: No tienes el permiso requerido'); 

    } 

} 

 

/** 

 * Requiere ser administrador 

 */ 

function requireAdmin() { 

    requireLogin(); 

     

    if (!isAdmin()) { 

        header('HTTP/1.1 403 Forbidden'); 

        die('Acceso denegado: Solo administradores'); 

    } 

} 

 

/** 

 * Requiere ser manager o superior 

 */ 

function requireManager() { 

    requireLogin(); 

     

    if (!isManager()) { 

        header('HTTP/1.1 403 Forbidden'); 

        die('Acceso denegado: Se requiere nivel de manager'); 

    } 

} 

 

/** 

 * Obtiene información completa del usuario actual 

 */ 

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

 

/** 

 * Obtiene los permisos del usuario actual 

 */ 

function getUserPermissions() { 

    return $_SESSION['user_permissions'] ?? []; 

} 

 

/** 

 * Cierra la sesión del usuario 

 */ 

function logout() { 

    session_start(); 

    session_unset(); 

    session_destroy(); 

    header('Location: /taskManagement/index.php'); 

    exit; 

} 

 

/** 

 * Obtiene un mensaje de bienvenida personalizado según el tipo de usuario 

 */ 

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

 

/** 

 * Verifica si la sesión ha expirado (opcional - configurar tiempo en segundos) 

 */ 

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