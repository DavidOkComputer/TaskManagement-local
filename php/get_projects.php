<?php
header('Content-Type: application/json');
require_once 'db_config.php';

$conn = getDBconnection();

if ($conn->connect_error) {
    die(json_encode(['error' => 'Connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

$sql = "SELECT 
    p.id_proyecto,
    p.nombre,
    p.descripcion,
    p.fecha_cumplimiento,
    p.progreso,
    p.estado,
    d.nombre_departamento,
    CONCAT(u.nombre, ' ', u.apellido) as nombre_participante
FROM tbl_proyectos p
LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
LEFT JOIN tbl_usuarios u ON p.id_participante = u.id_usuario
ORDER BY p.fecha_creacion DESC";

$result = $conn->query($sql);

$projects = array();

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $projects[] = array(
            'id_proyecto' => $row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'departamento' => $row['nombre_departamento'] ?? 'N/A',
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'progreso' => $row['progreso'],
            'estado' => $row['estado'],
            'participante' => $row['nombre_participante'] ?? 'Sin asignar'
        );
    }
}

$conn->close();

echo json_encode($projects);
?>