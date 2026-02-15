<?php
/*get_dashboard_stats.php para los contadores del dashboard*/

ob_start();
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('db_config.php');
$conexion = getDBConnection();

if (ob_get_length()) ob_clean();

header('Content-Type: application/json; charset=utf-8');

try {
    if (!$conexion) {
        throw new Exception('Conexión a base de datos no establecida');
    }

    $response = [
        'success' => false,
        'message' => '',
        'stats' => []
    ];

    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    $id_rol = null;
    $departamentos_usuario = [];
    $is_admin = false;
    $is_manager = false;

    if ($id_usuario) {
        // Obtener rol principal
        $role_query = "
            SELECT ur.id_rol, ur.id_departamento, ur.es_principal
            FROM tbl_usuario_roles ur
            WHERE ur.id_usuario = ? AND ur.activo = 1
            ORDER BY ur.es_principal DESC
        ";
        $role_stmt = $conexion->prepare($role_query);
        if ($role_stmt) {
            $role_stmt->bind_param('i', $id_usuario);
            $role_stmt->execute();
            $role_result = $role_stmt->get_result();
            
            while ($row = $role_result->fetch_assoc()) {
                if ($row['es_principal'] == 1 || $id_rol === null) {
                    $id_rol = (int)$row['id_rol'];
                }
                
                // Administrador
                if ($row['id_rol'] == 1) {
                    $is_admin = true;
                }
                
                // Gerente - guardar departamentos
                if ($row['id_rol'] == 2) {
                    $is_manager = true;
                    $departamentos_usuario[] = (int)$row['id_departamento'];
                }
            }
            $role_stmt->close();
        }
    }

    // Función helper para verificar si una tabla existe
    function tableExists($conexion, $tableName) {
        try {
            $stmt = $conexion->prepare("SELECT 1 FROM {$tableName} LIMIT 1");
            if (!$stmt) return false;
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // ==================== OBJETIVOS ====================
    $totalObjetivos = 0;
    $objetivosCompletados = 0;
    $objetivosPendientes = 0;
    $objetivosEnProceso = 0;
    $objetivosRetrasados = 0;
    $porcentajeObjetivos = 0;

    if (tableExists($conexion, 'tbl_objetivos')) {
        try {
            if ($is_admin || !$id_usuario) {
                $queryObjetivos = "SELECT estado, COUNT(*) as cantidad FROM tbl_objetivos GROUP BY estado";
                $stmtObjetivos = $conexion->prepare($queryObjetivos);
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $queryObjetivos = "SELECT estado, COUNT(*) as cantidad FROM tbl_objetivos WHERE id_departamento IN ($placeholders) GROUP BY estado";
                $stmtObjetivos = $conexion->prepare($queryObjetivos);
                $types = str_repeat('i', count($departamentos_usuario));
                $stmtObjetivos->bind_param($types, ...$departamentos_usuario);
            } else {
                $stmtObjetivos = null;
            }
            
            if ($stmtObjetivos) {
                $stmtObjetivos->execute();
                $resultObjetivos = $stmtObjetivos->get_result();
                while ($row = $resultObjetivos->fetch_assoc()) {
                    $estado = strtolower(trim($row['estado']));
                    $cantidad = (int)$row['cantidad'];
                    $totalObjetivos += $cantidad;
                    
                    if ($estado === 'completado') {
                        $objetivosCompletados = $cantidad;
                    } elseif ($estado === 'pendiente') {
                        $objetivosPendientes = $cantidad;
                    } elseif ($estado === 'en proceso') {
                        $objetivosEnProceso = $cantidad;
                    } elseif ($estado === 'vencido') {
                        $objetivosRetrasados = $cantidad;
                    }
                }
                $stmtObjetivos->close();
            }

            $porcentajeObjetivos = $totalObjetivos > 0 ? round(($objetivosCompletados / $totalObjetivos) * 100, 1) : 0;

        } catch (Exception $e) {
            error_log('Error consultando objetivos: ' . $e->getMessage());
        }
    }

    // ==================== PROYECTOS ====================
    $totalProyectos = 0;
    $proyectosCompletados = 0;
    $proyectosEnProceso = 0;
    $proyectosPendientes = 0;
    $proyectosVencidos = 0;

    if (tableExists($conexion, 'tbl_proyectos')) {
        try {
            // Construir WHERE clause según rol
            if ($is_admin || !$id_usuario) {
                $whereClause = "1=1";
                $params = [];
                $types = "";
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $whereClause = "(p.id_departamento IN ($placeholders) OR p.id_creador = ? OR p.id_participante = ? OR pu.id_usuario = ?)";
                $params = array_merge($departamentos_usuario, [$id_usuario, $id_usuario, $id_usuario]);
                $types = str_repeat('i', count($departamentos_usuario)) . 'iii';
            } else {
                $whereClause = "(p.id_creador = ? OR p.id_participante = ? OR pu.id_usuario = ?)";
                $params = [$id_usuario, $id_usuario, $id_usuario];
                $types = "iii";
            }

            // Contar por estado
            $queryEstados = "
                SELECT p.estado, COUNT(DISTINCT p.id_proyecto) as cantidad
                FROM tbl_proyectos p
                LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
                WHERE $whereClause
                GROUP BY p.estado
            ";
            $stmtEstados = $conexion->prepare($queryEstados);
            if ($stmtEstados && !empty($params)) {
                $stmtEstados->bind_param($types, ...$params);
            }
            if ($stmtEstados) {
                $stmtEstados->execute();
                $resultEstados = $stmtEstados->get_result();
                while ($estado = $resultEstados->fetch_assoc()) {
                    $estadoNombre = strtolower(trim($estado['estado']));
                    $cantidad = (int)$estado['cantidad'];
                    $totalProyectos += $cantidad;
                    
                    if ($estadoNombre === 'completado') {
                        $proyectosCompletados = $cantidad;
                    } elseif ($estadoNombre === 'en proceso') {
                        $proyectosEnProceso = $cantidad;
                    } elseif ($estadoNombre === 'pendiente') {
                        $proyectosPendientes = $cantidad;
                    } elseif ($estadoNombre === 'vencido') {
                        $proyectosVencidos = $cantidad;
                    }
                }
                $stmtEstados->close();
            }

        } catch (Exception $e) {
            error_log('Error consultando proyectos: ' . $e->getMessage());
        }
    }

    // ==================== TAREAS ====================
    $totalTareas = 0;
    $tareasCompletadas = 0;
    $tareasPendientes = 0;
    $tareasVencidas = 0;
    $tareasPorVencer = 0; // Tareas que vencen en los próximos 3 días
    $porcentajeTareas = 0;

    if (tableExists($conexion, 'tbl_tareas')) {
        try {
            // Construir WHERE clause según rol
            if ($is_admin || !$id_usuario) {
                $whereClauseTareas = "1=1";
                $paramsTareas = [];
                $typesTareas = "";
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $whereClauseTareas = "(p.id_departamento IN ($placeholders) OR t.id_creador = ? OR t.id_participante = ?)";
                $paramsTareas = array_merge($departamentos_usuario, [$id_usuario, $id_usuario]);
                $typesTareas = str_repeat('i', count($departamentos_usuario)) . 'ii';
            } else {
                $whereClauseTareas = "(t.id_creador = ? OR t.id_participante = ?)";
                $paramsTareas = [$id_usuario, $id_usuario];
                $typesTareas = "ii";
            }

            // Contar tareas por estado
            $queryTareasEstado = "
                SELECT t.estado, COUNT(*) as cantidad
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE $whereClauseTareas
                GROUP BY t.estado
            ";
            $stmtTareasEstado = $conexion->prepare($queryTareasEstado);
            if ($stmtTareasEstado && !empty($paramsTareas)) {
                $stmtTareasEstado->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtTareasEstado) {
                $stmtTareasEstado->execute();
                $resultTareasEstado = $stmtTareasEstado->get_result();
                while ($row = $resultTareasEstado->fetch_assoc()) {
                    $estado = strtolower(trim($row['estado']));
                    $cantidad = (int)$row['cantidad'];
                    $totalTareas += $cantidad;
                    
                    if ($estado === 'completado') {
                        $tareasCompletadas = $cantidad;
                    } elseif ($estado === 'pendiente') {
                        $tareasPendientes = $cantidad;
                    } elseif ($estado === 'vencido') {
                        $tareasVencidas = $cantidad;
                    }
                }
                $stmtTareasEstado->close();
            }

            // Tareas por vencer (próximos 3 días)
            $queryPorVencer = "
                SELECT COUNT(*) as cantidad
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE $whereClauseTareas
                AND t.estado = 'pendiente'
                AND t.fecha_cumplimiento IS NOT NULL
                AND t.fecha_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
            ";
            $stmtPorVencer = $conexion->prepare($queryPorVencer);
            if ($stmtPorVencer && !empty($paramsTareas)) {
                $stmtPorVencer->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtPorVencer) {
                $stmtPorVencer->execute();
                $resultPorVencer = $stmtPorVencer->get_result();
                if ($resultPorVencer && $row = $resultPorVencer->fetch_assoc()) {
                    $tareasPorVencer = (int)$row['cantidad'];
                }
                $stmtPorVencer->close();
            }

            $porcentajeTareas = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100, 1) : 0;

        } catch (Exception $e) {
            error_log('Error consultando tareas: ' . $e->getMessage());
        }
    }

    // ==================== PROYECTOS POR VENCER ====================
    $proyectosPorVencer = 0;
    
    if (tableExists($conexion, 'tbl_proyectos')) {
        try {
            if ($is_admin || !$id_usuario) {
                $queryProyPorVencer = "
                    SELECT COUNT(*) as cantidad
                    FROM tbl_proyectos
                    WHERE estado IN ('pendiente', 'en proceso')
                    AND fecha_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ";
                $stmtProyPorVencer = $conexion->prepare($queryProyPorVencer);
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $queryProyPorVencer = "
                    SELECT COUNT(DISTINCT p.id_proyecto) as cantidad
                    FROM tbl_proyectos p
                    LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
                    WHERE (p.id_departamento IN ($placeholders) OR p.id_creador = ? OR p.id_participante = ? OR pu.id_usuario = ?)
                    AND p.estado IN ('pendiente', 'en proceso')
                    AND p.fecha_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ";
                $stmtProyPorVencer = $conexion->prepare($queryProyPorVencer);
                $paramsProyVencer = array_merge($departamentos_usuario, [$id_usuario, $id_usuario, $id_usuario]);
                $typesProyVencer = str_repeat('i', count($departamentos_usuario)) . 'iii';
                $stmtProyPorVencer->bind_param($typesProyVencer, ...$paramsProyVencer);
            } else {
                $queryProyPorVencer = "
                    SELECT COUNT(DISTINCT p.id_proyecto) as cantidad
                    FROM tbl_proyectos p
                    LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
                    WHERE (p.id_creador = ? OR p.id_participante = ? OR pu.id_usuario = ?)
                    AND p.estado IN ('pendiente', 'en proceso')
                    AND p.fecha_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ";
                $stmtProyPorVencer = $conexion->prepare($queryProyPorVencer);
                $stmtProyPorVencer->bind_param('iii', $id_usuario, $id_usuario, $id_usuario);
            }
            
            if ($stmtProyPorVencer) {
                $stmtProyPorVencer->execute();
                $resultProyPorVencer = $stmtProyPorVencer->get_result();
                if ($resultProyPorVencer && $row = $resultProyPorVencer->fetch_assoc()) {
                    $proyectosPorVencer = (int)$row['cantidad'];
                }
                $stmtProyPorVencer->close();
            }
        } catch (Exception $e) {
            error_log('Error consultando proyectos por vencer: ' . $e->getMessage());
        }
    }

    // ==================== CALCULAR PORCENTAJES ====================
    $porcentajeCompletados = $totalProyectos > 0 ? round(($proyectosCompletados / $totalProyectos) * 100, 1) : 0;
    $porcentajeVencidos = $totalProyectos > 0 ? round(($proyectosVencidos / $totalProyectos) * 100, 1) : 0;
    $porcentajePendientes = $totalProyectos > 0 ? round(($proyectosPendientes / $totalProyectos) * 100, 1) : 0;
    $porcentajeEnProceso = $totalProyectos > 0 ? round(($proyectosEnProceso / $totalProyectos) * 100, 1) : 0;

    // ==================== CONSTRUIR RESPUESTA ====================
    $response['success'] = true;
    $response['stats'] = [
        // Objetivos
        'total_objetivos' => (int)$totalObjetivos,
        'objetivos_completados' => (int)$objetivosCompletados,
        'objetivos_pendientes' => (int)$objetivosPendientes,
        'objetivos_en_proceso' => (int)$objetivosEnProceso,
        'objetivos_retrasados' => (int)$objetivosRetrasados,
        'porcentaje_objetivos' => (float)$porcentajeObjetivos,
        
        // Proyectos
        'total_proyectos' => (int)$totalProyectos,
        'proyectos_completados' => (int)$proyectosCompletados,
        'proyectos_en_proceso' => (int)$proyectosEnProceso,
        'proyectos_pendientes' => (int)$proyectosPendientes,
        'proyectos_vencidos' => (int)$proyectosVencidos,
        'proyectos_por_vencer' => (int)$proyectosPorVencer,
        'porcentaje_completados' => (float)$porcentajeCompletados,
        'porcentaje_vencidos' => (float)$porcentajeVencidos,
        'porcentaje_pendientes' => (float)$porcentajePendientes,
        'porcentaje_en_proceso' => (float)$porcentajeEnProceso,
        
        // Tareas
        'total_tareas' => (int)$totalTareas,
        'tareas_completadas' => (int)$tareasCompletadas,
        'tareas_pendientes' => (int)$tareasPendientes,
        'tareas_vencidas' => (int)$tareasVencidas,
        'tareas_por_vencer' => (int)$tareasPorVencer,
        'porcentaje_tareas' => (float)$porcentajeTareas
    ];

    // Metadata sobre el alcance
    $response['scope'] = [
        'user_id' => $id_usuario,
        'is_admin' => $is_admin,
        'is_manager' => $is_manager,
        'managed_departments' => $departamentos_usuario,
        'scope_type' => $is_admin ? 'global' : ($is_manager ? 'managed_departments' : 'personal')
    ];

    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    ob_clean();

    $response = [
        'success' => false,
        'message' => 'Error del servidor',
        'error_detail' => $e->getMessage(),
        'stats' => [
            'total_objetivos' => 0,
            'objetivos_completados' => 0,
            'objetivos_pendientes' => 0,
            'objetivos_en_proceso' => 0,
            'objetivos_retrasados' => 0,
            'porcentaje_objetivos' => 0,
            'total_proyectos' => 0,
            'proyectos_completados' => 0,
            'proyectos_en_proceso' => 0,
            'proyectos_pendientes' => 0,
            'proyectos_vencidos' => 0,
            'proyectos_por_vencer' => 0,
            'porcentaje_completados' => 0,
            'porcentaje_vencidos' => 0,
            'porcentaje_pendientes' => 0,
            'porcentaje_en_proceso' => 0,
            'total_tareas' => 0,
            'tareas_completadas' => 0,
            'tareas_pendientes' => 0,
            'tareas_vencidas' => 0,
            'tareas_por_vencer' => 0,
            'porcentaje_tareas' => 0
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats.php: ' . $e->getMessage());
}

ob_end_flush();
?>