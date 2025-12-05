<?php
/*manager_get_department.php obtener el departamento del usuario*/

//prevenir output antes del json
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
    'message' => ''
];

try {
    $id_usuario = null;//obtener id de la sesion
    
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }
    
    //verificar el rol del usuario
    $id_rol = isset($_SESSION['id_rol']) ? (int)$_SESSION['id_rol'] : 0;
   
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    //obtener info del departamento del usuario
    $query = "
        SELECT 
            u.id_usuario,
            u.nombre AS usuario_nombre,
            u.apellido AS usuario_apellido,
            u.id_departamento,
            d.nombre AS departamento_nombre,
            d.descripcion AS departamento_descripcion
        FROM tbl_usuarios u
        INNER JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
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
        throw new Exception('Usuario no encontrado o sin departamento asignado');
    }
    
    if (!$user['id_departamento'] || $user['id_departamento'] == 0) {
        throw new Exception('El usuario no tiene un departamento asignado');
    }
    
    $response['success'] = true;
    $response['department'] = [
        'id_departamento' => (int)$user['id_departamento'],
        'nombre' => $user['departamento_nombre'],
        'descripcion' => $user['departamento_descripcion']
    ];
    $response['user'] = [
        'id_usuario' => (int)$user['id_usuario'],
        'nombre' => $user['usuario_nombre'] . ' ' . $user['usuario_apellido']
    ];
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_department.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>