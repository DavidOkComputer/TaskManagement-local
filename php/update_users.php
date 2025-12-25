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
    $id_rol = isset($data['id_rol']) ? intval($data['id_rol']) : null;
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
    
    // Validar el rol si se ingresa
    if ($id_rol) {
        $check_rol = $conn->prepare("SELECT id_rol FROM tbl_roles WHERE id_rol = ?");
        $check_rol->bind_param("i", $id_rol);
        $check_rol->execute();
        $rol_result = $check_rol->get_result();
        if ($rol_result->num_rows === 0) {
            throw new Exception('Rol no válido');
        }
        $check_rol->close();
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
    
    // Iniciar transacción
    $conn->begin_transaction();
    
    try {
        // Actualizar datos básicos del usuario en tbl_usuarios
        $update_stmt = $conn->prepare("
            UPDATE tbl_usuarios 
            SET nombre = ?, apellido = ?, usuario = ?, e_mail = ?, foto_perfil = ? 
            WHERE id_usuario = ?
        ");
        if (!$update_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $update_stmt->bind_param("sssssi", $nombre, $apellido, $usuario, $e_mail, $newPhotoFilename, $id_usuario);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Error al actualizar usuario: " . $update_stmt->error);
        }
        $update_stmt->close();
        
        if ($id_departamento && $id_rol) {
            // Verificar si ya tiene un rol principal
            $check_principal = $conn->prepare("
                SELECT id_usuario_roles, id_departamento, id_rol 
                FROM tbl_usuario_roles 
                WHERE id_usuario = ? AND es_principal = 1 AND activo = 1
            ");
            $check_principal->bind_param("i", $id_usuario);
            $check_principal->execute();
            $principal_result = $check_principal->get_result();
            $current_principal = $principal_result->fetch_assoc();
            $check_principal->close();
            
            if ($current_principal) {
                // Ya tiene rol principal - actualizar si cambió
                if ($current_principal['id_departamento'] != $id_departamento || 
                    $current_principal['id_rol'] != $id_rol) {
                    
                    // Verificar si ya existe una asignación para el nuevo departamento
                    $check_existing = $conn->prepare("
                        SELECT id_usuario_roles 
                        FROM tbl_usuario_roles 
                        WHERE id_usuario = ? AND id_departamento = ?
                    ");
                    $check_existing->bind_param("ii", $id_usuario, $id_departamento);
                    $check_existing->execute();
                    $existing_result = $check_existing->get_result();
                    $existing_role = $existing_result->fetch_assoc();
                    $check_existing->close();
                    
                    if ($existing_role) {
                        // Actualizar la asignación existente y hacerla principal
                        // Primero quitar el flag principal de todas
                        $conn->query("UPDATE tbl_usuario_roles SET es_principal = 0 WHERE id_usuario = $id_usuario");
                        
                        // Actualizar la existente
                        $update_role = $conn->prepare("
                            UPDATE tbl_usuario_roles 
                            SET id_rol = ?, es_principal = 1, activo = 1 
                            WHERE id_usuario_roles = ?
                        ");
                        $update_role->bind_param("ii", $id_rol, $existing_role['id_usuario_roles']);
                        $update_role->execute();
                        $update_role->close();
                    } else {
                        // Quitar el flag principal de la anterior
                        $conn->query("UPDATE tbl_usuario_roles SET es_principal = 0 WHERE id_usuario = $id_usuario");
                        
                        // Crear nueva asignación como principal
                        $insert_role = $conn->prepare("
                            INSERT INTO tbl_usuario_roles 
                            (id_usuario, id_departamento, id_rol, es_principal, activo) 
                            VALUES (?, ?, ?, 1, 1)
                        ");
                        $insert_role->bind_param("iii", $id_usuario, $id_departamento, $id_rol);
                        $insert_role->execute();
                        $insert_role->close();
                    }
                }
            } else {
                // No tiene rol principal - crear uno nuevo
                $insert_role = $conn->prepare("
                    INSERT INTO tbl_usuario_roles 
                    (id_usuario, id_departamento, id_rol, es_principal, activo) 
                    VALUES (?, ?, ?, 1, 1)
                ");
                $insert_role->bind_param("iii", $id_usuario, $id_departamento, $id_rol);
                $insert_role->execute();
                $insert_role->close();
            }
            
            // COMPATIBILIDAD: También actualizar campos legacy en tbl_usuarios
            $update_legacy = $conn->prepare("
                UPDATE tbl_usuarios 
                SET id_departamento = ?, id_rol = ? 
                WHERE id_usuario = ?
            ");
            $update_legacy->bind_param("iii", $id_departamento, $id_rol, $id_usuario);
            $update_legacy->execute();
            $update_legacy->close();
        }
        
        // Commit de la transacción
        $conn->commit();
        
        // Obtener información actualizada del usuario para la respuesta
        $get_updated = $conn->prepare("
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.usuario,
                u.e_mail,
                u.foto_perfil,
                ur.id_departamento,
                ur.id_rol,
                d.nombre as nombre_departamento,
                r.nombre as nombre_rol
            FROM tbl_usuarios u
            LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario 
                AND ur.es_principal = 1 AND ur.activo = 1
            LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
            LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol
            WHERE u.id_usuario = ?
        ");
        $get_updated->bind_param("i", $id_usuario);
        $get_updated->execute();
        $updated_user = $get_updated->get_result()->fetch_assoc();
        $get_updated->close();
        
        $response['success'] = true;
        $response['message'] = 'Usuario actualizado exitosamente';
        $response['usuario'] = [
            'id_usuario' => $id_usuario,
            'nombre' => $nombre,
            'apellido' => $apellido,
            'usuario' => $usuario,
            'e_mail' => $e_mail,
            'id_departamento' => $updated_user['id_departamento'] ? (int)$updated_user['id_departamento'] : null,
            'nombre_departamento' => $updated_user['nombre_departamento'],
            'id_rol' => $updated_user['id_rol'] ? (int)$updated_user['id_rol'] : null,
            'nombre_rol' => $updated_user['nombre_rol'],
            'foto_perfil' => $newPhotoFilename,
            'foto_url' => $newPhotoFilename ? 'uploads/profile_pictures/' . $newPhotoFilename : null,
            'foto_thumbnail' => $newPhotoFilename ? 'uploads/profile_pictures/thumbnails/thumb_' . $newPhotoFilename : null
        ];
        
    } catch (Exception $e) {
        // Rollback en caso de error
        $conn->rollback();
        throw $e;
    }
    
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