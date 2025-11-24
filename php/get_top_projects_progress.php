<?php
// get_top_projects_progress.php top 5 proyectos con mayor progreso

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once('db_config.php');
    $conexion = getDBConnection();

    if (!isset($conexion)) {
        throw new Exception('Conexión a base de datos no establecida');
    }

    if (ob_get_length()) ob_clean();

    $response = [
        'success' => false,
        'message' => '',
        'proyectos' => []
    ];

    // Función para calcular el progreso de un proyecto basado en sus tareas
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre,
            p.progreso,
            p.estado,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
        FROM tbl_proyectos p
        LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
        WHERE p.estado != 'completado'
        GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
        ORDER BY p.progreso DESC
        LIMIT 5
    ";

    $stmt = $conexion->prepare($query);
    if (!$stmt) {
        throw new Exception('Error en la preparación de la consulta: ' . $conexion->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception('Error ejecutando la consulta: ' . $conexion->error);
    }

    $proyectos = [];

    while ($row = $result->fetch_assoc()) {
        // Calcular progreso automático basado en tareas
        $total_tareas = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        // Si hay tareas, calcular porcentaje basado en tareas completadas
        if ($total_tareas > 0) {
            $progreso_calculado = round(($tareas_completadas / $total_tareas) * 100, 1);
        } else {
            // Si no hay tareas, usar el progreso almacenado
            $progreso_calculado = (float)$row['progreso'];
        }

        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'progreso' => $progreso_calculado,
            'estado' => $row['estado'],
            'total_tareas' => $total_tareas,
            'tareas_completadas' => $tareas_completadas
        ];
    }

    $stmt->close();

    $response['success'] = true;
    $response['proyectos'] = $proyectos;

    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();

    $response = [
        'success' => false,
        'message' => 'Error al cargar proyectos: ' . $e->getMessage(),
        'proyectos' => []
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_top_projects_progress.php: ' . $e->getMessage());
}

ob_end_flush();
?>