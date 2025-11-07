<?php
header('Content-Type: application/json');
require_once('db_config.php');
// conexion a la base de datos
$conn = getDBConnection();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}


$conn->set_charset("utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_proyecto'])) {
    echo json_encode(['success' => false, 'error' => 'ID de proyecto no proporcionado']);
    exit;
}

$id_proyecto = intval($data['id_proyecto']);

$stmt = $conn->prepare("DELETE FROM tbl_proyectos WHERE id_proyecto = ?");
$stmt->bind_param("i", $id_proyecto);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Proyecto eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Proyecto no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el proyecto: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>