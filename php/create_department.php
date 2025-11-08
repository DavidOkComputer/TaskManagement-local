<?php 
// procesar_departamento.php 

//quitar cuando este terminado
error_reporting(E_ALL); 
ini_set('display_errors', 0); //mostrar errores en log en vez de al usuario 

//respuesta en json 
header('Content-Type: application/json'); 

require_once 'db_config.php';
 
$response = [//inicializar respuesta en array 
    'success' => false, 
    'message' => '' 
]; 

try { 
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {//revisar que se usa metodo post 
        throw new Exception('Método de solicitud no válido'); 
    } 

    //validar y limpiar info 
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : ''; 
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : ''; 
    $id_creador = isset($_POST['id_creador']) ? intval($_POST['id_creador']) : 0; 
 
    if (empty($nombre)) { 
        throw new Exception('El nombre del departamento es requerido'); 
    } 

    if (strlen($nombre) > 200) { 
        throw new Exception('El nombre del departamento no puede exceder 200 caracteres'); 
    } 

    if (empty($descripcion)) { 
        throw new Exception('La descripción del departamento es requerida'); 
    } 

    if (strlen($descripcion) > 200) { 
        throw new Exception('La descripción no puede exceder 200 caracteres'); 
    } 

    if ($id_creador <= 0) { 
        throw new Exception('ID de creador no válido'); 
    } 
    
    
    $conn = getDBConnection();//conexion a base de datos 

    if ($conn->connect_error) {//revisar conexion 
        throw new Exception('Error de conexión a la base de datos: ' . $conn->connect_error); 
    } 

    $conn->set_charset('utf8mb4'); 

    //revisar si el creador existe y es valido 
    $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE id_usuario = ?"); 
    $stmt->bind_param("i", $id_creador); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 

     
    if ($result->num_rows === 0) { 
        throw new Exception('El usuario creador no existe'); 
    } 
    $stmt->close(); 

    //revisar si ya existe el nombre del departamento que se esta creadno 
    $stmt = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE nombre = ?"); 
    $stmt->bind_param("s", $nombre); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 
    if ($result->num_rows > 0) { 
        throw new Exception('Ya existe un departamento con ese nombre'); 
    } 
    $stmt->close(); 

    //preparar insercion 
    $stmt = $conn->prepare("INSERT INTO tbl_departamentos (nombre, descripcion, id_creador) VALUES (?, ?, ?)"); 
    if (!$stmt) { 
        throw new Exception('Error al preparar la consulta: ' . $conn->error); 
    } 
    
    $stmt->bind_param("ssi", $nombre, $descripcion, $id_creador); 

    if ($stmt->execute()) { //ejecutar lo preparado
        $nuevo_id = $stmt->insert_id; 
        $response['success'] = true; 
        $response['message'] = 'Departamento creado exitosamente'; 
        $response['id_departamento'] = $nuevo_id; 
        $response['nombre'] = $nombre; 

        //logear la accion 
        error_log("Departamento creado: ID={$nuevo_id}, Nombre={$nombre}, Creador={$id_creador}"); 
    } else { 
        throw new Exception('Error al crear el departamento: ' . $stmt->error); 
    } 
 
    $stmt->close(); 
    $conn->close();//cerrar conexion y query 

} catch (Exception $e) { 
    $response['success'] = false; 
    $response['message'] = $e->getMessage(); 
    error_log("Error en procesar_departamento.php: " . $e->getMessage());// mostrar error para debug 
} 

echo json_encode($response, JSON_UNESCAPED_UNICODE);//respuesta en json 
exit; 
?> 