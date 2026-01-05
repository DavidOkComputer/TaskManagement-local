<?php
/*process_email_queue.php - Procesa la cola de emails pendientes*/

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Directorio base
define('BASE_PATH', dirname(__DIR__));
define('LOG_PATH', __DIR__ . '/logs');

// Crear directorio de logs si no existe
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Archivo de log
$logFile = LOG_PATH . '/email_queue_' . date('Y-m-d') . '.log';

function writeLog($message, $logFile) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

function exitWithLog($message, $logFile, $code = 0) {
    writeLog($message, $logFile);
    exit($code);
}

writeLog("=== Iniciando procesamiento de cola de emails ===", $logFile);

// Verificar lock para evitar ejecuciones simultáneas
$lockFile = LOG_PATH . '/queue_processor.lock';
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    // Si el lock tiene más de 10 minutos, eliminarlo (proceso anterior probablemente falló)
    if (time() - $lockTime > 600) {
        unlink($lockFile);
        writeLog("Lock antiguo eliminado", $logFile);
    } else {
        exitWithLog("Proceso ya en ejecución. Saliendo.", $logFile);
    }
}

// Crear lock
file_put_contents($lockFile, getmypid());

try {
    // Cargar configuración de base de datos
    require_once BASE_PATH . '/php/db_config.php';
    require_once BASE_PATH . '/email/EmailService.php';
    
    $conn = getDBConnection();
    
    if (!$conn || $conn->connect_error) {
        throw new Exception("Error de conexión a base de datos: " . ($conn ? $conn->connect_error : 'No connection'));
    }
    
    writeLog("Conexión a base de datos establecida", $logFile);
    
    // Inicializar servicio de email
    $emailService = new EmailService($conn);
    
    // Verificar si el sistema está habilitado
    if (!$emailService->isEnabled()) {
        exitWithLog("Sistema de email deshabilitado. Saliendo.", $logFile);
    }
    
    // Verificar modo de prueba
    if ($emailService->isTestMode()) {
        writeLog("MODO PRUEBA ACTIVO - Los emails se enviarán a la dirección de prueba", $logFile);
    }
    
    // Procesar cola
    $result = $emailService->processQueue();
    
    $message = sprintf(
        "Procesamiento completado - Total: %d, Exitosos: %d, Fallidos: %d",
        $result['processed'],
        $result['success'],
        $result['failed']
    );
    
    writeLog($message, $logFile);
    
    // Obtener estado de la cola
    $queueCount = $emailService->getQueueCount();
    writeLog("Estado de cola: " . json_encode($queueCount), $logFile);
    
    $conn->close();
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage(), $logFile);
} finally {
    // Eliminar lock
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
    writeLog("=== Procesamiento finalizado ===\n", $logFile);
}
