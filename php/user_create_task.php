<?php
/* user_create_task.php para crear tareas como usuario */

header('Content-Type: application/json');
session_start();
require_once 'db_config.php';
require_once 'notification_triggers.php';
require_once '../email/NotificationHelper.php';

ob_start();

$response = [
    'success' => false,
    'message' => '',
    'task' => null
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Método no permitido';
    ob_clean();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

try {
    //autenticacion
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }
    $id_usuario = (int)$_SESSION['user_id'];

    //obtner info del form
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $fecha_cumplimiento = isset($_POST['fecha_cumplimiento']) ? trim($_POST['fecha_cumplimiento']) : null;
    $id_participante = isset($_POST['id_participante']) ? intval($_POST['id_participante']) : 0;

    //validaciones basicas
    if ($id_proyecto <= 0) throw new Exception('ID de proyecto inválido');
    if (empty($nombre)) throw new Exception('El nombre de la tarea es requerido');
    if (strlen($nombre) > 100) throw new Exception('El nombre no puede exceder 100 caracteres');
    if (strlen($descripcion) > 250) throw new Exception('La descripción no puede exceder 250 caracteres');

    if (!empty($fecha_cumplimiento)) {
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_cumplimiento);
        if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_cumplimiento) {
            throw new Exception('Formato de fecha inválido');
        }
    }

    $conn = getDBConnection();
    if (!$conn) throw new Exception('Error de conexión a la base de datos');

    //obtener detalles del proyceto
    $query_check = "
        SELECT
            p.id_proyecto,
            p.id_creador,
            p.puede_editar_otros,
            p.nombre as proyecto_nombre,
            p.id_tipo_proyecto,
            p.id_participante,
            p.es_libre
        FROM tbl_proyectos p
        WHERE p.id_proyecto = ?
    ";
    $stmt_check = $conn->prepare($query_check);
    if (!$stmt_check) throw new Exception('Error preparando consulta de verificación');
    $stmt_check->bind_param("i", $id_proyecto);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check->num_rows === 0) throw new Exception('El proyecto no existe');
    $proyecto = $result_check->fetch_assoc();
    $stmt_check->close();

    $es_creador = (int)$proyecto['id_creador'] === $id_usuario;
    $es_participante = (int)$proyecto['id_participante'] === $id_usuario;
    $es_proyecto_grupal = (int)$proyecto['id_tipo_proyecto'] === 1;
    $es_libre = (int)$proyecto['es_libre'] === 1;


    $final_id_participante = $id_usuario; // default

    if ($id_participante > 0 && $id_participante !== $id_usuario) {
        //solo permitir asignaciones a otros si el proyecto es libre
        if (!$es_libre) {
            throw new Exception('No puedes asignar tareas a otros usuarios en este proyecto.');
        }

        //revisar si el usuario acutal tiene permiso para asignar
        $puede_asignar = $es_creador || ((int)$proyecto['puede_editar_otros'] === 1);
        if (!$puede_asignar) {
            throw new Exception('No tienes permiso para asignar tareas a otros en este proyecto.');
        }

        //verificar que el usuario objetivo pertenece al proyecto
        $usuario_pertenece = false;
        if ($es_proyecto_grupal) {
            $query_grupo = "SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?";
            $stmt_grupo = $conn->prepare($query_grupo);
            $stmt_grupo->bind_param("ii", $id_proyecto, $id_participante);
            $stmt_grupo->execute();
            $result_grupo = $stmt_grupo->get_result();
            $usuario_pertenece = $result_grupo->num_rows > 0;
            $stmt_grupo->close();
        } else {
            //proyecto individual
            $usuario_pertenece = ((int)$proyecto['id_participante'] === $id_participante);
        }
        if (!$usuario_pertenece) {
            throw new Exception('El usuario seleccionado no pertenece a este proyecto.');
        }

        $final_id_participante = $id_participante;
    }

    //revisar permisos generales de creacion
    $puede_crear = false;
    if ($es_creador) {
        $puede_crear = true;
    } else {
        $tiene_acceso = $es_participante;
        if ($es_proyecto_grupal) {
            $query_grupo = "SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?";
            $stmt_grupo = $conn->prepare($query_grupo);
            $stmt_grupo->bind_param("ii", $id_proyecto, $id_usuario);
            $stmt_grupo->execute();
            $result_grupo = $stmt_grupo->get_result();
            $tiene_acceso = $tiene_acceso || $result_grupo->num_rows > 0;
            $stmt_grupo->close();
        }
        $proyecto_permite_edicion = (int)$proyecto['puede_editar_otros'] === 1;
        if ($tiene_acceso && $proyecto_permite_edicion) {
            $puede_crear = true;
        }
    }
    if (!$puede_crear) {
        throw new Exception('No tienes permiso para crear tareas en este proyecto.');
    }

    // Insertar la tarea
    $fecha_para_db = !empty($fecha_cumplimiento) ? $fecha_cumplimiento : '0000-00-00';
    $query_insert = "
        INSERT INTO tbl_tareas
        (nombre, descripcion, id_proyecto, id_creador, fecha_cumplimiento, estado, id_participante)
        VALUES (?, ?, ?, ?, ?, 'pendiente', ?)
    ";
    $stmt_insert = $conn->prepare($query_insert);
    if (!$stmt_insert) throw new Exception('Error preparando inserción: ' . $conn->error);
    $stmt_insert->bind_param("ssiisi", $nombre, $descripcion, $id_proyecto, $id_usuario, $fecha_para_db, $final_id_participante);
    if (!$stmt_insert->execute()) throw new Exception('Error al crear la tarea: ' . $stmt_insert->error);
    $id_nueva_tarea = $conn->insert_id;
    $stmt_insert->close();

    //recalcular el progreso del poryecto
    recalculateProjectProgress($conn, $id_proyecto);

    //construir el nombre de participante
    $query_user = "SELECT nombre, apellido, num_empleado FROM tbl_usuarios WHERE id_usuario = ?";
    $stmt_user = $conn->prepare($query_user);
    $stmt_user->bind_param("i", $final_id_participante);
    $stmt_user->execute();
    $result_user = $stmt_user->get_result();
    $usuario_data = $result_user->fetch_assoc();
    $stmt_user->close();
    $participante_display = $usuario_data['nombre'] . ' ' . $usuario_data['apellido'] . ' (#' . $usuario_data['num_empleado'] . ')';

    $response = [
        'success' => true,
        'message' => 'Tarea creada exitosamente',
        'task' => [
            'id_tarea' => $id_nueva_tarea,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'fecha_cumplimiento' => $fecha_para_db,
            'estado' => 'pendiente',
            'id_proyecto' => $id_proyecto,
            'id_participante' => $final_id_participante,
            'participante' => $participante_display,
            'proyecto' => $proyecto['proyecto_nombre']
        ]
    ];

    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('user_create_task.php Error: ' . $e->getMessage());
}

ob_clean();
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
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        $fecha_vencimiento = strtotime($row['fecha_cumplimiento']);
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
?>