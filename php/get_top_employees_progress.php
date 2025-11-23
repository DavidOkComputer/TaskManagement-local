<?php
/*
get_top_employees_progress.php - Obtiene los top 5 empleados con mayor progreso en sus proyectos
Calcula el progreso promedio basado en tareas completadas vs totales asignadas
*/

header('Content-Type: application/json');
require_once 'db_config.php';

$response = [
    'success' => false,
    'empleados' => [],
    'message' => ''
];

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query optimizada para obtener progreso de cada empleado
    // Calcula: total de tareas asignadas, tareas completadas, y porcentaje de progreso
    $sql = "
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.num_empleado,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
            CASE 
                WHEN COUNT(t.id_tarea) = 0 THEN 0
                ELSE ROUND((SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) / COUNT(t.id_tarea)) * 100, 1)
            END as progreso
        FROM tbl_usuarios u
        LEFT JOIN tbl_tareas t ON u.id_usuario = t.id_participante
        WHERE u.acceso = 1
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
        HAVING total_tareas > 0
        ORDER BY progreso DESC, tareas_completadas DESC
        LIMIT 5
    ";

    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Error en la consulta: ' . $conn->error);
    }

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

    if (count($empleados) > 0) {
        $response['success'] = true;
        $response['empleados'] = $empleados;
    } else {
        $response['message'] = 'No hay empleados con tareas asignadas';
        $response['success'] = true;
        $response['empleados'] = [];
    }

    $result->free();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar empleados: ' . $e->getMessage();
    error_log('get_top_employees_progress.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>