<?php
/*user_api_get_projects.php para saber proyectos - Usuario*/

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

//saber el ID del usuario de la sesión
$id_usuario = $_SESSION['user_id'] ?? null;

if (!$id_usuario) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
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
            p.id_participante,
            p.id_tipo_proyecto,
            d.nombre AS nombre_departamento,
            tp.nombre AS tipo_proyecto,
            u_creador.nombre AS creador_nombre,
            u_creador.apellido AS creador_apellido
        FROM tbl_proyectos p
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE p.id_creador = ? 
           OR p.id_participante = ? 
           OR pu.id_usuario = ?
        ORDER BY p.fecha_creacion DESC
    ";
    
    $stmt = $conexion->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conexion->error);
    }
    
    $stmt->bind_param('iii', $id_usuario, $id_usuario, $id_usuario);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    while ($proyecto = $result->fetch_assoc()) {
        $estado_display = match($proyecto['estado']) {
            'pendiente' => 'Pendiente',
            'en proceso' => 'En Progreso',
            'vencido' => 'Vencido',
            'completado' => 'Completado',
            default => $proyecto['estado']
        };
        
        $estado_style = match($proyecto['estado']) {
            'pendiente' => 'badge-danger',
            'en proceso' => 'badge-warning',
            'vencido' => 'badge-danger',
            'completado' => 'badge-success',
            default => 'badge-secondary'
        };
        
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
        'total' => count($proyectos)
    ]);
    
} catch (Exception $e) {
    error_log('Error en api_get_projects.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener proyectos',
        'error' => $e->getMessage()
    ]);
}

restore_error_handler();
?>