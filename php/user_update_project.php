<?php
/*
 * user_update_project.php
 * Actualiza proyectos creados por usuarios normales.
 * Soporta Proyecto Libre (es_libre type-locked, nunca cambia) y proyecto grupal.
 */

ob_start();
header('Content-Type: application/json; charset=UTF-8');

require_once 'db_config.php';
require_once 'notification_triggers.php';
require_once '../email/NotificationHelper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }

    /* ── Auth ─────────────────────────────────────────── */
    $id_usuario = null;
    if (isset($_SESSION['user_id'])) {
        $id_usuario = intval($_SESSION['user_id']);
    } elseif (isset($_SESSION['id_usuario'])) {
        $id_usuario = intval($_SESSION['id_usuario']);
    }
    if (!$id_usuario) {
        throw new Exception('Sesión no válida. Por favor, inicie sesión nuevamente.');
    }

    /* ── Required base fields ─────────────────────────── */
    $required = [
        'id_proyecto', 'nombre', 'descripcion',
        'fecha_creacion', 'fecha_cumplimiento', 'id_tipo_proyecto'
    ];
    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $id_proyecto        = intval($_POST['id_proyecto']);
    $nombre             = trim($_POST['nombre']);
    $descripcion        = trim($_POST['descripcion']);
    $fecha_creacion     = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso           = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar                 = isset($_POST['ar'])       ? trim($_POST['ar'])         : '';
    $archivo_adjunto    = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
    $id_tipo_proyecto   = intval($_POST['id_tipo_proyecto']);
    $puede_editar_otros = isset($_POST['puede_editar_otros']) ? intval($_POST['puede_editar_otros']) : 0;

    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    /* ── DB connection ────────────────────────────────── */
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    /* ── Load current project ─────────────────────────── */
    $stmt_old = $conn->prepare("
        SELECT id_creador, id_participante, id_departamento,
               estado, id_tipo_proyecto, es_libre
        FROM tbl_proyectos
        WHERE id_proyecto = ?
    ");
    $stmt_old->bind_param('i', $id_proyecto);
    $stmt_old->execute();
    $old = $stmt_old->get_result()->fetch_assoc();
    $stmt_old->close();

    if (!$old) {
        throw new Exception('El proyecto no existe');
    }

    /* ── Ownership check ──────────────────────────────── */
    // Allow creator or participant (non-libre individual)
    if (
        (int)$old['id_creador'] !== $id_usuario &&
        (int)$old['id_participante'] !== $id_usuario
    ) {
        // Also allow grupo members if puede_editar_otros = 1
        $chk_grp = $conn->prepare("
            SELECT 1 FROM tbl_proyecto_usuarios
            WHERE id_proyecto = ? AND id_usuario = ?
        ");
        $chk_grp->bind_param('ii', $id_proyecto, $id_usuario);
        $chk_grp->execute();
        $is_grupo_member = $chk_grp->get_result()->num_rows > 0;
        $chk_grp->close();

        if (!$is_grupo_member) {
            throw new Exception('No tienes permisos para editar este proyecto');
        }
    }

    /* ── es_libre is type-locked ──────────────────────── */
    $es_libre        = (int)$old['es_libre'];    // use DB value always
    $old_es_libre_req = isset($_POST['es_libre']) ? intval($_POST['es_libre']) : $es_libre;
    if ($old_es_libre_req !== $es_libre) {
        throw new Exception(
            $es_libre === 1
                ? 'No se puede convertir un Proyecto Libre en proyecto departamental.'
                : 'No se puede convertir un proyecto departamental en Proyecto Libre.'
        );
    }

    /* ── id_departamento ──────────────────────────────── */
    // Keep original — user cannot move a project to another dept
    $id_departamento = $old['id_departamento'];  // may be NULL for libre

    /* ── Validations ──────────────────────────────────── */
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 200) {
        throw new Exception('La descripción no puede exceder 200 caracteres');
    }
    if (strlen($archivo_adjunto) > 300) {
        throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
    }

    if (strpos($fecha_creacion, 'T') !== false) {
        $fecha_creacion = str_replace('T', ' ', $fecha_creacion);
    }
    if (!strtotime($fecha_creacion)) {
        throw new Exception('La fecha de creación no es válida');
    }
    if (!strtotime($fecha_cumplimiento)) {
        throw new Exception('La fecha de cumplimiento no es válida');
    }
    if (strtotime($fecha_cumplimiento) < strtotime($fecha_creacion)) {
        throw new Exception('La fecha de entrega debe ser posterior a la fecha de inicio');
    }

    /* ── Auto-calculate estado ────────────────────────── */
    $today    = date('Y-m-d');
    $deadline = substr($fecha_cumplimiento, 0, 10);

    if ($progreso >= 100) {
        $estado = 'completado';
    } elseif ($deadline < $today) {
        $estado = 'vencido';
    } elseif ($progreso > 0) {
        $estado = 'en proceso';
    } else {
        $estado = 'pendiente';
    }

    /* ── Get old grupo users (for notification diff) ──── */
    $old_usuarios_grupo = [];
    if ((int)$old['id_tipo_proyecto'] === 1) {
        $stmt_ou = $conn->prepare(
            'SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ?'
        );
        $stmt_ou->bind_param('i', $id_proyecto);
        $stmt_ou->execute();
        $res_ou = $stmt_ou->get_result();
        while ($row = $res_ou->fetch_assoc()) {
            $old_usuarios_grupo[] = (int)$row['id_usuario'];
        }
        $stmt_ou->close();
    }

    /* ── New participants ─────────────────────────────── */
    $usuarios_grupo  = [];
    $id_participante = 0;

    if ($id_tipo_proyecto == 1) {
        if (!isset($_POST['usuarios_grupo'])) {
            throw new Exception('Debes seleccionar usuarios para el proyecto grupal');
        }
        $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
        if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
            throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
        }
    } else {
        $id_participante = isset($_POST['id_participante'])
            ? intval($_POST['id_participante'])
            : $id_usuario;
    }

    /* ── UPDATE ───────────────────────────────────────── */
    if ($es_libre) {
        /*
         * Libre: id_departamento stays NULL
         * s s s s i s s s i i i i
         *   nombre descripcion fecha_creacion fecha_cumplimiento
         *   progreso ar estado archivo_adjunto
         *   id_participante id_tipo_proyecto puede_editar_otros
         *   id_proyecto
         * = s s s s i s s s i i i i → 12 chars
         */
        $sql = "UPDATE tbl_proyectos SET
                    nombre            = ?,
                    descripcion       = ?,
                    id_departamento   = NULL,
                    fecha_inicio      = ?,
                    fecha_cumplimiento = ?,
                    progreso          = ?,
                    ar                = ?,
                    estado            = ?,
                    archivo_adjunto   = ?,
                    id_participante   = ?,
                    id_tipo_proyecto  = ?,
                    puede_editar_otros = ?
                WHERE id_proyecto = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        $stmt->bind_param(
            'ssssisssiii' . 'i', // 12 chars ✓
            // s:nombre s:descripcion s:fecha_creacion s:fecha_cumplimiento
            // i:progreso s:ar s:estado s:archivo_adjunto
            // i:id_participante i:id_tipo_proyecto i:puede_editar_otros
            // i:id_proyecto
            $nombre,            // s 1
            $descripcion,       // s 2
            $fecha_creacion,    // s 3
            $fecha_cumplimiento,// s 4
            $progreso,          // i 5
            $ar,                // s 6
            $estado,            // s 7
            $archivo_adjunto,   // s 8
            $id_participante,   // i 9
            $id_tipo_proyecto,  // i 10
            $puede_editar_otros,// i 11
            $id_proyecto        // i 12
        );
    } else {
        /*
         * Regular: id_departamento is an int param (kept from DB)
         * s s i s s i s s s i i i i
         *   nombre descripcion id_departamento
         *   fecha_creacion fecha_cumplimiento progreso
         *   ar estado archivo_adjunto
         *   id_participante id_tipo_proyecto puede_editar_otros
         *   id_proyecto
         * = s s i s s i s s s i i i i → 13 chars
         */
        $sql = "UPDATE tbl_proyectos SET
                    nombre             = ?,
                    descripcion        = ?,
                    id_departamento    = ?,
                    fecha_inicio       = ?,
                    fecha_cumplimiento = ?,
                    progreso           = ?,
                    ar                 = ?,
                    estado             = ?,
                    archivo_adjunto    = ?,
                    id_participante    = ?,
                    id_tipo_proyecto   = ?,
                    puede_editar_otros = ?
                WHERE id_proyecto = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        $stmt->bind_param(
            'ssississsiii' . 'i', // 13 chars ✓
            // s:nombre s:descripcion i:id_departamento
            // s:fecha_creacion s:fecha_cumplimiento i:progreso
            // s:ar s:estado s:archivo_adjunto
            // i:id_participante i:id_tipo_proyecto i:puede_editar_otros
            // i:id_proyecto
            $nombre,            // s 1
            $descripcion,       // s 2
            $id_departamento,   // i 3
            $fecha_creacion,    // s 4
            $fecha_cumplimiento,// s 5
            $progreso,          // i 6
            $ar,                // s 7
            $estado,            // s 8
            $archivo_adjunto,   // s 9
            $id_participante,   // i 10
            $id_tipo_proyecto,  // i 11
            $puede_editar_otros,// i 12
            $id_proyecto        // i 13
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error);
    }
    $stmt->close();

    /* ── Vencido notification ─────────────────────────── */
    if ($old['estado'] !== 'vencido' && $estado === 'vencido') {
        triggerNotificacionProyectoVencido($conn, $id_proyecto, $old['estado']);
    }

    /* ── Individual participant change notification ────── */
    $old_participante = (int)$old['id_participante'];
    if ($id_tipo_proyecto == 2
        && $id_participante > 0
        && $id_participante !== $old_participante
    ) {
        triggerNotificacionProyectoAsignado(
            $conn, $id_proyecto, $id_participante, $old_participante, $es_libre
        );
        $notifier = new NotificationHelper($conn);
        $notifier->notifyProjectAssigned($id_proyecto, $id_participante, $id_usuario);
    }

    /* ── Grupo: delete old, insert new, notify new members */
    if ($id_tipo_proyecto == 1) {
        $stmt_del = $conn->prepare(
            'DELETE FROM tbl_proyecto_usuarios WHERE id_proyecto = ?'
        );
        $stmt_del->bind_param('i', $id_proyecto);
        $stmt_del->execute();
        $stmt_del->close();

        if (!empty($usuarios_grupo)) {
            $stmt_ins = $conn->prepare(
                'INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)'
            );
            if (!$stmt_ins) {
                throw new Exception('Error al preparar inserción de usuarios: ' . $conn->error);
            }

            $notifier = new NotificationHelper($conn);

            foreach ($usuarios_grupo as $uid) {
                $uid = intval($uid);
                $stmt_ins->bind_param('ii', $id_proyecto, $uid);
                if (!$stmt_ins->execute()) {
                    throw new Exception('Error al asignar usuarios: ' . $stmt_ins->error);
                }

                // Notify only newly added members
                if (!in_array($uid, $old_usuarios_grupo) && $uid !== $id_usuario) {
                    triggerNotificacionProyectoGrupal(
                        $conn, $id_proyecto, $uid, $es_libre
                    );
                    $notifier->notifyProjectAssigned($id_proyecto, $uid, $id_usuario);
                }
            }
            $stmt_ins->close();
        }
    }

    $conn->close();

    $response['success']     = true;
    $response['message']     = 'Proyecto actualizado exitosamente';
    $response['id_proyecto'] = $id_proyecto;
    $response['es_libre']    = (bool)$es_libre;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('user_update_project.php Error: ' . $e->getMessage());
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>