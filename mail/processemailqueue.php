<?php
/**
 * process_email_queue.php
 * Script de cron para procesar la cola de emails
 * 
 * Ejecutar cada 5 minutos:
 * / 5 * * * * /usr/bin/php /ruta/a/cron/process_email_queue.php >> /var/log/email_queue.log 2>&1
 * 
 * @package TaskManagement\Email\Cron
 */

// Permitir ejecución solo desde CLI
if (php_sapi_name() !== 'cli') {
    die('Este script solo puede ejecutarse desde la línea de comandos');
}

// Configuración de tiempo y memoria
set_time_limit(300); // 5 minutos máximo
ini_set('memory_limit', '256M');

// Cargar configuración de base de datos
$config_path = __DIR__ . '/../php/db_config.php';
if (!file_exists($config_path)) {
    // Intentar ruta alternativa
    $config_path = __DIR__ . '/../config/database.php';
}

if (file_exists($config_path)) {
    require_once $config_path;
} else {
    // Configuración manual si no existe el archivo
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'task_management_db');
}

require_once __DIR__ . '/../includes/email/EmailService.php';

// Configuración de logging
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/email_queue_' . date('Y-m-d') . '.log';

/**
 * Registrar mensaje en log
 */
function logMessage($message, $level = 'INFO') {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $formatted = "[$timestamp] [$level] $message\n";
    file_put_contents($log_file, $formatted, FILE_APPEND);
    
    // También mostrar en consola
    echo $formatted;
}

// Inicio del proceso
$start_time = microtime(true);
logMessage("=== Iniciando procesamiento de cola de emails ===");

try {
    // Conectar a la base de datos
    if (function_exists('getDBConnection')) {
        $conn = getDBConnection();
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    }
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión a base de datos: " . $conn->connect_error);
    }
    
    $conn->set_charset('utf8mb4');
    logMessage("Conexión a base de datos establecida");
    
    // Crear servicio de email
    $emailService = new EmailService($conn);
    
    // Verificar si el servicio está habilitado
    $config = $emailService->getConfig();
    if (!$config->isEnabled()) {
        logMessage("El servicio de email está deshabilitado. Saliendo.", 'WARNING');
        exit(0);
    }
    
    // Obtener estadísticas antes de procesar
    $stats_before = $emailService->getQueueStats();
    logMessage("Emails pendientes antes de procesar: " . $stats_before['pendientes']);
    
    // Procesar cola
    $results = $emailService->processQueue();
    
    // Calcular tiempo transcurrido
    $elapsed = round(microtime(true) - $start_time, 2);
    
    // Registrar resultados
    logMessage("Procesamiento completado en {$elapsed} segundos");
    logMessage("Procesados: {$results['processed']}, Enviados: {$results['sent']}, Fallidos: {$results['failed']}");
    
    // Registrar errores si los hay
    if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
            logMessage("ERROR [ID:{$error['id']}] [{$error['email']}]: {$error['error']}", 'ERROR');
        }
    }
    
    // Limpiar emails antiguos (opcional - emails enviados hace más de 30 días)
    $cleanup_result = $conn->query(
        "DELETE FROM tbl_email_queue 
         WHERE estado = 'enviado' 
         AND enviado_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    
    if ($cleanup_result && $conn->affected_rows > 0) {
        logMessage("Limpieza: {$conn->affected_rows} emails antiguos eliminados");
    }
    
    // Estadísticas finales
    $stats_after = $emailService->getQueueStats();
    logMessage("Emails pendientes después de procesar: " . $stats_after['pendientes']);
    
    $conn->close();
    logMessage("=== Proceso completado exitosamente ===\n");
    
} catch (Exception $e) {
    logMessage("ERROR CRÍTICO: " . $e->getMessage(), 'CRITICAL');
    exit(1);
}

exit(0);