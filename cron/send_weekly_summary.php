<?php
/*send_weekly_summary.php Script de cron para enviar resumen semanal a usuarios*/

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

// Cargar servicios
require_once $base_path . "/email/EmailService.php";
require_once $base_path . "/email/EmailTemplate.php";

// Configuración de logging
$log_dir = __DIR__ . "/logs";
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . "/weekly_summary_" . date("Y-m-d") . ".log";

function logMessage($message, $level = "INFO") {
    global $log_file;
    $timestamp = date("Y-m-d H:i:s");
    $formatted = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $formatted, FILE_APPEND);
    echo $formatted;
}

function renderActiveProjects($projects) {
    if (empty($projects)) {
        return '<p style="color: #666; font-style: italic;">No tienes proyectos activos en este momento.</p>';
    }

    $html = '<h3 style="margin-top: 20px; color: #333;">Proyectos Activos:</h3>';
    $html .= '<div style="margin: 15px 0;">';

    foreach ($projects as $project) {
        $progreso = (int)($project['progreso'] ?? 0);
        $estado = ucfirst($project['estado'] ?? 'pendiente');
        $fecha = isset($project['fecha_cumplimiento']) && $project['fecha_cumplimiento'] != '0000-00-00'
            ? date('d/m/Y', strtotime($project['fecha_cumplimiento']))
            : 'Sin fecha';
        
        $progressColor = $progreso >= 75 ? '#009B4A' : ($progreso >= 50 ? '#ffc107' : '#dc3545');
        
        $html .= '<div style="background: #f8f9fa; border-left: 4px solid ' . $progressColor . '; padding: 12px; margin-bottom: 10px; border-radius: 0 4px 4px 0;">';
        $html .= '<strong style="color: #333;">' . htmlspecialchars($project['nombre']) . '</strong>';
        $html .= '<div style="margin-top: 5px; font-size: 13px; color: #666;">';
        $html .= '<span>Progreso: ' . $progreso . '%</span>';
        $html .= ' | <span>Estado: ' . $estado . '</span>';
        $html .= ' | <span>Vence: ' . $fecha . '</span>';
        if (!empty($project['departamento_nombre'])) {
            $html .= '<br><small>Departamento: ' . htmlspecialchars($project['departamento_nombre']) . '</small>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

function renderActiveObjectives($objectives) {
    if (empty($objectives)) {
        return '<p style="color: #666; font-style: italic;">No hay objetivos activos en tu departamento.</p>';
    }

    $html = '<h3 style="margin-top: 20px; color: #333;">Objetivos del Departamento:</h3>';
    $html .= '<div style="margin: 15px 0;">';

    foreach ($objectives as $objective) {
        $estado = ucfirst($objective['estado'] ?? 'pendiente');
        $fecha = isset($objective['fecha_cumplimiento']) && $objective['fecha_cumplimiento'] != '0000-00-00'
            ? date('d/m/Y', strtotime($objective['fecha_cumplimiento']))
            : 'Sin fecha';
        
        $estadoColor = $estado == 'Completado' ? '#009B4A' : ($estado == 'En proceso' ? '#ffc107' : '#6c757d');
        
        $html .= '<div style="background: #f8f9fa; border-left: 4px solid ' . $estadoColor . '; padding: 12px; margin-bottom: 10px; border-radius: 0 4px 4px 0;">';
        $html .= '<strong style="color: #333;">' . htmlspecialchars($objective['nombre']) . '</strong>';
        $html .= '<div style="margin-top: 5px; font-size: 13px; color: #666;">';
        $html .= '<span>Estado: ' . $estado . '</span>';
        $html .= ' | <span>Fecha límite: ' . $fecha . '</span>';
        if (!empty($objective['departamento_nombre'])) {
            $html .= '<br><small>Departamento: ' . htmlspecialchars($objective['departamento_nombre']) . '</small>';
        }
        $html .= '</div>';
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
}

logMessage("=== Iniciando envío de resumen semanal ===");

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
    
    $emailService = new EmailService($conn);
    $templates = new EmailTemplates();

    $system_url = "http://10.109.17.87/taskManagement";
    $queued_count = 0;

    // Obtener usuarios que tienen habilitado el resumen semanal
    // Usa el rol/departamento principal (es_principal = 1)
    $users_stmt = $conn->prepare("
        SELECT  
            u.id_usuario,  
            u.nombre,  
            u.apellido,  
            u.e_mail,  
            u.foto_perfil, 
            ur.id_departamento, 
            ur.id_rol, 
            d.nombre as departamento_nombre, 
            r.nombre as rol_nombre, 
            np.hora_preferida 
        FROM tbl_usuarios u 
        LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
            AND ur.es_principal = 1  
            AND ur.activo = 1 
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 
        LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        WHERE (np.notif_resumen_semanal = 1 OR np.id_preferencia IS NULL) 
            AND u.e_mail IS NOT NULL  
            AND u.e_mail != ''
    ");

    $users_stmt->execute();
    $users_result = $users_stmt->get_result();

    logMessage("Usuarios para enviar resumen: " . $users_result->num_rows);

    while ($user = $users_result->fetch_assoc()) {
        logMessage("Procesando usuario: {$user["nombre"]} {$user["apellido"]} ({$user["e_mail"]})");

        // Obtener estadísticas del usuario
        $stats_stmt = $conn->prepare("
            SELECT  
                SUM(CASE WHEN estado = 'completado' AND fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as completadas, 
                SUM(CASE WHEN estado != 'completado' AND ( 
                    fecha_cumplimiento IS NULL OR  
                    fecha_cumplimiento = '0000-00-00' OR  
                    fecha_cumplimiento >= CURDATE() 
                ) THEN 1 ELSE 0 END) as pendientes, 
                SUM(CASE WHEN estado != 'completado' AND  
                    fecha_cumplimiento IS NOT NULL AND  
                    fecha_cumplimiento != '0000-00-00' AND  
                    fecha_cumplimiento < CURDATE() THEN 1 ELSE 0 END) as vencidas 
            FROM tbl_tareas 
            WHERE id_participante = ? OR id_creador = ?
        ");

        $stats_stmt->bind_param("ii", $user["id_usuario"], $user["id_usuario"]);
        $stats_stmt->execute();
        $stats = $stats_stmt->get_result()->fetch_assoc();
        $stats_stmt->close();

        // Obtener tareas próximas a vencer (próximos 7 días)
        $upcoming_stmt = $conn->prepare("
            SELECT  
                t.nombre,  
                t.fecha_cumplimiento,  
                p.nombre as proyecto_nombre, 
                d.nombre as departamento_nombre 
            FROM tbl_tareas t 
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
            WHERE (t.id_participante = ? OR t.id_creador = ?) 
                AND t.estado != 'completado' 
                AND t.fecha_cumplimiento IS NOT NULL 
                AND t.fecha_cumplimiento != '0000-00-00' 
                AND t.fecha_cumplimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
            ORDER BY t.fecha_cumplimiento ASC 
            LIMIT 5
        ");

        $upcoming_stmt->bind_param("ii", $user["id_usuario"], $user["id_usuario"]);
        $upcoming_stmt->execute();
        $upcoming_result = $upcoming_stmt->get_result();
        $upcoming_tasks = [];

        while ($task = $upcoming_result->fetch_assoc()) {
            $upcoming_tasks[] = $task;
        }
        $upcoming_stmt->close();

        // Obtener proyectos activos del usuario
        $projects_stmt = $conn->prepare("
            SELECT DISTINCT  
                p.id_proyecto,  
                p.nombre,  
                p.progreso,  
                p.estado,  
                p.fecha_cumplimiento, 
                d.nombre as departamento_nombre, 
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') as tareas_completadas, 
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as total_tareas 
            FROM tbl_proyectos p 
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto 
            WHERE (p.id_participante = ? OR p.id_creador = ? OR pu.id_usuario = ?) 
                AND p.estado NOT IN ('completado', 'cancelado') 
            ORDER BY p.fecha_cumplimiento ASC 
            LIMIT 5
        ");

        $projects_stmt->bind_param("iii", $user["id_usuario"], $user["id_usuario"], $user["id_usuario"]);
        $projects_stmt->execute();
        $projects_result = $projects_stmt->get_result();
        $active_projects = [];

        while ($project = $projects_result->fetch_assoc()) {
            $active_projects[] = $project;
        }
        $projects_stmt->close();

        // Obtener objetivos activos del departamento del usuario
        $objectives_stmt = $conn->prepare("
            SELECT  
                o.id_objetivo,  
                o.nombre,  
                o.estado,  
                o.fecha_cumplimiento, 
                d.nombre as departamento_nombre 
            FROM tbl_objetivos o 
            LEFT JOIN tbl_departamentos d ON o.id_departamento = d.id_departamento 
            WHERE o.id_departamento IN ( 
                SELECT ur.id_departamento  
                FROM tbl_usuario_roles ur  
                WHERE ur.id_usuario = ? AND ur.activo = 1 
            ) 
            AND o.estado NOT IN ('completado') 
            ORDER BY o.fecha_cumplimiento ASC 
            LIMIT 5
        ");

        $objectives_stmt->bind_param("i", $user["id_usuario"]);
        $objectives_stmt->execute();
        $objectives_result = $objectives_stmt->get_result();
        $active_objectives = [];

        while ($objective = $objectives_result->fetch_assoc()) {
            $active_objectives[] = $objective;
        }
        $objectives_stmt->close();

        // Generar secciones del email usando funciones locales
        $upcoming_html = $templates->renderUpcomingTasks($upcoming_tasks);
        $projects_html = renderActiveProjects($active_projects);
        $objectives_html = renderActiveObjectives($active_objectives);

        // Renderizar email completo
        $html = $templates->render("resumen_semanal", [
            "SUBJECT" => "Tu resumen semanal",
            "NOMBRE_USUARIO" => $user["nombre"],
            "APELLIDO_USUARIO" => $user["apellido"],
            "DEPARTAMENTO" => $user["departamento_nombre"] ?? "Sin departamento",
            "ROL" => $user["rol_nombre"] ?? "Sin rol",
            "TAREAS_COMPLETADAS" => (int)($stats["completadas"] ?? 0),
            "TAREAS_PENDIENTES" => (int)($stats["pendientes"] ?? 0),
            "TAREAS_VENCIDAS" => (int)($stats["vencidas"] ?? 0),
            "SECCION_PROXIMAS_TAREAS" => $upcoming_html,
            "SECCION_PROYECTOS_ACTIVOS" => $projects_html,
            "SECCION_OBJETIVOS_ACTIVOS" => $objectives_html,
            "URL_SISTEMA" => $system_url,
            "FOTO_PERFIL" => $user["foto_perfil"],
            "FECHA_RESUMEN" => date("d/m/Y")
        ]);

        // Agregar a la cola de emails
        $result = $emailService->queueEmail(
            $user["e_mail"],
            $user["nombre"] . " " . $user["apellido"],
            "Tu resumen semanal - Sistema de Tareas",
            $html,
            "resumen_semanal",
            "usuario",
            $user["id_usuario"],
            8 // Prioridad baja
        );

        if ($result) {
            $queued_count++;
            logMessage("Email encolado correctamente para {$user["e_mail"]}");
        } else {
            logMessage("Error al encolar email para {$user["e_mail"]}: " . $emailService->getLastError(), "ERROR");
        }
    }

    $users_stmt->close();

    logMessage("=== Resumen semanal completado ===");
    logMessage("Total de emails en cola: $queued_count");

    $conn->close();

} catch (Exception $e) {
    logMessage("ERROR CRÍTICO: " . $e->getMessage(), "CRITICAL");
    exit(1);
}

exit(0);