<?php
// update_users.php
header('Content-Type: application/json');
require_once('db_config.php');

$conn = getDBConnection();

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}

$conn->set_charset("utf8mb4");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['id_usuario']) || !isset($data['nombre']) || !isset($data['apellido']) || 
    !isset($data['usuario']) || !isset($data['e_mail'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$id_usuario = intval($data['id_usuario']);
$nombre = trim($data['nombre']);
$apellido = trim($data['apellido']);
$usuario = trim($data['usuario']);
$e_mail = trim($data['e_mail']);
$id_departamento = isset($data['id_departamento']) ? intval($data['id_departamento']) : null;

// Validate email format
if (!filter_var($e_mail, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Formato de email inválido']);
    exit;
}

// Validate empty fields
if (empty($nombre) || empty($apellido) || empty($usuario) || empty($e_mail)) {
    echo json_encode(['success' => false, 'error' => 'Los campos no pueden estar vacíos']);
    exit;
}

try {
    // Check if username is already taken by another user
    $check_stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE usuario = ? AND id_usuario != ?");
    $check_stmt->bind_param("si", $usuario, $id_usuario);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'El nombre de usuario ya está en uso']);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Check if email is already taken by another user
    $check_email = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE e_mail = ? AND id_usuario != ?");
    $check_email->bind_param("si", $e_mail, $id_usuario);
    $check_email->execute();
    $email_result = $check_email->get_result();
    
    if ($email_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'El email ya está registrado']);
        $check_email->close();
        exit;
    }
    $check_email->close();
    
    // Validate department if provided
    if ($id_departamento) {
        $check_dept = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE id_departamento = ?");
        $check_dept->bind_param("i", $id_departamento);
        $check_dept->execute();
        $dept_result = $check_dept->get_result();
        
        if ($dept_result->num_rows === 0) {
            echo json_encode(['success' => false, 'error' => 'Departamento no válido']);
            $check_dept->close();
            exit;
        }
        $check_dept->close();
    }
    
    // Update user with department
    if ($id_departamento) {
        $update_stmt = $conn->prepare("UPDATE tbl_usuarios SET nombre = ?, apellido = ?, usuario = ?, e_mail = ?, id_departamento = ? WHERE id_usuario = ?");
        
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $update_stmt->bind_param("ssssii", $nombre, $apellido, $usuario, $e_mail, $id_departamento, $id_usuario);
    } else {
        $update_stmt = $conn->prepare("UPDATE tbl_usuarios SET nombre = ?, apellido = ?, usuario = ?, e_mail = ? WHERE id_usuario = ?");
        
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $update_stmt->bind_param("ssssi", $nombre, $apellido, $usuario, $e_mail, $id_usuario);
    }
    
    if ($update_stmt->execute()) {
        if ($update_stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'usuario' => [
                    'id_usuario' => $id_usuario,
                    'nombre' => $nombre,
                    'apellido' => $apellido,
                    'usuario' => $usuario,
                    'e_mail' => $e_mail,
                    'id_departamento' => $id_departamento
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se realizaron cambios. Usuario no encontrado o datos idénticos']);
        }
    } else {
        throw new Exception("Execute failed: " . $conn->error);
    }
    
    $update_stmt->close();
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al actualizar usuario: ' . $e->getMessage()]);
}

$conn->close();
?>