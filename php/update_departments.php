<?php
// actualizar_departamento.php script para actualizar el departamento
header('Content-Type: application/json'); 
error_reporting(E_ALL); 
ini_set( 'display_errors', 0); 

//configuracion base de datos
require_once 'db_config.php';

$response=['success'=>false, 'message' => '' ]; 
try { //revisar metodo post
        if ($_SERVER['REQUEST_METHOD']!== 'POST') {
            throw new Exception('Método de solicitud no válido');
        } //validar y limpiar input
        $id_departamento = isset($_POST['id_departamento']) ? intval($_POST['id_departamento']) : 0;
        $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
         //Validacion
        if ($id_departamento<=0 ) {
            throw new Exception( 'ID de departamento no válido');
        } 
        if (empty($nombre)){
            throw new Exception( 'El nombre del departamento es requerido'); 
        }
        if(strlen($nombre)>200) {
            throw new Exception('El nombre no puede exceder 200 caracteres');
        } 
        if (empty($descripcion)) { 
            throw new Exception('La descripción del departamento es requerida'); 
        } 
        if (strlen($descripcion) > 200) { 
            throw new Exception('Lad escripción no puede exceder 200 caracteres');
        } //crear conexion a base de datos
        
        $conn = getDBConnection(); 
        if ($conn->connect_error){ 
            throw new Exception('Error de conexión a la base de datos'); 
        }

        $conn->set_charset('utf8mb4');
        //revisar si el departamento existe
        $stmt = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE id_departamento = ?"); 
        $stmt->bind_param("i",$id_departamento); 
        $stmt->execute(); 
        $result = $stmt->get_result();

        if($result->num_rows === 0) { 
            throw new Exception('El departamento no existe');
        } 
        $stmt->close(); 
        //revisar si existe otro departamento con el mismo nombre (excepto el mismo)
        $stmt = $conn->prepare("SELECT id_departamento FROM tbl_departamentos WHERE nombre = ? AND id_departamento != ?");
        $stmt->bind_param("si", $nombre, $id_departamento);
        $stmt->execute();
        $result = $stmt->get_result();
    
        if ($result->num_rows > 0) { 
            throw new Exception('Ya existe otro departamento con ese nombre');
        } 
        $stmt->close(); 
        //actualizar departamento
        $stmt = $conn->prepare("UPDATE tbl_departamentos SET nombre = ?, descripcion = ? WHERE id_departamento = ?");
        $stmt->bind_param("ssi", $nombre, $descripcion, $id_departamento);
    
        if ($stmt->execute()) { 
            $response['success'] = true;
            $response['message'] = 'Departamento actualizado exitosamente';
            $response['id_departamento'] = $id_departamento;
            $response['nombre'] = $nombre;
            error_log("Departamento actualizado: ID={$id_departamento}, Nombre={$nombre}");
        } 
        else { 
            throw new Exception('Error al actualizar el departamento');
        } 
        $stmt->close();
        $conn->close();
    } 

    catch (Exception $e) { $response['success'] = false;
    $response['message'] = $e->getMessage(); 
    error_log("Error en actualizar_departamento.php: " . $e->getMessage()); } echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit; ?>