<?php
/**
 * get_project_users.php (OPTIMIZED VERSION)
 * obtiene todos los usuarios asignados a un proyecto especifico con su progreso calculado
 * usando una sola query con agregación en lugar de múltiples consultas por usuario
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'usuarios' => []];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_GET['id']);

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    /**
     * OPTIMIZED QUERY - Single query with JOIN and aggregation
     * This is much more efficient than N+1 queries
     */
    $sql = "SELECT 
                pu.id_usuario,
                u.nombre,
                u.apellido,
                u.e_mail,
                u.num_empleado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            LEFT JOIN tbl_tareas t ON (
                t.id_participante = pu.id_usuario 
                AND t.id_proyecto = pu.id_proyecto
            )
            WHERE pu.id_proyecto = ?
            GROUP BY pu.id_usuario, u.nombre, u.apellido, u.e_mail, u.num_empleado
            ORDER BY u.apellido ASC, u.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $total_tasks = (int)$row['total_tareas'];
        $completed_tasks = (int)$row['tareas_completadas'];
        
        // Calcular progreso
        $progress = $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0;
        
        $response['usuarios'][] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'e_mail' => $row['e_mail'],
            'num_empleado' => (int)$row['num_empleado'],
            'progreso' => $progress,
            'progreso_porcentaje' => round($progress, 1),
            'tareas_totales' => $total_tasks,
            'tareas_completadas' => $completed_tasks
        ];
    }

    $response['success'] = true;

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error en get_project_users.php: ' . $e->getMessage());
}

echo json_encode($response);
?>