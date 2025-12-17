<?php
/*get_dashboard_stats_manager.php
estadísticas del dashboard filtradas por el departamento del gerente
Incluye todas las métricas del dashboard de administrador*/
 
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
    
    // ==========================================
    // OBJETIVOS DEL DEPARTAMENTO
    // ==========================================
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
    
    $totalObjetivos = 0;
    $objetivosCompletados = 0;
    $objetivosRetrasados = 0;
    
    if ($row = $resultObjetivos->fetch_assoc()) {
        $totalObjetivos = (int)$row['total'];
        $objetivosCompletados = (int)$row['completados'];
        $objetivosRetrasados = (int)$row['vencidos'];
    }
    $stmtObjetivos->close();
    
    // Calcular porcentaje de objetivos completados
    $porcentajeObjetivos = $totalObjetivos > 0
        ? round(($objetivosCompletados / $totalObjetivos) * 100, 1)
        : 0;
    
    // ==========================================
    // PROYECTOS DEL DEPARTAMENTO
    // ==========================================
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
    
    $totalProyectos = 0;
    $proyectosCompletados = 0;
    $proyectosEnProceso = 0;
    $proyectosPendientes = 0;
    $proyectosVencidos = 0;
    $progresoPromedioEnProceso = 0;
    
    if ($row = $resultProyectos->fetch_assoc()) {
        $totalProyectos = (int)$row['total'];
        $proyectosCompletados = (int)$row['completados'];
        $proyectosEnProceso = (int)$row['en_proceso'];
        $proyectosPendientes = (int)$row['pendientes'];
        $proyectosVencidos = (int)$row['vencidos'];
        $progresoPromedioEnProceso = round((float)($row['progreso_promedio'] ?? 0), 1);
    }
    $stmtProyectos->close();
    
    // Proyectos completados a tiempo
    $queryProyectosATiempo = "
        SELECT COUNT(*) as on_time
        FROM tbl_proyectos
        WHERE id_departamento = ?
        AND estado = 'completado'
        AND fecha_cumplimiento >= DATE(NOW())
    ";
    
    $stmtProyectosATiempo = $conexion->prepare($queryProyectosATiempo);
    $stmtProyectosATiempo->bind_param("i", $id_departamento);
    $stmtProyectosATiempo->execute();
    $resultProyectosATiempo = $stmtProyectosATiempo->get_result();
    
    $proyectosATiempo = 0;
    if ($row = $resultProyectosATiempo->fetch_assoc()) {
        $proyectosATiempo = (int)$row['on_time'];
    }
    $stmtProyectosATiempo->close();
    
    // Calcular porcentajes de proyectos
    $porcentajeCompletados = $totalProyectos > 0
        ? round(($proyectosCompletados / $totalProyectos) * 100, 1)
        : 0;
    
    $porcentajeVencidos = $totalProyectos > 0
        ? round(($proyectosVencidos / $totalProyectos) * 100, 1)
        : 0;
    
    $porcentajePendientes = $totalProyectos > 0
        ? round(($proyectosPendientes / $totalProyectos) * 100, 1)
        : 0;
    
    // ==========================================
    // TAREAS DEL DEPARTAMENTO
    // ==========================================
    $queryTareas = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM tbl_tareas t
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE p.id_departamento = ?
    ";
    
    $stmtTareas = $conexion->prepare($queryTareas);
    $stmtTareas->bind_param("i", $id_departamento);
    $stmtTareas->execute();
    $resultTareas = $stmtTareas->get_result();
    
    $totalTareas = 0;
    $tareasCompletadas = 0;
    $tareasPendientes = 0;
    $tareasRetrasadas = 0;
    $tareasEnProceso = 0;
    
    if ($row = $resultTareas->fetch_assoc()) {
        $totalTareas = (int)$row['total'];
        $tareasCompletadas = (int)$row['completadas'];
        $tareasPendientes = (int)$row['pendientes'];
        $tareasRetrasadas = (int)$row['vencidas'];
        $tareasEnProceso = (int)$row['en_proceso'];
    }
    $stmtTareas->close();
    
    // Calcular porcentaje de tareas completadas
    $porcentajeTareas = $totalTareas > 0
        ? round(($tareasCompletadas / $totalTareas) * 100, 1)
        : 0;
    
    // ==========================================
    // EMPLEADOS DEL DEPARTAMENTO (adicional)
    // ==========================================
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
    
    $conexion->close();
    
    // ==========================================
    // CONSTRUIR RESPUESTA
    // ==========================================
    // Incluir tanto nombres compatibles con admin como nombres legacy de manager
    $response['success'] = true;
    $response['stats'] = [
        // Contadores principales (compatibles con admin)
        'total_objetivos' => $totalObjetivos,
        'total_proyectos' => $totalProyectos,
        'total_tareas' => $totalTareas,
        'porcentaje_tareas' => $porcentajeTareas,
        'porcentaje_objetivos' => $porcentajeObjetivos,
        
        // Estados de proyectos
        'proyectos_completados' => $proyectosCompletados,
        'proyectos_en_proceso' => $proyectosEnProceso,
        'proyectos_pendientes' => $proyectosPendientes,
        'proyectos_vencidos' => $proyectosVencidos,
        
        // Estadísticas adicionales calculadas
        'objetivos_retrasados' => $objetivosRetrasados,
        'tareas_retrasadas' => $tareasRetrasadas,
        'tareas_en_proceso' => $tareasEnProceso,
        'porcentaje_completados' => $porcentajeCompletados,
        'porcentaje_vencidos' => $porcentajeVencidos,
        'porcentaje_pendientes' => $porcentajePendientes,
        'progreso_promedio_en_proceso' => $progresoPromedioEnProceso,
        'proyectos_a_tiempo' => $proyectosATiempo,
        
        // Información adicional específica de gerente
        'total_empleados' => $totalEmpleados,
        'id_departamento' => $id_departamento,
        
        // === ALIASES PARA COMPATIBILIDAD CON JS LEGACY ===
        // Estos campos mantienen compatibilidad con manager_dashboard_stats.js existente
        'objetivos_departamento' => $totalObjetivos,
        'proyectos_departamento' => $totalProyectos,
        'tareas_departamento' => $totalTareas,
        'porcentaje_tareas_completadas' => $porcentajeTareas,
        'objetivos_completados' => $objetivosCompletados,
        'objetivos_vencidos' => $objetivosRetrasados,
        'tareas_completadas' => $tareasCompletadas,
        'tareas_pendientes' => $tareasPendientes,
        'tareas_vencidas' => $tareasRetrasadas,
        'progreso_promedio' => $progresoPromedioEnProceso
    ];
    
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    ob_clean();
    
    $response = [
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'stats' => [
            'total_objetivos' => 0,
            'total_proyectos' => 0,
            'total_tareas' => 0,
            'porcentaje_tareas' => 0,
            'porcentaje_objetivos' => 0,
            'proyectos_completados' => 0,
            'proyectos_en_proceso' => 0,
            'proyectos_pendientes' => 0,
            'proyectos_vencidos' => 0,
            'objetivos_retrasados' => 0,
            'tareas_retrasadas' => 0,
            'tareas_en_proceso' => 0,
            'porcentaje_completados' => 0,
            'porcentaje_vencidos' => 0,
            'porcentaje_pendientes' => 0,
            'progreso_promedio_en_proceso' => 0,
            'proyectos_a_tiempo' => 0,
            'total_empleados' => 0,
            'id_departamento' => 0
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats_manager.php: ' . $e->getMessage());
}
 
ob_end_flush();
?>