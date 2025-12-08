<?php 

/*user_create_task.php para crear tareas como usuario*/ 

header('Content-Type: application/json'); 
session_start(); 
require_once 'db_config.php'; 
require_once 'notification_triggers.php';
require_once 'email/NotificationHelper.php';

// Buffer de salida para evitar problemas con JSON 
ob_start(); 

$response = [ 
    'success' => false, 
    'message' => '', 
    'task' => null 
]; 

// Solo permitir POST 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { 
    $response['message'] = 'Método no permitido'; 
    ob_clean(); 
    echo json_encode($response, JSON_UNESCAPED_UNICODE); 
    ob_end_flush(); 
    exit; 
} 

try { 
    // Verificar autenticación 
    if (!isset($_SESSION['user_id'])) { 
        throw new Exception('Usuario no autenticado'); 
    } 
    $id_usuario = (int)$_SESSION['user_id']; 
    
    // Obtener y validar datos del formulario 
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0; 
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : ''; 
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : ''; 
    $fecha_cumplimiento = isset($_POST['fecha_cumplimiento']) ? trim($_POST['fecha_cumplimiento']) : null; 

    // Validaciones básicas 
    if ($id_proyecto <= 0) { 
        throw new Exception('ID de proyecto inválido'); 
    } 

    if (empty($nombre)) { 
        throw new Exception('El nombre de la tarea es requerido'); 
    } 

    if (strlen($nombre) > 100) { 
        throw new Exception('El nombre no puede exceder 100 caracteres'); 
    } 

    if (strlen($descripcion) > 250) { 
        throw new Exception('La descripción no puede exceder 250 caracteres'); 
    } 

    // Validar formato de fecha si se proporciona 
    if (!empty($fecha_cumplimiento)) { 
        $fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_cumplimiento); 
        if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_cumplimiento) { 
            throw new Exception('Formato de fecha inválido'); 
        } 
    } 

    $conn = getDBConnection(); 
    if (!$conn) { 
        throw new Exception('Error de conexión a la base de datos'); 
    } 

    // Obtener información del proyecto 
    $query_check = " 
        SELECT  
            p.id_proyecto, 
            p.id_creador, 
            p.puede_editar_otros, 
            p.nombre as proyecto_nombre, 
            p.id_tipo_proyecto, 
            p.id_participante 
        FROM tbl_proyectos p 
        WHERE p.id_proyecto = ? 
    "; 

    $stmt_check = $conn->prepare($query_check); 
    if (!$stmt_check) { 
        throw new Exception('Error preparando consulta de verificación'); 
    } 

    $stmt_check->bind_param("i", $id_proyecto); 
    $stmt_check->execute(); 
    $result_check = $stmt_check->get_result(); 

    if ($result_check->num_rows === 0) { 
        throw new Exception('El proyecto no existe'); 
    } 

    $proyecto = $result_check->fetch_assoc(); 
    $stmt_check->close(); 

    $es_creador = (int)$proyecto['id_creador'] === $id_usuario; 
    $es_participante = (int)$proyecto['id_participante'] === $id_usuario; 
    $es_proyecto_grupal = (int)$proyecto['id_tipo_proyecto'] === 1; 

    // Verificar si es miembro del grupo (para proyectos grupales) 
    $es_miembro_grupo = false; 
    if ($es_proyecto_grupal) { 
        $query_grupo = "SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?"; 
        $stmt_grupo = $conn->prepare($query_grupo); 
        $stmt_grupo->bind_param("ii", $id_proyecto, $id_usuario); 
        $stmt_grupo->execute(); 
        $result_grupo = $stmt_grupo->get_result(); 
        $es_miembro_grupo = $result_grupo->num_rows > 0; 
        $stmt_grupo->close(); 
    } 

    // LÓGICA DE PERMISOS: 
    // 1. El CREADOR siempre puede crear tareas en su proyecto 
    // 2. Para NO-creadores: solo si puede_editar_otros = 1 Y es participante/miembro 

    $puede_crear = false; 

    if ($es_creador) { 
        // El creador SIEMPRE puede crear tareas en su propio proyecto 
        $puede_crear = true; 
    } else { 
        // Para no-creadores, verificar restricción y membresía 
        $tiene_acceso = $es_participante || $es_miembro_grupo; 
        $proyecto_permite_edicion = (int)$proyecto['puede_editar_otros'] === 1; 

        if ($tiene_acceso && $proyecto_permite_edicion) { 
            $puede_crear = true; 
        } 
    } 

    if (!$puede_crear) { 
        if (!$es_creador && !$es_participante && !$es_miembro_grupo) { 
            throw new Exception('No tienes acceso a este proyecto'); 
        } else { 
            throw new Exception('No tienes permiso para crear tareas en este proyecto. Solo el creador puede agregar tareas.'); 
        } 
    } 

    // Preparar fecha de cumplimiento 
    $fecha_para_db = !empty($fecha_cumplimiento) ? $fecha_cumplimiento : '0000-00-00'; 

    // Insertar la tarea 
    // El participante siempre será el usuario actual (restricción de usuario normal) 
    $query_insert = " 
        INSERT INTO tbl_tareas  
            (nombre, descripcion, id_proyecto, id_creador, fecha_cumplimiento, estado, id_participante) 
        VALUES  
            (?, ?, ?, ?, ?, 'pendiente', ?) 
    "; 

    $stmt_insert = $conn->prepare($query_insert); 
    if (!$stmt_insert) { 
        throw new Exception('Error preparando inserción: ' . $conn->error); 
    } 

    // El participante es el mismo usuario que crea la tarea 
    $stmt_insert->bind_param( 
        "ssiisi",
        $nombre, 
        $descripcion, 
        $id_proyecto, 
        $id_usuario, 
        $fecha_para_db, 
        $id_usuario  // La tarea se asigna al usuario que la crea 
    ); 

    if (!$stmt_insert->execute()) { 
        throw new Exception('Error al crear la tarea: ' . $stmt_insert->error); 
    } 

    $id_nueva_tarea = $conn->insert_id; 
    $stmt_insert->close(); 

    // Recalcular progreso del proyecto 
    recalculateProjectProgress($conn, $id_proyecto); 

    // Obtener datos del usuario para la respuesta 
    $query_user = "SELECT nombre, apellido, num_empleado FROM tbl_usuarios WHERE id_usuario = ?"; 
    $stmt_user = $conn->prepare($query_user); 
    $stmt_user->bind_param("i", $id_usuario); 
    $stmt_user->execute(); 
    $result_user = $stmt_user->get_result(); 
    $usuario_data = $result_user->fetch_assoc(); 
    $stmt_user->close(); 

    $participante_display = $usuario_data['nombre'] . ' ' . $usuario_data['apellido'] .  
                           ' (#' . $usuario_data['num_empleado'] . ')'; 
    
    // Respuesta exitosa 
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
            'id_participante' => $id_usuario, 
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
        // Contar total de tareas 
        $stmt = $conn->prepare(" 
            SELECT COUNT(*) as total  
            FROM tbl_tareas  
            WHERE id_proyecto = ? 
        "); 

        $stmt->bind_param("i", $id_proyecto); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 
        $row = $result->fetch_assoc(); 
        $total_tasks = (int)$row['total']; 
        $stmt->close(); 

        if ($total_tasks === 0) { 
            $progress = 0; 
        } else { 

            // Contar tareas completadas 
            $stmt = $conn->prepare(" 
                SELECT COUNT(*) as completadas  
                FROM tbl_tareas  
                WHERE id_proyecto = ? AND estado = 'completado' 
            "); 

            $stmt->bind_param("i", $id_proyecto); 
            $stmt->execute(); 
            $result = $stmt->get_result(); 
            $row = $result->fetch_assoc(); 
            $completed_tasks = (int)$row['completadas']; 
            $stmt->close(); 
            $progress = round(($completed_tasks / $total_tasks) * 100); 
        } 

        //saber el estado del proyecto 
        $nuevo_estado = determineProjectStatus($progress, $id_proyecto, $conn); 

        // Actualizar proyecto 
        $stmt = $conn->prepare(" 
            UPDATE tbl_proyectos  
            SET progreso = ?, estado = ?  
            WHERE id_proyecto = ? 
        "); 

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
        $stmt = $conn->prepare(" 
            SELECT fecha_cumplimiento  
            FROM tbl_proyectos  
            WHERE id_proyecto = ? 
        "); 

        $stmt->bind_param("i", $id_proyecto); 
        $stmt->execute(); 
        $result = $stmt->get_result(); 
        $row = $result->fetch_assoc(); 
        $stmt->close(); 
        $fecha_vencimiento = strtotime($row['fecha_cumplimiento']); 
        $hoy = time(); 

        // Proyecto vencido e incompleto 
        if ($hoy > $fecha_vencimiento && $progress < 100) { 
            return 'vencido'; 
        } 

        // Proyecto completado 
        if ($progress == 100) { 
            return 'completado'; 
        } 

        // Proyecto en progreso 
        if ($progress > 0) { 
            return 'en proceso'; 
        } 
        // Default 
        return 'pendiente'; 

    } catch (Exception $e) { 
        error_log("Error determinando estado: " . $e->getMessage()); 
        return 'pendiente'; 
    } 
} 
?>