<?php 
/*manager_api_get_pending_projects para proyectos pendientes filtrados por departamento*/ 
ob_start(); 
session_start(); 
header('Content-Type: application/json'); 
ob_end_clean(); 

 
set_error_handler(function($errno, $errstr, $errfile, $errline) { 
    error_log("PHP Error: $errstr in $errfile on line $errline"); 
}); 

require_once('db_config.php'); 

$conexion = getDBConnection(); 

if (!$conexion) { 
    http_response_code(500); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Database connection failed' 
    ]); 
    exit; 
} 

//cargar el id del usuario desde la sesion
$id_usuario = null; 

if (isset($_SESSION['user_id'])) { 
    $id_usuario = (int)$_SESSION['user_id']; 
} elseif (isset($_SESSION['id_usuario'])) { 
    $id_usuario = (int)$_SESSION['id_usuario']; 
} 

if (!$id_usuario) { 
    http_response_code(401); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Usuario no autenticado', 
        'debug_session' => array_keys($_SESSION) 
    ]); 
    exit; 
} 

//saber el departamento del uuario
$id_departamento = null; 
$departamento_nombre = null; 

try { 
    $dept_query = " 
        SELECT  
            u.id_departamento,  
            d.nombre as departamento_nombre 
        FROM tbl_usuarios u 
        LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
        WHERE u.id_usuario = ? 
    "; 

    $dept_stmt = $conexion->prepare($dept_query); 
     
    if (!$dept_stmt) { 
        throw new Exception('Error preparando consulta de departamento: ' . $conexion->error); 
    } 

    $dept_stmt->bind_param('i', $id_usuario); 

    if (!$dept_stmt->execute()) { 
        throw new Exception('Error ejecutando consulta de departamento: ' . $dept_stmt->error); 
    } 

    $dept_result = $dept_stmt->get_result(); 
    $user_data = $dept_result->fetch_assoc(); 

    if ($user_data && $user_data['id_departamento']) { 
        $id_departamento = (int)$user_data['id_departamento']; 
        $departamento_nombre = $user_data['departamento_nombre']; 
    } 
    $dept_stmt->close(); 

} catch (Exception $e) { 
    error_log('Error obteniendo departamento: ' . $e->getMessage()); 
} 

if (!$id_departamento) { 
    http_response_code(403); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Usuario no tiene departamento asignado', 
        'user_id' => $id_usuario 
    ]); 
    exit; 
} 

$proyectos = []; 

try { 
    $query = " 
        SELECT DISTINCT 
            p.id_proyecto, 
            p.nombre, 
            p.descripcion, 
            p.id_departamento, 
            p.fecha_inicio, 
            p.fecha_cumplimiento, 
            p.progreso, 
            p.estado, 
            p.id_creador, 
            p.id_tipo_proyecto, 
            d.nombre AS nombre_departamento, 
            tp.nombre AS tipo_proyecto, 
            u_creador.nombre AS creador_nombre, 
            u_creador.apellido AS creador_apellido 
        FROM tbl_proyectos p 
        INNER JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto 
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario 
        WHERE p.id_departamento = ? 
            AND p.estado = 'pendiente' 
        ORDER BY p.fecha_cumplimiento ASC, p.fecha_creacion DESC 
    "; 

    $stmt = $conexion->prepare($query); 

    if (!$stmt) { 
        throw new Exception('Error preparando consulta: ' . $conexion->error); 
    } 

    $stmt->bind_param('i', $id_departamento); 

    if (!$stmt->execute()) { 
        throw new Exception('Error ejecutando consulta: ' . $stmt->error); 
    } 

    $result = $stmt->get_result(); 

    //darle formato a los resultados 
    while ($proyecto = $result->fetch_assoc()) { 
        $estado_display = match($proyecto['estado']) { 
            'pendiente' => 'Pendiente', 
            'en proceso' => 'En Progreso', 
            'vencido' => 'Vencido', 
            'completado' => 'Completado', 
            default => $proyecto['estado'] 
        }; 

        //estado de la insignia 
        $estado_style = match($proyecto['estado']) { 
            'pendiente' => 'badge-danger', 
            'en proceso' => 'badge-warning', 
            'vencido' => 'badge-danger', 
            'completado' => 'badge-success', 
            default => 'badge-secondary' 
        }; 

        //color de la barra de progreso 
        $progreso_color = match($proyecto['estado']) { 
            'pendiente' => 'bg-danger', 
            'en proceso' => 'bg-warning', 
            'vencido' => 'bg-danger', 
            'completado' => 'bg-success', 
            default => 'bg-secondary' 
        }; 

        $proyectos[] = [ 
            'id_proyecto' => $proyecto['id_proyecto'], 
            'nombre' => htmlspecialchars($proyecto['nombre']), 
            'descripcion' => htmlspecialchars($proyecto['descripcion']), 
            'departamento' => htmlspecialchars($proyecto['nombre_departamento'] ?? 'N/A'), 
            'tipo_proyecto' => htmlspecialchars($proyecto['tipo_proyecto'] ?? 'N/A'), 
            'creador' => htmlspecialchars(($proyecto['creador_nombre'] ?? 'N/A') . ' ' . ($proyecto['creador_apellido'] ?? '')), 
            'fecha_cumplimiento' => $proyecto['fecha_cumplimiento'], 
            'progreso' => (int)$proyecto['progreso'], 
            'estado' => $proyecto['estado'], 
            'estado_display' => $estado_display, 
            'estado_style' => $estado_style, 
            'progreso_color' => $progreso_color 
        ]; 
    } 

    $stmt->close(); 

    echo json_encode([ 
        'success' => true, 
        'data' => $proyectos, 
        'total' => count($proyectos), 
        'department_id' => $id_departamento, 
        'department_name' => $departamento_nombre 
    ]); 

} catch (Exception $e) { 
    error_log('Error en manager_api_get_pending_projects.php: ' . $e->getMessage()); 
    http_response_code(500); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Error al obtener proyectos pendientes', 
        'error' => $e->getMessage() 
    ]); 
} 

$conexion->close(); 
restore_error_handler(); 
?> 