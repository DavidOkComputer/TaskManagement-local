<?php
// get_departments.php psra obtener los departamentos

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
                td.id_departamento, 
                td.nombre, 
                td.descripcion, 
                td.id_creador,
                CONCAT(tu.nombre, ' ', tu.apellido) as nombre_creador
              FROM tbl_departamentos td
              LEFT JOIN tbl_usuarios tu ON td.id_creador = tu.id_usuario
              ORDER BY td.nombre ASC";
    
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
            'id_creador' => (int)$row['id_creador'],
            'nombre_creador' => $row['nombre_creador'] ?? 'N/A'
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