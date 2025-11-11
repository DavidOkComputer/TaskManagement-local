<?php
/**
 * update_task.php
 * Updates an existing task with new information
 * 
 * Expected POST parameters:
 * - id_tarea: Task ID to update
 * - nombre: Task name
 * - descripcion: Task description
 * - id_proyecto: Project ID
 * - fecha_vencimiento: Due date
 * - estado: Task status
 */

header('Content-Type: application/json');

// Include database connection
require_once('db_connection.php');

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud inválido'
    ]);
    exit;
}

// Validate required fields
$required_fields = ['id_tarea', 'nombre', 'descripcion', 'id_proyecto', 'estado'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Campos requeridos faltantes: ' . implode(', ', $missing_fields)
    ]);
    exit;
}

// Get and sanitize input data
$id_tarea = intval($_POST['id_tarea']);
$nombre = trim($_POST['nombre']);
$descripcion = trim($_POST['descripcion']);
$id_proyecto = intval($_POST['id_proyecto']);
$fecha_vencimiento = isset($_POST['fecha_vencimiento']) && !empty($_POST['fecha_vencimiento']) 
    ? $_POST['fecha_vencimiento'] 
    : null;
$estado = trim($_POST['estado']);

// Validate status
$valid_statuses = ['pendiente', 'en proceso', 'en-progreso', 'completado', 'vencido'];
if (!in_array($estado, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Estado de tarea inválido'
    ]);
    exit;
}

// Normalize status (convert 'en-progreso' to 'en proceso')
if ($estado === 'en-progreso') {
    $estado = 'en proceso';
}

try {
    // Check if task exists
    $check_stmt = $conn->prepare("SELECT id_tarea FROM tareas WHERE id_tarea = ?");
    $check_stmt->bind_param("i", $id_tarea);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Tarea no encontrada'
        ]);
        $check_stmt->close();
        exit;
    }
    $check_stmt->close();
    
    // Check if project exists
    $project_stmt = $conn->prepare("SELECT id_proyecto FROM proyectos WHERE id_proyecto = ?");
    $project_stmt->bind_param("i", $id_proyecto);
    $project_stmt->execute();
    $project_result = $project_stmt->get_result();
    
    if ($project_result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Proyecto no encontrado'
        ]);
        $project_stmt->close();
        exit;
    }
    $project_stmt->close();
    
    // Prepare update statement
    if ($fecha_vencimiento !== null) {
        $stmt = $conn->prepare(
            "UPDATE tareas 
             SET nombre = ?, 
                 descripcion = ?, 
                 id_proyecto = ?, 
                 fecha_vencimiento = ?, 
                 estado = ?,
                 fecha_actualizacion = NOW()
             WHERE id_tarea = ?"
        );
        $stmt->bind_param("ssissi", 
            $nombre, 
            $descripcion, 
            $id_proyecto, 
            $fecha_vencimiento, 
            $estado, 
            $id_tarea
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE tareas 
             SET nombre = ?, 
                 descripcion = ?, 
                 id_proyecto = ?, 
                 fecha_vencimiento = NULL, 
                 estado = ?,
                 fecha_actualizacion = NOW()
             WHERE id_tarea = ?"
        );
        $stmt->bind_param("ssisi", 
            $nombre, 
            $descripcion, 
            $id_proyecto, 
            $estado, 
            $id_tarea
        );
    }
    
    // Execute update
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Tarea actualizada exitosamente',
                'task_id' => $id_tarea
            ]);
        } else {
            // No rows affected - data might be the same
            echo json_encode([
                'success' => true,
                'message' => 'No se realizaron cambios (los datos son iguales)',
                'task_id' => $id_tarea
            ]);
        }
    } else {
        throw new Exception('Error al ejecutar la actualización: ' . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    error_log('Error updating task: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la tarea: ' . $e->getMessage()
    ]);
}

// Close database connection
$conn->close();
?>