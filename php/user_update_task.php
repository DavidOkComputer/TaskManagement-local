<?php
// user_update_task.php para actualizar tarea

ob_start();
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
require_once 'notification_triggers.php';

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

    //recoger campos
    $id_tarea = isset($_POST['id_tarea']) ? intval($_POST['id_tarea']) : 0;
    $nombre   = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;
    $fecha_vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $estado   = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
    $id_participante = isset($_POST['id_participante']) && !empty($_POST['id_participante']) ? intval($_POST['id_participante']) : null;

    // Validaciones básicas
    if ($id_tarea <= 0) throw new Exception('ID de tarea inválido');
    if (empty($nombre)) throw new Exception('El nombre es requerido');
    if (strlen($nombre) > 100) throw new Exception('Nombre demasiado largo');
    if (strlen($descripcion) > 250) throw new Exception('Descripción demasiado larga');
    if ($id_proyecto <= 0) throw new Exception('Proyecto inválido');

    if (!empty($fecha_vencimiento)) {
        $d = DateTime::createFromFormat('Y-m-d', $fecha_vencimiento);
        if (!$d || $d->format('Y-m-d') !== $fecha_vencimiento) {
            throw new Exception('Fecha inválida');
        }
    }

    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    if (!in_array(strtolower($estado), $estados_validos)) {
        throw new Exception('Estado no válido');
    }

    $conn = getDBConnection();
    if (!$conn) throw new Exception('Error de conexión');

    // Obtener la tarea y su proyecto, junto con los permisos
    $stmt = $conn->prepare("
        SELECT t.id_proyecto, t.id_participante as old_participante,
               p.id_creador, p.puede_editar_otros, p.id_tipo_proyecto, p.id_participante as proyecto_participante
        FROM tbl_tareas t
        JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE t.id_tarea = ?
    ");
    $stmt->bind_param("i", $id_tarea);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Tarea no encontrada');
    $task = $result->fetch_assoc();
    $stmt->close();

    // Verificar que el usuario pertenece al proyecto
    $es_miembro = ($task['id_creador'] == $user_id) || ($task['proyecto_participante'] == $user_id);
    if (!$es_miembro && (int)$task['id_tipo_proyecto'] === 1) {
        $stmt = $conn->prepare("SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $task['id_proyecto'], $user_id);
        $stmt->execute();
        $es_miembro = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }
    if (!$es_miembro) throw new Exception('No tienes acceso a este proyecto');

    //ver permiso para editar solo creador o puede_editar_otros = 1
    $puede_editar = ($task['id_creador'] == $user_id) || ($task['puede_editar_otros'] == 1);
    if (!$puede_editar) throw new Exception('No tienes permiso para editar tareas en este proyecto');

    //si se asigna a un usuario verificar que pertenece al proyecto
    if ($id_participante !== null) {
        $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_participante);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) throw new Exception('Usuario asignado no existe');
        $stmt->close();

        // Verificar pertenencia al proyecto
        if ((int)$task['id_tipo_proyecto'] === 1) {
            $stmt = $conn->prepare("SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
            $stmt->bind_param("ii", $id_proyecto, $id_participante);
            $stmt->execute();
            if ($stmt->get_result()->num_rows === 0) throw new Exception('El usuario no está asignado al proyecto');
            $stmt->close();
        } else {
            if ((int)$task['proyecto_participante'] !== $id_participante) {
                throw new Exception('El usuario no está asignado a este proyecto individual');
            }
        }
    }

    // Actualizar la tarea
    $sql = "UPDATE tbl_tareas SET nombre = ?, descripcion = ?, id_proyecto = ?, fecha_cumplimiento = ?, estado = ?";
    $params = [$nombre, $descripcion, $id_proyecto, $fecha_vencimiento, $estado];
    if ($id_participante === null) {
        $sql .= ", id_participante = NULL WHERE id_tarea = ?";
        $params[] = $id_tarea;
        $types = "ssissi";
    } else {
        $sql .= ", id_participante = ? WHERE id_tarea = ?";
        $params[] = $id_participante;
        $params[] = $id_tarea;
        $types = "ssissii";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    if (!$stmt->execute()) throw new Exception('Error al actualizar: ' . $stmt->error);
    $stmt->close();

    // Obtener el nombre del nuevo participante
    $participante_display = null;
    if ($id_participante !== null && $id_participante != $task['old_participante']) {
        $stmt = $conn->prepare("SELECT nombre, apellido, num_empleado FROM tbl_usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_participante);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if ($res) {
            $participante_display = $res['nombre'] . ' ' . $res['apellido'] . ' (#' . $res['num_empleado'] . ')';
        }
        $stmt->close();
    }

    // Recalcular progreso del proyecto
    require_once 'db_config.php';
    recalculateProjectProgress($conn, $id_proyecto);

    ob_clean();
    $response = [
        'success' => true,
        'message' => 'Tarea actualizada exitosamente',
        'participante_display' => $participante_display
    ];

} catch (Exception $e) {
    ob_clean();
    $response['message'] = $e->getMessage();
    error_log('user_update_task.php: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();

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
        error_log("Progreso actualizado para proyecto $id_proyecto: $progress% - $nuevo_estado");
    } catch (Exception $e) {
        error_log("Error recalculando progreso: " . $e->getMessage());
    }
}

function determineProjectStatus($progress, $id_proyecto, $conn) {
    try {
        $stmt = $conn->prepare("SELECT fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $fecha_vencimiento = strtotime($result['fecha_cumplimiento']);
        $hoy = time();

        if ($hoy > $fecha_vencimiento && $progress < 100) return 'vencido';
        if ($progress == 100) return 'completado';
        if ($progress > 0) return 'en proceso';
        return 'pendiente';
    } catch (Exception $e) {
        error_log("Error determinando estado: " . $e->getMessage());
        return 'pendiente';
    }
}