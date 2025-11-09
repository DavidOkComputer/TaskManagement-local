<?php
// get_roles.php script para tener todos lls roles de la base de datos
header('Content-Type: application/json'); 
error_reporting(E_ALL); 
ini_set('display_errors', 0); 
require_once('db_config.php');
$response = [ 'success' => false, 'message' => '', 'roles' => [] ]; 
try { 
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); 
        if ($conn->connect_error) { 
            throw new Exception('Error de conexión a la base de datos'); 
        } 
        $conn->set_charset('utf8mb4');
        // Query para tener todos los roles
        $query = " SELECT id_rol, nombre, descripcion FROM tbl_roles ORDER BY id_rol ASC "; 
        $result = $conn->query($query); 
        if ($result === false) { 
            throw new Exception('Error al ejecutar la consulta'); 
        } 
        $roles = [];
        while ($row = $result->fetch_assoc()) {
                $roles[] = [ 'id_rol' => (int)$row['id_rol'], 'nombre' => ucfirst($row['nombre']), 
                'descripcion' => $row['descripcion'] ];//poner en mayuscula la primer letra 
            }
            $response['success'] = true; 
            $response['roles'] = $roles;
            $response['total'] = count($roles); 
            $conn->close(); 
    } 
    catch (Exception $e) {
        $response['success'] = false;
        $response['message'] = $e->getMessage(); 
        error_log("Error en get_roles.php: " . $e->getMessage()); 
    }
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    exit; ?>