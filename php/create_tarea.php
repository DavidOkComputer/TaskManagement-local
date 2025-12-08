<?php
//create_tarea.php para crear nueva tarea

header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
require_once 'notification_triggers.php';
require_once 'email/NotificationHelper.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }
    
    $required_fields = [//validar campos requeridos
        'nombre',
        'descripcion',
        'id_proyecto',
        'fecha_vencimiento',
        'estado'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);//limpiar y validar inputs
    $descripcion = trim($_POST['descripcion']);
    $id_proyecto = intval($_POST['id_proyecto']);
    $fecha_cumplimiento = trim($_POST['fecha_vencimiento']);
    $estado = trim($_POST['estado']);
    //saber id_participante si se proporciona
    $id_participante = isset($_POST['id_participante']) && !empty($_POST['id_participante']) ? intval($_POST['id_participante']) : null;

    //validar longitud
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 250) {
        throw new Exception('La descripción no puede exceder 250 caracteres');
    }
    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }
    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }

    $conn = getDBConnection();//conexion a base de datos
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //verificar que existe el proyecto
    $verify_query = "SELECT id_proyecto, estado FROM tbl_proyectos WHERE id_proyecto = ?";
    $verify_stmt = $conn->prepare($verify_query);
    
    if (!$verify_stmt) {
        throw new Exception('Error al preparar la consulta de verificación: ' . $conn->error);
    }

    $verify_stmt->bind_param("i", $id_proyecto);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    
    if ($verify_result->num_rows === 0) {
        throw new Exception('El proyecto especificado no existe');
    }
    
    $proyecto_data = $verify_result->fetch_assoc();
    $estado_anterior_proyecto = $proyecto_data['estado']; //Guardar estado anterior
    $verify_stmt->close();

    // Obtener id de la sesión
    $id_creador = isset($_SESSION['id_usuario']) ? intval($_SESSION['id_usuario']) : 1;

    // Preparar y ejecutar el insert incluir id_participante
    $sql = "INSERT INTO tbl_tareas (
                nombre,
                descripcion,
                id_proyecto,
                id_creador,
                fecha_cumplimiento,
                estado,
                id_participante
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssiissi",
        $nombre,
        $descripcion,
        $id_proyecto,
        $id_creador,
        $fecha_cumplimiento,
        $estado,
        $id_participante
    );

    if ($stmt->execute()) {
        $task_id = $stmt->insert_id;
        
        if ($id_participante !== null && $id_participante != $id_creador) {
            triggerNotificacionTareaAsignada($conn, $task_id, $id_participante, null);
            error_log("Notificación enviada: Nueva tarea {$task_id} asignada a usuario {$id_participante}");
        }

        //para correo de notificacion de asignacion de tarea
        $notifier = new NotificationHelper($conn);
        $notifier->notifyTaskAssigned($tarea_id, $usuario_asignador_id);

        // Recalcular progreso del proyecto
        recalculateProjectProgress($conn, $id_proyecto);
        
        $estado_nuevo_proyecto = getProjectState($conn, $id_proyecto);
        if ($estado_anterior_proyecto !== 'vencido' && $estado_nuevo_proyecto === 'vencido') {
            triggerNotificacionProyectoVencido($conn, $id_proyecto, $estado_anterior_proyecto);
        }
        
        $response['success'] = true;
        $response['message'] = 'Tarea registrada exitosamente';
        $response['task_id'] = $task_id;
    } else {
        throw new Exception('Error al crear la tarea: ' . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('create_tarea.php Error: ' . $e->getMessage());
}

echo json_encode($response);

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

// Función para recalcular progreso
function recalculateProjectProgress($conn, $id_proyecto) {
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_tareas WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_tasks = (int)$row['total'];
        $stmt->close();

        if ($total_tasks === 0) {
            $progress = 0;
        } else {
            $stmt = $conn->prepare("SELECT COUNT(*) as completadas FROM tbl_tareas WHERE id_proyecto = ? AND estado = 'completado'");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $completed_tasks = (int)$row['completadas'];
            $stmt->close();
            $progress = round(($completed_tasks / $total_tasks) * 100);
        }

        $nuevo_estado = determineProjectStatus($progress, $id_proyecto, $conn);

        $stmt = $conn->prepare("UPDATE tbl_proyectos SET progreso = ?, estado = ? WHERE id_proyecto = ?");
        $stmt->bind_param("isi", $progress, $nuevo_estado, $id_proyecto);
        $stmt->execute();
        $stmt->close();

    } catch (Exception $e) {
        error_log("Error recalculando progreso: " . $e->getMessage());
    }
}

function determineProjectStatus($progress, $id_proyecto, $conn) {
    try {
        $stmt = $conn->prepare("SELECT fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $fecha_cumplimiento = strtotime($row['fecha_cumplimiento']);
        $hoy = time();

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
        error_log("Error determinando estado: " . $e->getMessage());
        return 'pendiente';
    }
}
?>