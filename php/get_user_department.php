<?php
/*get_user_department.php Obtiene el departamento del usuario actual*/

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'department' => null];

try {
    // Debug: Log what's in the session (remove this after fixing)
    error_log('SESSION DATA: ' . print_r($_SESSION, true));
    
    $id_usuario = null;
    
    // Try multiple session variable names (your system uses different ones)
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } 
    elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }
    elseif (isset($_REQUEST['id_usuario'])) {
        $id_usuario = (int)$_REQUEST['id_usuario'];
    }
    
    // Add debug info to response
    if (!$id_usuario) {
        $response['message'] = 'ID de usuario no disponible';
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

    // Get user's department with better error handling
    $query = "SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.id_departamento,
                d.id_departamento as dept_id,
                d.nombre as departamento_nombre,
                d.descripcion as departamento_descripcion
              FROM tbl_usuarios u
              LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento
              WHERE u.id_usuario = ?";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_usuario);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user) {
        throw new Exception('Usuario no encontrado con ID: ' . $id_usuario);
    }

    // Debug: log the user data
    error_log('USER DATA: ' . print_r($user, true));

    // Check if user has a department assigned
    if (!$user['id_departamento'] || $user['id_departamento'] == 0) {
        throw new Exception('Usuario no tiene departamento asignado en la base de datos');
    }

    $response['success'] = true;
    $response['department'] = [
        'id_departamento' => (int)$user['id_departamento'],
        'nombre' => $user['departamento_nombre'],
        'descripcion' => $user['departamento_descripcion'],
        'usuario_nombre' => $user['nombre'] . ' ' . $user['apellido']
    ];

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error en get_user_department.php: ' . $e->getMessage());
}

echo json_encode($response);
?>