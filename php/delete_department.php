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

if (!isset($data['id_departamento'])) {
    echo json_encode(['success' => false, 'error' => 'ID de departamento no proporcionado']);
    exit;
}

$id_departamento = intval($data['id_departamento']);

$stmt = $conn->prepare("DELETE FROM tbl_departamentos WHERE id_departamento = ?");
$stmt->bind_param("i", $id_departamento);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Departamento eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Departamento no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el departamento: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>