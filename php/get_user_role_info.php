<?php
/*get_user_role_info.php para obtener el rol de cada usuario logeado*/

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once('db_config.php');

//revisar si el usuario esta logeado
if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

//obtener id de usuario desde la sesion
$id_usuario = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    //obtener info del usuario y su departamento
    $query = "
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.id_rol,
            u.id_departamento,
            r.nombre AS nombre_rol,
            d.nombre AS nombre_departamento
        FROM tbl_usuarios u
        LEFT JOIN tbl_roles r ON u.id_rol = r.id_rol
        LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
        WHERE u.id_usuario = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $id_usuario);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    $canViewAllDepartments = ($user['id_rol'] == 1); // solo el admin puede ver todos los departamentos
    $isManager = ($user['id_rol'] == 2); // rol de gerente
    $isAdmin = ($user['id_rol'] == 1); // Admin rol
    
    echo json_encode([
        'success' => true,
        'data' => [
            'id_usuario' => (int)$user['id_usuario'],
            'nombre' => $user['nombre'],
            'apellido' => $user['apellido'],
            'id_rol' => (int)$user['id_rol'],
            'nombre_rol' => $user['nombre_rol'],
            'id_departamento' => (int)$user['id_departamento'],
            'nombre_departamento' => $user['nombre_departamento'],
            'can_view_all_departments' => $canViewAllDepartments,
            'is_admin' => $isAdmin,
            'is_manager' => $isManager,
            'show_department_dropdown' => $canViewAllDepartments //mostrar el dorpdown solo para el admin
        ]
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log('Error in get_user_role_info.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener información del usuario: ' . $e->getMessage()
    ]);
}
?>