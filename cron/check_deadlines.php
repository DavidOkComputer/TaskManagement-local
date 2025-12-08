<?php
/**
 * check_deadlines.php script de cron para verificar vencimientos y enviar recordatorios
 * 
 * Ejecutar diariamente a las 8:00 AM:
 * 0 8 * * * /usr/bin/php /ruta/a/cron/check_deadlines.php >> /var/log/deadlines.log 2>&1
 * 
 * @package TaskManagement\Email\Cron
 */

// Permitir ejecución solo desde CLI
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde la línea de comandos');
}

set_time_limit(600);
ini_set('memory_limit', '256M');

// Cargar configuración
$config_path = __DIR__ . '/../php/db_config.php';
if (!file_exists($config_path)) {
    $config_path = __DIR__ . '/../config/database.php';
}

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'task_management_db');
}

require_once __DIR__ . '/../email/EmailService.php';
require_once __DIR__ . '/../email/EmailTemplates.php';

// Configuración de logging
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/deadlines_' . date('Y-m-d') . '.log';

function logMessage($message, $level = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $formatted, FILE_APPEND);
    echo $formatted;
}

logMessage("=== Iniciando verificación de vencimientos ===");

try {
    // Conectar a base de datos
    if (function_exists('getDBConnection')) {
        $conn = getDBConnection();
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    
    $emailService = new EmailService($conn);
    $templates = new EmailTemplates();
    $config = $emailService->getConfig();
    
    if (!$config->isEnabled()) {
        logMessage("El servicio de email está deshabilitado", 'WARNING');
        exit(0);
    }
    
    $system_url = $config->get('system_url', 'http://localhost/taskManagement');
    $dias_recordatorio = (int) $config->get('dias_recordatorio_antes', 3);
    
    $queued = [
        'upcoming' => 0,
        'tomorrow' => 0,
        'overdue' => 0
    ];
    
    logMessage("Buscando tareas que vencen en $dias_recordatorio días...");
    
    $stmt = $conn->prepare("
        SELECT 
            t.id_tarea,
            t.nombre as tarea_nombre,
            t.fecha_cumplimiento,
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.e_mail,
            p.nombre as proyecto_nombre
        FROM tbl_tareas t
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario
        WHERE t.estado != 'completado'
        AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL ? DAY)
        AND (np.notif_tarea_vencimiento = 1 OR np.id_preferencia IS NULL)
        AND u.e_mail IS NOT NULL 
        AND u.e_mail != ''
    ");
    
    $stmt->bind_param("i", $dias_recordatorio);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($task = $result->fetch_assoc()) {
        $html = $templates->render('tarea_vencimiento', [
            'SUBJECT' => 'Recordatorio: Tarea próxima a vencer',
            'NOMBRE_USUARIO' => $task['nombre'],
            'NOMBRE_TAREA' => $task['tarea_nombre'],
            'NOMBRE_PROYECTO' => $task['proyecto_nombre'] ?? 'Sin proyecto',
            'DIAS_RESTANTES' => $dias_recordatorio,
            'FECHA_VENCIMIENTO' => date('d/m/Y', strtotime($task['fecha_cumplimiento'])),
            'URL_SISTEMA' => $system_url
        ]);
        
        $emailService->queueEmail(
            $task['e_mail'],
            $task['nombre'] . ' ' . $task['apellido'],
            "Recordatorio: '{$task['tarea_nombre']}' vence en $dias_recordatorio días",
            $html,
            'tarea_vencimiento',
            'tarea',
            $task['id_tarea'],
            3
        );
        $queued['upcoming']++;
    }
    $stmt->close();
    
    logMessage("Tareas próximas a vencer: {$queued['upcoming']} notificaciones en cola");
    
    logMessage("Buscando tareas que vencen mañana...");
    
    $stmt = $conn->prepare("
        SELECT 
            t.id_tarea,
            t.nombre as tarea_nombre,
            t.fecha_cumplimiento,
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.e_mail,
            p.nombre as proyecto_nombre
        FROM tbl_tareas t
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario
        WHERE t.estado != 'completado'
        AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        AND (np.notif_tarea_vencimiento = 1 OR np.id_preferencia IS NULL)
        AND u.e_mail IS NOT NULL 
        AND u.e_mail != ''
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($task = $result->fetch_assoc()) {
        $html = $templates->render('tarea_vencimiento', [
            'SUBJECT' => 'URGENTE: Tarea vence mañana',
            'NOMBRE_USUARIO' => $task['nombre'],
            'NOMBRE_TAREA' => $task['tarea_nombre'],
            'NOMBRE_PROYECTO' => $task['proyecto_nombre'] ?? 'Sin proyecto',
            'DIAS_RESTANTES' => 1,
            'FECHA_VENCIMIENTO' => date('d/m/Y', strtotime($task['fecha_cumplimiento'])),
            'URL_SISTEMA' => $system_url
        ]);
        
        $emailService->queueEmail(
            $task['e_mail'],
            $task['nombre'] . ' ' . $task['apellido'],
            "URGENTE: '{$task['tarea_nombre']}' vence MAÑANA",
            $html,
            'tarea_vencimiento',
            'tarea',
            $task['id_tarea'],
            1 // Máxima prioridad
        );
        $queued['tomorrow']++;
    }
    $stmt->close();
    
    logMessage("Tareas que vencen mañana: {$queued['tomorrow']} notificaciones en cola");
    
    logMessage("Buscando tareas vencidas...");
    
    // Enviar recordatorio de vencidas solo los lunes o si acaban de vencer (1 día)
    $dia_semana = date('N'); // 1 = Lunes
    
    $stmt = $conn->prepare("
        SELECT 
            t.id_tarea,
            t.nombre as tarea_nombre,
            t.fecha_cumplimiento,
            DATEDIFF(CURDATE(), t.fecha_cumplimiento) as dias_vencidos,
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.e_mail,
            p.nombre as proyecto_nombre
        FROM tbl_tareas t
        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario
        WHERE t.estado != 'completado'
        AND t.fecha_cumplimiento < CURDATE()
        AND t.fecha_cumplimiento != '0000-00-00'
        AND (np.notif_tarea_vencida = 1 OR np.id_preferencia IS NULL)
        AND u.e_mail IS NOT NULL 
        AND u.e_mail != ''
    ");
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($task = $result->fetch_assoc()) {
        // Solo notificar si es lunes o si acaba de vencer (1 día)
        if ($dia_semana == 1 || $task['dias_vencidos'] == 1) {
            $html = $templates->render('tarea_vencida', [
                'SUBJECT' => 'Tarea vencida',
                'NOMBRE_USUARIO' => $task['nombre'],
                'NOMBRE_TAREA' => $task['tarea_nombre'],
                'NOMBRE_PROYECTO' => $task['proyecto_nombre'] ?? 'Sin proyecto',
                'DIAS_VENCIDOS' => $task['dias_vencidos'],
                'FECHA_VENCIMIENTO' => date('d/m/Y', strtotime($task['fecha_cumplimiento'])),
                'URL_SISTEMA' => $system_url
            ]);
            
            $emailService->queueEmail(
                $task['e_mail'],
                $task['nombre'] . ' ' . $task['apellido'],
                "🚨 Tarea vencida: '{$task['tarea_nombre']}' (hace {$task['dias_vencidos']} días)",
                $html,
                'tarea_vencida',
                'tarea',
                $task['id_tarea'],
                2
            );
            $queued['overdue']++;
        }
    }
    $stmt->close();
    
    logMessage("Tareas vencidas: {$queued['overdue']} notificaciones en cola");
    
    $total = $queued['upcoming'] + $queued['tomorrow'] + $queued['overdue'];
    logMessage("=== Verificación completada ===");
    logMessage("Total de notificaciones en cola: $total");
    logMessage("  - Próximas a vencer ($dias_recordatorio días): {$queued['upcoming']}");
    logMessage("  - Vencen mañana: {$queued['tomorrow']}");
    logMessage("  - Vencidas: {$queued['overdue']}");
    
    $conn->close();
    
} catch (Exception $e) {
    logMessage("ERROR CRÍTICO: " . $e->getMessage(), 'CRITICAL');
    exit(1);
}

exit(0);