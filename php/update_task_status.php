<?php
/*update_task_status.php para actualizar el estado de las tareas cuando se marca completado*/
ob_start();
 
// Configurar manejo de errores - no mostrar errores en output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
 
header('Content-Type: application/json');
 
require_once('db_config.php');
require_once('notification_triggers.php');
 
// Limpiar cualquier output capturado antes de enviar JSON
ob_clean();
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}
 
try {
    //validar y limpiar inputs
    $id_tarea = isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';
    
    if ($id_tarea <= 0) {
        throw new Exception('ID de tarea inválido');
    }
    
    if (empty($nuevo_estado)) {
        throw new Exception('El nuevo estado es requerido');
    }
    
    //validar estado
    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    $nuevo_estado = strtolower($nuevo_estado);
    
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('El estado de la tarea no es válido');
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    //obtener la info de la tarea para encontrar el proyecto
    $stmt = $conn->prepare("SELECT id_proyecto FROM tbl_tareas WHERE id_tarea = ?");
    $stmt->bind_param("i", $id_tarea);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('La tarea especificada no existe');
    }
    
    $row = $result->fetch_assoc();
    $id_proyecto = $row['id_proyecto'];
    $stmt->close();
    
    $estado_anterior_proyecto = getProjectState($conn, $id_proyecto);
    
    //actualizar el estado de la tarea
    $stmt = $conn->prepare("UPDATE tbl_tareas SET estado = ? WHERE id_tarea = ?");
    $stmt->bind_param("si", $nuevo_estado, $id_tarea);
    
    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar la tarea: " . $stmt->error);
    }
    
    $stmt->close();
    
    // Recalcular el progreso del proyecto
    recalculateProjectProgress($conn, $id_proyecto);
    
    $estado_nuevo_proyecto = getProjectState($conn, $id_proyecto);
    
    if ($estado_anterior_proyecto !== 'vencido' && $estado_nuevo_proyecto === 'vencido') {
        triggerNotificacionProyectoVencido($conn, $id_proyecto, $estado_anterior_proyecto);
        error_log("Notificación enviada: Proyecto {$id_proyecto} cambió a vencido");
    }
    
    // Asegurar que el output está limpio antes de enviar JSON
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'message' => 'Estado de tarea actualizado',
        'id_tarea' => $id_tarea,
        'nuevo_estado' => $nuevo_estado
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    // Limpiar buffer antes de enviar respuesta de error
    ob_clean();
    
    // Log del error completo para debugging
    error_log("Error en update_task_status.php: " . $e->getMessage() . " - " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la tarea: ' . $e->getMessage()
    ]);
}
 
ob_end_flush();
 
//Función auxiliar para obtener estado del proyecto
function getProjectState($conn, $id_proyecto) {
    $stmt = $conn->prepare("SELECT estado FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['estado'] ?? 'pendiente';
}
 
function recalculateProjectProgress($conn, $id_proyecto) {
    try {
        //obtener el conteo total de tareas
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_tareas WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_tasks = (int)$row['total'];
        $stmt->close();
        
        //si no hay tareas el progreso es 0
        if ($total_tasks === 0) {
            $progress = 0;
        } else {
            //obtener el conteo de tareas completadas
            $stmt = $conn->prepare("SELECT COUNT(*) as completadas FROM tbl_tareas WHERE id_proyecto = ? AND estado = 'completado'");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $completed_tasks = (int)$row['completadas'];
            $stmt->close();
            
            //calcular el porcentaje de progreso
            $progress = round(($completed_tasks / $total_tasks) * 100);
        }
        
        //actualizar progreso de proyecto y estatus basado en progreso
        $nuevo_estado = determineProjectStatus($progress, $id_proyecto, $conn);
        
        $stmt = $conn->prepare("UPDATE tbl_proyectos SET progreso = ?, estado = ? WHERE id_proyecto = ?");
        $stmt->bind_param("isi", $progress, $nuevo_estado, $id_proyecto);
        $stmt->execute();
        $stmt->close();
        
        error_log("Progreso del proyecto $id_proyecto actualizado a: $progress% - Estado: $nuevo_estado");
        
    } catch (Exception $e) {
        error_log("Error recalculando progreso del proyecto: " . $e->getMessage());
    }
}
 
//determinar el estado del progreso basado en la fecha de entrega
function determineProjectStatus($progress, $id_proyecto, $conn) {
    try {
        //obtener fecha de entrega de proyecto
        $stmt = $conn->prepare("SELECT fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $fecha_cumplimiento = strtotime($row['fecha_cumplimiento']);
        $hoy = time();
        
        //si la fecha de entrega paso y no se ha completado marcar como vencido
        if ($hoy > $fecha_cumplimiento && $progress < 100) {
            return 'vencido';
        }
        
        if ($progress == 100) {
            return 'completado';
        }
        
        if ($progress > 0) {
            return 'en proceso';
        }
        
        return 'pendiente';
        
    } catch (Exception $e) {
        error_log("Error determinando estado del proyecto: " . $e->getMessage());
        return 'pendiente';
    }
}
?>