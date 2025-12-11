<?php
//get_dashboard_stats.php para los contadores del dashboard
ob_start();

// Suprimir errores de visualización
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('db_config.php');
$conexion = getDBConnection();

// Limpiar cualquier salida previa
if (ob_get_length()) ob_clean();

// Establecer header JSON
header('Content-Type: application/json; charset=utf-8');

try {
    if (!file_exists('db_config.php')) {
        throw new Exception('Archivo de conexión no encontrado');
    }

    require_once('db_config.php');

    if (!isset($conexion)) {
        throw new Exception('Conexión a base de datos no establecida');
    }

    $response = [
        'success' => false,
        'message' => '',
        'stats' => []
    ];

    // Función helper para verificar si una tabla existe (usando MySQLi)
    function tableExists($conexion, $tableName) {
        try {
            $stmt = $conexion->prepare("SELECT 1 FROM {$tableName} LIMIT 1");
            if (!$stmt) {
                return false;
            }
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    // Obtener total de objetivos (si la tabla existe)
    $totalObjetivos = 0;
    $objetivosCompletados = 0;
    $porcentajeObjetivos = 0;
    $objetivosRetrasados = 0;

    if (tableExists($conexion, 'tbl_objetivos')) {
        try {
            $queryObjetivos = "SELECT COUNT(*) as total FROM tbl_objetivos";
            $stmtObjetivos = $conexion->prepare($queryObjetivos);
            if ($stmtObjetivos) {
                $stmtObjetivos->execute();
                $resultObjetivos = $stmtObjetivos->get_result();
                if ($resultObjetivos && $resultObjetivos->num_rows > 0) {
                    $row = $resultObjetivos->fetch_assoc();
                    if ($row && isset($row['total'])) {
                        $totalObjetivos = (int)$row['total'];
                    }
                }
                $stmtObjetivos->close();
            }

            $queryObjetivosCompletados = "
                SELECT COUNT(*) as completados
                FROM tbl_objetivos
                WHERE estado = 'completado'
            ";
            $stmtObjetivosCompletados = $conexion->prepare($queryObjetivosCompletados);
            if ($stmtObjetivosCompletados) {
                $stmtObjetivosCompletados->execute();
                $resultObjetivosCompletados = $stmtObjetivosCompletados->get_result();
                if ($resultObjetivosCompletados && $resultObjetivosCompletados->num_rows > 0) {
                    $row = $resultObjetivosCompletados->fetch_assoc();
                    if ($row && isset($row['completados'])) {
                        $objetivosCompletados = (int)$row['completados'];
                    }
                }
                $stmtObjetivosCompletados->close();
            }

            $queryObjetivosRetrasados = "
                SELECT COUNT(*) as retrasados
                FROM tbl_objetivos
                WHERE estado = 'vencido'
            ";
            $stmtObjetivosRetrasados = $conexion->prepare($queryObjetivosRetrasados);
            if ($stmtObjetivosRetrasados) {
                $stmtObjetivosRetrasados->execute();
                $resultObjetivosRetrasados = $stmtObjetivosRetrasados->get_result();
                if ($resultObjetivosRetrasados && $resultObjetivosRetrasados->num_rows > 0) {
                    $row = $resultObjetivosRetrasados->fetch_assoc();
                    if ($row && isset($row['retrasados'])) {
                        $objetivosRetrasados = (int)$row['retrasados'];
                    }
                }
                $stmtObjetivosRetrasados->close();
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
    $totalProyectosCompletados = 0;

    if (tableExists($conexion, 'tbl_proyectos')) {
        try {
            // Total de proyectos
            $queryProyectos = "SELECT COUNT(*) as total FROM tbl_proyectos";
            $stmtProyectos = $conexion->prepare($queryProyectos);
            if ($stmtProyectos) {
                $stmtProyectos->execute();
                $resultProyectos = $stmtProyectos->get_result();
                if ($resultProyectos && $resultProyectos->num_rows > 0) {
                    $row = $resultProyectos->fetch_assoc();
                    if ($row && isset($row['total'])) {
                        $totalProyectos = (int)$row['total'];
                    }
                }
                $stmtProyectos->close();
            }

            $queryEstados = "
                SELECT
                    estado,
                    COUNT(*) as cantidad
                FROM tbl_proyectos
                GROUP BY estado
            ";
            $stmtEstados = $conexion->prepare($queryEstados);
            if ($stmtEstados) {
                $stmtEstados->execute();
                $resultEstados = $stmtEstados->get_result();
                
                if ($resultEstados && $resultEstados->num_rows > 0) {
                    while ($estado = $resultEstados->fetch_assoc()) {
                        $estadoNombre = strtolower(trim($estado['estado']));
                        if (isset($estadosCount[$estadoNombre])) {
                            $estadosCount[$estadoNombre] = (int)$estado['cantidad'];
                        }
                    }
                }
                $stmtEstados->close();
            }

            // Proyectos completados a tiempo
            $queryOnTime = "
                SELECT COUNT(*) as on_time
                FROM tbl_proyectos
                WHERE estado = 'completado'
                AND fecha_cumplimiento >= DATE(NOW())
            ";
            $stmtOnTime = $conexion->prepare($queryOnTime);
            if ($stmtOnTime) {
                $stmtOnTime->execute();
                $resultOnTime = $stmtOnTime->get_result();
                if ($resultOnTime && $resultOnTime->num_rows > 0) {
                    $row = $resultOnTime->fetch_assoc();
                    if ($row && isset($row['on_time'])) {
                        $projectsOnTime = (int)$row['on_time'];
                    }
                }
                $stmtOnTime->close();
            }

            // Proyectos vencidos
            $queryOverdue = "
                SELECT COUNT(*) as overdue
                FROM tbl_proyectos
                WHERE estado = 'vencido'
            ";
            $stmtOverdue = $conexion->prepare($queryOverdue);
            if ($stmtOverdue) {
                $stmtOverdue->execute();
                $resultOverdue = $stmtOverdue->get_result();
                if ($resultOverdue && $resultOverdue->num_rows > 0) {
                    $row = $resultOverdue->fetch_assoc();
                    if ($row && isset($row['overdue'])) {
                        $projectsOverdue = (int)$row['overdue'];
                    }
                }
                $stmtOverdue->close();
            }

            $totalProyectosCompletados = $estadosCount['completado'];
        } catch (Exception $e) {
            error_log('Error consultando proyectos: ' . $e->getMessage());
        }
    }

    // Obtener total de tareas 
    $totalTareas = 0;
    $tareasCompletadas = 0;
    $porcentajeTareas = 0;
    $tareasRetrasadas = 0;
    $tareasEnProceso = 0;

    if (tableExists($conexion, 'tbl_tareas')) {
        try {
            // Total de tareas
            $queryTareas = "SELECT COUNT(*) as total FROM tbl_tareas";
            $stmtTareas = $conexion->prepare($queryTareas);
            if ($stmtTareas) {
                $stmtTareas->execute();
                $resultTareas = $stmtTareas->get_result();
                if ($resultTareas && $resultTareas->num_rows > 0) {
                    $row = $resultTareas->fetch_assoc();
                    if ($row && isset($row['total'])) {
                        $totalTareas = (int)$row['total'];
                    }
                }
                $stmtTareas->close();
            }

            // Tareas completadas
            $queryTareasCompletadas = "
                SELECT COUNT(*) as completadas
                FROM tbl_tareas
                WHERE estado = 'completado'
            ";
            $stmtTareasCompletadas = $conexion->prepare($queryTareasCompletadas);
            if ($stmtTareasCompletadas) {
                $stmtTareasCompletadas->execute();
                $resultTareasCompletadas = $stmtTareasCompletadas->get_result();
                if ($resultTareasCompletadas && $resultTareasCompletadas->num_rows > 0) {
                    $row = $resultTareasCompletadas->fetch_assoc();
                    if ($row && isset($row['completadas'])) {
                        $tareasCompletadas = (int)$row['completadas'];
                    }
                }
                $stmtTareasCompletadas->close();
            }

            // Tareas vencidas
            $queryTareasRetrasadas = "
                SELECT COUNT(*) as retrasadas
                FROM tbl_tareas
                WHERE estado = 'vencido'
            ";
            $stmtTareasRetrasadas = $conexion->prepare($queryTareasRetrasadas);
            if ($stmtTareasRetrasadas) {
                $stmtTareasRetrasadas->execute();
                $resultTareasRetrasadas = $stmtTareasRetrasadas->get_result();
                if ($resultTareasRetrasadas && $resultTareasRetrasadas->num_rows > 0) {
                    $row = $resultTareasRetrasadas->fetch_assoc();
                    if ($row && isset($row['retrasadas'])) {
                        $tareasRetrasadas = (int)$row['retrasadas'];
                    }
                }
                $stmtTareasRetrasadas->close();
            }

            // Tareas en proceso
            $queryTareasEnProceso = "
                SELECT COUNT(*) as en_proceso
                FROM tbl_tareas
                WHERE estado = 'en proceso'
            ";
            $stmtTareasEnProceso = $conexion->prepare($queryTareasEnProceso);
            if ($stmtTareasEnProceso) {
                $stmtTareasEnProceso->execute();
                $resultTareasEnProceso = $stmtTareasEnProceso->get_result();
                if ($resultTareasEnProceso && $resultTareasEnProceso->num_rows > 0) {
                    $row = $resultTareasEnProceso->fetch_assoc();
                    if ($row && isset($row['en_proceso'])) {
                        $tareasEnProceso = (int)$row['en_proceso'];
                    }
                }
                $stmtTareasEnProceso->close();
            }

            $porcentajeTareas = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100, 1) : 0;
        } catch (Exception $e) {
            error_log('Error consultando tareas: ' . $e->getMessage());
        }
    }

    // Calcular progreso promedio de proyectos en proceso
    $progresoPromedioEnProceso = 0;
    if ($estadosCount['en proceso'] > 0) {
        try {
            $queryProgresoPromedio = "
                SELECT AVG(progreso) as promedio
                FROM tbl_proyectos
                WHERE estado = 'en proceso'
            ";
            $stmtProgresoPromedio = $conexion->prepare($queryProgresoPromedio);
            if ($stmtProgresoPromedio) {
                $stmtProgresoPromedio->execute();
                $resultProgresoPromedio = $stmtProgresoPromedio->get_result();
                if ($resultProgresoPromedio && $resultProgresoPromedio->num_rows > 0) {
                    $row = $resultProgresoPromedio->fetch_assoc();
                    if ($row && isset($row['promedio'])) {
                        $progresoPromedioEnProceso = round((float)$row['promedio'], 1);
                    }
                }
                $stmtProgresoPromedio->close();
            }
        } catch (Exception $e) {
            error_log('Error calculando progreso promedio: ' . $e->getMessage());
        }
    }

    // Calcular porcentaje de proyectos completados
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
        //estadisticas adicionales calculadas para mostrar debajo de contadores
        'objetivos_retrasados' => (int)$objetivosRetrasados,
        'tareas_retrasadas' => (int)$tareasRetrasadas,
        'tareas_en_proceso' => (int)$tareasEnProceso,
        'porcentaje_completados' => (float)$porcentajeCompletados,
        'porcentaje_vencidos' => (float)$porcentajeVencidos,
        'porcentaje_pendientes' => (float)$porcentajePendientes,
        'progreso_promedio_en_proceso' => (float)$progresoPromedioEnProceso,
        'proyectos_a_tiempo' => (int)$projectsOnTime
    ];

    // Limpiar buffer de salida antes de enviar JSON
    ob_clean();

    // Enviar respuesta JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // Limpiar buffer
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

    // Log del error
    error_log('Error en get_dashboard_stats.php: ' . $e->getMessage());
}

// Finalizar y limpiar buffer
ob_end_flush();
?>