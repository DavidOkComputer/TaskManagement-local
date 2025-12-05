<?php
/*get_dashboard_stats_user.php obtiene estadisticas del dashboard para el usuario actual*/

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar que el usuario esté autenticado
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_department'])) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario = (int)$_SESSION['user_id'];
    $id_departamento = (int)$_SESSION['user_department'];

    require_once('db_config.php');
    $conexion = getDBConnection();

    if (!$conexion) {
        throw new Exception('Error de conexión a la base de datos');
    }

    if (ob_get_length()) ob_clean();

    $response = [
        'success' => false,
        'message' => '',
        'stats' => []
    ];
    
    // Proyectos donde el usuario es participante directo o es parte del grupo
    $queryMisProyectos = "
        SELECT COUNT(DISTINCT p.id_proyecto) as total
        FROM tbl_proyectos p
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE p.id_departamento = ?
        AND (
            p.id_participante = ?
            OR pu.id_usuario = ?
            OR p.id_creador = ?
        )
    ";
    
    $stmtMisProyectos = $conexion->prepare($queryMisProyectos);
    $stmtMisProyectos->bind_param("iiii", $id_departamento, $id_usuario, $id_usuario, $id_usuario);
    $stmtMisProyectos->execute();
    $resultMisProyectos = $stmtMisProyectos->get_result();
    $misProyectos = 0;
    if ($row = $resultMisProyectos->fetch_assoc()) {
        $misProyectos = (int)$row['total'];
    }
    $stmtMisProyectos->close();

    // Total de tareas asignadas al usuario
    $queryMisTareas = "
        SELECT 
            COUNT(*) as total_tareas,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidas
        FROM tbl_tareas
        WHERE id_participante = ?
    ";
    
    $stmtMisTareas = $conexion->prepare($queryMisTareas);
    $stmtMisTareas->bind_param("i", $id_usuario);
    $stmtMisTareas->execute();
    $resultMisTareas = $stmtMisTareas->get_result();
    
    $totalTareas = 0;
    $tareasCompletadas = 0;
    $tareasPendientes = 0;
    $tareasVencidas = 0;
    
    if ($row = $resultMisTareas->fetch_assoc()) {
        $totalTareas = (int)$row['total_tareas'];
        $tareasCompletadas = (int)$row['completadas'];
        $tareasPendientes = (int)$row['pendientes'];
        $tareasVencidas = (int)$row['vencidas'];
    }
    $stmtMisTareas->close();

    // Calcular porcentaje de tareas completadas
    $porcentajeTareasCompletadas = $totalTareas > 0 
        ? round(($tareasCompletadas / $totalTareas) * 100, 1) 
        : 0;
    
    // Total de proyectos del departamento
    $queryProyectosDept = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidos
        FROM tbl_proyectos
        WHERE id_departamento = ?
    ";
    
    $stmtProyectosDept = $conexion->prepare($queryProyectosDept);
    $stmtProyectosDept->bind_param("i", $id_departamento);
    $stmtProyectosDept->execute();
    $resultProyectosDept = $stmtProyectosDept->get_result();
    
    $proyectosDept = [
        'total' => 0,
        'completados' => 0,
        'en_proceso' => 0,
        'pendientes' => 0,
        'vencidos' => 0
    ];
    
    if ($row = $resultProyectosDept->fetch_assoc()) {
        $proyectosDept['total'] = (int)$row['total'];
        $proyectosDept['completados'] = (int)$row['completados'];
        $proyectosDept['en_proceso'] = (int)$row['en_proceso'];
        $proyectosDept['pendientes'] = (int)$row['pendientes'];
        $proyectosDept['vencidos'] = (int)$row['vencidos'];
    }
    $stmtProyectosDept->close();

    // Construir respuesta
    $response['success'] = true;
    $response['stats'] = [
        // Estadísticas personales del usuario
        'mis_proyectos' => $misProyectos,
        'mis_tareas' => $totalTareas,
        'tareas_completadas' => $tareasCompletadas,
        'tareas_pendientes' => $tareasPendientes,
        'tareas_vencidas' => $tareasVencidas,
        'porcentaje_tareas_completadas' => $porcentajeTareasCompletadas,
        
        // Estadísticas del departamento
        'proyectos_departamento' => $proyectosDept['total'],
        'proyectos_dept_completados' => $proyectosDept['completados'],
        'proyectos_dept_en_proceso' => $proyectosDept['en_proceso'],
        'proyectos_dept_pendientes' => $proyectosDept['pendientes'],
        'proyectos_dept_vencidos' => $proyectosDept['vencidos'],
        
        // Info adicional
        'id_usuario' => $id_usuario,
        'id_departamento' => $id_departamento
    ];

    $conexion->close();
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();

    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'stats' => [
            'mis_proyectos' => 0,
            'mis_tareas' => 0,
            'tareas_completadas' => 0,
            'tareas_pendientes' => 0,
            'tareas_vencidas' => 0,
            'porcentaje_tareas_completadas' => 0,
            'proyectos_departamento' => 0,
            'proyectos_dept_completados' => 0,
            'proyectos_dept_en_proceso' => 0,
            'proyectos_dept_pendientes' => 0,
            'proyectos_dept_vencidos' => 0
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats_user.php: ' . $e->getMessage());
}

ob_end_flush();
?>