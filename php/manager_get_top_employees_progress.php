<?php
/*
 * get_top_employees_progress_manager.php para los 5 empleados con mayor progreso en el departamento del gerente
 */

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';

$response = [
    'success' => false,
    'empleados' => [],
    'message' => ''
];

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_department'])) {
        throw new Exception('Usuario no autenticado');
    }

    // Verificar rol de gerente
    if ($_SESSION['id_rol'] != 2) {
        throw new Exception('Acceso no autorizado');
    }

    $id_departamento = (int)$_SESSION['user_department'];

    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query para obtener top empleados del departamento con su progreso
    // Solo considera tareas de proyectos del departamento
    $sql = "
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.num_empleado,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
            ROUND(
                (SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) / COUNT(t.id_tarea)) * 100, 
                1
            ) as progreso
        FROM tbl_usuarios u
        INNER JOIN tbl_tareas t ON u.id_usuario = t.id_participante
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE u.id_departamento = ?
        AND p.id_departamento = ?
        AND t.id_participante IS NOT NULL
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
        HAVING total_tareas > 0
        ORDER BY progreso DESC, tareas_completadas DESC
        LIMIT 5
    ";

    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar consulta: ' . $conn->error);
    }

    $stmt->bind_param("ii", $id_departamento, $id_departamento);
    
    if (!$stmt->execute()) {
        throw new Exception('Error en la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    $empleados = [];
    
    while ($row = $result->fetch_assoc()) {
        $empleados[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'num_empleado' => (int)$row['num_empleado'],
            'total_tareas' => (int)$row['total_tareas'],
            'tareas_completadas' => (int)$row['tareas_completadas'],
            'progreso' => (float)$row['progreso']
        ];
    }

    $stmt->close();
    $conn->close();

    if (count($empleados) > 0) {
        $response['success'] = true;
        $response['empleados'] = $empleados;
    } else {
        $response['success'] = true;
        $response['message'] = 'No hay empleados con tareas asignadas en el departamento';
        $response['empleados'] = [];
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar empleados: ' . $e->getMessage();
    error_log('get_top_employees_progress_manager.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>