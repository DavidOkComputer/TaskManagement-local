<?php
/*get_dashboard_stats_manager.php estadisticas de gerente para contador de dashboard*/

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

header('Content-Type: application/json; charset=utf-8');

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['id_usuario'])) {
        throw new Exception('Usuario no autenticado');
    }
    
    $id_usuario = (int)($_SESSION['user_id'] ?? $_SESSION['id_usuario']);
    
    require_once('db_config.php');
    $conexion = getDBConnection();
    
    if (!$conexion) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conexion->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_manager = false;
    $is_admin = false;
    $departamentos_gerente = [];
    $departamento_principal = null;
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        
        if ($row['id_rol'] == 2) {
            $is_manager = true;
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
        
        if ($row['es_principal'] == 1 || $departamento_principal === null) {
            $departamento_principal = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    // Verificar que sea gerente
    if (!$is_manager && !$is_admin) {
        throw new Exception('Acceso no autorizado - Se requiere rol de gerente');
    }
    
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Usuario sin departamentos asignados como gerente');
    }
    
    if (ob_get_length()) ob_clean();
    
    $response = [
        'success' => false,
        'message' => '',
        'stats' => []
    ];
    
    if ($is_admin) {
        $whereClauseDept = "1=1";
        $paramsDept = [];
        $typesDept = "";
    } else {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $whereClauseDept = "id_departamento IN ($placeholders)";
        $paramsDept = $departamentos_gerente;
        $typesDept = str_repeat('i', count($departamentos_gerente));
    }
    
    $queryObjetivos = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) as vencidos
        FROM tbl_objetivos
        WHERE $whereClauseDept
    ";
    
    $stmtObjetivos = $conexion->prepare($queryObjetivos);
    if (!empty($paramsDept)) {
        $stmtObjetivos->bind_param($typesDept, ...$paramsDept);
    }
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
    
    $porcentajeObjetivos = $totalObjetivos > 0
        ? round(($objetivosCompletados / $totalObjetivos) * 100, 1)
        : 0;
    
    if ($is_admin) {
        $whereClauseProyectos = "1=1";
        $paramsProyectos = [];
        $typesProyectos = "";
    } else {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $whereClauseProyectos = "(p.id_departamento IN ($placeholders) OR p.id_creador = ? OR p.id_participante = ? OR pu.id_usuario = ?)";
        $paramsProyectos = array_merge($departamentos_gerente, [$id_usuario, $id_usuario, $id_usuario]);
        $typesProyectos = str_repeat('i', count($departamentos_gerente)) . 'iii';
    }
    
    $queryProyectos = "
        SELECT
            COUNT(DISTINCT p.id_proyecto) as total,
            SUM(CASE WHEN p.estado = 'completado' THEN 1 ELSE 0 END) as completados,
            SUM(CASE WHEN p.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso,
            SUM(CASE WHEN p.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN p.estado = 'vencido' THEN 1 ELSE 0 END) as vencidos,
            AVG(CASE WHEN p.estado = 'en proceso' THEN p.progreso ELSE NULL END) as progreso_promedio
        FROM tbl_proyectos p
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE $whereClauseProyectos
    ";
    
    $stmtProyectos = $conexion->prepare($queryProyectos);
    if (!empty($paramsProyectos)) {
        $stmtProyectos->bind_param($typesProyectos, ...$paramsProyectos);
    }
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
        SELECT COUNT(DISTINCT p.id_proyecto) as on_time
        FROM tbl_proyectos p
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE $whereClauseProyectos
            AND p.estado = 'completado'
            AND p.fecha_cumplimiento >= DATE(NOW())
    ";
    
    $stmtProyectosATiempo = $conexion->prepare($queryProyectosATiempo);
    if (!empty($paramsProyectos)) {
        $stmtProyectosATiempo->bind_param($typesProyectos, ...$paramsProyectos);
    }
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
    
    if ($is_admin) {
        $whereClauseTareas = "1=1";
        $paramsTareas = [];
        $typesTareas = "";
    } else {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $whereClauseTareas = "(p.id_departamento IN ($placeholders) OR t.id_creador = ? OR t.id_participante = ?)";
        $paramsTareas = array_merge($departamentos_gerente, [$id_usuario, $id_usuario]);
        $typesTareas = str_repeat('i', count($departamentos_gerente)) . 'ii';
    }
    
    $queryTareas = "
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as completadas,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as vencidas,
            SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) as en_proceso
        FROM tbl_tareas t
        INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE $whereClauseTareas
    ";
    
    $stmtTareas = $conexion->prepare($queryTareas);
    if (!empty($paramsTareas)) {
        $stmtTareas->bind_param($typesTareas, ...$paramsTareas);
    }
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
    
    $porcentajeTareas = $totalTareas > 0
        ? round(($tareasCompletadas / $totalTareas) * 100, 1)
        : 0;
    
    if ($is_admin) {
        $queryEmpleados = "SELECT COUNT(DISTINCT id_usuario) as total FROM tbl_usuarios";
        $stmtEmpleados = $conexion->prepare($queryEmpleados);
    } else {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $queryEmpleados = "
            SELECT COUNT(DISTINCT ur.id_usuario) as total
            FROM tbl_usuario_roles ur
            WHERE ur.id_departamento IN ($placeholders)
                AND ur.activo = 1
        ";
        $stmtEmpleados = $conexion->prepare($queryEmpleados);
        $stmtEmpleados->bind_param($typesDept, ...$paramsDept);
    }
    
    $stmtEmpleados->execute();
    $resultEmpleados = $stmtEmpleados->get_result();
    
    $totalEmpleados = 0;
    if ($row = $resultEmpleados->fetch_assoc()) {
        $totalEmpleados = (int)$row['total'];
    }
    $stmtEmpleados->close();
    
    $conexion->close();
    
    $response['success'] = true;
    $response['stats'] = [
        // Contadores principales
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
        
        // Estadísticas adicionales
        'objetivos_retrasados' => $objetivosRetrasados,
        'tareas_retrasadas' => $tareasRetrasadas,
        'tareas_en_proceso' => $tareasEnProceso,
        'porcentaje_completados' => $porcentajeCompletados,
        'porcentaje_vencidos' => $porcentajeVencidos,
        'porcentaje_pendientes' => $porcentajePendientes,
        'progreso_promedio_en_proceso' => $progresoPromedioEnProceso,
        'proyectos_a_tiempo' => $proyectosATiempo,
        
        // Información de gerente
        'total_empleados' => $totalEmpleados,
        'id_departamento' => $departamento_principal,
        'managed_departments' => $departamentos_gerente,
        'managed_departments_count' => count($departamentos_gerente),
        
        // Compatibilidad
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
    
    // Metadata de alcance
    $response['scope'] = [
        'user_id' => $id_usuario,
        'is_admin' => $is_admin,
        'is_manager' => $is_manager,
        'managed_departments' => $departamentos_gerente,
        'scope_type' => $is_admin ? 'global' : 'managed_departments'
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
            'id_departamento' => 0,
            'managed_departments' => [],
            'managed_departments_count' => 0
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats_manager.php: ' . $e->getMessage());
}

ob_end_flush();
?>