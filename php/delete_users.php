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

if (!isset($data['id_usuario'])) {
    echo json_encode(['success' => false, 'error' => 'ID de usuario no proporcionado']);
    exit;
}

$id_usuario = intval($data['id_usuario']);

$stmt = $conn->prepare("DELETE FROM tbl_usuarios WHERE id_usuario = ?");
$stmt->bind_param("i", $id_usuario);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al eliminar el usuario: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>