<?php 
// create_user.php para crear usuarios
 
header('Content-Type: application/json'); 
error_reporting(E_ALL); 
ini_set('display_errors', 0); 

// Iniciar buffer de output para hacer catch a output inesperado 
ob_start(); 

$response = [ 
    'success' => false, 
    'message' => '' 
]; 

try { 
    if (!file_exists('db_config.php')) { 
        throw new Exception('db_config.php no encontrado en ' . __DIR__); 
    } 
    require_once('db_config.php'); 
    require_once('profile_picture_handler.php'); 

    if (!function_exists('getDBConnection')) { 
        throw new Exception('Función getDBConnection no encontrada en db_config.php'); 
    } 

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
        throw new Exception('Método de solicitud no válido'); 
    } 

    // Validar y limpiar input 
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : ''; 
    $apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : ''; 
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : ''; 
    $acceso = isset($_POST['acceso']) ? $_POST['acceso'] : ''; 
    $num_empleado = isset($_POST['num_empleado']) ? intval($_POST['num_empleado']) : 0; 
    $id_departamento = isset($_POST['id_departamento']) ? intval($_POST['id_departamento']) : 0; 
    $id_rol = isset($_POST['id_rol']) ? intval($_POST['id_rol']) : 0; 
    $id_superior = isset($_POST['id_superior']) ? intval($_POST['id_superior']) : 0; 
    $e_mail = isset($_POST['e_mail']) ? trim($_POST['e_mail']) : ''; 

    // Validaciones 

    if (empty($nombre)) { 
        throw new Exception('El nombre es requerido'); 
    } 

    if (strlen($nombre) < 2 || strlen($nombre) > 100) { 
        throw new Exception('El nombre debe tener entre 2 y 100 caracteres'); 
    } 

    if (empty($apellido)) { 
        throw new Exception('El apellido es requerido'); 
    } 

    if (strlen($apellido) < 2 || strlen($apellido) > 100) { 
        throw new Exception('El apellido debe tener entre 2 y 100 caracteres'); 
    } 

    if (empty($usuario)) { 
        throw new Exception('El nombre de usuario es requerido'); 
    } 

    if (strlen($usuario) < 4 || strlen($usuario) > 100) { 
        throw new Exception('El usuario debe tener entre 4 y 100 caracteres'); 
    } 

    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $usuario)) { 
        throw new Exception('El usuario solo puede contener letras, números, punto, guión y guión bajo'); 
    } 

    if (empty($acceso)) { 
        throw new Exception('La contraseña es requerida'); 
    } 

    if (strlen($acceso) < 6) { 
        throw new Exception('La contraseña debe tener al menos 6 caracteres'); 
    } 

    if ($num_empleado <= 0) { 
        throw new Exception('Número de empleado no válido'); 
    } 

    if ($id_departamento <= 0) { 
        throw new Exception('Debe seleccionar un departamento'); 
    } 

    if ($id_rol <= 0) { 
        throw new Exception('Debe seleccionar un rol'); 
    } 

    if (!empty($e_mail) && !filter_var($e_mail, FILTER_VALIDATE_EMAIL)) { 
        throw new Exception('El formato del correo electrónico no es válido'); 
    } 

    // Validar foto de perfil si se envió 
    $foto_perfil = null; 
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) { 
        $imageValidation = ProfilePictureHandler::validateImageFile($_FILES['foto_perfil']); 
        if (!$imageValidation['valid']) { 
            throw new Exception(implode('. ', $imageValidation['errors'])); 
        } 
    } 
    
    $conn = getDBConnection(); 

    if (!$conn) { 
        throw new Exception('No se pudo obtener la conexión a la base de datos'); 
    } 

    if ($conn->connect_error) { 
        throw new Exception('Error de conexión a la base de datos: ' . $conn->connect_error); 
    } 

    $conn->set_charset('utf8mb4'); 

    // Iniciar transacción para asegurar integridad de datos 
    $conn->begin_transaction(); 

    try { 
        // Revisar si ya existe el usuario 
        $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE usuario = ?"); 
        if (!$stmt) { 
            throw new Exception('Error en prepare (usuario): ' . $conn->error); 
        } 

        $stmt->bind_param("s", $usuario); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 

        if ($result->num_rows > 0) { 
            throw new Exception('El nombre de usuario ya está en uso'); 
        } 

        $stmt->close(); 
        // Revisar si ya existe el número de empleado 
        $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE num_empleado = ?"); 
        if (!$stmt) { 
            throw new Exception('Error en prepare (num_empleado): ' . $conn->error); 
        } 

        $stmt->bind_param("i", $num_empleado); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 

        if ($result->num_rows > 0) { 
            throw new Exception('El número de empleado ya está registrado'); 
        } 

        $stmt->close(); 

        // Verificar que exista el departamento 
        $stmt = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE id_departamento = ?"); 
        if (!$stmt) { 
            throw new Exception('Error en prepare (departamento): ' . $conn->error); 
        } 

        $stmt->bind_param("i", $id_departamento); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 

        if ($result->num_rows === 0) { 
            throw new Exception('El departamento seleccionado no existe'); 
        } 

        $stmt->close(); 

        // Verificar que existe el rol 
        $stmt = $conn->prepare("SELECT id_rol FROM tbl_roles WHERE id_rol = ?"); 
        if (!$stmt) { 
            throw new Exception('Error en prepare (rol): ' . $conn->error); 
        } 

        $stmt->bind_param("i", $id_rol); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 
        if ($result->num_rows === 0) { 
            throw new Exception('El rol seleccionado no existe'); 
        } 

        $stmt->close(); 
 
        // Verificar que existe el superior si se selecciona 

        if ($id_superior > 0) { 
            $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE id_usuario = ?"); 
            if (!$stmt) { 
                throw new Exception('Error en prepare (superior): ' . $conn->error); 
            } 
            $stmt->bind_param("i", $id_superior); 
            $stmt->execute(); 
            $result = $stmt->get_result(); 

            if ($result->num_rows === 0) { 
                throw new Exception('El superior seleccionado no existe'); 
            } 
            $stmt->close(); 
        } 

        $acceso_hash = password_hash($acceso, PASSWORD_DEFAULT, ['cost' => 12]); 

        if ($acceso_hash === false) { 
            throw new Exception('Error al procesar la contraseña'); 
        } 

        // Insertar usuario en tbl_usuarios 
        // Mantenemos id_departamento e id_rol para compatibilidad con código existente 
        $stmt = $conn->prepare(" 
            INSERT INTO tbl_usuarios (nombre, apellido, usuario, num_empleado, acceso, id_departamento, id_rol, id_superior, e_mail, foto_perfil)  
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
        "); 

        if (!$stmt) { 
            throw new Exception('Error en prepare (INSERT usuario): ' . $conn->error); 
        } 

        $stmt->bind_param( 
            "sssisiiiss", 
            $nombre, 
            $apellido, 
            $usuario, 
            $num_empleado, 
            $acceso_hash, 
            $id_departamento, 
            $id_rol, 
            $id_superior, 
            $e_mail, 
            $foto_perfil 
        ); 
 
        if (!$stmt->execute()) { 
            throw new Exception('Error al crear el usuario: ' . $stmt->error); 
        } 

        $nuevo_id = $stmt->insert_id; 
        $stmt->close(); 

        //Insertar en tbl_usuario_roles  
        $es_principal = 1; // Primera asignación es siempre principal 
        $activo = 1; 

        $stmt = $conn->prepare(" 
            INSERT INTO tbl_usuario_roles (id_usuario, id_departamento, id_rol, es_principal, activo)  
            VALUES (?, ?, ?, ?, ?) 
        "); 

        if (!$stmt) { 
            throw new Exception('Error en prepare (INSERT usuario_roles): ' . $conn->error); 
        } 

        $stmt->bind_param("iiiii", $nuevo_id, $id_departamento, $id_rol, $es_principal, $activo); 

        if (!$stmt->execute()) { 
            throw new Exception('Error al asignar rol al usuario: ' . $stmt->error); 
        } 

        $stmt->close(); 

        // Insertar preferencias de notificación por defecto 
        $stmt = $conn->prepare(" 
            INSERT INTO tbl_notificacion_preferencias (id_usuario)  
            VALUES (?) 
            ON DUPLICATE KEY UPDATE id_usuario = id_usuario 
        "); 

        if ($stmt) { 
            $stmt->bind_param("i", $nuevo_id); 
            $stmt->execute(); 
            $stmt->close(); 
        } 

        // Commit de la transacción 
        $conn->commit(); 

        // Procesar foto de perfil si se envió 
        if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) { 
            $uploadResult = ProfilePictureHandler::uploadProfilePicture($_FILES['foto_perfil'], $nuevo_id); 
 
            if ($uploadResult['success']) { 
                // Actualizar el registro con el nombre del archivo 
                $updateStmt = $conn->prepare("UPDATE tbl_usuarios SET foto_perfil = ? WHERE id_usuario = ?"); 
                if ($updateStmt) { 
                    $updateStmt->bind_param("si", $uploadResult['filename'], $nuevo_id); 
                    $updateStmt->execute(); 
                    $updateStmt->close(); 
                    $foto_perfil = $uploadResult['filename']; 
                } 

            } else { 
                // Log del error pero no fallar la creación del usuario 
                error_log("Error al subir foto de perfil para usuario {$nuevo_id}: " . $uploadResult['message']); 
            } 
        } 

        $response['success'] = true; 
        $response['message'] = "Usuario '{$usuario}' creado exitosamente"; 
        $response['id_usuario'] = $nuevo_id; 
        $response['usuario'] = $usuario; 
        $response['foto_perfil'] = $foto_perfil; 
        $response['rol_asignado'] = [ 
            'id_departamento' => $id_departamento, 
            'id_rol' => $id_rol, 
            'es_principal' => true 
        ]; 

        if (isset($uploadResult) && !$uploadResult['success'] && $_FILES['foto_perfil']['error'] !== UPLOAD_ERR_NO_FILE) { 
            $response['foto_warning'] = $uploadResult['message']; 
        } 

        error_log("Usuario creado: ID={$nuevo_id}, Usuario={$usuario}, Num Empleado={$num_empleado}, Rol={$id_rol}, Depto={$id_departamento}"); 
 
    } catch (Exception $e) { 
        // Rollback en caso de error 
        $conn->rollback(); 
        throw $e; 
    } 
    $conn->close(); 

} catch (Exception $e) { 
    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 
    error_log("Error en create_user.php: " . $e->getMessage()); 
} 
// Limpiar output inesperado 
ob_end_clean(); 

// Enviar respuesta 
header('Content-Type: application/json; charset=utf-8'); 
echo json_encode($response, JSON_UNESCAPED_UNICODE); 
exit; 
?>