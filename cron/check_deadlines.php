<?php
/*check_deadlines.php Script de cron para verificar vencimientos y enviar recordatorios*/

// Permitir ejecución solo desde CLI
if (php_sapi_name() !== "cli") {
    die("Este script solo puede ejecutarse desde la línea de comandos");
}

set_time_limit(600);
ini_set("memory_limit", "256M");

// Determinar la ruta base del proyecto
$base_path = dirname(__DIR__);

// Cargar configuración de base de datos
$config_path = $base_path . "/php/db_config.php";
if (!file_exists($config_path)) {
    $config_path = $base_path . "/config/database.php";
}

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    define("DB_HOST", "localhost");
    define("DB_USER", "root");
    define("DB_PASS", "");
    define("DB_NAME", "task_management_db");
}

// Cargar servicios de email y notificaciones
require_once $base_path . "/email/EmailService.php";
require_once $base_path . "/email/EmailTemplate.php";
require_once $base_path . "/email/NotificationHelper.php";

// Configuración de logging
$log_dir = __DIR__ . "/logs";
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . "/deadlines_" . date("Y-m-d") . ".log";

function logMessage($message, $level = "INFO") {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    $formatted = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $formatted, FILE_APPEND);
    echo $formatted;
}

logMessage("=== Iniciando verificación de vencimientos ===");

