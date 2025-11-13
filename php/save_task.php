<?php
/*
 * save_task.php 
 * guardar tareaes y actualiza la barra de progreso basado en las tareas completadas
 * FIXED: Corrected bind_param types and POST key for fecha_cumplimiento
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
    //validar y limpiar los inputs
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;
    // FIXED: Changed from 'fecha_cumplimiento' to 'fecha_vencimiento' to match JavaScript
    $fecha_cumplimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
    $id_creador = isset($_POST['id_creador']) ? intval($_POST['id_creador']) : 1; // Default to user 1 if not provided

    //validaciones 
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

    //validar informacion
    if (!empty($fecha_cumplimiento)) {
        if (strtotime($fecha_cumplimiento) === false) {
            throw new Exception('La fecha de cumplimiento no es válida');
        }
    }

    //validar el estado
    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    $estado = strtolower($estado);
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado de la tarea no es válido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //verificar que el proyecto existe
    $stmt = $conn->prepare("SELECT id_proyecto FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El proyecto especificado no existe');
    }
    $stmt->close();

    //insertar tarea
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

    // FIXED: Changed from "ssiiis" to "ssiiss"
    // s - nombre
    // s - descripcion
    // i - id_proyecto
    // i - id_creador
    // s - fecha_cumplimiento (WAS: i - WRONG!)
    // s - estado
    $stmt->bind_param(
        "ssiiss",
        $nombre,
        $descripcion,
        $id_proyecto,
        $id_creador,
        $fecha_cumplimiento,
        $estado
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $task_id = $stmt->insert_id;
    $stmt->close();

    //recalcular y actualizar el progreso del proyecto
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

        //  sino hay tareas el progreso es 0
        if ($total_tasks === 0) {
            $progress = 0;
        } else {
            //obtener el conteo completo de tareas
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

//determinar el estado del progreso basado en la fecha de entrea
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

        // Default en pendiente
        return 'pendiente';

    } catch (Exception $e) {
        error_log("Error determinando estado del proyecto: " . $e->getMessage());
        return 'pendiente';
    }
}
?>