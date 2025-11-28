<?php
/*get_dashboard_stats_manager.php estadísticas del dashboard filtradas por el departamento del gerente*/
 
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
 
session_start();
 
header('Content-Type: application/json; charset=utf-8');
 
try {
    // Verificar autenticación y rol de gerente
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_department'])) {
        throw new Exception('Usuario no autenticado');
    }
 
    if ($_SESSION['id_rol'] != 2) {
        throw new Exception('Acceso no autorizado - Se requiere rol de gerente');
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
 
    $queryEmpleados = "
        SELECT COUNT(*) as total
        FROM tbl_usuarios
        WHERE id_departamento = ?
    ";
 
    $stmtEmpleados = $conexion->prepare($queryEmpleados);
    $stmtEmpleados->bind_param("i", $id_departamento);
    $stmtEmpleados->execute();
    $resultEmpleados = $stmtEmpleados->get_result();
 
    $totalEmpleados = 0;
    if ($row = $resultEmpleados->fetch_assoc()) {
        $totalEmpleados = (int)$row['total'];
    }
    $stmtEmpleados->close();
 
    $queryProyectos = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidos,
            AVG(CASE WHEN estado = 'en proceso' THEN progreso ELSE NULL END) as progreso_promedio
        FROM tbl_proyectos
        WHERE id_departamento = ?
    ";
 
    $stmtProyectos = $conexion->prepare($queryProyectos);
    $stmtProyectos->bind_param("i", $id_departamento);
    $stmtProyectos->execute();
    $resultProyectos = $stmtProyectos->get_result();
 
    $proyectos = [
        'total' => 0,
        'completados' => 0,
        'en_proceso' => 0,
        'pendientes' => 0,
        'vencidos' => 0,
        'progreso_promedio' => 0
    ];
 
    if ($row = $resultProyectos->fetch_assoc()) {
        $proyectos['total'] = (int)$row['total'];
        $proyectos['completados'] = (int)$row['completados'];
        $proyectos['en_proceso'] = (int)$row['en_proceso'];
        $proyectos['pendientes'] = (int)$row['pendientes'];
        $proyectos['vencidos'] = (int)$row['vencidos'];
        $proyectos['progreso_promedio'] = round((float)($row['progreso_promedio'] ?? 0), 1);
    }
    $stmtProyectos->close();
 
    $queryTareas = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as vencidas
        FROM tbl_tareas t
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE p.id_departamento = ?
    ";
 
    $stmtTareas = $conexion->prepare($queryTareas);
    $stmtTareas->bind_param("i", $id_departamento);
    $stmtTareas->execute();
    $resultTareas = $stmtTareas->get_result();
 
    $tareas = [
        'total' => 0,
        'completadas' => 0,
        'pendientes' => 0,
        'vencidas' => 0
    ];
 
    if ($row = $resultTareas->fetch_assoc()) {
        $tareas['total'] = (int)$row['total'];
        $tareas['completadas'] = (int)$row['completadas'];
        $tareas['pendientes'] = (int)$row['pendientes'];
        $tareas['vencidas'] = (int)$row['vencidas'];
    }
    $stmtTareas->close();
 
    // Calcular porcentajes
    $porcentajeCompletados = $proyectos['total'] > 0
        ? round(($proyectos['completados'] / $proyectos['total']) * 100, 1)
        : 0;
 
    $porcentajeTareasCompletadas = $tareas['total'] > 0
        ? round(($tareas['completadas'] / $tareas['total']) * 100, 1)
        : 0;
 
    $queryObjetivos = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidos
        FROM tbl_objetivos
        WHERE id_departamento = ?
    ";
 
    $stmtObjetivos = $conexion->prepare($queryObjetivos);
    $stmtObjetivos->bind_param("i", $id_departamento);
    $stmtObjetivos->execute();
    $resultObjetivos = $stmtObjetivos->get_result();
 
    $objetivos = [
        'total' => 0,
        'completados' => 0,
        'vencidos' => 0
    ];
 
    if ($row = $resultObjetivos->fetch_assoc()) {
        $objetivos['total'] = (int)$row['total'];
        $objetivos['completados'] = (int)$row['completados'];
        $objetivos['vencidos'] = (int)$row['vencidos'];
    }
    $stmtObjetivos->close();
 
    $conexion->close();
 
    // Construir respuesta
    $response['success'] = true;
    $response['stats'] = [
        // Empleados
        'total_empleados' => $totalEmpleados,
 
        // Proyectos del departamento
        'proyectos_departamento' => $proyectos['total'],
        'proyectos_completados' => $proyectos['completados'],
        'proyectos_en_proceso' => $proyectos['en_proceso'],
        'proyectos_pendientes' => $proyectos['pendientes'],
        'proyectos_vencidos' => $proyectos['vencidos'],
        'progreso_promedio' => $proyectos['progreso_promedio'],
        'porcentaje_completados' => $porcentajeCompletados,
 
        // Tareas del departamento
        'tareas_departamento' => $tareas['total'],
        'tareas_completadas' => $tareas['completadas'],
        'tareas_pendientes' => $tareas['pendientes'],
        'tareas_vencidas' => $tareas['vencidas'],
        'porcentaje_tareas_completadas' => $porcentajeTareasCompletadas,
 
        // Objetivos del departamento
        'objetivos_departamento' => $objetivos['total'],
        'objetivos_completados' => $objetivos['completados'],
        'objetivos_vencidos' => $objetivos['vencidos'],
 
        // Info adicional
        'id_departamento' => $id_departamento
    ];
 
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
 
} catch (Exception $e) {
    ob_clean();
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'stats' => []
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats_manager.php: ' . $e->getMessage());
}
 
ob_end_flush();
?>