try {
    // Conectar a base de datos
    if (function_exists("getDBConnection")) {
        $conn = getDBConnection();
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    
    // Inicializar servicios
    $emailService = new EmailService($conn);
    $templates = new EmailTemplates();
    $notificationHelper = new NotificationHelper($conn);
    $config = $emailService->getConfig(
        "system_url",
      "http://10.109.17.87/projectManagement",);

    // Obtener configuración
    $system_url = "http://10.109.17.87/projectManagement";
    $dias_recordatorio = 3; // Días antes de vencimiento para recordatorio

    $queued = [
        "upcoming" => 0,
        "tomorrow" => 0,
        "overdue" => 0,
        "creators" => 0
    ];

    logMessage("Buscando tareas que vencen en $dias_recordatorio días...");
    
    $stmt = $conn->prepare("
        SELECT  
            t.id_tarea,  
            t.nombre as tarea_nombre,  
            t.fecha_cumplimiento, 
            t.id_creador,
            u.id_usuario,  
            u.nombre,  
            u.apellido,  
            u.e_mail, 
            u.foto_perfil, 
            p.nombre as proyecto_nombre,
            p.id_departamento,
            d.nombre as departamento_nombre,
            COALESCE(
                (SELECT r.nombre FROM tbl_usuario_roles ur 
                 JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                 WHERE ur.id_usuario = u.id_usuario AND ur.es_principal = 1 AND ur.activo = 1 LIMIT 1),
                'Sin rol'
            ) as rol_nombre
        FROM tbl_tareas t 
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 
        WHERE t.estado != 'completado' 
            AND t.fecha_cumplimiento IS NOT NULL 
            AND t.fecha_cumplimiento != '0000-00-00' 
            AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL ? DAY) 
            AND (np.notif_tarea_vencimiento = 1 OR np.id_preferencia IS NULL) 
            AND u.e_mail IS NOT NULL  
            AND u.e_mail != ''
    ");

    $stmt->bind_param("i", $dias_recordatorio);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($task = $result->fetch_assoc()) {
        // Verificar si ya se envió notificación hoy
        $tipo_evento = "tarea_vencimiento_" . $dias_recordatorio;
        if ($notificationHelper->notificacionYaEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"])) {
            continue;
        }

        // Crear notificación dentro de la app
        $titulo = "Tarea próxima a vencer";
        $mensaje = "La tarea '{$task["tarea_nombre"]}' vence en $dias_recordatorio días.";
        if ($task["proyecto_nombre"]) {
            $mensaje .= " (Proyecto: {$task["proyecto_nombre"]})";
        }

        $notificationHelper->crearNotificacion(
            $task["id_usuario"],
            "tarea_vencida", // Usar tipo existente en enum
            $titulo,
            $mensaje,
            $task["id_tarea"],
            "tarea"
        );

        // Generar y encolar email
        $html = $templates->render("tarea_vencimiento", [
            "SUBJECT" => "Recordatorio: Tarea próxima a vencer",
            "NOMBRE_USUARIO" => $task["nombre"],
            "APELLIDO_USUARIO" => $task["apellido"],
            "NOMBRE_TAREA" => $task["tarea_nombre"],
            "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
            "DEPARTAMENTO" => $task["departamento_nombre"] ?? "Sin departamento",
            "ROL" => $task["rol_nombre"] ?? "Sin rol",
            "DIAS_RESTANTES" => $dias_recordatorio,
            "FECHA_VENCIMIENTO" => date("d/m/Y", strtotime($task["fecha_cumplimiento"])),
            "URL_SISTEMA" => $system_url,
            "FOTO_PERFIL" => $task["foto_perfil"]
        ]);

        $emailService->queueEmail(
            $task["e_mail"],
            $task["nombre"] . " " . $task["apellido"],
            "Recordatorio: '{$task["tarea_nombre"]}' vence en $dias_recordatorio días",
            $html,
            "tarea_vencimiento",
            "tarea",
            $task["id_tarea"],
            3
        );

        // Registrar que se envió
        $notificationHelper->registrarNotificacionEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"]);
        $queued["upcoming"]++;
    }
    $stmt->close();
    logMessage("Tareas próximas a vencer: {$queued["upcoming"]} notificaciones procesadas");

    logMessage("Buscando tareas que vencen mañana...");

    $stmt = $conn->prepare("
        SELECT  
            t.id_tarea,  
            t.nombre as tarea_nombre,  
            t.fecha_cumplimiento, 
            t.id_creador,
            u.id_usuario,  
            u.nombre,  
            u.apellido,  
            u.e_mail, 
            u.foto_perfil, 
            p.nombre as proyecto_nombre,
            p.id_departamento,
            d.nombre as departamento_nombre,
            COALESCE(
                (SELECT r.nombre FROM tbl_usuario_roles ur 
                 JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                 WHERE ur.id_usuario = u.id_usuario AND ur.es_principal = 1 AND ur.activo = 1 LIMIT 1),
                'Sin rol'
            ) as rol_nombre
        FROM tbl_tareas t 
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 
        WHERE t.estado != 'completado' 
            AND t.fecha_cumplimiento IS NOT NULL 
            AND t.fecha_cumplimiento != '0000-00-00' 
            AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
            AND (np.notif_tarea_vencimiento = 1 OR np.id_preferencia IS NULL) 
            AND u.e_mail IS NOT NULL  
            AND u.e_mail != ''
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    while ($task = $result->fetch_assoc()) {
        $tipo_evento = "tarea_vencimiento_1";
        if ($notificationHelper->notificacionYaEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"])) {
            continue;
        }

        // Crear notificación dentro de la app
        $titulo = "URGENTE: Tarea vence mañana";
        $mensaje = "La tarea '{$task["tarea_nombre"]}' vence MAÑANA.";
        if ($task["proyecto_nombre"]) {
            $mensaje .= " (Proyecto: {$task["proyecto_nombre"]})";
        }

        $notificationHelper->crearNotificacion(
            $task["id_usuario"],
            "tarea_vencida",
            $titulo,
            $mensaje,
            $task["id_tarea"],
            "tarea"
        );

        // Generar y encolar email
        $html = $templates->render("tarea_vencimiento", [
            "SUBJECT" => "URGENTE: Tarea vence mañana",
            "NOMBRE_USUARIO" => $task["nombre"],
            "APELLIDO_USUARIO" => $task["apellido"],
            "NOMBRE_TAREA" => $task["tarea_nombre"],
            "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
            "DEPARTAMENTO" => $task["departamento_nombre"] ?? "Sin departamento",
            "ROL" => $task["rol_nombre"] ?? "Sin rol",
            "DIAS_RESTANTES" => 1,
            "FECHA_VENCIMIENTO" => date("d/m/Y", strtotime($task["fecha_cumplimiento"])),
            "URL_SISTEMA" => $system_url,
            "FOTO_PERFIL" => $task["foto_perfil"]
        ]);

        $emailService->queueEmail(
            $task["e_mail"],
            $task["nombre"] . " " . $task["apellido"],
            "URGENTE: '{$task["tarea_nombre"]}' vence MAÑANA",
            $html,
            "tarea_vencimiento",
            "tarea",
            $task["id_tarea"],
            1 // Máxima prioridad
        );

        $notificationHelper->registrarNotificacionEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"]);
        $queued["tomorrow"]++;
    }
    $stmt->close();
    logMessage("Tareas que vencen mañana: {$queued["tomorrow"]} notificaciones procesadas");

    logMessage("Buscando tareas vencidas...");
    
    // Enviar recordatorio de vencidas solo los lunes o si acaban de vencer (1 día)
    $dia_semana = date("N"); // 1 = Lunes

    $stmt = $conn->prepare("
        SELECT  
            t.id_tarea,  
            t.nombre as tarea_nombre,  
            t.fecha_cumplimiento, 
            DATEDIFF(CURDATE(), t.fecha_cumplimiento) as dias_vencidos, 
            t.id_creador,
            u.id_usuario,  
            u.nombre,  
            u.apellido,  
            u.e_mail, 
            u.foto_perfil, 
            p.nombre as proyecto_nombre,
            p.id_departamento,
            d.nombre as departamento_nombre,
            COALESCE(
                (SELECT r.nombre FROM tbl_usuario_roles ur 
                 JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                 WHERE ur.id_usuario = u.id_usuario AND ur.es_principal = 1 AND ur.activo = 1 LIMIT 1),
                'Sin rol'
            ) as rol_nombre
        FROM tbl_tareas t 
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 
        WHERE t.estado != 'completado' 
            AND t.fecha_cumplimiento IS NOT NULL 
            AND t.fecha_cumplimiento != '0000-00-00' 
            AND t.fecha_cumplimiento < CURDATE() 
            AND (np.notif_tarea_vencida = 1 OR np.id_preferencia IS NULL) 
            AND u.e_mail IS NOT NULL  
            AND u.e_mail != ''
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    while ($task = $result->fetch_assoc()) {
        // Solo notificar si es lunes o si acaba de vencer (1 día)
        if ($dia_semana != 1 && $task["dias_vencidos"] != 1) {
            continue;
        }

        $tipo_evento = "tarea_vencida_" . date("Y-m-d");
        if ($notificationHelper->notificacionYaEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"])) {
            continue;
        }

        // Crear notificación dentro de la app
        $notificationHelper->notificarTareaVencida(
            $task["id_tarea"],
            $task["id_usuario"],
            $task["tarea_nombre"],
            $task["proyecto_nombre"]
        );

        // Generar y encolar email
        $html = $templates->render("tarea_vencida", [
            "SUBJECT" => "Tarea vencida",
            "NOMBRE_USUARIO" => $task["nombre"],
            "APELLIDO_USUARIO" => $task["apellido"],
            "NOMBRE_TAREA" => $task["tarea_nombre"],
            "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
            "DEPARTAMENTO" => $task["departamento_nombre"] ?? "Sin departamento",
            "ROL" => $task["rol_nombre"] ?? "Sin rol",
            "DIAS_VENCIDOS" => $task["dias_vencidos"],
            "FECHA_VENCIMIENTO" => date("d/m/Y", strtotime($task["fecha_cumplimiento"])),
            "URL_SISTEMA" => $system_url,
            "FOTO_PERFIL" => $task["foto_perfil"]
        ]);

        $emailService->queueEmail(
            $task["e_mail"],
            $task["nombre"] . " " . $task["apellido"],
            "Tarea vencida: '{$task["tarea_nombre"]}' (hace {$task["dias_vencidos"]} días)",
            $html,
            "tarea_vencida",
            "tarea",
            $task["id_tarea"],
            2
        );

        $notificationHelper->registrarNotificacionEnviada($tipo_evento, $task["id_tarea"], $task["id_usuario"]);
        $queued["overdue"]++;
    }
    $stmt->close();
    logMessage("Tareas vencidas (asignados): {$queued["overdue"]} notificaciones procesadas");

    logMessage("Buscando tareas vencidas para notificar a creadores...");

    $stmt = $conn->prepare("
        SELECT  
            t.id_tarea,  
            t.nombre as tarea_nombre,  
            t.fecha_cumplimiento, 
            DATEDIFF(CURDATE(), t.fecha_cumplimiento) as dias_vencidos, 
            creador.id_usuario as creador_id, 
            creador.nombre as creador_nombre,  
            creador.apellido as creador_apellido,  
            creador.e_mail as creador_email, 
            creador.foto_perfil as creador_foto, 
            asignado.nombre as asignado_nombre, 
            asignado.apellido as asignado_apellido, 
            p.nombre as proyecto_nombre, 
            d.nombre as departamento_nombre 
        FROM tbl_tareas t 
        JOIN tbl_usuarios creador ON t.id_creador = creador.id_usuario 
        LEFT JOIN tbl_usuarios asignado ON t.id_participante = asignado.id_usuario 
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
        LEFT JOIN tbl_notificacion_preferencias np ON creador.id_usuario = np.id_usuario 
        WHERE t.estado != 'completado' 
            AND t.fecha_cumplimiento IS NOT NULL 
            AND t.fecha_cumplimiento != '0000-00-00' 
            AND t.fecha_cumplimiento < CURDATE() 
            AND t.id_creador != COALESCE(t.id_participante, 0) 
            AND (np.notif_tarea_vencida = 1 OR np.id_preferencia IS NULL) 
            AND creador.e_mail IS NOT NULL  
            AND creador.e_mail != ''
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    while ($task = $result->fetch_assoc()) {
        if ($dia_semana != 1 && $task["dias_vencidos"] != 1) {
            continue;
        }

        $tipo_evento = "tarea_vencida_creador_" . date("Y-m-d");
        if ($notificationHelper->notificacionYaEnviada($tipo_evento, $task["id_tarea"], $task["creador_id"])) {
            continue;
        }

        $asignado_nombre = $task["asignado_nombre"]
            ? $task["asignado_nombre"] . " " . $task["asignado_apellido"]
            : "Sin asignar";

        // Crear notificación in-app para creador
        $titulo = "Tarea vencida (como creador)";
        $mensaje = "La tarea '{$task["tarea_nombre"]}' que creaste está vencida hace {$task["dias_vencidos"]} días. Asignado a: $asignado_nombre";

        $notificationHelper->crearNotificacion(
            $task["creador_id"],
            "tarea_vencida",
            $titulo,
            $mensaje,
            $task["id_tarea"],
            "tarea"
        );

        // Generar email con template especial para creador
        $html = $templates->render("tarea_vencida", [
            "SUBJECT" => "Tarea vencida (como creador)",
            "NOMBRE_USUARIO" => $task["creador_nombre"],
            "APELLIDO_USUARIO" => $task["creador_apellido"],
            "NOMBRE_TAREA" => $task["tarea_nombre"],
            "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
            "DEPARTAMENTO" => $task["departamento_nombre"] ?? "Sin departamento",
            "ROL" => "Creador",
            "DIAS_VENCIDOS" => $task["dias_vencidos"],
            "FECHA_VENCIMIENTO" => date("d/m/Y", strtotime($task["fecha_cumplimiento"])),
            "URL_SISTEMA" => $system_url,
            "FOTO_PERFIL" => $task["creador_foto"]
        ]);

        $emailService->queueEmail(
            $task["creador_email"],
            $task["creador_nombre"] . " " . $task["creador_apellido"],
            "Tarea que creaste está vencida: '{$task["tarea_nombre"]}'",
            $html,
            "tarea_vencida",
            "tarea",
            $task["id_tarea"],
            4
        );

        $notificationHelper->registrarNotificacionEnviada($tipo_evento, $task["id_tarea"], $task["creador_id"]);
        $queued["creators"]++;
    }
    $stmt->close();
    logMessage("Creadores notificados de tareas vencidas: {$queued["creators"]}");

    logMessage("Buscando proyectos vencidos...");
    $proyectos_vencidos = 0;

    $stmt = $conn->prepare("
        SELECT 
            p.id_proyecto,
            p.nombre as proyecto_nombre,
            p.fecha_cumplimiento,
            DATEDIFF(CURDATE(), p.fecha_cumplimiento) as dias_vencidos,
            p.id_creador,
            p.id_participante,
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.e_mail,
            d.nombre as departamento_nombre
        FROM tbl_proyectos p
        JOIN tbl_usuarios u ON (p.id_participante = u.id_usuario OR p.id_creador = u.id_usuario)
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario
        WHERE p.estado NOT IN ('completado', 'cancelado')
            AND p.fecha_cumplimiento IS NOT NULL
            AND p.fecha_cumplimiento < CURDATE()
            AND (np.notif_tarea_vencida = 1 OR np.id_preferencia IS NULL)
            AND u.e_mail IS NOT NULL
            AND u.e_mail != ''
        GROUP BY p.id_proyecto, u.id_usuario
    ");

    $stmt->execute();
    $result = $stmt->get_result();

    while ($project = $result->fetch_assoc()) {
        if ($dia_semana != 1 && $project["dias_vencidos"] != 1) {
            continue;
        }

        $tipo_evento = "proyecto_vencido_" . date("Y-m-d");
        if ($notificationHelper->notificacionYaEnviada($tipo_evento, $project["id_proyecto"], $project["id_usuario"])) {
            continue;
        }

        $notificationHelper->notificarProyectoVencido(
            $project["id_proyecto"],
            $project["id_usuario"],
            $project["proyecto_nombre"]
        );

        $proyectos_vencidos++;
    }
    $stmt->close();
    logMessage("Proyectos vencidos notificados: $proyectos_vencidos");

    $total = $queued["upcoming"] + $queued["tomorrow"] + $queued["overdue"] + $queued["creators"] + $proyectos_vencidos;

    logMessage("=== Verificación completada ===");
    logMessage("Total de notificaciones procesadas: $total");
    logMessage(" - Próximas a vencer ($dias_recordatorio días): {$queued["upcoming"]}");
    logMessage(" - Vencen mañana: {$queued["tomorrow"]}");
    logMessage(" - Vencidas (asignados): {$queued["overdue"]}");
    logMessage(" - Vencidas (creadores): {$queued["creators"]}");
    logMessage(" - Proyectos vencidos: $proyectos_vencidos");

    $conn->close();

} catch (Exception $e) {
    logMessage("ERROR CRÍTICO: " . $e->getMessage(), "CRITICAL");
    exit(1);
}

exit(0);