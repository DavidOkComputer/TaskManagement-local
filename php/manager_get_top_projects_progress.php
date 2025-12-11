<?php
/*manager_get_top_projects_progress_manager.php para los proyectos mas avanzados*/

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json; charset=utf-8');

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

    require_once('db_config.php');
    $conexion = getDBConnection();

    if (!$conexion) {
        throw new Exception('Conexión a base de datos no establecida');
    }

    if (ob_get_length()) ob_clean();

    $response = [
        'success' => false,
        'message' => '',
        'proyectos' => []
    ];

    // Query para obtener proyectos del departamento con su progreso
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
        WHERE p.id_departamento = ?
        AND p.estado != 'completado'
        GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
        ORDER BY p.progreso DESC, p.estado ASC
        LIMIT 5
    ";

    $stmt = $conexion->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error en la preparación de la consulta: ' . $conexion->error);
    }

    $stmt->bind_param("i", $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception('Error ejecutando la consulta: ' . $conexion->error);
    }

    $proyectos = [];

    while ($row = $result->fetch_assoc()) {
        $total_tareas = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        // Calcular progreso basado en tareas si hay tareas
        if ($total_tareas > 0) {
            $progreso_calculado = round(($tareas_completadas / $total_tareas) * 100, 1);
        } else {
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
    $conexion->close();

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
    error_log('Error en get_top_projects_progress_manager.php: ' . $e->getMessage());
}

ob_end_flush();
?>