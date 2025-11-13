<?php
/**
 * API Endpoint: Get Projects
 * 
 * Purpose: Fetches all projects related to the authenticated user
 * A user can see projects where they are:
 * 1. The creator (id_creador)
 * 2. An assigned participant (through tbl_proyecto_usuarios)
 * 
 * Security: 
 * - Requires active session
 * - Uses prepared statements to prevent SQL injection
 * - Returns only authorized data
 * 
 * Returns: JSON response with projects array or error message
 */

// Start session and require authentication check
session_start();
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['id_usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Usuario no autenticado'
    ]);
    exit;
}

// Include database connection
require_once('php/conexion.php');

$id_usuario = $_SESSION['id_usuario'];
$proyectos = [];
$error = null;

try {
    // Query to get all projects where user is creator or participant
    // Includes project details with status and progress information
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
        LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
        WHERE p.id_creador = ? OR pu.id_usuario = ?
        ORDER BY p.fecha_creacion DESC
    ";
    
    // Prepare statement with proper error handling
    $stmt = $conexion->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conexion->error);
    }
    
    // Bind parameters (i = integer type)
    $stmt->bind_param('ii', $id_usuario, $id_usuario);
    
    // Execute query
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    // Get results
    $result = $stmt->get_result();
    
    // Format results into array
    while ($proyecto = $result->fetch_assoc()) {
        // Map Spanish status to readable format
        $estado_display = match($proyecto['estado']) {
            'pendiente' => 'Pendiente',
            'en proceso' => 'En Progreso',
            'vencido' => 'Vencido',
            'completado' => 'Completado',
            default => $proyecto['estado']
        };
        
        // Map status to badge color/style
        $estado_style = match($proyecto['estado']) {
            'pendiente' => 'badge-danger',
            'en proceso' => 'badge-warning',
            'vencido' => 'badge-danger',
            'completado' => 'badge-success',
            default => 'badge-secondary'
        };
        
        // Map status to progress bar color
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
    
    // Return successful response
    echo json_encode([
        'success' => true,
        'data' => $proyectos,
        'total' => count($proyectos)
    ]);
    
} catch (Exception $e) {
    // Handle errors
    error_log('Error en api_get_proyectos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener proyectos',
        'error' => $e->getMessage()
    ]);
}
?>