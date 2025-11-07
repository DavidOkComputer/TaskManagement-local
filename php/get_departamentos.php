<?php
header('Content-Type: application/json');
require_once('db_config.php');

$conn = getDBConnection();

$sql = "SELECT id_departamento, nombre, id_creador FROM tbl_departamentos ORDER BY nombre ASC";
$result = $conn->query($sql);

$departamentos = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $departamentos[] = [
            'id' => $row['id_departamento'],
            'nombre' => $row['nombre'],
            'id_creador'=> $row['id_creador']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $departamentos
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No se encontraron departamentos',
        'data' => []
    ]);
}

$conn->close();
?>