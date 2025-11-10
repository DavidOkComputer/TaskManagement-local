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
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_cumplimiento,
                p.progreso,
                p.estado,
                d.nombre as area,
                u.nombre as participante_nombre,
                u.apellido as participante_apellido,
                p.id_participante
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
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'area' => $row['area'] ?? 'Sin asignar',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => (int)$row['progreso'],
            'estado' => $row['estado'],
            'participante' => ($row['participante_nombre'] ? $row['participante_nombre'] . ' ' . $row['participante_apellido'] : 'Sin asignar'),
            'id_participante' => (int)$row['id_participante']
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