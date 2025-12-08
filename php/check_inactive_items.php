<?php
/**
* check_inactive_items.php
* Script para verificar proyectos y tareas inactivos (pendientes sin cambios)
*
* CONFIGURACIÓN WINDOWS TASK SCHEDULER:
* - Programa: C:\xampp\php\php.exe (ajustar según tu instalación)
* - Argumentos: -f "C:\ruta\completa\a\check_inactive_items.php"
* - Directorio inicial: C:\ruta\completa\a\
* - Ejecutar: Semanalmente los Lunes a las 9:00 AM
* - Usuario: SYSTEM o tu usuario con privilegios
*
* Alternativamente, usar el archivo batch: check_inactive_items.bat
*/
 
// Para ejecución desde línea de comandos, establecer directorio de trabajo
if (php_sapi_name() === 'cli') {
    chdir(__DIR__);
}
 
require_once(__DIR__ . '/db_config.php');
require_once(__DIR__ . '/notification_helper.php');
 
// Configuración
$DIAS_INACTIVIDAD = 7; // Días sin actividad para generar notificación
 
// Log de inicio
error_log("=== Iniciando verificación de items inactivos: " . date('Y-m-d H:i:s') . " ===");
 
try {
    $conn = getDBConnection();
    $notificationHelper = new NotificationHelper($conn);
    
    $notificaciones_creadas = 0;
    
    // Proyectos pendientes o en proceso que no han tenido cambios en X días
    $query_proyectos = "
        SELECT
            p.id_proyecto,
            p.nombre,
            p.estado,
            p.fecha_inicio,
            p.id_participante,
            p.id_creador,
            DATEDIFF(CURDATE(), p.fecha_inicio) as dias_sin_actividad
        FROM tbl_proyectos p
        WHERE p.estado IN ('pendiente', 'en proceso')
            AND p.progreso < 100
            AND DATEDIFF(CURDATE(), p.fecha_inicio) >= ?
    ";
    
    $stmt = $conn->prepare($query_proyectos);
    $stmt->bind_param("i", $DIAS_INACTIVIDAD);
    $stmt->execute();
    $result_proyectos = $stmt->get_result();
    
    while ($proyecto = $result_proyectos->fetch_assoc()) {
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
        $stmt_part = $conn->prepare("SELECT id_usuario FROM tbl_proyecto_usuarios WHERE id_proyecto = ?");
        $stmt_part->bind_param("i", $proyecto['id_proyecto']);
        $stmt_part->execute();
        $participantes_result = $stmt_part->get_result();
        
        while ($participante = $participantes_result->fetch_assoc()) {
            $usuarios_notificar[] = (int)$participante['id_usuario'];
        }
        $stmt_part->close();
        
        $usuarios_notificar = array_unique($usuarios_notificar);
        
        foreach ($usuarios_notificar as $id_usuario) {
            $result_notif = $notificationHelper->notificarInactividadProyecto(
                $proyecto['id_proyecto'],
                $id_usuario,
                $proyecto['nombre'],
                $proyecto['dias_sin_actividad']
            );
            
            if ($result_notif['success']) {
                $notificaciones_creadas++;
                error_log("Notificación de inactividad proyecto creada - Proyecto: {$proyecto['nombre']}, Usuario: {$id_usuario}, Días: {$proyecto['dias_sin_actividad']}");
            }
        }
    }
    $stmt->close();
    
    // Tareas pendientes que no han tenido cambios en X días
    $query_tareas = "
        SELECT
            t.id_tarea,
            t.nombre,
            t.estado,
            t.fecha_inicio,
            t.id_participante,
            t.id_creador,
            p.nombre as nombre_proyecto,
            DATEDIFF(CURDATE(), t.fecha_inicio) as dias_sin_actividad
        FROM tbl_tareas t
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        WHERE t.estado = 'pendiente'
            AND DATEDIFF(CURDATE(), t.fecha_inicio) >= ?
    ";
    
    $stmt = $conn->prepare($query_tareas);
    $stmt->bind_param("i", $DIAS_INACTIVIDAD);
    $stmt->execute();
    $result_tareas = $stmt->get_result();
    
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
            $result_notif = $notificationHelper->notificarInactividadTarea(
                $tarea['id_tarea'],
                $id_usuario,
                $tarea['nombre'],
                $tarea['dias_sin_actividad']
            );
            
            if ($result_notif['success']) {
                $notificaciones_creadas++;
                error_log("Notificación de inactividad tarea creada - Tarea: {$tarea['nombre']}, Usuario: {$id_usuario}, Días: {$tarea['dias_sin_actividad']}");
            }
        }
    }
    $stmt->close();
    
    $conn->close();
    
    error_log("=== Verificación de inactividad completada: {$notificaciones_creadas} notificaciones creadas ===");
    
    // Si se ejecuta desde CLI, mostrar resultado
    if (php_sapi_name() === 'cli') {
        echo "Verificación completada: {$notificaciones_creadas} notificaciones de inactividad creadas\n";
    }
    
} catch (Exception $e) {
    error_log("check_inactive_items.php Error: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
?>