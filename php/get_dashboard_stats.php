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
        
        // Si es gerente, también incluir proyectos donde está asignado
        if ($is_manager && !$is_admin) {
            // Los departamentos de proyectos asignados se manejarán en las consultas
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

    $totalObjetivos = 0;
    $objetivosCompletados = 0;
    $porcentajeObjetivos = 0;
    $objetivosRetrasados = 0;

    if (tableExists($conexion, 'tbl_objetivos')) {
        try {
            if ($is_admin || !$id_usuario) {
                // Admin ve todo
                $queryObjetivos = "SELECT COUNT(*) as total FROM tbl_objetivos";
                $stmtObjetivos = $conexion->prepare($queryObjetivos);
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                // Gerente ve objetivos de sus departamentos
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $queryObjetivos = "SELECT COUNT(*) as total FROM tbl_objetivos WHERE id_departamento IN ($placeholders)";
                $stmtObjetivos = $conexion->prepare($queryObjetivos);
                $types = str_repeat('i', count($departamentos_usuario));
                $stmtObjetivos->bind_param($types, ...$departamentos_usuario);
            } else {
                // Usuario normal - solo sus departamentos
                $stmtObjetivos = null;
            }
            
            if ($stmtObjetivos) {
                $stmtObjetivos->execute();
                $resultObjetivos = $stmtObjetivos->get_result();
                if ($resultObjetivos && $row = $resultObjetivos->fetch_assoc()) {
                    $totalObjetivos = (int)$row['total'];
                }
                $stmtObjetivos->close();
            }

            // Objetivos completados
            if ($is_admin || !$id_usuario) {
                $queryCompletados = "SELECT COUNT(*) as completados FROM tbl_objetivos WHERE estado = 'completado'";
                $stmtCompletados = $conexion->prepare($queryCompletados);
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $queryCompletados = "SELECT COUNT(*) as completados FROM tbl_objetivos WHERE estado = 'completado' AND id_departamento IN ($placeholders)";
                $stmtCompletados = $conexion->prepare($queryCompletados);
                $types = str_repeat('i', count($departamentos_usuario));
                $stmtCompletados->bind_param($types, ...$departamentos_usuario);
            } else {
                $stmtCompletados = null;
            }

            if ($stmtCompletados) {
                $stmtCompletados->execute();
                $resultCompletados = $stmtCompletados->get_result();
                if ($resultCompletados && $row = $resultCompletados->fetch_assoc()) {
                    $objetivosCompletados = (int)$row['completados'];
                }
                $stmtCompletados->close();
            }

            // Objetivos vencidos
            if ($is_admin || !$id_usuario) {
                $queryRetrasados = "SELECT COUNT(*) as retrasados FROM tbl_objetivos WHERE estado = 'vencido'";
                $stmtRetrasados = $conexion->prepare($queryRetrasados);
            } elseif ($is_manager && !empty($departamentos_usuario)) {
                $placeholders = implode(',', array_fill(0, count($departamentos_usuario), '?'));
                $queryRetrasados = "SELECT COUNT(*) as retrasados FROM tbl_objetivos WHERE estado = 'vencido' AND id_departamento IN ($placeholders)";
                $stmtRetrasados = $conexion->prepare($queryRetrasados);
                $types = str_repeat('i', count($departamentos_usuario));
                $stmtRetrasados->bind_param($types, ...$departamentos_usuario);
            } else {
                $stmtRetrasados = null;
            }

            if ($stmtRetrasados) {
                $stmtRetrasados->execute();
                $resultRetrasados = $stmtRetrasados->get_result();
                if ($resultRetrasados && $row = $resultRetrasados->fetch_assoc()) {
                    $objetivosRetrasados = (int)$row['retrasados'];
                }
                $stmtRetrasados->close();
            }

            $porcentajeObjetivos = $totalObjetivos > 0 ? round(($objetivosCompletados / $totalObjetivos) * 100, 1) : 0;

        } catch (Exception $e) {
            error_log('Error consultando objetivos: ' . $e->getMessage());
        }
    }

    $totalProyectos = 0;
    $estadosCount = [
        'completado' => 0,
        'en proceso' => 0,
        'pendiente' => 0,
        'vencido' => 0
    ];
    $projectsOnTime = 0;
    $projectsOverdue = 0;

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

            // Total de proyectos
            $queryProyectos = "
                SELECT COUNT(DISTINCT p.id_proyecto) as total 
                FROM tbl_proyectos p
                LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
                WHERE $whereClause
            ";
            $stmtProyectos = $conexion->prepare($queryProyectos);
            if ($stmtProyectos && !empty($params)) {
                $stmtProyectos->bind_param($types, ...$params);
            }
            if ($stmtProyectos) {
                $stmtProyectos->execute();
                $resultProyectos = $stmtProyectos->get_result();
                if ($resultProyectos && $row = $resultProyectos->fetch_assoc()) {
                    $totalProyectos = (int)$row['total'];
                }
                $stmtProyectos->close();
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
                    if (isset($estadosCount[$estadoNombre])) {
                        $estadosCount[$estadoNombre] = (int)$estado['cantidad'];
                    }
                }
                $stmtEstados->close();
            }

            $projectsOverdue = $estadosCount['vencido'];

        } catch (Exception $e) {
            error_log('Error consultando proyectos: ' . $e->getMessage());
        }
    }

    $totalTareas = 0;
    $tareasCompletadas = 0;
    $porcentajeTareas = 0;
    $tareasRetrasadas = 0;
    $tareasEnProceso = 0;

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

            // Total tareas
            $queryTareas = "
                SELECT COUNT(*) as total 
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE $whereClauseTareas
            ";
            $stmtTareas = $conexion->prepare($queryTareas);
            if ($stmtTareas && !empty($paramsTareas)) {
                $stmtTareas->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtTareas) {
                $stmtTareas->execute();
                $resultTareas = $stmtTareas->get_result();
                if ($resultTareas && $row = $resultTareas->fetch_assoc()) {
                    $totalTareas = (int)$row['total'];
                }
                $stmtTareas->close();
            }

            // Tareas completadas
            $queryTareasCompletadas = "
                SELECT COUNT(*) as completadas 
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE t.estado = 'completado' AND $whereClauseTareas
            ";
            $stmtTareasCompletadas = $conexion->prepare($queryTareasCompletadas);
            if ($stmtTareasCompletadas && !empty($paramsTareas)) {
                $stmtTareasCompletadas->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtTareasCompletadas) {
                $stmtTareasCompletadas->execute();
                $resultTareasCompletadas = $stmtTareasCompletadas->get_result();
                if ($resultTareasCompletadas && $row = $resultTareasCompletadas->fetch_assoc()) {
                    $tareasCompletadas = (int)$row['completadas'];
                }
                $stmtTareasCompletadas->close();
            }

            // Tareas vencidas
            $queryTareasRetrasadas = "
                SELECT COUNT(*) as retrasadas 
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE t.estado = 'vencido' AND $whereClauseTareas
            ";
            $stmtTareasRetrasadas = $conexion->prepare($queryTareasRetrasadas);
            if ($stmtTareasRetrasadas && !empty($paramsTareas)) {
                $stmtTareasRetrasadas->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtTareasRetrasadas) {
                $stmtTareasRetrasadas->execute();
                $resultTareasRetrasadas = $stmtTareasRetrasadas->get_result();
                if ($resultTareasRetrasadas && $row = $resultTareasRetrasadas->fetch_assoc()) {
                    $tareasRetrasadas = (int)$row['retrasadas'];
                }
                $stmtTareasRetrasadas->close();
            }

            // Tareas en proceso
            $queryTareasEnProceso = "
                SELECT COUNT(*) as en_proceso 
                FROM tbl_tareas t
                LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
                WHERE t.estado = 'en proceso' AND $whereClauseTareas
            ";
            $stmtTareasEnProceso = $conexion->prepare($queryTareasEnProceso);
            if ($stmtTareasEnProceso && !empty($paramsTareas)) {
                $stmtTareasEnProceso->bind_param($typesTareas, ...$paramsTareas);
            }
            if ($stmtTareasEnProceso) {
                $stmtTareasEnProceso->execute();
                $resultTareasEnProceso = $stmtTareasEnProceso->get_result();
                if ($resultTareasEnProceso && $row = $resultTareasEnProceso->fetch_assoc()) {
                    $tareasEnProceso = (int)$row['en_proceso'];
                }
                $stmtTareasEnProceso->close();
            }

            $porcentajeTareas = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100, 1) : 0;

        } catch (Exception $e) {
            error_log('Error consultando tareas: ' . $e->getMessage());
        }
    }

    // Calcular porcentajes
    $porcentajeCompletados = $totalProyectos > 0 ? round(($estadosCount['completado'] / $totalProyectos) * 100, 1) : 0;
    $porcentajeVencidos = $totalProyectos > 0 ? round(($estadosCount['vencido'] / $totalProyectos) * 100, 1) : 0;
    $porcentajePendientes = $totalProyectos > 0 ? round(($estadosCount['pendiente'] / $totalProyectos) * 100, 1) : 0;

    // Construir respuesta
    $response['success'] = true;
    $response['stats'] = [
        'total_objetivos' => (int)$totalObjetivos,
        'total_proyectos' => (int)$totalProyectos,
        'total_tareas' => (int)$totalTareas,
        'porcentaje_tareas' => (float)$porcentajeTareas,
        'porcentaje_objetivos' => (float)$porcentajeObjetivos,
        'proyectos_completados' => (int)$estadosCount['completado'],
        'proyectos_en_proceso' => (int)$estadosCount['en proceso'],
        'proyectos_pendientes' => (int)$estadosCount['pendiente'],
        'proyectos_vencidos' => (int)$estadosCount['vencido'],
        'objetivos_retrasados' => (int)$objetivosRetrasados,
        'tareas_retrasadas' => (int)$tareasRetrasadas,
        'tareas_en_proceso' => (int)$tareasEnProceso,
        'porcentaje_completados' => (float)$porcentajeCompletados,
        'porcentaje_vencidos' => (float)$porcentajeVencidos,
        'porcentaje_pendientes' => (float)$porcentajePendientes,
        'progreso_promedio_en_proceso' => 0,
        'proyectos_a_tiempo' => (int)$projectsOnTime
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
            'proyectos_a_tiempo' => 0
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_dashboard_stats.php: ' . $e->getMessage());
}

ob_end_flush();
?>