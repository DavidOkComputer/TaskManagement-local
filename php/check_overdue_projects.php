<?php
/*check_overdue_projects.php script para verificar proyectos vencidos y generar notificaciones*/
 
// Para ejecución desde línea de comandos, establecer directorio de trabajo
if (php_sapi_name() === 'cli') {
    chdir(__DIR__);
}
 
require_once(__DIR__ . '/db_config.php');
require_once(__DIR__ . '/notification_helper.php');
 
// Log de inicio
error_log("=== Iniciando verificación de proyectos vencidos: " . date('Y-m-d H:i:s') . " ===");
 
try {
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    
    $notificaciones_creadas = 0;
    $proyectos_procesados = 0;
    
    // Buscar proyectos que acaban de vencer (estado era diferente a 'vencido' pero fecha ya pasó)
    // También incluir proyectos que ya están marcados como vencido para notificar a participantes
    $query = "
        SELECT
            p.id_proyecto,
            p.nombre,
            p.estado,
            p.fecha_cumplimiento,
            p.id_participante,
            p.id_creador
        FROM tbl_proyectos p
        WHERE p.fecha_cumplimiento < CURDATE()
            AND p.estado != 'completado'
            AND p.progreso < 100
    ";
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($proyecto = $result->fetch_assoc()) {
            $proyectos_procesados++;
            
            // Lista de usuarios a notificar
            $usuarios_notificar = [];
            
            // Agregar creador
            if ($proyecto['id_creador']) {
                $usuarios_notificar[] = (int)$proyecto['id_creador'];
            }
            
            // Agregar participante individual
            if ($proyecto['id_participante']) {
                $usuarios_notificar[] = (int)$proyecto['id_participante'];
            }
            
            // Buscar participantes de proyecto grupal
            $stmt = $conn->prepare("SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ?");
            $stmt->bind_param("i", $proyecto['id_proyecto']);
            $stmt->execute();
            $participantes_result = $stmt->get_result();
            
            while ($participante = $participantes_result->fetch_assoc()) {
                $usuarios_notificar[] = (int)$participante['id_usuario'];
            }
            $stmt->close();
            
            // Eliminar duplicados
            $usuarios_notificar = array_unique($usuarios_notificar);
            
            // Crear notificación para cada usuario
            foreach ($usuarios_notificar as $id_usuario) {
                $result_notif = $notificationHelper->notificarProyectoVencido(
                    $proyecto['id_proyecto'],
                    $id_usuario,
                    $proyecto['nombre']
                );
                
                if ($result_notif['success']) {
                    $notificaciones_creadas++;
                    error_log("Notificación de proyecto vencido creada - Proyecto: {$proyecto['nombre']}, Usuario: {$id_usuario}");
                }
            }
            
            // Actualizar estado del proyecto a 'vencido' si no lo está
            if ($proyecto['estado'] !== 'vencido') {
                $update_stmt = $conn->prepare("UPDATE tbl_proyectos SET estado = 'vencido' WHERE id_proyecto = ?");
                $update_stmt->bind_param("i", $proyecto['id_proyecto']);
                $update_stmt->execute();
                $update_stmt->close();
                
                error_log("Proyecto actualizado a vencido: {$proyecto['nombre']}");
            }
        }
    }
    
    // También verificar tareas vencidas
    $query_tareas = "
        SELECT
            t.id_tarea,
            t.nombre,
            t.estado,
            t.fecha_cumplimiento,
            t.id_participante,
            t.id_creador,
            p.nombre as nombre_proyecto
        FROM tbl_tareas t
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE t.fecha_cumplimiento < CURDATE()
            AND t.fecha_cumplimiento != '0000-00-00'
            AND t.estado != 'completado'
    ";
    
    $result_tareas = $conn->query($query_tareas);
    
    if ($result_tareas) {
        while ($tarea = $result_tareas->fetch_assoc()) {
            $usuarios_notificar = [];
            
            if ($tarea['id_creador']) {
                $usuarios_notificar[] = (int)$tarea['id_creador'];
            }
            
            if ($tarea['id_participante']) {
                $usuarios_notificar[] = (int)$tarea['id_participante'];
            }
            
            $usuarios_notificar = array_unique($usuarios_notificar);
            
            foreach ($usuarios_notificar as $id_usuario) {
                // Verificar si ya se envió
                if (!$notificationHelper->notificacionYaEnviada('tarea_vencida', $tarea['id_tarea'], $id_usuario)) {
                    $titulo = "Tarea vencida";
                    $mensaje = "La tarea '{$tarea['nombre']}' ha superado su fecha de entrega.";
                    
                    $result_notif = $notificationHelper->crearNotificacion(
                        $id_usuario,
                        NotificationHelper::TIPO_TAREA_VENCIDA,
                        $titulo,
                        $mensaje,
                        $tarea['id_tarea'],
                        NotificationHelper::REF_TAREA
                    );
                    
                    if ($result_notif['success']) {
                        $notificationHelper->registrarNotificacionEnviada('tarea_vencida', $tarea['id_tarea'], $id_usuario);
                        $notificaciones_creadas++;
                        error_log("Notificación de tarea vencida creada - Tarea: {$tarea['nombre']}, Usuario: {$id_usuario}");
                    }
                }
            }
            
            // Actualizar estado de la tarea a 'vencido'
            if ($tarea['estado'] !== 'vencido') {
                $update_stmt = $conn->prepare("UPDATE tbl_tareas SET estado = 'vencido' WHERE id_tarea = ?");
                $update_stmt->bind_param("i", $tarea['id_tarea']);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
    }
    
    $conn->close();
    
    error_log("=== Verificación completada: {$proyectos_procesados} proyectos procesados, {$notificaciones_creadas} notificaciones creadas ===");
    
    // Si se ejecuta desde CLI, mostrar resultado
    if (php_sapi_name() === 'cli') {
        echo "Verificación completada: {$proyectos_procesados} proyectos procesados, {$notificaciones_creadas} notificaciones creadas\n";
    }
    
} catch (Exception $e) {
    error_log("check_overdue_projects.php Error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>