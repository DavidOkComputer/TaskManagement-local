<?php
/**
 * get_user_department.php
 * Obtiene el departamento del usuario actual
 * Nota: La autenticación se verifica en el sistema principal
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'department' => null];

try {
    // Get user ID from session or parameter
    $id_usuario = null;
    
    // Try to get from SESSION first (if available)
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } 
    // Try to get from GET/POST parameter
    elseif (isset($_REQUEST['id_usuario'])) {
        $id_usuario = (int)$_REQUEST['id_usuario'];
    }
    
    // If no user ID available, return error but don't fail completely
    if (!$id_usuario) {
        $response['message'] = 'ID de usuario no disponible';
        echo json_encode($response);
        exit();
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Get user's department
    $query = "SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.id_departamento,
                d.id_departamento,
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
        throw new Exception('Usuario no encontrado');
    }

    // Check if user has a department assigned
    if (!$user['id_departamento'] || !$user['departamento_nombre']) {
        throw new Exception('Usuario no tiene departamento asignado');
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