<?php
/*
 * user_create_project.php
 * Crea proyectos para usuarios normales.
 * Soporta Proyecto Libre (es_libre = 1, id_departamento = NULL)
 * y proyecto grupal (id_tipo_proyecto = 1).
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

    /* ── DB connection ────────────────────────────────── */
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    /* ── User dept from tbl_usuario_roles ────────────── */
    $stmt_user = $conn->prepare("
        SELECT ur.id_departamento, ur.id_rol, d.nombre AS departamento_nombre
        FROM tbl_usuario_roles ur
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento
        WHERE ur.id_usuario = ? AND ur.activo = 1
        ORDER BY ur.es_principal DESC
        LIMIT 1
    ");
    if (!$stmt_user) {
        throw new Exception('Error al preparar consulta de usuario: ' . $conn->error);
    }
    $stmt_user->bind_param('i', $id_usuario);
    $stmt_user->execute();
    $user_data = $stmt_user->get_result()->fetch_assoc();
    $stmt_user->close();

    if (!$user_data) {
        throw new Exception('Usuario no tiene roles asignados. Contacte al administrador.');
    }
    if (!$user_data['id_departamento']) {
        throw new Exception('El usuario no tiene un departamento asignado. Contacte al administrador.');
    }

    $id_creador         = $id_usuario;
    $id_dept_usuario    = intval($user_data['id_departamento']);

    /* ── Detect Proyecto Libre ────────────────────────── */
    $es_libre = isset($_POST['es_libre']) && intval($_POST['es_libre']) === 1 ? 1 : 0;

    /* ── Required fields ──────────────────────────────── */
    $required = [
        'nombre', 'descripcion',
        'fecha_creacion', 'fecha_cumplimiento',
        'id_tipo_proyecto'
    ];
    // dept only required for non-libre projects
    if (!$es_libre) {
        $required[] = 'id_departamento';
    }

    foreach ($required as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    /* ── Sanitise inputs ──────────────────────────────── */
    $nombre             = trim($_POST['nombre']);
    $descripcion        = trim($_POST['descripcion']);
    $fecha_creacion     = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso           = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar                 = isset($_POST['ar'])       ? trim($_POST['ar'])         : '';
    $estado             = isset($_POST['estado'])   ? trim($_POST['estado'])     : 'pendiente';
    $archivo_adjunto    = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
    $id_tipo_proyecto   = intval($_POST['id_tipo_proyecto']);
    $puede_editar_otros = isset($_POST['puede_editar_otros']) ? intval($_POST['puede_editar_otros']) : 0;

    /* ── Determine id_departamento ────────────────────── */
    // Libre → NULL in DB
    // Non-libre → verify the POST value equals the user's own dept
    if ($es_libre) {
        $id_departamento = null;  // will be written as NULL
    } else {
        $id_dept_post = intval($_POST['id_departamento']);
        // Security: user can only create projects in their own dept
        if ($id_dept_post !== $id_dept_usuario) {
            $id_departamento = $id_dept_usuario; // silently use real dept
        } else {
            $id_departamento = $id_dept_usuario;
        }
    }

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

    $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('Estado inválido');
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

    /* ── Participants ─────────────────────────────────── */
    $usuarios_grupo  = [];
    $id_participante = 0;

    if ($id_tipo_proyecto == 1) {
        // Grupal
        if (!isset($_POST['usuarios_grupo'])) {
            throw new Exception('Debes seleccionar usuarios para el proyecto grupal');
        }
        $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
        if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
            throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
        }

        // For non-libre grupales: verify each user belongs to same dept
        if (!$es_libre) {
            foreach ($usuarios_grupo as $uid) {
                $uid = intval($uid);
                $chk = $conn->prepare("
                    SELECT 1 FROM tbl_usuario_roles
                    WHERE id_usuario = ? AND id_departamento = ? AND activo = 1
                ");
                $chk->bind_param('ii', $uid, $id_dept_usuario);
                $chk->execute();
                if ($chk->get_result()->num_rows === 0) {
                    throw new Exception(
                        "El usuario #{$uid} no pertenece a tu departamento. " .
                        "Activa Proyecto Libre para asignar usuarios de otros departamentos."
                    );
                }
                $chk->close();
            }
        }
    } else {
        // Individual
        $id_participante = isset($_POST['id_participante'])
            ? intval($_POST['id_participante'])
            : $id_creador;

        // For non-libre individual: verify participant belongs to same dept
        if (!$es_libre && $id_participante > 0 && $id_participante !== $id_creador) {
            $chk = $conn->prepare("
                SELECT 1 FROM tbl_usuario_roles
                WHERE id_usuario = ? AND id_departamento = ? AND activo = 1
            ");
            $chk->bind_param('ii', $id_participante, $id_dept_usuario);
            $chk->execute();
            if ($chk->get_result()->num_rows === 0) {
                throw new Exception(
                    "El usuario seleccionado no pertenece a tu departamento. " .
                    "Activa Proyecto Libre para asignar usuarios de otros departamentos."
                );
            }
            $chk->close();
        }
    }

    /* ── INSERT ───────────────────────────────────────── */
    if ($es_libre) {
        /*
         * Libre: id_departamento written as literal NULL
         * Params (12): s s s s i s s s i i i i
         *   nombre descripcion fecha_creacion fecha_cumplimiento
         *   progreso ar estado archivo_adjunto
         *   id_creador id_participante id_tipo_proyecto
         *   es_libre puede_editar_otros
         * Wait — 13 params. Let me count the columns:
         *   nombre(s) descripcion(s) fecha_inicio(s) fecha_cumplimiento(s)
         *   progreso(i) ar(s) estado(s) archivo_adjunto(s)
         *   id_creador(i) id_participante(i) id_tipo_proyecto(i)
         *   es_libre(i) puede_editar_otros(i)
         * = s s s s i s s s i i i i i  → 13 chars
         */
        $sql = "INSERT INTO tbl_proyectos (
                    nombre, descripcion, id_departamento,
                    fecha_inicio, fecha_cumplimiento,
                    progreso, ar, estado, archivo_adjunto,
                    id_creador, id_participante,
                    id_tipo_proyecto, es_libre, puede_editar_otros
                ) VALUES (
                    ?, ?, NULL,
                    ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?
                )";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        $stmt->bind_param(
            'ssssisssiiiii',
            // s:nombre s:descripcion s:fecha_creacion s:fecha_cumplimiento
            // i:progreso s:ar s:estado s:archivo_adjunto
            // i:id_creador i:id_participante
            // i:id_tipo_proyecto i:es_libre i:puede_editar_otros
            // = s s s s i s s s i i i i i  → 13 chars ✓
            $nombre,            // s 1
            $descripcion,       // s 2
            $fecha_creacion,    // s 3
            $fecha_cumplimiento,// s 4
            $progreso,          // i 5
            $ar,                // s 6
            $estado,            // s 7
            $archivo_adjunto,   // s 8
            $id_creador,        // i 9
            $id_participante,   // i 10
            $id_tipo_proyecto,  // i 11
            $es_libre,          // i 12
            $puede_editar_otros // i 13
        );
    } else {
        /*
         * Regular: id_departamento is an integer param
         * s s i s s i s s s i i i i i → 14 chars
         *   nombre descripcion id_departamento
         *   fecha_creacion fecha_cumplimiento progreso
         *   ar estado archivo_adjunto
         *   id_creador id_participante id_tipo_proyecto
         *   es_libre puede_editar_otros
         */
        $sql = "INSERT INTO tbl_proyectos (
                    nombre, descripcion, id_departamento,
                    fecha_inicio, fecha_cumplimiento,
                    progreso, ar, estado, archivo_adjunto,
                    id_creador, id_participante,
                    id_tipo_proyecto, es_libre, puede_editar_otros
                ) VALUES (
                    ?, ?, ?,
                    ?, ?,
                    ?, ?, ?, ?,
                    ?, ?,
                    ?, ?, ?
                )";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta: ' . $conn->error);
        }

        $stmt->bind_param(
            'ssississsiiiii',
            // s:nombre s:descripcion i:id_departamento
            // s:fecha_creacion s:fecha_cumplimiento i:progreso
            // s:ar s:estado s:archivo_adjunto
            // i:id_creador i:id_participante
            // i:id_tipo_proyecto i:es_libre i:puede_editar_otros
            // = s s i s s i s s s i i i i i → 14 chars ✓
            $nombre,            // s 1
            $descripcion,       // s 2
            $id_departamento,   // i 3
            $fecha_creacion,    // s 4
            $fecha_cumplimiento,// s 5
            $progreso,          // i 6
            $ar,                // s 7
            $estado,            // s 8
            $archivo_adjunto,   // s 9
            $id_creador,        // i 10
            $id_participante,   // i 11
            $id_tipo_proyecto,  // i 12
            $es_libre,          // i 13
            $puede_editar_otros // i 14
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Error al crear el proyecto: ' . $stmt->error);
    }

    $id_proyecto = $stmt->insert_id;
    $stmt->close();

    /* ── Grupo users ──────────────────────────────────── */
    if ($id_tipo_proyecto == 1 && !empty($usuarios_grupo)) {
        $stmt_pu = $conn->prepare(
            'INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)'
        );
        if (!$stmt_pu) {
            throw new Exception('Error al preparar asignación de usuarios: ' . $conn->error);
        }

        $notifier = new NotificationHelper($conn);

        foreach ($usuarios_grupo as $uid) {
            $uid = intval($uid);
            $stmt_pu->bind_param('ii', $id_proyecto, $uid);
            if (!$stmt_pu->execute()) {
                throw new Exception('Error al asignar usuarios: ' . $stmt_pu->error);
            }

            if ($uid !== $id_creador) {
                triggerNotificacionProyectoGrupal(
                    $conn, $id_proyecto, $uid, $es_libre
                );
                $notifier->notifyProjectAssigned($id_proyecto, $uid, $id_creador);
            }
        }
        $stmt_pu->close();
    }

    /* ── Individual notification ──────────────────────── */
    if ($id_tipo_proyecto == 2
        && $id_participante > 0
        && $id_participante !== $id_creador
    ) {
        triggerNotificacionProyectoAsignado(
            $conn, $id_proyecto, $id_participante, null, $es_libre
        );
        $notifier = new NotificationHelper($conn);
        $notifier->notifyProjectAssigned($id_proyecto, $id_participante, $id_creador);
    }

    $conn->close();

    $response['success']     = true;
    $response['message']     = $es_libre
        ? 'Proyecto Libre creado exitosamente'
        : 'Proyecto creado exitosamente';
    $response['id_proyecto'] = $id_proyecto;
    $response['es_libre']    = (bool)$es_libre;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('user_create_project.php Error: ' . $e->getMessage());
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit;
?>