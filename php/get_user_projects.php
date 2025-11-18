<?php 
/*get_user_projects.php  Obtiene todos los proyectos asignados a un usuario específico con su progreso calculado */ 

header('Content-Type: application/json'); 
require_once 'db_config.php'; 
$response = ['success' => false, 'proyectos' => []]; 
try { 
    if (!isset($_GET['id_usuario'])) { 
        throw new Exception('ID de usuario requerido'); 
    } 
    $id_usuario = intval($_GET['id_usuario']);  
    $conn = getDBConnection(); 
    if (!$conn) { 
        throw new Exception('Error de conexión a la base de datos'); 
    } 
    // Query optimizada - obtiene proyectos del usuario con progreso 
    $sql = "SELECT  
                p.id_proyecto, 
                p.nombre as proyecto_nombre, 
                p.descripcion, 
                p.fecha_cumplimiento, 
                p.estado as proyecto_estado, 
                p.id_departamento, 
                d.nombre as area,
                COUNT(t.id_tarea) as total_tareas, 
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas 
            FROM tbl_proyecto_usuarios pu 
            JOIN tbl_proyectos p ON pu.id_proyecto = p.id_proyecto 
            LEFT JOIN tbl_tareas t ON ( 
                t.id_proyecto = p.id_proyecto  
                AND t.id_participante = pu.id_usuario 
            ) 
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            WHERE pu.id_usuario = ? 
            GROUP BY p.id_proyecto, p.nombre, p.descripcion, p.fecha_cumplimiento, p.estado, p.id_departamento
            ORDER BY p.fecha_cumplimiento DESC";

    $stmt = $conn->prepare($sql); 
    if (!$stmt) { 
        throw new Exception('Error al preparar la consulta: ' . $conn->error); 
    } 
    $stmt->bind_param("i", $id_usuario); 
    if (!$stmt->execute()) { 
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error); 
    } 
    $result = $stmt->get_result(); 
    while ($row = $result->fetch_assoc()) { 
        $total_tasks = (int)$row['total_tareas']; 
        $completed_tasks = (int)$row['tareas_completadas']; 
        $progress = $total_tasks > 0 ? ($completed_tasks / $total_tasks) * 100 : 0; //calcular progreso
        $response['proyectos'][] = [ 
            'id_proyecto' => (int)$row['id_proyecto'], 
            'nombre' => $row['proyecto_nombre'], 
            'descripcion' => $row['descripcion'], 
            'area' => $row['area'], 
            'fecha_cumplimiento' => $row['fecha_cumplimiento'], 
            'estado' => $row['proyecto_estado'], 
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
    error_log('Error en get_user_projects.php: ' . $e->getMessage()); 
} 
echo json_encode($response); 
?> 