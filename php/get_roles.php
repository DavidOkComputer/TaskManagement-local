<?php
// get_roles.php

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
    $query = "SELECT id_rol, nombre 
              FROM tbl_roles 
              ORDER BY nombre ASC";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error en la consulta: " . $conn->error);
    }
    
    $roles = [];
    
    while ($row = $result->fetch_assoc()) {
        $roles[] = [
            'id_rol' => (int)$row['id_rol'],
            'nombre' => $row['nombre']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'roles' => $roles
    ]);
    
    $result->free();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar roles: ' . $e->getMessage(),
        'roles' => []
    ]);
}

$conn->close();
?>