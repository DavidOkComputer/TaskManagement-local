<?php
/**
 * get_project_stats.php - Get project details with task statistics
 * 
 * Returns project information including:
 * - Total tasks count
 * - Completed tasks count
 * - Current progress percentage
 * - Task breakdown by status
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'proyecto' => null];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_GET['id']);

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Get project details
    $sql = "SELECT * FROM tbl_proyectos WHERE id_proyecto = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $proyecto = $result->fetch_assoc();

    if (!$proyecto) {
        throw new Exception('Proyecto no encontrado');
    }

    // Get task statistics
    $sql_stats = "SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN estado = 'en proceso' OR estado = 'en-progreso' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pending_tasks,
                    SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as expired_tasks
                  FROM tbl_tareas 
                  WHERE id_proyecto = ?";

    $stmt_stats = $conn->prepare($sql_stats);
    if (!$stmt_stats) {
        throw new Exception('Error al preparar consulta de estadísticas: ' . $conn->error);
    }

    $stmt_stats->bind_param("i", $id_proyecto);
    if (!$stmt_stats->execute()) {
        throw new Exception('Error al obtener estadísticas: ' . $stmt_stats->error);
    }

    $result_stats = $stmt_stats->get_result();
    $stats = $result_stats->fetch_assoc();

    // Add statistics to project
    $proyecto['estadisticas'] = [
        'total_tareas' => (int)$stats['total_tasks'],
        'tareas_completadas' => (int)$stats['completed_tasks'] ?? 0,
        'tareas_en_progreso' => (int)$stats['in_progress_tasks'] ?? 0,
        'tareas_pendientes' => (int)$stats['pending_tasks'] ?? 0,
        'tareas_vencidas' => (int)$stats['expired_tasks'] ?? 0,
        'porcentaje_progreso' => $stats['total_tasks'] > 0 
            ? round((((int)$stats['completed_tasks'] ?? 0) / (int)$stats['total_tasks']) * 100)
            : 0
    ];

    // Get list of all tasks for this project (optional, for detailed view)
    $sql_tasks = "SELECT 
                    id_tarea,
                    nombre,
                    descripcion,
                    estado,
                    fecha_cumplimiento,
                    fecha_inicio
                  FROM tbl_tareas 
                  WHERE id_proyecto = ?
                  ORDER BY fecha_cumplimiento ASC";

    $stmt_tasks = $conn->prepare($sql_tasks);
    if ($stmt_tasks) {
        $stmt_tasks->bind_param("i", $id_proyecto);
        if ($stmt_tasks->execute()) {
            $result_tasks = $stmt_tasks->get_result();
            $tasks = [];
            while ($row = $result_tasks->fetch_assoc()) {
                $tasks[] = [
                    'id_tarea' => (int)$row['id_tarea'],
                    'nombre' => $row['nombre'],
                    'descripcion' => $row['descripcion'],
                    'estado' => $row['estado'],
                    'fecha_cumplimiento' => $row['fecha_cumplimiento'],
                    'fecha_inicio' => $row['fecha_inicio']
                ];
            }
            $proyecto['tareas'] = $tasks;
        }
        $stmt_tasks->close();
    }

    $response['success'] = true;
    $response['proyecto'] = $proyecto;

    $stmt->close();
    $stmt_stats->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error en get_project_stats.php: ' . $e->getMessage());
}

echo json_encode($response);
?>