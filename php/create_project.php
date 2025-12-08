<?php
/*create_project.php para crear nuevo proyecto
*/

header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'notification_triggers.php';
require_once 'email/NotificationHelper.php';


error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }
    
    $required_fields = [
        'nombre',
        'descripcion',
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'estado',
        'id_creador',
        'id_tipo_proyecto'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';
    $estado = trim($_POST['estado']);
    $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
    $id_creador = intval($_POST['id_creador']);
    $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);
    $puede_editar_otros = isset($_POST['puede_editar_otros']) ? intval($_POST['puede_editar_otros']) : 0;
    
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
    }
    if (strpos($fecha_creacion, 'T') !== false) {
        $fecha_creacion = str_replace('T', ' ', $fecha_creacion);
    }
    if (strtotime($fecha_creacion) === false) {
        throw new Exception('La fecha de creación no es válida');
    }
    if (strtotime($fecha_cumplimiento) === false) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }
    if (strtotime($fecha_cumplimiento) < strtotime($fecha_creacion)) {
        throw new Exception('La fecha de entrega debe ser posterior o igual a la fecha de inicio');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $usuarios_grupo = [];
    $id_participante = 0;
    
    if ($id_tipo_proyecto == 1) {
        if (isset($_POST['usuarios_grupo'])) {
            $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
            if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
                throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
            }
        } else {
            throw new Exception('Debes seleccionar usuarios para el proyecto grupal');
        }
    } else {
        if (isset($_POST['id_participante'])) {
            $id_participante = intval($_POST['id_participante']);
        }
    }

    $sql = "INSERT INTO tbl_proyectos (
                nombre,
                descripcion,
                id_departamento,
                fecha_inicio,
                fecha_cumplimiento,
                progreso,
                ar,
                estado,
                archivo_adjunto,
                id_creador,
                id_participante,
                id_tipo_proyecto,
                puede_editar_otros
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param(
        "ssissississii",
        $nombre,
        $descripcion,
        $id_departamento,
        $fecha_creacion,
        $fecha_cumplimiento,
        $progreso,
        $ar,
        $estado,
        $archivo_adjunto,
        $id_creador,
        $id_participante,
        $id_tipo_proyecto,
        $puede_editar_otros
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $id_proyecto = $stmt->insert_id;
    $stmt->close();

    if ($id_tipo_proyecto == 2 && $id_participante > 0 && $id_participante != $id_creador) {
        // Notificar al participante que se le asignó el proyecto
        triggerNotificacionProyectoAsignado($conn, $id_proyecto, $id_participante, null);
        error_log("Notificación enviada: Nuevo proyecto {$id_proyecto} asignado a usuario {$id_participante}");
    }

    //para email de notificacion de asignacion de proyecto
    $notifier = new NotificationHelper($conn);
    $notifier->notifyProjectAssigned($proyecto_id, $usuario_asignado_id, $creador_id);

    // Manejo de usuarios para proyecto grupal
    if ($id_tipo_proyecto == 1 && !empty($usuarios_grupo)) {
        $sql_usuarios = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
        }

        foreach ($usuarios_grupo as $id_usuario) {
            $id_usuario = intval($id_usuario);
            $stmt_usuarios->bind_param("ii", $id_proyecto, $id_usuario);
            
            if (!$stmt_usuarios->execute()) {
                throw new Exception('Error al asignar usuarios al proyecto: ' . $stmt_usuarios->error);
            }
            
            if ($id_usuario != $id_creador) {
                triggerNotificacionProyectoGrupal($conn, $id_proyecto, $id_usuario);
                error_log("Notificación enviada: Proyecto grupal {$id_proyecto} - usuario {$id_usuario} agregado");
            }
        }
        $stmt_usuarios->close();
    }

    $response['success'] = true;
    $response['message'] = 'Proyecto registrado exitosamente';
    $response['id_proyecto'] = $id_proyecto;

    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in create_project.php: ' . $e->getMessage());
}

ob_end_clean();
echo json_encode($response);
exit;
?>