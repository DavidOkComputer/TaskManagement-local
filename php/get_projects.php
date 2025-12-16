<?php
// get_projects.php - Obtiene todos los proyectos con conteo de tareas

header('Content-Type: application/json');
require_once 'db_config.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    $query = "SELECT 
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_cumplimiento,
                p.progreso,
                p.estado,
                p.id_tipo_proyecto,
                p.id_creador,
                d.nombre as area,
                u.nombre as participante_nombre,
                u.apellido as participante_apellido,
                p.id_participante,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as total_tareas
              FROM tbl_proyectos p
              LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
              LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
              ORDER BY p.fecha_cumplimiento ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        // Determinar el texto del participante según el tipo de proyecto
        if ((int)$row['id_tipo_proyecto'] === 1) {
            $participante_text = 'Grupo';
        } elseif ($row['participante_nombre']) {
            $participante_text = $row['participante_nombre'] . ' ' . $row['participante_apellido'];
        } else {
            $participante_text = 'Sin asignar';
        }
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => $participante_text,
            'id_participante' => (int)$row['id_participante'],
            'id_tipo_proyecto' => (int)$row['id_tipo_proyecto'],
            'id_creador' => (int)$row['id_creador'],
            'total_tareas' => (int)$row['total_tareas']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'proyectos' => $proyectos,
        'total' => count($proyectos)
    ]);
    
    $result->free();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar proyectos: ' . $e->getMessage(),
        'proyectos' => []
    ]);
}

$conn->close();
?>