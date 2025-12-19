<?php
// update_users.php para actualizar usuarios
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

require_once('db_config.php');
require_once('Profile_picture_handler.php');

$response = [
    'success' => false,
    'message' => ''
];

try {
    $conn = getDBConnection();
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Determinar si es una solicitud multipart (con archivo) o JSON
    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($contentType, 'multipart/form-data') !== false) {
        // Solicitud con archivo - usar $_POST y $_FILES
        $data = $_POST;
        $hasFile = isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE;
    } else {
        // Solicitud JSON
        $data = json_decode(file_get_contents('php://input'), true);
        $hasFile = false;
    }
    
    // Validar campos requeridos
    if (!isset($data['id_usuario']) || !isset($data['nombre']) || 
        !isset($data['apellido']) || !isset($data['usuario']) || !isset($data['e_mail'])) {
        throw new Exception('Datos incompletos');
    }
    
    $id_usuario = intval($data['id_usuario']);
    $nombre = trim($data['nombre']);
    $apellido = trim($data['apellido']);
    $usuario = trim($data['usuario']);
    $e_mail = trim($data['e_mail']);
    $id_departamento = isset($data['id_departamento']) ? intval($data['id_departamento']) : null;
    $remove_photo = isset($data['remove_photo']) && $data['remove_photo'] === 'true';
    
    // Validar formato email
    if (!filter_var($e_mail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Formato de email inválido');
    }
    
    // Validar campos vacíos
    if (empty($nombre) || empty($apellido) || empty($usuario) || empty($e_mail)) {
        throw new Exception('Los campos no pueden estar vacíos');
    }
    
    // Validar longitud de campos
    if (strlen($nombre) < 2 || strlen($nombre) > 100) {
        throw new Exception('El nombre debe tener entre 2 y 100 caracteres');
    }
    if (strlen($apellido) < 2 || strlen($apellido) > 100) {
        throw new Exception('El apellido debe tener entre 2 y 100 caracteres');
    }
    if (strlen($usuario) < 4 || strlen($usuario) > 100) {
        throw new Exception('El usuario debe tener entre 4 y 100 caracteres');
    }
    
    // Validar formato de usuario
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) {
        throw new Exception('El usuario solo puede contener letras, números, punto, guión y guión bajo');
    }
    
    // Validar imagen si se envió
    if ($hasFile) {
        $imageValidation = ProfilePictureHandler::validateImageFile($_FILES['foto_perfil']);
        if (!$imageValidation['valid']) {
            throw new Exception(implode('. ', $imageValidation['errors']));
        }
    }
    
    // Revisar si el nombre de usuario ya está tomado por otro usuario
    $check_stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE usuario = ? AND id_usuario != ?");
    $check_stmt->bind_param("si", $usuario, $id_usuario);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        throw new Exception('El nombre de usuario ya está en uso');
    }
    $check_stmt->close();
    
    // Revisar si el email ya está registrado por otro usuario
    $check_email = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE e_mail = ? AND id_usuario != ?");
    $check_email->bind_param("si", $e_mail, $id_usuario);
    $check_email->execute();
    $email_result = $check_email->get_result();
    if ($email_result->num_rows > 0) {
        throw new Exception('El email ya está registrado');
    }
    $check_email->close();
    
    // Validar el departamento si se ingresa
    if ($id_departamento) {
        $check_dept = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE id_departamento = ?");
        $check_dept->bind_param("i", $id_departamento);
        $check_dept->execute();
        $dept_result = $check_dept->get_result();
        if ($dept_result->num_rows === 0) {
            throw new Exception('Departamento no válido');
        }
        $check_dept->close();
    }
    
    // Obtener la foto actual del usuario
    $getCurrentPhoto = $conn->prepare("SELECT foto_perfil FROM tbl_usuarios WHERE id_usuario = ?");
    $getCurrentPhoto->bind_param("i", $id_usuario);
    $getCurrentPhoto->execute();
    $currentPhotoResult = $getCurrentPhoto->get_result();
    $currentPhoto = null;
    if ($row = $currentPhotoResult->fetch_assoc()) {
        $currentPhoto = $row['foto_perfil'];
    }
    $getCurrentPhoto->close();
    
    $newPhotoFilename = $currentPhoto; // Mantener la foto actual por defecto
    
    // Procesar eliminación de foto
    if ($remove_photo && $currentPhoto) {
        ProfilePictureHandler::deleteProfilePicture($currentPhoto);
        $newPhotoFilename = null;
    }
    
    // Procesar nueva foto
    if ($hasFile) {
        // Eliminar foto anterior si existe
        if ($currentPhoto) {
            ProfilePictureHandler::deleteProfilePicture($currentPhoto);
        }
        
        // Subir nueva foto
        $uploadResult = ProfilePictureHandler::uploadProfilePicture($_FILES['foto_perfil'], $id_usuario);
        
        if ($uploadResult['success']) {
            $newPhotoFilename = $uploadResult['filename'];
        } else {
            throw new Exception('Error al subir la imagen: ' . $uploadResult['message']);
        }
    }
    
    // Construir query de actualización
    if ($id_departamento) {
        $update_stmt = $conn->prepare("
            UPDATE tbl_usuarios 
            SET nombre = ?, apellido = ?, usuario = ?, e_mail = ?, id_departamento = ?, foto_perfil = ? 
            WHERE id_usuario = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $update_stmt->bind_param("ssssisi", $nombre, $apellido, $usuario, $e_mail, $id_departamento, $newPhotoFilename, $id_usuario);
    } else {
        $update_stmt = $conn->prepare("
            UPDATE tbl_usuarios 
            SET nombre = ?, apellido = ?, usuario = ?, e_mail = ?, foto_perfil = ? 
            WHERE id_usuario = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $update_stmt->bind_param("sssssi", $nombre, $apellido, $usuario, $e_mail, $newPhotoFilename, $id_usuario);
    }
    
    if ($update_stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Usuario actualizado exitosamente';
        $response['usuario'] = [
            'id_usuario' => $id_usuario,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'usuario' => $usuario,
            'e_mail' => $e_mail,
            'id_departamento' => $id_departamento,
            'foto_perfil' => $newPhotoFilename,
            'foto_url' => $newPhotoFilename ? 'uploads/profile_pictures/' . $newPhotoFilename : null,
            'foto_thumbnail' => $newPhotoFilename ? 'uploads/profile_pictures/thumbnails/thumb_' . $newPhotoFilename : null
        ];
    } else {
        throw new Exception("Error al actualizar: " . $update_stmt->error);
    }
    
    $update_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Error en update_users.php: " . $e->getMessage());
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>