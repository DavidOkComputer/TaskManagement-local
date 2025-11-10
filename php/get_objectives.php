<?php
// obtener_proyectos.php

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
                p.id_objetivo,
                p.nombre,
                p.descripcion,
                p.fecha_cumplimiento,
                p.progreso,
                p.estado,
                d.nombre as area
              FROM tbl_objetivos p
              LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
              ORDER BY p.fecha_cumplimiento ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        $proyectos[] = [
            'id_objetivo' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'objetivos' => $proyectos,
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