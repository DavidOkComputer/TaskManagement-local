<?php
/*get_top_employees_progress.php 5 empleados con mayor progreso en sus proyectos
el progrteso se calcula con las tareas completadas frente a las tareas que se tienen asignadas*/

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

    // Calcula: total de tareas asignadas, tareas completadas, y porcentaje de progreso
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
        WHERE t.id_participante IS NOT NULL
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
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