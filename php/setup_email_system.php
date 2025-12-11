<?php
/*setup_email_system.php Script de instalación y configuración del sistema de emails  */

// Solo permitir ejecución CLI
if (php_sapi_name() !== 'cli') {
    die("Este script debe ejecutarse desde la línea de comandos.\n");
}

// Colores para la terminal
define('COLOR_GREEN', "\033[32m");
define('COLOR_RED', "\033[31m");
define('COLOR_YELLOW', "\033[33m");
define('COLOR_BLUE', "\033[34m");
define('COLOR_RESET', "\033[0m");

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'task_management_db');

// Obtener comando
$command = $argv[1] ?? 'help';

echo "\n" . COLOR_BLUE . "╔════════════════════════════════════════════════════════════╗\n";
echo "║     Sistema de Notificaciones por Email - Setup           ║\n";
echo "╚════════════════════════════════════════════════════════════╝" . COLOR_RESET . "\n\n";

// Conectar a base de datos
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo COLOR_RED . "✗ Error de conexión: " . $conn->connect_error . COLOR_RESET . "\n";
    exit(1);
}
$conn->set_charset('utf8mb4');
echo COLOR_GREEN . "✓ Conexión a base de datos establecida" . COLOR_RESET . "\n\n";

switch ($command) {
    case 'install':
        installTables($conn);
        break;
        
    case 'configure':
        configureGmail($conn);
        break;
        
    case 'test':
        testConnection($conn);
        break;
        
    case 'status':
        showStatus($conn);
        break;
        
    case 'enable':
        toggleService($conn, true);
        break;
        
    case 'disable':
        toggleService($conn, false);
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

$conn->close();
echo "\n";

function installTables($conn) {
    echo COLOR_YELLOW . "Instalando tablas del sistema de email...\n" . COLOR_RESET;
    
    $sql_file = __DIR__ . '/email_system_tables.sql';
    
    if (!file_exists($sql_file)) {
        // Crear el SQL inline si no existe el archivo
        $tables = getTableDefinitions();
    } else {
        $tables = file_get_contents($sql_file);
    }
    
    // Separar y ejecutar cada statement
    $statements = array_filter(
        array_map('trim', explode(';', $tables)),
        function($s) { return !empty($s) && strpos($s, '--') !== 0; }
    );
    
    $success = 0;
    $errors = 0;
    
    foreach ($statements as $sql) {
        if (empty(trim($sql))) continue;
        
        if ($conn->query($sql)) {
            $success++;
        } else {
            // Ignorar errores de "ya existe"
            if (strpos($conn->error, 'already exists') === false && 
                strpos($conn->error, 'Duplicate') === false) {
                echo COLOR_RED . "  ✗ Error: " . $conn->error . COLOR_RESET . "\n";
                $errors++;
            }
        }
    }
    
    echo COLOR_GREEN . "\n✓ Instalación completada" . COLOR_RESET . "\n";
    echo "  - Statements ejecutados: $success\n";
    if ($errors > 0) {
        echo COLOR_YELLOW . "  - Errores: $errors" . COLOR_RESET . "\n";
    }
    
    echo "\nSiguiente paso: Ejecute 'php setup_email_system.php configure' para configurar Gmail\n";
}

function configureGmail($conn) {
    echo COLOR_YELLOW . "Configuración de Gmail SMTP\n" . COLOR_RESET;
    echo "───────────────────────────────────────────\n\n";
    
    echo "Antes de continuar, asegúrese de:\n";
    echo "  1. Tener habilitada la verificación en 2 pasos en su cuenta de Google\n";
    echo "  2. Haber generado una 'Contraseña de aplicación' en:\n";
    echo "     " . COLOR_BLUE . "https://myaccount.google.com/apppasswords" . COLOR_RESET . "\n\n";
    
    // Solicitar datos
    echo "Ingrese su email de Gmail: ";
    $email = trim(fgets(STDIN));
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo COLOR_RED . "✗ Email inválido" . COLOR_RESET . "\n";
        return;
    }
    
    echo "Ingrese la contraseña de aplicación (16 caracteres, sin espacios): ";
    system('stty -echo'); // Ocultar entrada
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
    
    $password = str_replace(' ', '', $password); // Quitar espacios
    
    if (strlen($password) !== 16) {
        echo COLOR_YELLOW . "Nota: La contraseña de aplicación suele tener 16 caracteres" . COLOR_RESET . "\n";
    }
    
    echo "Nombre del remitente (Enter para 'Sistema de Tareas'): ";
    $from_name = trim(fgets(STDIN));
    if (empty($from_name)) {
        $from_name = 'Sistema de Tareas';
    }
    
    echo "URL del sistema (Enter para 'http://localhost/task_management'): ";
    $system_url = trim(fgets(STDIN));
    if (empty($system_url)) {
        $system_url = 'http://localhost/task_management';
    }
    
    // Guardar configuración
    echo "\n" . COLOR_YELLOW . "Guardando configuración..." . COLOR_RESET . "\n";
    
    $configs = [
        ['smtp_host', 'smtp.gmail.com', 0],
        ['smtp_port', '587', 0],
        ['smtp_encryption', 'tls', 0],
        ['smtp_user', $email, 0],
        ['smtp_from_email', $email, 0],
        ['smtp_from_name', $from_name, 0],
        ['system_url', $system_url, 0],
        ['email_enabled', '0', 0], // Deshabilitado por defecto hasta probar
        ['test_mode', '1', 0]
    ];
    
    foreach ($configs as $config) {
        $stmt = $conn->prepare(
            "INSERT INTO tbl_email_config (config_key, config_value, is_encrypted) 
             VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE config_value = ?, is_encrypted = ?"
        );
        $stmt->bind_param("ssisi", $config[0], $config[1], $config[2], $config[1], $config[2]);
        $stmt->execute();
        $stmt->close();
    }
    
    // Guardar contraseña encriptada
    $encryption_key = 'task_management_email_key_2024';
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $encryption_key, 0, $iv);
    $encrypted_password = base64_encode($iv . $encrypted);
    
    $stmt = $conn->prepare(
        "INSERT INTO tbl_email_config (config_key, config_value, is_encrypted) 
         VALUES ('smtp_password', ?, 1) 
         ON DUPLICATE KEY UPDATE config_value = ?, is_encrypted = 1"
    );
    $stmt->bind_param("ss", $encrypted_password, $encrypted_password);
    $stmt->execute();
    $stmt->close();
    
    echo COLOR_GREEN . "✓ Configuración guardada exitosamente" . COLOR_RESET . "\n\n";
    echo "Siguiente paso: Ejecute 'php setup_email_system.php test' para probar la conexión\n";
}

