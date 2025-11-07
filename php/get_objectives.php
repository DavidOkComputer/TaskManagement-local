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
    $query = "SELECT id_objetivo, nombre,  descripcion, id_departamento, fecha_creacion, fecha_cumplimiento, progreso, estado, ar, archivo_adjunto, id_creador FROM tbl_objetivos ORDER BY id_objetivo ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $projects = [];
    
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id_objetivo' => $row['id_objetivo'],
            'nombre' => $row['nombre'], 
            'descripcion' => $row['descripcion'],
            'id_departamento'=> $row['id_departamento'],
            'fecha_creacion'=> $row['fecha_creacion'],
            'fecha_cumplimiento'=> $row['fecha_cumplimiento'],
            'progreso'=> $row['progreso'],
            'estado'=> $row['estado'],
            'ar'=> $row['ar'],
            'archivo_adjunto'=> $row['archivo_adjunto'],
            'id_creador'=> $row['id_creador']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'projects' => $projects
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