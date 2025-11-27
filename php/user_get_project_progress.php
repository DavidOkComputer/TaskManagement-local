<?php
/*
 * get_my_projects_progress.php
 * Obtiene el progreso del usuario en los proyectos donde participa
 */

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';

$response = [
    'success' => false,
    'message' => '',
    'proyectos' => []
];

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario = (int)$_SESSION['user_id'];
    $id_departamento = (int)($_SESSION['user_department'] ?? 0);

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query para obtener proyectos donde el usuario participa y su progreso personal
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre,
            p.estado,
            p.progreso as progreso_proyecto,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
        FROM tbl_proyectos p
        LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto AND t.id_participante = ?
        WHERE p.id_departamento = ?
        AND (
            p.id_participante = ?
            OR p.id_creador = ?
            OR EXISTS (
                SELECT 1 FROM tbl_proyecto_usuarios pu 
                WHERE pu.id_proyecto = p.id_proyecto AND pu.id_usuario = ?
            )
            OR EXISTS (
                SELECT 1 FROM tbl_tareas t2 
                WHERE t2.id_proyecto = p.id_proyecto AND t2.id_participante = ?
            )
        )
        GROUP BY p.id_proyecto, p.nombre, p.estado, p.progreso
        ORDER BY 
            CASE WHEN p.estado = 'en proceso' THEN 1
                 WHEN p.estado = 'pendiente' THEN 2
                 WHEN p.estado = 'vencido' THEN 3
                 ELSE 4 END,
            p.fecha_cumplimiento ASC
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("iiiiii", $id_usuario, $id_departamento, $id_usuario, $id_usuario, $id_usuario, $id_usuario);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        $total_tareas = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        // Calcular progreso personal del usuario en este proyecto
        $mi_progreso = $total_tareas > 0 
            ? round(($tareas_completadas / $total_tareas) * 100, 1)
            : 0;

        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'estado' => $row['estado'],
            'progreso_proyecto' => (int)$row['progreso_proyecto'],
            'mi_progreso' => $mi_progreso,
            'mis_tareas_total' => $total_tareas,
            'mis_tareas_completadas' => $tareas_completadas
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar progreso: ' . $e->getMessage();
    error_log('get_my_projects_progress.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>