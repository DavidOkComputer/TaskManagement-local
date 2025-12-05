<?php
/* user_get_tasks.php Obtiene las tareas asignadas al usuario actual*/

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';

$response = [
    'success' => false,
    'message' => '',
    'tareas' => [],
    'total' => 0
];

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario = (int)$_SESSION['user_id'];
    
    // Parámetro opcional para limitar resultados
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $limit = min(max($limit, 1), 50); // Entre 1 y 50

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query para obtener las tareas del usuario
    $query = "
        SELECT 
            t.id_tarea,
            t.nombre,
            t.descripcion,
            t.estado,
            t.fecha_cumplimiento,
            t.fecha_creacion,
            p.nombre as nombre_proyecto,
            p.id_proyecto
        FROM tbl_tareas t
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE t.id_participante = ?
        ORDER BY 
            CASE 
                WHEN t.estado = 'vencido' THEN 1
                WHEN t.estado = 'pendiente' THEN 2
                WHEN t.estado = 'completado' THEN 3
                ELSE 4
            END,
            t.fecha_cumplimiento ASC
        LIMIT ?
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("ii", $id_usuario, $limit);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    
    $tareas = [];
    
    while ($row = $result->fetch_assoc()) {
        $tareas[] = [
            'id_tarea' => (int)$row['id_tarea'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'estado' => $row['estado'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'fecha_creacion' => $row['fecha_creacion'],
            'nombre_proyecto' => $row['nombre_proyecto'],
            'id_proyecto' => (int)$row['id_proyecto']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['tareas'] = $tareas;
    $response['total'] = count($tareas);
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar tareas: ' . $e->getMessage();
    error_log('get_my_tasks.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>