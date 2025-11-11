<?php
/**
 * save_task.php - Updated to handle all task fields and recalculate project progress
 * 
 * Saves new task and automatically updates project progress based on completed tasks
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
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;
    $fecha_vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
    $id_creador = isset($_POST['id_creador']) ? intval($_POST['id_creador']) : 1; // Default to user 1 if not provided

    // Validations
    if (empty($nombre)) {
        throw new Exception('El nombre de la tarea es requerido');
    }

    if (empty($descripcion)) {
        throw new Exception('La descripción es requerida');
    }

    if ($id_proyecto <= 0) {
        throw new Exception('Debe seleccionar un proyecto válido');
    }

    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }

    if (strlen($descripcion) > 250) {
        throw new Exception('La descripción no puede exceder 250 caracteres');
    }

    // Validate date if provided
    if (!empty($fecha_vencimiento)) {
        if (strtotime($fecha_vencimiento) === false) {
            throw new Exception('La fecha de vencimiento no es válida');
        }
    }

    // Validate estado
    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    $estado = strtolower($estado);
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado de la tarea no es válido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verify project exists
    $stmt = $conn->prepare("SELECT id_proyecto FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El proyecto especificado no existe');
    }
    $stmt->close();

    // Insert task - Include all fields
    $sql = "INSERT INTO tbl_tareas (
                nombre, 
                descripcion, 
                id_proyecto, 
                id_creador, 
                fecha_cumplimiento, 
                estado
            ) VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param(
        "ssiiis",
        $nombre,
        $descripcion,
        $id_proyecto,
        $id_creador,
        $fecha_vencimiento,
        $estado
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $task_id = $stmt->insert_id;
    $stmt->close();

    // Recalculate and update project progress
    recalculateProjectProgress($conn, $id_proyecto);

    echo json_encode([
        'success' => true,
        'message' => 'Tarea guardada exitosamente',
        'task_id' => $task_id
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la tarea: ' . $e->getMessage()
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