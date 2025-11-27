<?php
/*get_user_role_info.php devuelve el rol de usuario, departamento y permisos, se usa en el dashboard*/

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once('db_config.php');

if (!isset($_SESSION['id_usuario']) && !isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

$id_usuario = isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : $_SESSION['user_id'];

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
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
    
    // Determine permission level based on role
    // id_rol: 1 = administrador (can see all), 2 = gerente (only own dept), 3 = usuario (only own dept)
    $canViewAllDepartments = ($user['id_rol'] == 1); // Only admin can view all departments
    $isManager = ($user['id_rol'] == 2); // Manager role
    $isAdmin = ($user['id_rol'] == 1); // Admin role
    
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
            'show_department_dropdown' => $canViewAllDepartments // Only show dropdown for admins
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