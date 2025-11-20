<?php
/*save_task.php - crear tareas con validacion de permisos de asignacion, fecha de inicio, y membresía del proyecto*/

header('Content-Type: application/json');
require_once('db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

try {
    //validar y limpiar inputs
    $nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    $id_proyecto = isset($_POST['id_proyecto']) ? intval($_POST['id_proyecto']) : 0;
    $fecha_vencimiento = isset($_POST['fecha_vencimiento']) ? trim($_POST['fecha_vencimiento']) : '';
    $estado = isset($_POST['estado']) ? trim($_POST['estado']) : 'pendiente';
    $id_participante = isset($_POST['id_participante']) && !empty($_POST['id_participante']) ? intval($_POST['id_participante']) : null;
    $id_creador = isset($_POST['id_creador']) ? intval($_POST['id_creador']) : 0;

    //validaciones
    if (empty($nombre)) {
        throw new Exception('El nombre de la tarea es requerido');
    }
    if (empty($descripcion)) {
        throw new Exception('La descripción es requerida');
    }
    if ($id_proyecto <= 0) {
        throw new Exception('Debe seleccionar un proyecto válido');
    }
    if (strlen($nombre) > 100) {
        throw new Exception('El nombre no puede exceder 100 caracteres');
    }
    if (strlen($descripcion) > 250) {
        throw new Exception('La descripción no puede exceder 250 caracteres');
    }
    
    //validar fecha si se proporciona
    if (!empty($fecha_vencimiento)) {
        if (strtotime($fecha_vencimiento) === false) {
            throw new Exception('La fecha de vencimiento no es válida');
        }
    }

    //validar estado
    $estados_validos = ['pendiente', 'en-progreso', 'en proceso', 'completado'];
    $estado = strtolower($estado);
    if (!in_array($estado, $estados_validos)) {
        throw new Exception('El estado de la tarea no es válido');
    }

    //validar id_participante si se proporciona
    if ($id_participante !== null && $id_participante <= 0) {
        $id_participante = null;
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //verificar que el proyecto existe y obtener sus permisos, tipo y fecha de inicio
    $stmt = $conn->prepare("SELECT id_creador, puede_editar_otros, fecha_inicio, id_tipo_proyecto, id_participante FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('El proyecto especificado no existe');
    }
    
    $projectData = $result->fetch_assoc();
    $stmt->close();

    //VALIDAR FECHA DE VENCIMIENTO CONTRA FECHA DE INICIO DEL PROYECTO
    if (!empty($fecha_vencimiento) && !empty($projectData['fecha_inicio'])) {
        $fecha_vencimiento_time = strtotime($fecha_vencimiento);
        $fecha_inicio_time = strtotime($projectData['fecha_inicio']);
        
        if ($fecha_vencimiento_time < $fecha_inicio_time) {
            throw new Exception('La fecha de vencimiento no puede ser anterior a la fecha de inicio del proyecto');
        }
    }

    //VALIDAR PERMISOS DE ASIGNACION
    //si el proyecto solo puede ser editado por el creador, solo el creador puede asignar tareas
    if ($projectData['puede_editar_otros'] == 0) {
        if ($projectData['id_creador'] != $id_creador) {
            throw new Exception('Solo el creador del proyecto puede asignar tareas a este proyecto');
        }
    }
    //si puede_editar_otros = 1, cualquiera puede asignar tareas

    //verificar que el participante existe si se asigna uno
    if ($id_participante !== null) {
        $stmt = $conn->prepare("SELECT id_usuario FROM tbl_usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $id_participante);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('El usuario especificado no existe');
        }
        $stmt->close();

        // VALIDAR QUE EL USUARIO ASIGNADO PERTENECE AL PROYECTO
        // Si es proyecto grupal (id_tipo_proyecto = 1)
        if ($projectData['id_tipo_proyecto'] == 1) {
            // Verificar que el usuario está en tbl_proyecto_usuarios
            $stmt = $conn->prepare("SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
            $stmt->bind_param("ii", $id_proyecto, $id_participante);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('El usuario especificado no está asignado a este proyecto grupal');
            }
            $stmt->close();
        } 
        // Si es proyecto individual (id_tipo_proyecto = 2)
        elseif ($projectData['id_tipo_proyecto'] == 2) {
            // Verificar que el usuario es el asignado al proyecto
            if ((int)$projectData['id_participante'] !== $id_participante) {
                throw new Exception('El usuario especificado no está asignado a este proyecto individual');
            }
        }
    }

    //crear tarea
    $sql = "INSERT INTO tbl_tareas (
                nombre,
                descripcion,
                id_proyecto,
                fecha_cumplimiento,
                estado,
                id_participante,
                id_creador,
                fecha_creacion
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param(
        "ssiisii",
        $nombre,
        $descripcion,
        $id_proyecto,
        $fecha_vencimiento,
        $estado,
        $id_participante,
        $id_creador
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al crear la tarea: " . $stmt->error);
    }

    $task_id = $stmt->insert_id;
    $stmt->close();

    //recalcular el progreso del proyecto
    recalculateProjectProgress($conn, $id_proyecto);

    echo json_encode([
        'success' => true,
        'message' => 'Tarea creada exitosamente',
        'task_id' => $task_id
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la tarea: ' . $e->getMessage()
    ]);
    error_log('save_task.php Error: ' . $e->getMessage());
}

function recalculateProjectProgress($conn, $id_proyecto) {
    try {
        //obtener el conteo total de tareas
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_tareas WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $total_tasks = (int)$row['total'];
        $stmt->close();

        //si no hay tareas el progreso es 0
        if ($total_tasks === 0) {
            $progress = 0;
        } else {
            //obtener el conteo de tareas completadas
            $stmt = $conn->prepare("SELECT COUNT(*) as completadas FROM tbl_tareas WHERE id_proyecto = ? AND estado = 'completado'");
            $stmt->bind_param("i", $id_proyecto);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $completed_tasks = (int)$row['completadas'];
            $stmt->close();

            //calcular el porcentaje de progreso
            $progress = round(($completed_tasks / $total_tasks) * 100);
        }

        //actualizar progreso y estatus del proyecto basado en el progreso
        $nuevo_estado = determineProjectStatus($progress, $id_proyecto, $conn);

        $stmt = $conn->prepare("UPDATE tbl_proyectos SET progreso = ?, estado = ? WHERE id_proyecto = ?");
        $stmt->bind_param("isi", $progress, $nuevo_estado, $id_proyecto);
        $stmt->execute();
        $stmt->close();

        error_log("Progreso del proyecto $id_proyecto actualizado a: $progress% - Estado: $nuevo_estado");

    } catch (Exception $e) {
        error_log("Error recalculando progreso del proyecto: " . $e->getMessage());
    }
}

//determinar el estado basado en la fecha de entrega
function determineProjectStatus($progress, $id_proyecto, $conn) {
    try {
        //obtener la fecha de cumplimiento del proyecto
        $stmt = $conn->prepare("SELECT fecha_cumplimiento FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        $fecha_cumplimiento = date($row['fecha_cumplimiento'], timestamp:null);
        $hoy = time();

        //si la fecha paso y no se ha completado marcar como vencido
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
        error_log("Error determinando estado del proyecto: " . $e->getMessage());
        return 'pendiente';
    }
}
?>