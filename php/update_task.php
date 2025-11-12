<?php
/*update_task.php actualiza una tarea existente con nueva informacion*/

header('Content-Type: application/json');

//incluir conexion a base de datos
require_once('db_config.php');
$conn = getDBConnection();
//revisar si el metodo solicitado es post
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método de solicitud inválido'
    ]);
    exit;
}

//validar campos requeridos
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

//obtener y limpiar informacion de input
$id_tarea = intval($_POST['id_tarea']);
$nombre = trim($_POST['nombre']);
$descripcion = trim($_POST['descripcion']);
$id_proyecto = intval($_POST['id_proyecto']);
$fecha_cumplimiento = isset($_POST['fecha_cumplimiento']) && !empty($_POST['fecha_cumplimiento']) 
    ? $_POST['fecha_cumplimiento'] 
    : null;
$estado = trim($_POST['estado']);

//validar estado
$valid_statuses = ['pendiente', 'en proceso', 'en-progreso', 'completado', 'vencido'];
if (!in_array($estado, $valid_statuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Estado de tarea inválido'
    ]);
    exit;
}

//normalizar estado
if ($estado === 'en-progreso') {
    $estado = 'en proceso';
}

try {
    //revisar si la tarea existe
    $check_stmt = $conn->prepare("SELECT id_tarea FROM tbl_tareas WHERE id_tarea = ?");
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
    
    //revisar si el proyecto existe
    $project_stmt = $conn->prepare("SELECT id_proyecto FROM tbl_proyectos WHERE id_proyecto = ?");
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
    
    //preparar actualizacion
    if ($fecha_cumplimiento !== null) {
        $stmt = $conn->prepare(
            "UPDATE tbl_tareas 
             SET nombre = ?, 
                 descripcion = ?, 
                 id_proyecto = ?, 
                 fecha_cumplimiento = ?, 
                 estado = ?
             WHERE id_tarea = ?"
        );
        $stmt->bind_param("ssissi", 
            $nombre, 
            $descripcion, 
            $id_proyecto, 
            $fecha_cumplimiento, 
            $estado, 
            $id_tarea
        );
    } else {
        $stmt = $conn->prepare(
            "UPDATE tbl_tareas 
             SET nombre = ?, 
                 descripcion = ?, 
                 id_proyecto = ?, 
                 fecha_cumplimiento = NULL, 
                 estado = ?
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
    
    //ejecutar actualizacion
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Tarea actualizada exitosamente',
                'task_id' => $id_tarea
            ]);
        } else {
            //sin filas afectadas, losdatos pueden ser los mismos
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

$conn->close();
?>