function testConnection($conn) {
    echo COLOR_YELLOW . "Probando conexión SMTP...\n" . COLOR_RESET;
    
    require_once __DIR__ . '/includes/email/EmailService.php';
    
    $emailService = new EmailService($conn);
    $result = $emailService->testConnection();
    
    if ($result['success']) {
        echo COLOR_GREEN . "✓ " . $result['message'] . COLOR_RESET . "\n\n";
        
        echo "¿Desea enviar un email de prueba? (s/n): ";
        $answer = strtolower(trim(fgets(STDIN)));
        
        if ($answer === 's' || $answer === 'si') {
            echo "Ingrese el email de destino: ";
            $test_email = trim(fgets(STDIN));
            
            echo "Enviando email de prueba...\n";
            $test_result = $emailService->sendTestEmail($test_email);
            
            if ($test_result['success']) {
                echo COLOR_GREEN . "✓ " . $test_result['message'] . COLOR_RESET . "\n";
                echo "\n¿Desea habilitar el servicio de email? (s/n): ";
                $enable = strtolower(trim(fgets(STDIN)));
                
                if ($enable === 's' || $enable === 'si') {
                    toggleService($conn, true);
                }
            } else {
                echo COLOR_RED . "✗ " . $test_result['message'] . COLOR_RESET . "\n";
            }
        }
    } else {
        echo COLOR_RED . "✗ " . $result['message'] . COLOR_RESET . "\n";
        if (isset($result['errors'])) {
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    }
}

function showStatus($conn) {
    echo COLOR_YELLOW . "Estado del Sistema de Email\n" . COLOR_RESET;
    echo "───────────────────────────────────────────\n\n";
    
    // Verificar tablas
    $tables = ['tbl_email_config', 'tbl_email_queue', 'tbl_email_log', 'tbl_notificacion_preferencias'];
    echo "Tablas:\n";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $exists = $result->num_rows > 0;
        echo "  " . ($exists ? COLOR_GREEN . "✓" : COLOR_RED . "✗") . COLOR_RESET . " $table\n";
    }
    
    // Obtener configuración
    echo "\nConfiguración:\n";
    $result = $conn->query("SELECT config_key, config_value, is_encrypted FROM tbl_email_config");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $value = $row['is_encrypted'] ? '********' : $row['config_value'];
            echo "  {$row['config_key']}: $value\n";
        }
    }
    
    // Estadísticas de cola
    echo "\nCola de Emails:\n";
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
            SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
            SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos
        FROM tbl_email_queue
    ");
    if ($result && $row = $result->fetch_assoc()) {
        echo "  Total: {$row['total']}\n";
        echo "  Pendientes: {$row['pendientes']}\n";
        echo "  Enviados: {$row['enviados']}\n";
        echo "  Fallidos: {$row['fallidos']}\n";
    }
}

