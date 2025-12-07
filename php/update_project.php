<?php
/*update_project.php para actualizar proyectos existentes
*/

header('Content-Type: application/json');
require_once 'db_config.php';
require_once 'notification_triggers.php'; 

error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método de solicitud inválido');
    }
    
    $required_fields = [
        'id_proyecto',
        'nombre',
        'descripcion',
        'id_departamento',
        'fecha_creacion',
        'fecha_cumplimiento',
        'id_tipo_proyecto'
    ];

    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) {
            throw new Exception("El campo {$field} es requerido");
        }
    }

    $id_proyecto = intval($_POST['id_proyecto']);
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_departamento = intval($_POST['id_departamento']);
    $fecha_creacion = trim($_POST['fecha_creacion']);
    $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
    $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
    $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';
    $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
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

    // Formato de fechas
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

    // Autocalcular estado basado en la fecha de entrega
    $today = date('Y-m-d');
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

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $stmt_old = $conn->prepare("SELECT id_participante, estado, id_tipo_proyecto FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt_old->bind_param("i", $id_proyecto);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    
    if ($result_old->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }
    
    $old_data = $result_old->fetch_assoc();
    $old_id_participante = (int)$old_data['id_participante'];
    $old_estado = $old_data['estado'];
    $old_tipo_proyecto = (int)$old_data['id_tipo_proyecto'];
    $stmt_old->close();
    
    // Obtener usuarios anteriores del proyecto grupal
    $old_usuarios_grupo = [];
    if ($old_tipo_proyecto == 1) {
        $stmt_old_users = $conn->prepare("SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ?");
        $stmt_old_users->bind_param("i", $id_proyecto);
        $stmt_old_users->execute();
        $result_old_users = $stmt_old_users->get_result();
        while ($row = $result_old_users->fetch_assoc()) {
            $old_usuarios_grupo[] = (int)$row['id_usuario'];
        }
        $stmt_old_users->close();
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

    // Actualizar el proyecto
    $sql = "UPDATE tbl_proyectos SET
            nombre = ?,
            descripcion = ?,
            id_departamento = ?,
            fecha_inicio = ?,
            fecha_cumplimiento = ?,
            progreso = ?,
            ar = ?,
            estado = ?,
            archivo_adjunto = ?,
            id_participante = ?,
            id_tipo_proyecto = ?,
            puede_editar_otros = ?
            WHERE id_proyecto = ?";

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
        $id_participante,
        $id_tipo_proyecto,
        $puede_editar_otros,
        $id_proyecto
    );

    if (!$stmt->execute()) {
        throw new Exception('Error al actualizar el proyecto: ' . $stmt->error);
    }

    $stmt->close();

    if ($old_estado !== 'vencido' && $estado === 'vencido') {
        triggerNotificacionProyectoVencido($conn, $id_proyecto, $old_estado);
    }

    if ($id_tipo_proyecto == 2 && $id_participante > 0 && $id_participante != $old_id_participante) {
        triggerNotificacionProyectoAsignado($conn, $id_proyecto, $id_participante, $old_id_participante);
        error_log("Notificación enviada: Proyecto {$id_proyecto} reasignado de usuario {$old_id_participante} a {$id_participante}");
    }

    // Manejo de usuarios en proyecto grupal
    if ($id_tipo_proyecto == 1) {
        // Eliminar asignaciones existentes
        $sql_delete = "DELETE FROM tbl_proyecto_usuarios WHERE id_proyecto = ?";
        $stmt_delete = $conn->prepare($sql_delete);
        
        if (!$stmt_delete) {
            throw new Exception('Error al preparar delete: ' . $conn->error);
        }

        $stmt_delete->bind_param("i", $id_proyecto);
        if (!$stmt_delete->execute()) {
            throw new Exception('Error al eliminar asignaciones anteriores: ' . $stmt_delete->error);
        }
        $stmt_delete->close();

        // Insertar nuevos usuarios
        if (!empty($usuarios_grupo)) {
            $sql_insert = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);

            if (!$stmt_insert) {
                throw new Exception('Error al preparar insert: ' . $conn->error);
            }

            foreach ($usuarios_grupo as $id_usuario) {
                $id_usuario = intval($id_usuario);
                $stmt_insert->bind_param("ii", $id_proyecto, $id_usuario);
                
                if (!$stmt_insert->execute()) {
                    throw new Exception('Error al asignar usuarios: ' . $stmt_insert->error);
                }
                
                if (!in_array($id_usuario, $old_usuarios_grupo)) {
                    triggerNotificacionProyectoGrupal($conn, $id_proyecto, $id_usuario);
                    error_log("Notificación enviada: Usuario {$id_usuario} agregado al proyecto grupal {$id_proyecto}");
                }
            }
            $stmt_insert->close();
        }
    }

    $response['success'] = true;
    $response['message'] = 'Proyecto actualizado exitosamente';
    $response['id_proyecto'] = $id_proyecto;

    $conn->close();

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error in update_project.php: ' . $e->getMessage());
}

ob_end_clean();
echo json_encode($response);
exit;
?>