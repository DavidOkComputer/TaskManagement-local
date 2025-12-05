<?php 
/*user_update_task_status.php para actualizar el estado de las tareas */ 

header('Content-Type: application/json'); 
session_start(); 
require_once('db_config.php'); 
ob_start(); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    ob_clean(); 
    echo json_encode([ 
        'success' => false, 
        'message' => 'Método no permitido' 
    ]); 
    ob_end_flush(); 
    exit; 
} 

try { 
    if (!isset($_SESSION['user_id'])) { 
        throw new Exception( 'Usuario no autenticado' ); 
    } 

    $id_usuario = (int)$_SESSION['user_id']; 
    $id_tarea =  isset($_POST['id_tarea'])  ? intval($_POST['id_tarea']) : 0; 
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : ''; 

    if ($id_tarea <= 0) { 
        throw new Exception( 
            'ID de tarea inválido' 
        ); 
    } 

    if (empty($nuevo_estado)) { 
        throw new Exception( 
            'Estado requerido' 
        ); 
    } 

    //validar estados 
    $estados_validos = [ 
        'pendiente',  
        'en-progreso',  
        'en proceso',  
        'completado' 
    ]; 

    $nuevo_estado =  strtolower($nuevo_estado); 
    if (!in_array( 
        $nuevo_estado,  
        $estados_validos 
    )) { 
        throw new Exception( 
            'Estado no válido' 
        ); 
    } 

    $conn = getDBConnection(); 

    if (!$conn) { 
        throw new Exception( 
            'Error de conexión a BD' 
        ); 
    } 

    //obtener info de creacion 
    $stmt = $conn->prepare("SELECT  
                            id_proyecto,  
                            id_participante,  
                            nombre  
                            FROM tbl_tareas  
                            WHERE id_tarea = ? 
                            "); 

    $stmt->bind_param("i", $id_tarea); 
    $stmt->execute(); 
    $result = $stmt->get_result(); 

    if ($result->num_rows === 0) { 
        throw new Exception( 
            'Tarea no existe' 
        ); 
    } 

    $row = $result->fetch_assoc(); 
    $id_proyecto = $row['id_proyecto']; 
    $id_participante =  $row['id_participante']; 
    $nombre_tarea = $row['nombre']; 
    $stmt->close(); 

    //verificar que el usuario sea uno asignado 
    if ($id_participante != $id_usuario) { 
        throw new Exception( 
            'No tienes permiso. ' . 'Solo puedes actualizar ' . 'tareas asignadas a ti.' 
        ); 
    } 

    // cuando el usurio esta autorizado entonces se actualiza 
    $stmt = $conn->prepare( "UPDATE tbl_tareas  
                             SET estado = ?  
                             WHERE id_tarea = ?" 
                            ); 

    $stmt->bind_param( "si", $nuevo_estado, $id_tarea ); 

    if (!$stmt->execute()) { 
        throw new Exception( 
            "Error actualizando: " .  
            $stmt->error 
        ); 
    } 
    $stmt->close(); 

    //Autoactualizar proyecto
    recalculateProjectProgress( $conn, $id_proyecto); 

    $response = [ 
        'success' => true, 
        'message' => 'Estado actualizado', 
        'id_tarea' => $id_tarea, 
        'nuevo_estado' => $nuevo_estado, 
        'nombre_tarea' => $nombre_tarea 
    ]; 
    $conn->close(); 

} catch (Exception $e) { 
    $response = [ 
        'success' => false, 
        'message' => $e->getMessage() 
    ]; 

    error_log( 'user_update_task_status: ' . $e->getMessage()); 
} 

ob_clean(); 
echo json_encode( $response, JSON_UNESCAPED_UNICODE); 
ob_end_flush(); 

function recalculateProjectProgress( $conn,  $id_proyecto 
) { 
    try { 
        //saber el total de tareas 

        $stmt = $conn->prepare("SELECT COUNT(*) as total  
                                FROM tbl_tareas  
                                WHERE id_proyecto = ?" 
        ); 

        $stmt->bind_param( "i", $id_proyecto); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 
        $row = $result->fetch_assoc(); 
        $total_tasks = (int)$row['total']; 
        $stmt->close(); 

        if ($total_tasks === 0) { 
            $progress = 0; 
        } else { 
            //saber los completados 

            $stmt = $conn->prepare("SELECT COUNT(*)  
                                    as completadas  
                                    FROM tbl_tareas  
                                    WHERE id_proyecto = ?  
                                    AND estado = 'completado' 
                                    "); 

            $stmt->bind_param("i", $id_proyecto); 
            $stmt->execute(); 
            $result = $stmt->get_result(); 
            $row = $result->fetch_assoc(); 
            $completed_tasks =  (int)$row['completadas']; 
            $stmt->close();

            //para calcular el progreso

            $progress = round(($completed_tasks / $total_tasks) * 100 ); 
        } 

        //actualizar el proyecto 
        $nuevo_estado = determineProjectStatus( $progress, $id_proyecto, $conn);
        $stmt = $conn->prepare( "UPDATE tbl_proyectos  
                                 SET progreso = ?,  
                                 estado = ?  
                                 WHERE id_proyecto = ?" 
                                ); 
        $stmt->bind_param( "isi", $progress, $nuevo_estado, $id_proyecto ); 
        $stmt->execute(); 
        $stmt->close(); 
        error_log( "Progreso actualizado: " . "$progress% - $nuevo_estado"); 
    } catch (Exception $e) { 
        error_log( 
            "Error recalculando: " .  
            $e->getMessage() 
        ); 
    } 
} 

function determineProjectStatus($progress, $id_proyecto, $conn 
) { 
    try { 
        //saber la fecha de vencimiento o entrega
        $stmt = $conn->prepare("SELECT fecha_cumplimiento  
                                FROM tbl_proyectos  
                                WHERE id_proyecto = ?" 
                                ); 

        $stmt->bind_param( "i", $id_proyecto); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 
        $row = $result->fetch_assoc(); 
        $stmt->close(); 
        $fecha_vencimiento = strtotime($row['fecha_cumplimiento']); 
        $hoy = time(); 

        //proyectos vencidos e incompltos 
        if ($hoy > $fecha_vencimiento  && $progress < 100) { 
            return 'vencido'; 
        } 

        //proyectos cienporciento completados
        if ($progress == 100) { 
            return 'completado';
        } 

        //proyectos que tengan progreso
        if ($progress > 0) { 
            return 'en proceso';
        } 
        //default para proectos
        return 'pendiente'; 

    } catch (Exception $e) { 
        error_log( 
            "Error status: " .  
            $e->getMessage() 
        ); 
        return 'pendiente'; 
    } 
} 
?> 