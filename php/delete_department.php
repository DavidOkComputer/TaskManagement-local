<?php
// eliminar_departamento.php
// php para eliminar departamentos
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set( 'display_errors', 0);
//cpmfiguracion de base de datos
require_once 'db_config.php';
$response=['success'=>false, 'message' => '' ]; 
  
try { // Check request method
    if ($_SERVER['REQUEST_METHOD']!== 'POST') { 
      throw new Exception('Método de solicitud no válido'); 
    } 

    //Validate input
    $id_departamento = isset($_POST['id_departamento']) ? intval($_POST['id_departamento']): 0;
    if ($id_departamento<=0 ) { 
      throw new Exception( 'ID de departamento no válido');
    } 
    
    //crear conexion a base de datos
    $conn=getDBConnection();
    if ($conn->connect_error) { 
      throw new Exception('Error de conexión a la base de datos');
    } 
    $conn->set_charset('utf8mb4');

     //revisar si el departamento existe
    $stmt = $conn->prepare("SELECT nombre FROM tbl_departamentos WHERE id_departamento = ?");
    $stmt->bind_param("i", $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows === 0) {
        throw new Exception('El departamento no existe');
    }

    $departamento = $result->fetch_assoc();
    $nombre_departamento = $departamento['nombre'];
    $stmt->close();
     
    //revisar si el departamento tiene usuarios relacionados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_usuarios WHERE id_departamento = ?");
    $stmt->bind_param("i", $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['total'] > 0) {
        throw new Exception("No se puede eliminar el departamento porque tiene {$row['total']} usuario(s) asociado(s). Por favor, reasigne los usuarios primero."); 
    } 
    $stmt->close();

    //Revisar si el departamento tiene objetivos asocidados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_objetivos WHERE id_departamento = ?");
    $stmt->bind_param("i", $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['total'] > 0) { 
        throw new Exception("No se puede eliminar el departamento porque tiene {$row['total']} objetivo(s) asociado(s).");
    }
    $stmt->close();

     //revisar si el departamento tiene proyectos asociados
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_proyectos WHERE id_departamento = ?"); $stmt->bind_param("i", $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    if ($row['total'] > 0) { 
        throw new Exception("No se puede eliminar el departamento porque tiene {$row['total']} proyecto(s) asociado(s).");
    }

    $stmt->close();
    //borrar departamento
    $stmt = $conn->prepare("DELETE FROM tbl_departamentos WHERE id_departamento = ?");
    $stmt->bind_param("i", $id_departamento);
    if ($stmt->execute()) { 
        $response['success'] = true; $response['message'] = "Departamento '{$nombre_departamento}' eliminado exitosamente";
        $response['id_departamento'] = $id_departamento; 
        error_log("Departamento eliminado: ID={$id_departamento}, Nombre={$nombre_departamento}");
    }
    else {
        throw new Exception('Error al eliminar el departamento');
    } 
    $stmt->close();
    $conn->close();
}
catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Error en eliminar_departamento.php: " . $e->getMessage());
}
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit; ?>