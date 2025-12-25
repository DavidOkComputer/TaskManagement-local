<?php
/*user_get_user_department.php para saber el departamento del usuario */ 

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
} 

header('Content-Type: application/json'); 
require_once 'db_config.php'; 

$response = [
    'success' => false, 
    'department' => null,
    'all_departments' => []
]; 

try { 
    error_log('SESSION DATA: ' . print_r($_SESSION, true)); 
    $id_usuario = null; 

    // Intentar múltiples nombres de variables de sesión
    if (isset($_SESSION['id_usuario'])) { 
        $id_usuario = (int)$_SESSION['id_usuario']; 
    } elseif (isset($_SESSION['user_id'])) { 
        $id_usuario = (int)$_SESSION['user_id']; 
    } elseif (isset($_REQUEST['id_usuario'])) { 
        $id_usuario = (int)$_REQUEST['id_usuario']; 
    } 

    if (!$id_usuario) { 
        $response['message'] = 'ID de usuario no disponible en la sesión'; 
        $response['debug'] = [ 
            'session_id_usuario' => isset($_SESSION['id_usuario']) ? $_SESSION['id_usuario'] : 'not set', 
            'session_user_id' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set', 
            'session_started' => session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no', 
            'session_id' => session_id() 
        ]; 
        echo json_encode($response); 
        exit(); 
    } 

    $conn = getDBConnection(); 
    if (!$conn) { 
        throw new Exception('Error de conexión a la base de datos'); 
    }

    $user_query = "
        SELECT u.id_usuario, u.nombre, u.apellido
        FROM tbl_usuarios u
        WHERE u.id_usuario = ?
    ";
    
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param('i', $id_usuario);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user) {
        throw new Exception('Usuario no encontrado con ID: ' . $id_usuario);
    }

    $dept_query = "
        SELECT 
            ur.id_departamento,
            ur.id_rol,
            ur.es_principal,
            d.nombre as departamento_nombre,
            d.descripcion as departamento_descripcion,
            r.nombre as rol_nombre
        FROM tbl_usuario_roles ur
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
        JOIN tbl_roles r ON ur.id_rol = r.id_rol
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC, d.nombre ASC
    ";

    $dept_stmt = $conn->prepare($dept_query); 
    if (!$dept_stmt) { 
        throw new Exception('Error al preparar la consulta: ' . $conn->error); 
    } 

    $dept_stmt->bind_param("i", $id_usuario);     

    if (!$dept_stmt->execute()) { 
        throw new Exception('Error al ejecutar la consulta: ' . $dept_stmt->error); 
    } 

    $dept_result = $dept_stmt->get_result();
    
    $primary_department = null;
    $all_departments = [];
    
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
    }
    $dept_stmt->close();

    if (empty($all_departments)) { 
        throw new Exception('Usuario no tiene departamentos asignados'); 
    } 

    error_log('USER DATA: ' . print_r($user, true)); 
    error_log('DEPARTMENTS: ' . print_r($all_departments, true));

    $response['success'] = true; 
    
    // Departamento principal (compatibilidad)
    $response['department'] = [ 
        'id_departamento' => $primary_department['id_departamento'], 
        'nombre' => $primary_department['nombre'], 
        'descripcion' => $primary_department['descripcion'], 
        'usuario_nombre' => $user['nombre'] . ' ' . $user['apellido'] 
    ];
    
    $response['all_departments'] = $all_departments;
    $response['has_multiple_departments'] = count($all_departments) > 1;
    $response['total_departments'] = count($all_departments);

    $conn->close(); 

} catch (Exception $e) { 
    $response['message'] = $e->getMessage(); 
    error_log('Error en get_user_department.php: ' . $e->getMessage()); 
} 

echo json_encode($response); 
?>