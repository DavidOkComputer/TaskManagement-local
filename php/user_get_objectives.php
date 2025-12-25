<?php
/*user_get_objectives.php obtiene objetivos creados por el usuario logeado*/

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
require_once 'db_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Obtener id desde la sesion
    $id_usuario = null;
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }

    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal,
            u.nombre,
            u.apellido
        FROM tbl_usuario_roles ur
        JOIN tbl_usuarios u ON ur.id_usuario = u.id_usuario
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conn->prepare($role_query);
    if (!$role_stmt) {
        throw new Exception('Error preparando consulta de rol: ' . $conn->error);
    }
    
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_user = false;
    $is_admin = false;
    $is_manager = false;
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) $is_admin = true;
        if ($row['id_rol'] == 2) $is_manager = true;
        if ($row['id_rol'] == 3) $is_user = true;
    }
    $role_stmt->close();

    // Verificar que tenga al menos un rol activo
    if (!$is_user && !$is_admin && !$is_manager) {
        throw new Exception('Usuario no tiene roles asignados');
    }

    // Para usuarios normales (rol 3), mostrar solo sus objetivos creados
    // Para gerentes y admins, también mostrar solo sus objetivos creados en esta vista de usuario
    $query = "SELECT
                o.id_objetivo,
                o.nombre,
                o.descripcion,
                o.fecha_cumplimiento,
                o.estado,
                o.archivo_adjunto,
                o.id_departamento,
                d.nombre as area
              FROM tbl_objetivos o
              INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
              WHERE o.id_creador = ?
              ORDER BY o.fecha_cumplimiento ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }

    $objetivos = [];
    while ($row = $result->fetch_assoc()) {
        $objetivos[] = [
            'id_objetivo' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'id_departamento' => (int)$row['id_departamento'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'archivo_adjunto' => $row['archivo_adjunto'] ?? null
        ];
    }

    echo json_encode([
        'success' => true,
        'objetivos' => $objetivos,
        'total' => count($objetivos),
        'id_creador' => $id_usuario,
        'is_user' => $is_user,
        'is_admin' => $is_admin,
        'is_manager' => $is_manager
    ], JSON_UNESCAPED_UNICODE);

    $result->free();
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'objetivos' => []
    ]);
    error_log('user_get_objectives.php Error: ' . $e->getMessage());
}
?>