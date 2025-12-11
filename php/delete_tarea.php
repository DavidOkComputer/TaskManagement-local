<?php
header('Content-Type: application/json');
require_once('db_config.php');
$conn = getDBConnection();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}


$conn->set_charset("utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_tarea'])) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea no proporcionado']);
    exit;
}

$id_tarea = intval($data['id_tarea']);

$stmt = $conn->prepare("DELETE FROM tbl_tareas WHERE id_tarea = ?");
$stmt->bind_param("i", $id_tarea);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Tarea eliminada exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Tarea no encontrada']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar la tarea: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>