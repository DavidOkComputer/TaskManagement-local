<?php
// get_departments.php

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
                d.id_departamento,
                d.nombre, 
                d.descripcion, 
                d.id_creador,
                u.nombre as creador_nombre
              FROM tbl_departamentos d 
              LEFT JOIN tbl_usuarios u ON d.id_creador = u.id_usuario
              ORDER BY nombre ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $departamentos = [];
    
    while ($row = $result->fetch_assoc()) {
        $departamentos[] = [
            'id_departamento' => (int)$row['id_departamento'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'id_creador' => $row['id_creador']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'departamentos' => $departamentos
    ]);
    
    $result->free();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar departamentos: ' . $e->getMessage(),
        'departamentos' => []
    ]);
}

$conn->close();
?>