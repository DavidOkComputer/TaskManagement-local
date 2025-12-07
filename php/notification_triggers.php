<?php
/*notification_triggers.php para crear notificationes automaticamente*/

require_once(__DIR__ . '/notification_helper.php');

function triggerNotificacionTareaAsignada($conn, $id_tarea, $id_usuario_asignado, $id_usuario_anterior = null) {
    // Si es el mismo usuario, no notificar
    if ($id_usuario_anterior !== null && (int)$id_usuario_anterior === (int)$id_usuario_asignado) {
        return false;
    }
    
    try {
        // Obtener información de la tarea y proyecto
        $stmt = $conn->prepare("
            SELECT t.nombre as nombre_tarea, p.nombre as nombre_proyecto
            FROM tbl_tareas t
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            WHERE t.id_tarea = ?
        ");
        $stmt->bind_param("i", $id_tarea);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $notificationHelper = new NotificationHelper($conn);
        return $notificationHelper->notificarTareaAsignada(
            $id_tarea,
            $id_usuario_asignado,
            $row['nombre_tarea'],
            $row['nombre_proyecto'] ?? 'Sin proyecto'
        );
        
    } catch (Exception $e) {
        error_log("triggerNotificacionTareaAsignada Error: " . $e->getMessage());
        return false;
    }
}

function triggerNotificacionProyectoAsignado($conn, $id_proyecto, $id_usuario_asignado, $id_usuario_anterior = null) {
    // Si es el mismo usuario, no notificar
    if ($id_usuario_anterior !== null && (int)$id_usuario_anterior === (int)$id_usuario_asignado) {
        return false;
    }
    
    try {
        // Obtener nombre del proyecto
        $stmt = $conn->prepare("SELECT nombre FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $notificationHelper = new NotificationHelper($conn);
        return $notificationHelper->notificarProyectoAsignado(
            $id_proyecto,
            $id_usuario_asignado,
            $row['nombre']
        );
        
    } catch (Exception $e) {
        error_log("triggerNotificacionProyectoAsignado Error: " . $e->getMessage());
        return false;
    }
}

function triggerNotificacionProyectoGrupal($conn, $id_proyecto, $id_usuario) {
    try {
        // Obtener nombre del proyecto
        $stmt = $conn->prepare("SELECT nombre FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $row = $result->fetch_assoc();
        $stmt->close();
        
        $notificationHelper = new NotificationHelper($conn);
        return $notificationHelper->notificarProyectoAsignado(
            $id_proyecto,
            $id_usuario,
            $row['nombre']
        );
        
    } catch (Exception $e) {
        error_log("triggerNotificacionProyectoGrupal Error: " . $e->getMessage());
        return false;
    }
}

function triggerNotificacionProyectoVencido($conn, $id_proyecto, $estado_anterior) {
    // Solo notificar si cambió de otro estado a vencido
    if ($estado_anterior === 'vencido') {
        return false;
    }
    
    try {
        // Obtener información del proyecto
        $stmt = $conn->prepare("SELECT nombre, id_creador, id_participante FROM tbl_proyectos WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            return false;
        }
        
        $proyecto = $result->fetch_assoc();
        $stmt->close();
        
        $usuarios_notificar = [];
        
        if ($proyecto['id_creador']) {
            $usuarios_notificar[] = (int)$proyecto['id_creador'];
        }
        
        if ($proyecto['id_participante']) {
            $usuarios_notificar[] = (int)$proyecto['id_participante'];
        }
        
        // Buscar participantes de proyecto grupal
        $stmt = $conn->prepare("SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ?");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $participantes_result = $stmt->get_result();
        while ($participante = $participantes_result->fetch_assoc()) {
            $usuarios_notificar[] = (int)$participante['id_usuario'];
        }
        $stmt->close();
        
        $usuarios_notificar = array_unique($usuarios_notificar);
        
        $notificationHelper = new NotificationHelper($conn);
        $resultados = [];
        
        foreach ($usuarios_notificar as $id_usuario) {
            $resultados[] = $notificationHelper->notificarProyectoVencido(
                $id_proyecto,
                $id_usuario,
                $proyecto['nombre']
            );
        }
        
        return $resultados;
        
    } catch (Exception $e) {
        error_log("triggerNotificacionProyectoVencido Error: " . $e->getMessage());
        return false;
    }
}
?>