function toggleService($conn, $enable) {
    $value = $enable ? '1' : '0';
    $status = $enable ? 'habilitado' : 'deshabilitado';
    
    $stmt = $conn->prepare(
        "UPDATE tbl_email_config SET config_value = ? WHERE config_key = 'email_enabled'"
    );
    $stmt->bind_param("s", $value);
    $stmt->execute();
    $stmt->close();
    
    // También deshabilitar modo prueba si estamos habilitando
    if ($enable) {
        $conn->query("UPDATE tbl_email_config SET config_value = '0' WHERE config_key = 'test_mode'");
    }
    
    echo COLOR_GREEN . "✓ Servicio de email $status" . COLOR_RESET . "\n";
}

function showHelp() {
    echo "Uso: php setup_email_system.php [comando]\n\n";
    echo "Comandos disponibles:\n";
    echo "  " . COLOR_BLUE . "install" . COLOR_RESET . "    - Crear tablas del sistema de email\n";
    echo "  " . COLOR_BLUE . "configure" . COLOR_RESET . "  - Configurar Gmail de forma interactiva\n";
    echo "  " . COLOR_BLUE . "test" . COLOR_RESET . "       - Probar conexión SMTP\n";
    echo "  " . COLOR_BLUE . "status" . COLOR_RESET . "     - Ver estado del sistema\n";
    echo "  " . COLOR_BLUE . "enable" . COLOR_RESET . "     - Habilitar servicio de email\n";
    echo "  " . COLOR_BLUE . "disable" . COLOR_RESET . "    - Deshabilitar servicio de email\n";
    echo "  " . COLOR_BLUE . "help" . COLOR_RESET . "       - Mostrar esta ayuda\n";
    echo "\nProceso de instalación recomendado:\n";
    echo "  1. php setup_email_system.php install\n";
    echo "  2. php setup_email_system.php configure\n";
    echo "  3. php setup_email_system.php test\n";
    echo "  4. php setup_email_system.php enable\n";
}

