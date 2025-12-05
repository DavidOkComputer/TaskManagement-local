<?php 
/* user_get_tasks_by_project.php para saber las tareas por proyecto */ 

header('Content-Type: application/json'); 
session_start(); 
require_once 'db_config.php'; 

error_reporting(E_ALL); 
ini_set('display_errors', 0); 
ob_start(); 

$response = [ 
    'success' => false,  
    'tasks' => [] 

]; 

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { 
    $response['message'] =  
        'Método inválido'; 
    ob_clean(); 
    echo json_encode($response); 
    ob_end_flush(); 
    exit; 
} 

try { 

    if (!isset($_SESSION['user_id'])) { 
        throw new Exception( 
            'Usuario no autenticado' 
        ); 
    } 

    $id_usuario =  (int)$_SESSION['user_id']; 

    //para validar el id del proyecto
    if (!isset($_GET['id_proyecto'])  || empty($_GET['id_proyecto'])) { 
        throw new Exception( 
            'ID del proyecto requerido' 
        ); 
    } 

    $id_proyecto =  intval($_GET['id_proyecto']); 

    if ($id_proyecto <= 0) { 
        throw new Exception( 
            'ID del proyecto no válido' 
        ); 
    } 

    $conn = getDBConnection(); 

    if (!$conn) { 
        throw new Exception( 
            'Error de conexión a BD' 
        ); 
    } 

    $query_access = " SELECT 1  
                      FROM tbl_proyectos p 
                      LEFT JOIN tbl_proyecto_usuarios pu  
                      ON p.id_proyecto =  pu.id_proyecto 
                      WHERE p.id_proyecto = ?  
                      AND ( p.id_creador = ?   
                      OR p.id_participante = ?  
                      OR pu.id_usuario = ? 
                      ) 
                      LIMIT 1 
    "; 

    $stmt_access = $conn->prepare($query_access); 

    if (!$stmt_access) { 
        throw new Exception( 
            'Error preparando consulta' 
        ); 
    } 

    $stmt_access->bind_param("iiii", $id_proyecto, $id_usuario, $id_usuario, $id_usuario ); 
    $stmt_access->execute(); 
    $result_access = $stmt_access->get_result(); 

    if ($result_access->num_rows === 0) { 
        throw new Exception( 
            'No tienes acceso al proyecto' 
        ); 
    } 

    $stmt_access->close(); 

    $query = "SELECT  
              t.id_tarea, 
              t.nombre, 
              t.descripcion, 
              t.fecha_cumplimiento, 
              t.estado, 
              t.fecha_creacion, 
              t.id_participante, 
              t.id_proyecto, 
              u_creador.nombre as creador, 
              u_participante.nombre as participante_nombre, 
              u_participante.apellido as participante_apellido, 
              u_participante.num_empleado as participante_num_empleado, 
              p.nombre as proyecto 
              FROM tbl_tareas t 
              LEFT JOIN tbl_usuarios u_creador  ON t.id_creador = u_creador.id_usuario 
              LEFT JOIN tbl_usuarios u_participante ON t.id_participante = u_participante.id_usuario 
              LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
              WHERE t.id_proyecto = ? AND t.id_participante = ? 
              ORDER BY t.fecha_cumplimiento ASC 
    "; 

    $stmt = $conn->prepare($query); 

    if (!$stmt) { 
        throw new Exception( 
            'Error preparando consulta' 
        ); 
    } 

    $stmt->bind_param( 
        "ii",  
        $id_proyecto,  
        $id_usuario 
    ); 

    $stmt->execute(); 
    $result = $stmt->get_result();  
    $tasks = []; 

    while ($row = $result->fetch_assoc()) { 

        //construir y mostrar el nombre
        $participante_display = null; 

        if ($row['participante_nombre']) { 
            $participante_display = $row['participante_nombre'] . ' ' . $row['participante_apellido'] . ' (#' . $row['participante_num_empleado'] . ')'; 
        } 

        $tasks[] = [ 
            'id_tarea' => (int)$row['id_tarea'], 
            'nombre' => $row['nombre'], 
            'descripcion' => $row['descripcion'], 
            'fecha_cumplimiento' => $row['fecha_cumplimiento'], 
            'estado' => $row['estado'], 
            'fecha_creacion' => $row['fecha_creacion'], 
            'id_participante' => (int)$row['id_participante'], 
            'id_proyecto' => (int)$row['id_proyecto'], 
            'creador' => $row['creador'], 
            'participante' => $participante_display, 
            'proyecto' => $row['proyecto'] 
        ]; 
    } 

    $response['success'] = true; 
    $response['tasks'] = $tasks; 
    $response['total'] = count($tasks); 
    $response['id_usuario'] = $id_usuario; 
    $result->free();
    $stmt->close(); 

} catch (Exception $e) { 
    $response['message'] =  
        'Error: ' . $e->getMessage(); 
    error_log( 'user_get_tasks_by_project: ' . $e->getMessage()); 
} 

if (isset($conn) && $conn) { 
    $conn->close(); 
} 

ob_clean(); 
echo json_encode( 
    $response,  
    JSON_UNESCAPED_UNICODE 
); 
ob_end_flush(); 
?> 