<?php 
// create_user.php 
header('Content-Type: application/json'); 

error_reporting(E_ALL); 
ini_set('display_errors', 0); 

//iniciar buffer de output para hacer catch a output inesperado
ob_start();

$response = [//inicializar respuesta antes de todo 
    'success' => false, 
    'message' => '' 
];

try {
    if (!file_exists('db_config.php')) {//revisar si existe el archivo 
        throw new Exception('db_config.php no encontrado en ' . __DIR__);
    }

    require_once('db_config.php');
    
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

    $conn = getDBConnection(); 

    if (!$conn) {
        throw new Exception('No se pudo obtener la conexión a la base de datos');
    }

    if ($conn->connect_error) { 
        throw new Exception('Error de conexión a la base de datos: ' . $conn->connect_error); 
    } 
    $conn->set_charset('utf8mb4'); 

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

    // Hash de contraseña usando password_hash() con bcrypt (seguro)
    // PASSWORD_DEFAULT actualmente usa bcrypt, pero se actualizará automáticamente
    // si PHP agrega algoritmos más seguros en el futuro
    $acceso_hash = password_hash($acceso, PASSWORD_DEFAULT, ['cost' => 12]);
    
    // Verificar que el hash se generó correctamente
    if ($acceso_hash === false) {
        throw new Exception('Error al procesar la contraseña');
    }

    $stmt = $conn->prepare(" 
        INSERT INTO tbl_usuarios  
        (nombre, apellido, usuario, num_empleado, acceso, id_departamento, id_rol, id_superior, e_mail)  
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) 
    "); 

    if (!$stmt) {
        throw new Exception('Error en prepare (INSERT): ' . $conn->error);
    }

    $stmt->bind_param( 
        "sssisiiis", 
        $nombre, 
        $apellido, 
        $usuario, 
        $num_empleado, 
        $acceso_hash, 
        $id_departamento, 
        $id_rol, 
        $id_superior,
        $e_mail 
    ); 

    if ($stmt->execute()) { 
        $nuevo_id = $stmt->insert_id; 
        $response['success'] = true; 
        $response['message'] = "Usuario '{$usuario}' creado exitosamente"; 
        $response['id_usuario'] = $nuevo_id; 
        $response['usuario'] = $usuario; 
        error_log("Usuario creado: ID={$nuevo_id}, Usuario={$usuario}, Num Empleado={$num_empleado}"); 
    } else { 
        throw new Exception('Error al crear el usuario: ' . $stmt->error); 
    } 
    $stmt->close(); 
    $conn->close(); 

} catch (Exception $e) { 
    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 
    error_log("Error en create_user.php: " . $e->getMessage()); 
}

ob_end_clean();//limpiar output inesperado

header('Content-Type: application/json; charset=utf-8');//enviar respuesta
echo json_encode($response, JSON_UNESCAPED_UNICODE); 
exit; 
?>