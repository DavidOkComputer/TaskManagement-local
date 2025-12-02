<?php
/**
 * manager_get_project.php sin s
 * Gets all projects for manager's department
 * Used by bar chart for project progress
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$response = [
    'success' => false,
    'proyectos' => [],
    'message' => ''
];

try {
    // Validate department ID
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Get all projects for the department
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre,
            p.descripcion,
            p.fecha_cumplimiento,
            p.progreso,
            p.estado,
            p.id_tipo_proyecto,
            p.id_participante,
            u.nombre AS participante_nombre,
            u.apellido AS participante_apellido
        FROM tbl_proyectos p
        LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
        WHERE p.id_departamento = ?
        ORDER BY 
            CASE p.estado
                WHEN 'vencido' THEN 1
                WHEN 'en proceso' THEN 2
                WHEN 'pendiente' THEN 3
                WHEN 'completado' THEN 4
                ELSE 5
            END,
            p.progreso DESC
    ";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $id_departamento);
    
    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        // Determine participant text
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante = 'Grupo';
        } elseif ($row['participante_nombre']) {
            $participante = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            $participante = 'Sin asignar';
        }
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => $participante,
            'id_tipo_proyecto' => (int)$row['id_tipo_proyecto']
        ];
    }
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['total'] = count($proyectos);
    $response['id_departamento'] = $id_departamento;
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_projects.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>