function getTableDefinitions() {
    return "
    CREATE TABLE IF NOT EXISTS tbl_email_config (
        id_config INT(11) NOT NULL AUTO_INCREMENT,
        config_key VARCHAR(100) NOT NULL,
        config_value TEXT,
        is_encrypted TINYINT(1) DEFAULT 0,
        descripcion VARCHAR(255) DEFAULT NULL,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_config),
        UNIQUE KEY unique_config_key (config_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    INSERT IGNORE INTO tbl_email_config (config_key, config_value, is_encrypted, descripcion) VALUES
    ('smtp_host', 'smtp.gmail.com', 0, 'Servidor SMTP'),
    ('smtp_port', '587', 0, 'Puerto SMTP'),
    ('smtp_user', '', 0, 'Usuario SMTP'),
    ('smtp_password', '', 1, 'Contraseña SMTP'),
    ('smtp_encryption', 'tls', 0, 'Tipo de encriptación'),
    ('smtp_from_name', 'Sistema de Tareas', 0, 'Nombre del remitente'),
    ('smtp_from_email', '', 0, 'Email del remitente'),
    ('smtp_reply_to', '', 0, 'Email para respuestas'),
    ('email_enabled', '0', 0, 'Servicio habilitado'),
    ('test_mode', '1', 0, 'Modo de prueba'),
    ('system_url', 'http://localhost/task_management', 0, 'URL del sistema'),
    ('dias_recordatorio_antes', '3', 0, 'Días antes del vencimiento'),
    ('max_reintentos', '3', 0, 'Máximo de reintentos'),
    ('emails_por_lote', '20', 0, 'Emails por ejecución');
    
    CREATE TABLE IF NOT EXISTS tbl_email_queue (
        id_email INT(11) NOT NULL AUTO_INCREMENT,
        destinatario_email VARCHAR(255) NOT NULL,
        destinatario_nombre VARCHAR(255) DEFAULT NULL,
        asunto VARCHAR(255) NOT NULL,
        cuerpo_html TEXT NOT NULL,
        cuerpo_texto TEXT,
        tipo_notificacion ENUM('tarea_asignada','tarea_vencimiento','tarea_vencida','tarea_completada','proyecto_asignado','proyecto_completado','objetivo_asignado','recordatorio_diario','resumen_semanal','prueba') NOT NULL,
        prioridad TINYINT(4) DEFAULT 5,
        estado ENUM('pendiente', 'enviado', 'fallido', 'cancelado') DEFAULT 'pendiente',
        intentos INT(11) DEFAULT 0,
        max_intentos INT(11) DEFAULT 3,
        ultimo_error TEXT,
        referencia_tipo ENUM('tarea', 'proyecto', 'objetivo', 'usuario') DEFAULT NULL,
        referencia_id INT(11) DEFAULT NULL,
        programado_para DATETIME DEFAULT CURRENT_TIMESTAMP,
        enviado_at DATETIME DEFAULT NULL,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_email),
        INDEX idx_estado_programado (estado, programado_para),
        INDEX idx_tipo_notificacion (tipo_notificacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS tbl_email_log (
        id_log INT(11) NOT NULL AUTO_INCREMENT,
        id_email INT(11) DEFAULT NULL,
        evento ENUM('queued', 'processing', 'sent', 'failed', 'opened', 'clicked', 'bounced') NOT NULL,
        detalle TEXT,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent TEXT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id_log),
        INDEX idx_email_evento (id_email, evento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    
    CREATE TABLE IF NOT EXISTS tbl_notificacion_preferencias (
        id_preferencia INT(11) NOT NULL AUTO_INCREMENT,
        id_usuario INT(11) NOT NULL,
        notif_tarea_asignada TINYINT(1) DEFAULT 1,
        notif_tarea_vencimiento TINYINT(1) DEFAULT 1,
        notif_tarea_vencida TINYINT(1) DEFAULT 1,
        notif_tarea_completada TINYINT(1) DEFAULT 1,
        notif_proyecto_asignado TINYINT(1) DEFAULT 1,
        notif_resumen_diario TINYINT(1) DEFAULT 0,
        notif_resumen_semanal TINYINT(1) DEFAULT 1,
        hora_preferida TIME DEFAULT '09:00:00',
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id_preferencia),
        UNIQUE KEY unique_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
}