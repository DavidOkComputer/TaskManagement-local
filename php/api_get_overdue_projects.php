<?php 

/* api_get_overdue_projects.php para obtener TODOS los proyectos vencidos*/ 
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

$proyectos = []; 

try { 
    // Obtener TODOS los proyectos vencidos 
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
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto 
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario 
        WHERE p.estado = 'vencido' 
        ORDER BY p.fecha_cumplimiento ASC, p.fecha_creacion DESC 
    "; 
     
    $stmt = $conexion->prepare($query); 
    
    if (!$stmt) { 
        throw new Exception('Error preparando consulta: ' . $conexion->error); 
    } 
     
    if (!$stmt->execute()) { 
        throw new Exception('Error ejecutando consulta: ' . $stmt->error); 
    } 
    
    $result = $stmt->get_result(); 
     
    while ($proyecto = $result->fetch_assoc()) {//darle formato a los resultados 
        $estado_display = match($proyecto['estado']) { 
            'pendiente' => 'Pendiente', 
            'en proceso' => 'En Progreso', 
            'vencido' => 'Vencido', 
            'completado' => 'Completado', 
            default => $proyecto['estado'] 
        }; 
        
        //estilo de insignias 

        $estado_style = match($proyecto['estado']) { 
            'pendiente' => 'badge-danger', 
            'en proceso' => 'badge-warning', 
            'vencido' => 'badge-danger', 
            'completado' => 'badge-success', 
            default => 'badge-secondary' 
        }; 
         
        $progreso_color = match($proyecto['estado']) {//colores de la barra de progreso 
            'pendiente' => 'bg-danger', 
            'en proceso' => 'bg-warning', 
            'vencido' => 'bg-danger', 
            'completado' => 'bg-success', 
            default => 'bg-secondary' 
        }; 
        
        $fecha_vencimiento = new DateTime($proyecto['fecha_cumplimiento']);//calcular los dias de vencido 
        $fecha_hoy = new DateTime(); 
        $dias_vencidos = $fecha_hoy->diff($fecha_vencimiento)->days; 
       
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
            'progreso_color' => $progreso_color, 
            'dias_vencidos' => $dias_vencidos 
        ]; 
    } 
    
    $stmt->close(); 
    
    echo json_encode([ 
        'success' => true, 
        'data' => $proyectos, 
        'total' => count($proyectos) 
    ]); 
    
} catch (Exception $e) { 
    error_log('Error en api_get_proyectos_vencidos.php: ' . $e->getMessage()); 
    http_response_code(500); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Error al obtener proyectos vencidos', 
        'error' => $e->getMessage() 
    ]); 
} 

restore_error_handler(); 
?> 