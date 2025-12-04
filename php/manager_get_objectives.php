<?php
/*manager_get_objectives.php obtiene objetivos del departamento del usuario logeado*/

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

    //obtener id desde la sesion
    $id_usuario = null;
    
    if (isset($_SESSION['id_usuario'])) {
        $id_usuario = (int)$_SESSION['id_usuario'];
    } elseif (isset($_SESSION['user_id'])) {
        $id_usuario = (int)$_SESSION['user_id'];
    }
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    //query para saber el rol y departamento
    $user_query = "
        SELECT 
            u.id_usuario,
            u.id_rol,
            u.id_departamento
        FROM tbl_usuarios u
        WHERE u.id_usuario = ?
        LIMIT 1
    ";
    
    $user_stmt = $conn->prepare($user_query);
    if (!$user_stmt) {
        throw new Exception('Error preparando consulta de usuario: ' . $conn->error);
    }
    
    $user_stmt->bind_param('i', $id_usuario);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user_data) {
        throw new Exception('Usuario no encontrado');
    }
    
    $id_rol = (int)$user_data['id_rol'];
    $id_departamento = (int)$user_data['id_departamento'];
    
    //verificar el id del usuaio
    if ($id_rol !== 2) {
        throw new Exception('Acceso no autorizado - Solo gerentes');
    }
    
    if ($id_departamento <= 0) {//saber si el usuario tiene un departamento asignado
        throw new Exception('Usuario sin departamento asignado');
    }

    //saber objetivos del departamento del usuario
    $query = "SELECT 
                o.id_objetivo,
                o.nombre,
                o.descripcion,
                o.fecha_cumplimiento,
                o.estado,
                o.archivo_adjunto,
                d.nombre as area
              FROM tbl_objetivos o
              INNER JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento
              WHERE o.id_departamento = ?
              ORDER BY o.fecha_cumplimiento ASC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    
    $stmt->bind_param("i", $id_departamento);
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
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'archivo_adjunto' => $row['archivo_adjunto'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'objetivos' => $objetivos,
        'total' => count($objetivos),
        'id_departamento' => $id_departamento
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
    error_log('manager_get_objectives.php Error: ' . $e->getMessage());
}
?>