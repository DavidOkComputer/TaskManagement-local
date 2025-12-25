<?php
/*manager_get_department.php obtener el departamento del usuario*/

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$response = [
    'success' => false,
    'department' => null,
    'all_departments' => [],
    'message' => ''
];

try {
    $id_usuario = null;
    
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $user_query = "
        SELECT 
            u.id_usuario,
            u.nombre AS usuario_nombre,
            u.apellido AS usuario_apellido
        FROM tbl_usuarios u
        WHERE u.id_usuario = ?
        LIMIT 1
    ";
    
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param('i', $id_usuario);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    $dept_query = "
        SELECT 
            ur.id_departamento,
            ur.id_rol,
            ur.es_principal,
            d.nombre AS departamento_nombre,
            d.descripcion AS departamento_descripcion,
            r.nombre AS rol_nombre
        FROM tbl_usuario_roles ur
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
        JOIN tbl_roles r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC, d.nombre ASC
    ";
    
    $dept_stmt = $conn->prepare($dept_query);
    if (!$dept_stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $dept_stmt->bind_param('i', $id_usuario);
    if (!$dept_stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $dept_stmt->error);
    }
    
    $dept_result = $dept_stmt->get_result();
    
    $primary_department = null;
    $all_departments = [];
    $managed_departments = [];
    
    while ($row = $dept_result->fetch_assoc()) {
        $dept_info = [
            'id_departamento' => (int)$row['id_departamento'],
            'nombre' => $row['departamento_nombre'],
            'descripcion' => $row['departamento_descripcion'],
            'id_rol' => (int)$row['id_rol'],
            'rol_nombre' => $row['rol_nombre'],
            'es_principal' => (bool)$row['es_principal']
        ];
        
        $all_departments[] = $dept_info;
        
        // Guardar el principal
        if ($row['es_principal'] == 1 || $primary_department === null) {
            $primary_department = $dept_info;
        }
        
        // Guardar departamentos donde es gerente
        if ($row['id_rol'] == 2) {
            $managed_departments[] = $dept_info;
        }
    }
    $dept_stmt->close();
    
    if (empty($all_departments)) {
        throw new Exception('El usuario no tiene departamentos asignados');
    }
    
    $response['success'] = true;
    
    // Departamento principal (compatibilidad)
    $response['department'] = [
        'id_departamento' => $primary_department['id_departamento'],
        'nombre' => $primary_department['nombre'],
        'descripcion' => $primary_department['descripcion']
    ];
    
    // Información del usuario
    $response['user'] = [
        'id_usuario' => (int)$user['id_usuario'],
        'nombre' => $user['usuario_nombre'] . ' ' . $user['usuario_apellido']
    ];
    
    //Información de múltiples departamentos
    $response['all_departments'] = $all_departments;
    $response['managed_departments'] = $managed_departments;
    $response['has_multiple_departments'] = count($all_departments) > 1;
    $response['is_manager'] = !empty($managed_departments);
    $response['total_departments'] = count($all_departments);
    $response['total_managed'] = count($managed_departments);
    
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_department.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>