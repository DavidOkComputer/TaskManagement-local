<?php
/*update_project_status.php para actualizar el estado de un proyecto manualmente */

header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'notification_triggers.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id_proyecto']) || empty($input['id_proyecto'])) {
        throw new Exception('ID de proyecto no proporcionado');
    }

    $id_proyecto = intval($input['id_proyecto']);

    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    if (!isset($input['estado']) || empty($input['estado'])) {
        throw new Exception('Estado no proporcionado');
    }

    $nuevo_estado = trim($input['estado']);

    // Validar estados permitidos
    $estados_validos = ['pendiente', 'en proceso', 'completado', 'vencido'];
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('Estado inválido: ' . $nuevo_estado);
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verificar que el proyecto existe y obtener estado actual y fecha de cumplimiento
    $check_sql = "SELECT id_proyecto, estado, progreso, nombre, fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?";
    $check_stmt = $conn->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception('Error al preparar verificación: ' . $conn->error);
    }

    $check_stmt->bind_param('i', $id_proyecto);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }

    $old_project = $check_result->fetch_assoc();
    $old_estado = $old_project['estado'];
    $fecha_cumplimiento = $old_project['fecha_cumplimiento'];
    $check_stmt->close();

    // Determinar el nuevo progreso basado en el estado
    $nuevo_progreso = $old_project['progreso'];
    
    if ($nuevo_estado === 'completado') {
        $nuevo_progreso = 100;
    } elseif ($nuevo_estado === 'pendiente') {
        // Si se revierte de completado a pendiente, verificar si está vencido
        $hoy = date('Y-m-d');
        
        if ($fecha_cumplimiento && $fecha_cumplimiento < $hoy) {
            // El proyecto está vencido, cambiar estado a vencido en lugar de pendiente
            $nuevo_estado = 'vencido';
        }
        
        // Recalcular progreso real de tareas si el progreso era 100
        if ($old_project['progreso'] >= 100) {
            $nuevo_progreso = calcularProgresoRealProyecto($conn, $id_proyecto);
        }
    }

    // Actualizar el proyecto
    $sql = "UPDATE tbl_proyectos SET estado = ?, progreso = ? WHERE id_proyecto = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param('sii', $nuevo_estado, $nuevo_progreso, $id_proyecto);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Estado del proyecto actualizado exitosamente';
            $response['id_proyecto'] = $id_proyecto;
            $response['nuevo_estado'] = $nuevo_estado;
            $response['nuevo_progreso'] = $nuevo_progreso;
            $response['estado_anterior'] = $old_estado;
            $response['fecha_cumplimiento'] = $fecha_cumplimiento;
            
            // Log para debugging
            error_log("Proyecto {$id_proyecto} actualizado: {$old_estado} -> {$nuevo_estado}, progreso: {$nuevo_progreso}");
        } else {
            $response['success'] = true;
            $response['message'] = 'Estado del proyecto actualizado (sin cambios en los datos)';
            $response['id_proyecto'] = $id_proyecto;
            $response['nuevo_estado'] = $nuevo_estado;
            $response['nuevo_progreso'] = $nuevo_progreso;
        }
    } else {
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error in update_project_status.php: ' . $e->getMessage());
}

function calcularProgresoRealProyecto($conn, $id_proyecto) {
    $sql = "SELECT 
                COUNT(*) as total_tareas,
                SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_tareas 
            WHERE id_proyecto = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    
    $stmt->bind_param('i', $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['total_tareas'] == 0) {
        return 0;
    }
    
    return round(($row['tareas_completadas'] / $row['total_tareas']) * 100);
}

ob_clean();
echo json_encode($response);
exit;
?>