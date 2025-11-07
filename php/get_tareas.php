<?php
// get_objectives.php 

header('Content-Type: application/json');

require_once 'db_config.php';

$conn=getDBConnection();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    //query para ver todos los objetivos
    $query = "SELECT id_tarea, nombre,  descripcion, id_proyecto, id_creador, fecha_creacion, fecha_cumplimiento, estado FROM tbl_tareas ORDER BY id_tarea ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $projects = [];
    
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id_tarea' => $row['id_tarea'],
            'nombre' => $row['nombre'], 
            'descripcion' => $row['descripcion'],
            'id_proyecto'=> $row['id_proyecto'],
            'id_creador'=> $row['id_creador'],
            'fecha_creacion'=> $row['fecha_creacion'],
            'fecha_cumplimiento'=> $row['fecha_cumplimiento'],
            'estado'=> $row['estado']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'tareas' => $projects
    ]);
    
    $result->free();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar objetivos: ' . $e->getMessage(),
        'projects' => []
    ]);
}

$conn->close();
?>