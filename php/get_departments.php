<?php
    // listar_departamentos.php o get_departments.php
    //php par a listar todos los departamentos 
    ('Content-Type: application/json'); 
    error_reporting(E_ALL);
    ini_set( 'display_errors', 0); 
    require_once 'db_config.php'; 
    
    $response=['success'=> false, 
               'message' => '', 
               'departamentos' => [] ];

    try{ //crear conexion a la base de datos
        $conn = getDBConnection();
        if ($conn->connect_error){ 
            throw new Exception('Error de conexión a la base de datos');
        }

        $conn->set_charset('utf8mb4');
        
        //query para obtener departamentos con la info del creador
        $query = "  SELECT d.id_departamento,
                    d.nombre, d.descripcion, d.id_creador, CONCAT(u.nombre, ' ', u.apellido)
                    as creador_nombre FROM tbl_departamentos d LEFT JOIN tbl_usuarios u ON
                    d.id_creador = u.id_usuario ORDER BY d.nombre ASC ";
        
        $result = $conn->query($query);

        if ($result === false) { 
            throw new Exception('Error al ejecutar la consulta');
        } 

        $departamentos = [];
        
        while ($row = $result->fetch_assoc()){ 
            $departamentos[] = [ 'id_departamento' => (int)$row['id_departamento'],
                                'nombre' => $row['nombre'],
                                'descripcion' => $row['descripcion'],
                                'id_creador' => (int)$row['id_creador'],
                                'creador_nombre' => $row['creador_nombre'] ?? 'N/A' ];
        }
        
        $response['success'] = true;
        $response['departamentos'] = $departamentos; 
        $response['total'] = count($departamentos); 
        $conn->close(); 
    } 
    catch (Exception $e) { 
        $response['success'] = false; $response['message'] = $e->getMessage(); 
        error_log("Error en listar_departamentos.php:" . $e->getMessage()); 
    } 

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit; ?>