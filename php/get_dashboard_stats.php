<?php
// Iniciar output buffering para capturar cualquier salida no deseada
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
    // Intentar incluir el archivo de conexión
    if (!file_exists('db_config.php')) {
        throw new Exception('Archivo de conexión no encontrado');
    }
    
    require_once('db_config.php');
    
    // Verificar que la conexión existe
    if (!isset($conexion)) {
        throw new Exception('Conexión a base de datos no establecida');
    }
 
    $response = [
        'success' => false,
        'message' => '',
        'stats' => []
    ];
 
    // Función helper para verificar si una tabla existe
    function tableExists($conexion, $tableName) {
        try {
            $result = $conexion->query("SELECT 1 FROM {$tableName} LIMIT 1");
            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
 
    // Obtener total de objetivos (si la tabla existe)
    $totalObjetivos = 0;
    $objetivosCompletados = 0;
    $porcentajeObjetivos = 0;
    
    if (tableExists($conexion, 'tbl_objetivos')) {
        try {
            $queryObjetivos = "SELECT COUNT(*) as total FROM tbl_objetivos";
            $stmtObjetivos = $conexion->prepare($queryObjetivos);
            $stmtObjetivos->execute();
            $resultObjetivos = $stmtObjetivos->fetch();
            $totalObjetivos = $resultObjetivos ? $resultObjetivos['total'] : 0;
 
            // Objetivos completados
            $queryObjetivosCompletados = "
                SELECT COUNT(*) as completados
                FROM tbl_objetivos
                WHERE estado = 'completado'
            ";
            $stmtObjetivosCompletados = $conexion->prepare($queryObjetivosCompletados);
            $stmtObjetivosCompletados->execute();
            $resultObjetivosCompletados = $stmtObjetivosCompletados->fetch(PDO::FETCH_ASSOC);
            $objetivosCompletados = $resultObjetivosCompletados ? $resultObjetivosCompletados['completados'] : 0;
            
            $porcentajeObjetivos = $totalObjetivos > 0 ? round(($objetivosCompletados / $totalObjetivos) * 100, 1) : 0;
        } catch (PDOException $e) {
            error_log('Error consultando objetivos: ' . $e->getMessage());
        }
    }
 
    // Obtener total de proyectos
    $totalProyectos = 0;
    $estadosCount = [
        'completado' => 0,
        'en proceso' => 0,
        'pendiente' => 0,
        'vencido' => 0
    ];
    
    if (tableExists($conexion, 'tbl_proyectos')) {
        try {
            $queryProyectos = "SELECT COUNT(*) as total FROM tbl_proyectos";
            $stmtProyectos = $conexion->prepare($queryProyectos);
            $stmtProyectos->execute();
            $resultProyectos = $stmtProyectos->fetch(PDO::FETCH_ASSOC);
            $totalProyectos = $resultProyectos ? $resultProyectos['total'] : 0;
 
            // Obtener proyectos por estado
            $queryEstados = "
                SELECT
                    estado,
                    COUNT(*) as cantidad
                FROM tbl_proyectos
                GROUP BY estado
            ";
            $stmtEstados = $conexion->prepare($queryEstados);
            $stmtEstados->execute();
            $estadosData = $stmtEstados->fetchAll(PDO::FETCH_ASSOC);
 
            // Llenar contadores con datos reales
            if ($estadosData) {
                foreach ($estadosData as $estado) {
                    $estadoNombre = strtolower(trim($estado['estado']));
                    if (isset($estadosCount[$estadoNombre])) {
                        $estadosCount[$estadoNombre] = (int)$estado['cantidad'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log('Error consultando proyectos: ' . $e->getMessage());
        }
    }
 
    // Obtener total de tareas (si la tabla existe)
    $totalTareas = 0;
    $tareasCompletadas = 0;
    $porcentajeTareas = 0;
    
    if (tableExists($conexion, 'tbl_tareas')) {
        try {
            $queryTareas = "SELECT COUNT(*) as total FROM tbl_tareas";
            $stmtTareas = $conexion->prepare($queryTareas);
            $stmtTareas->execute();
            $resultTareas = $stmtTareas->fetch(PDO::FETCH_ASSOC);
            $totalTareas = $resultTareas ? $resultTareas['total'] : 0;
 
            // Tareas completadas
            $queryTareasCompletadas = "
                SELECT COUNT(*) as completadas
                FROM tbl_tareas
                WHERE estado = 'completado'
            ";
            $stmtTareasCompletadas = $conexion->prepare($queryTareasCompletadas);
            $stmtTareasCompletadas->execute();
            $resultTareasCompletadas = $stmtTareasCompletadas->fetch(PDO::FETCH_ASSOC);
            $tareasCompletadas = $resultTareasCompletadas ? $resultTareasCompletadas['completadas'] : 0;
            
            $porcentajeTareas = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100, 1) : 0;
        } catch (PDOException $e) {
            error_log('Error consultando tareas: ' . $e->getMessage());
        }
    }
 
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
        'proyectos_vencidos' => (int)$estadosCount['vencido']
    ];
 
    // Limpiar buffer de salida antes de enviar JSON
    ob_clean();
    
    // Enviar respuesta JSON
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
 
} catch (PDOException $e) {
    // Limpiar buffer
    ob_clean();
    
    $response = [
        'success' => false,
        'message' => 'Error de base de datos',
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
            'proyectos_vencidos' => 0
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
    // Log del error
    error_log('Error en get_dashboard_stats.php: ' . $e->getMessage());
    
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
            'proyectos_vencidos' => 0
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
    // Log del error
    error_log('Error en get_dashboard_stats.php: ' . $e->getMessage());
}
 
// Finalizar y limpiar buffer
ob_end_flush();
?>