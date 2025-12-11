<?php
header('Content-Type: application/json');
require_once('db_config.php');
$conn = getDBConnection();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}


$conn->set_charset("utf8mb4");

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_objetivo'])) {
    echo json_encode(['success' => false, 'error' => 'ID de objetivo no proporcionado']);
    exit;
}

$id_objetivo = intval($data['id_objetivo']);

$stmt = $conn->prepare("DELETE FROM tbl_objetivos WHERE id_objetivo = ?");
$stmt->bind_param("i", $id_objetivo);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Objetivo eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Objetivo no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el objetivo: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>