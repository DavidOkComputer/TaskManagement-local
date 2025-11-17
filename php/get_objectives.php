<?php
// get_objectives.php

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
                p.estado,
                p.archivo_adjunto,
                d.nombre as area
              FROM tbl_objetivos p
              LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
              ORDER BY p.fecha_cumplimiento ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $objetivos = [];
    
    while ($row = $result->fetch_assoc()) {
        $objetivos[] = [
            'id_objetivo' => (int)$row['id_objetivo'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'archivo_adjunto' => $row['archivo_adjunto'] ?? null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'objetivos' => $objetivos,
        'total' => count($objetivos)
    ]);
    
    $result->free();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar objetivos: ' . $e->getMessage(),
        'objetivos' => []
    ]);
}

$conn->close();
?>