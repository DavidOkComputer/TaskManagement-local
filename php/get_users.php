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
    $query = "SELECT id_usuario, nombre, apellido, usuario, num_empleado, acceso, id_departamento, id_rol, id_superior FROM tbl_usuarios ORDER BY id_usuario ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $projects = [];
    
    while ($row = $result->fetch_assoc()) {
        $projects[] = [
            'id_usuario' => $row['id_usuario'],
            'nombre' => $row['nombre'], 
            'apellido' => $row['apellido'],
            'usuario'=> $row['usuario'],
            'num_empleado'=> $row['num_empleado'],
            'acceso'=> $row['acceso'],
            'id_departamento'=> $row['id_departamento'],
            'id_rol'=> $row['id_rol'],
            'id_superior'=> $row['id_superior']
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