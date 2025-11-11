<?php
/**
 * update_task_status.php - Update task status and recalculate project progress
 * 
 * When a task status changes, this automatically recalculates the parent project's progress
 */

header('Content-Type: application/json');
require_once('db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    // Validate and sanitize inputs
    $id_tarea = isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;
    $nuevo_estado = isset($_POST['estado']) ? trim($_POST['estado']) : '';

    if ($id_tarea <= 0) {
        throw new Exception('ID de tarea inválido');
    }

    if (empty($nuevo_estado)) {
        throw new Exception('El nuevo estado es requerido');
    }

    // Validate estado
    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    $nuevo_estado = strtolower($nuevo_estado);
    if (!in_array($nuevo_estado, $estados_validos)) {
        throw new Exception('El estado de la tarea no es válido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Get task info to find its project
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

    // Update task status
    $stmt = $conn->prepare("UPDATE tbl_tareas SET estado = ? WHERE id_tarea = ?");
    $stmt->bind_param("si", $nuevo_estado, $id_tarea);

    if (!$stmt->execute()) {
        throw new Exception("Error al actualizar la tarea: " . $stmt->error);
    }

    $stmt->close();

    // Recalculate project progress
    recalculateProjectProgress($conn, $id_proyecto);

    echo json_encode([
        'success' => true,
        'message' => 'Estado de tarea actualizado',
        'id_tarea' => $id_tarea,
        'nuevo_estado' => $nuevo_estado
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la tarea: ' . $e->getMessage()
    ]);
}

/**
 * Recalculate project progress based on completed tasks
 * 
 * @param mysqli $conn Database connection
 * @param int $id_proyecto Project ID
 */
function recalculateProjectProgress($conn, $id_proyecto) {
    try {
        // Get total tasks count
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_tareas WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_tasks = (int)$row['total'];
        $stmt->close();

        // If no tasks, set progress to 0
        if ($total_tasks === 0) {
            $progress = 0;
        } else {
            // Get completed tasks count
            $stmt = $conn->prepare("SELECT COUNT(*) as completadas FROM tbl_tareas WHERE id_proyecto = ? AND estado = 'completado'");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $completed_tasks = (int)$row['completadas'];
            $stmt->close();

            // Calculate progress percentage
            $progress = round(($completed_tasks / $total_tasks) * 100);
        }

        // Update project progress and status based on progress
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

/**
 * Determine project status based on progress and deadline
 * 
 * @param int $progress Progress percentage (0-100)
 * @param int $id_proyecto Project ID
 * @param mysqli $conn Database connection
 * @return string Project status
 */
function determineProjectStatus($progress, $id_proyecto, $conn) {
    try {
        // Get project due date
        $stmt = $conn->prepare("SELECT fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $fecha_vencimiento = strtotime($row['fecha_cumplimiento']);
        $hoy = time();

        // If deadline passed and not completed, mark as expired
        if ($hoy > $fecha_vencimiento && $progress < 100) {
            return 'vencido';
        }

        // If 100% complete, mark as completed
        if ($progress == 100) {
            return 'completado';
        }

        // If has progress but not complete, mark as in progress
        if ($progress > 0) {
            return 'en proceso';
        }

        // Default to pending
        return 'pendiente';

    } catch (Exception $e) {
        error_log("Error determinando estado del proyecto: " . $e->getMessage());
        return 'pendiente';
    }
}
?>