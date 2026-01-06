<?php

/*check_deadlines.php - Script de cron para verificar vencimientos y enviar recordatorios */

// Permitir ejecución solo desde CLI

if (php_sapi_name() !== "cli") {
  die("Este script solo puede ejecutarse desde la línea de comandos");
}

set_time_limit(600);

ini_set("memory_limit", "256M");

// Determinar directorio base

$base_dir = dirname(__DIR__);

// Cargar configuración

$config_paths = [
  __DIR__ . "/../php/db_config.php",

  __DIR__ . "/../config/database.php",

  __DIR__ . "/db_config.php",
];

$config_loaded = false;

foreach ($config_paths as $path) {
  if (file_exists($path)) {
    require_once $path;

    $config_loaded = true;

    break;
  }
}

if (!$config_loaded) {
  define("DB_HOST", "localhost");

  define("DB_USER", "root");

  define("DB_PASS", "");

  define("DB_NAME", "task_management_db");
}

// Cargar dependencias de email

$email_paths = [
  __DIR__ . "/../email/EmailService.php",

  __DIR__ . "/EmailService.php",
];

foreach ($email_paths as $path) {
  if (file_exists($path)) {
    require_once $path;

    break;
  }
}

$template_paths = [
  __DIR__ . "/../email/EmailTemplates.php",
  __DIR__ . "/../email/EmailTemplate.php",
  __DIR__ . "/EmailTemplate.php",
];

foreach ($template_paths as $path) {
  if (file_exists($path)) {
    require_once $path;
    break;
  }
}

// Configuración de logging
$log_dir = __DIR__ . "/logs";

if (!is_dir($log_dir)) {
  mkdir($log_dir, 0755, true);
}

$log_file = $log_dir . "/deadlines_" . date("Y-m-d") . ".log";

function logMessage($message, $level = "INFO")
{
  global $log_file;
  $timestamp = date("Y-m-d H:i:s");
  $formatted = "[$timestamp] [$level] $message\n";
  file_put_contents($log_file, $formatted, FILE_APPEND);
  echo $formatted;
}

function notificacionYaEnviada($conn, $tipo, $id_referencia, $id_usuario)
{
  $stmt = $conn->prepare(" 
        SELECT COUNT(*) as count  

        FROM tbl_notificaciones_enviadas  

        WHERE tipo_evento = ?  

        AND id_referencia = ?  

        AND id_usuario = ? 

        AND DATE(fecha_envio) = CURDATE() 

    ");

  $stmt->bind_param("sii", $tipo, $id_referencia, $id_usuario);

  $stmt->execute();

  $result = $stmt->get_result()->fetch_assoc();

  $stmt->close();

  return $result["count"] > 0;
}

/** 

 * Registrar notificación enviada 

 */

function registrarNotificacionEnviada($conn, $tipo, $id_referencia, $id_usuario)
{
  $stmt = $conn->prepare(" 

        INSERT INTO tbl_notificaciones_enviadas  

        (tipo_evento, id_referencia, id_usuario, fecha_envio) 

        VALUES (?, ?, ?, NOW()) 

    ");

  $stmt->bind_param("sii", $tipo, $id_referencia, $id_usuario);

  $stmt->execute();

  $stmt->close();
}

/** 

 * Generar HTML de email para recordatorio de vencimiento 

 */

function generarEmailVencimiento($data, $system_url)
{
  $color = $data["urgente"] ? "#dc3545" : "#ffc107";

  $titulo = $data["urgente"] ? "URGENTE" : "Recordatorio";

  return ' 

    <!DOCTYPE html> 

    <html> 

    <head><meta charset="UTF-8"></head> 

    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;"> 

        <div style="max-width: 600px; margin: 0 auto; padding: 20px;"> 

            <div style="background: ' .
    $color .
    '; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0;"> 

                <h1 style="margin: 0;">' .
    $titulo .
    '</h1> 

            </div> 

            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px;"> 

                <p>Hola <strong>' .
    htmlspecialchars($data["nombre_usuario"]) .
    '</strong>,</p> 

                 

                <div style="background: #f8f9fa; border-left: 4px solid ' .
    $color .
    '; padding: 15px; margin: 20px 0;"> 

                    <h3 style="margin: 0 0 10px 0;">Tarea: ' .
    htmlspecialchars($data["nombre_tarea"]) .
    '</h3> 

                    <p style="margin: 5px 0;"><strong>Proyecto:</strong> ' .
    htmlspecialchars($data["nombre_proyecto"] ?? "Sin proyecto") .
    '</p> 

                    <p style="margin: 5px 0;"><strong>Fecha de entrega:</strong> ' .
    $data["fecha_vencimiento"] .
    '</p> 

                    <p style="margin: 5px 0; color: ' .
    $color .
    '; font-weight: bold;">' .
    $data["mensaje_dias"] .
    '</p> 

                </div> 

                 

                <p style="text-align: center; margin-top: 25px;"> 

                    <a href="' .
    htmlspecialchars($system_url) .
    "/tareas.php?id=" .
    $data["id_tarea"] .
    '"  

                       style="display: inline-block; background: ' .
    $color .
    '; color: white; padding: 12px 30px;  

                              text-decoration: none; border-radius: 5px; font-weight: bold;"> 

                        Ver Tarea 

                    </a> 

                </p> 

                 

                <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;"> 

                <p style="color: #666; font-size: 12px; text-align: center;"> 

                    Sistema de Gestión de Tareas - Notificación automática 

                </p> 

            </div> 

        </div> 

    </body> 

    </html>';
}

/** 

 * Generar HTML de email para tarea vencida 

 */

function generarEmailVencida($data, $system_url)
{
  return ' 

    <!DOCTYPE html> 

    <html> 

    <head><meta charset="UTF-8"></head> 

    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;"> 

        <div style="max-width: 600px; margin: 0 auto; padding: 20px;"> 

            <div style="background: #dc3545; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0;"> 

                <h1 style="margin: 0;">Tarea Vencida</h1> 

            </div> 

            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px;"> 

                <p>Hola <strong>' .
    htmlspecialchars($data["nombre_usuario"]) .
    '</strong>,</p> 

                 

                <p>La siguiente tarea ha superado su fecha de entrega:</p> 

                 

                <div style="background: #fff3f3; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;"> 

                    <h3 style="margin: 0 0 10px 0; color: #dc3545;">' .
    htmlspecialchars($data["nombre_tarea"]) .
    '</h3> 

                    <p style="margin: 5px 0;"><strong>Proyecto:</strong> ' .
    htmlspecialchars($data["nombre_proyecto"] ?? "Sin proyecto") .
    '</p> 

                    <p style="margin: 5px 0;"><strong>Fecha de entrega:</strong> ' .
    $data["fecha_vencimiento"] .
    '</p> 

                    <p style="margin: 5px 0; color: #dc3545; font-weight: bold;">Vencida hace ' .
    $data["dias_vencidos"] .
    ' día(s)</p> 

                </div> 

                 

                <p>Por favor, actualiza el estado de esta tarea lo antes posible.</p> 

                 

                <p style="text-align: center; margin-top: 25px;"> 

                    <a href="' .
    htmlspecialchars($system_url) .
    "/tareas.php?id=" .
    $data["id_tarea"] .
    '"  

                       style="display: inline-block; background: #dc3545; color: white; padding: 12px 30px;  

                              text-decoration: none; border-radius: 5px; font-weight: bold;"> 

                        Ver Tarea 

                    </a> 

                </p> 

                 

                <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;"> 

                <p style="color: #666; font-size: 12px; text-align: center;"> 

                    Sistema de Gestión de Tareas - Notificación automática 

                </p> 

            </div> 

        </div> 

    </body> 

    </html>';
}

/** 

 * Generar HTML de resumen semanal para gerentes 

 */

function generarEmailResumenSemanal($data, $system_url)
{
  $porcentaje_completado =
    $data["total_tareas"] > 0
      ? round(($data["completadas"] / $data["total_tareas"]) * 100)
      : 0;

  return ' 

    <!DOCTYPE html> 

    <html> 

    <head><meta charset="UTF-8"></head> 

    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;"> 

        <div style="max-width: 600px; margin: 0 auto; padding: 20px;"> 

            <div style="background: #007bff; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0;"> 

                <h1 style="margin: 0;">Resumen Semanal</h1> 

                <p style="margin: 10px 0 0 0; opacity: 0.9;">' .
    htmlspecialchars($data["departamento"]) .
    '</p> 

            </div> 

            <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px;"> 

                <p>Hola <strong>' .
    htmlspecialchars($data["nombre_usuario"]) .
    '</strong>,</p> 

                 

                <p>Aquí está el resumen de tareas de tu departamento:</p> 

                 

                <div style="display: flex; flex-wrap: wrap; gap: 15px; margin: 20px 0;"> 

                    <div style="flex: 1; min-width: 120px; background: #e7f3ff; padding: 15px; border-radius: 8px; text-align: center;"> 

                        <div style="font-size: 28px; font-weight: bold; color: #007bff;">' .
    $data["total_tareas"] .
    '</div> 

                        <div style="font-size: 12px; color: #666;">Total Tareas</div> 

                    </div> 

                    <div style="flex: 1; min-width: 120px; background: #e8f5e9; padding: 15px; border-radius: 8px; text-align: center;"> 

                        <div style="font-size: 28px; font-weight: bold; color: #28a745;">' .
    $data["completadas"] .
    '</div> 

                        <div style="font-size: 12px; color: #666;">Completadas</div> 

                    </div> 

                    <div style="flex: 1; min-width: 120px; background: #fff3e0; padding: 15px; border-radius: 8px; text-align: center;"> 

                        <div style="font-size: 28px; font-weight: bold; color: #ffc107;">' .
    $data["pendientes"] .
    '</div> 

                        <div style="font-size: 12px; color: #666;">Pendientes</div> 

                    </div> 

                    <div style="flex: 1; min-width: 120px; background: #ffebee; padding: 15px; border-radius: 8px; text-align: center;"> 

                        <div style="font-size: 28px; font-weight: bold; color: #dc3545;">' .
    $data["vencidas"] .
    '</div> 

                        <div style="font-size: 12px; color: #666;">Vencidas</div> 

                    </div> 

                </div> 

                 

                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;"> 

                    <p style="margin: 0;"><strong>Progreso general:</strong> ' .
    $porcentaje_completado .
    '% completado</p> 

                    <div style="background: #e9ecef; border-radius: 4px; height: 20px; margin-top: 10px; overflow: hidden;"> 

                        <div style="background: #28a745; height: 100%; width: ' .
    $porcentaje_completado .
    '%;"></div> 

                    </div> 

                </div> 

                 

                ' .
    ($data["vencidas"] > 0
      ? ' 

                <div style="background: #fff3f3; border-left: 4px solid #dc3545; padding: 15px; margin: 20px 0;"> 

                    <p style="margin: 0; color: #dc3545;"><strong>Atención:</strong> Hay ' .
        $data["vencidas"] .
        ' tarea(s) vencida(s) que requieren atención inmediata.</p> 

                </div> 

                '
      : "") .
    ' 

                 

                <p style="text-align: center; margin-top: 25px;"> 

                    <a href="' .
    htmlspecialchars($system_url) .
    '"  

                       style="display: inline-block; background: #007bff; color: white; padding: 12px 30px;  

                              text-decoration: none; border-radius: 5px; font-weight: bold;"> 

                        Ver Dashboard 

                    </a> 

                </p> 

                 

                <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;"> 

                <p style="color: #666; font-size: 12px; text-align: center;"> 

                    Sistema de Gestión de Tareas - Resumen generado el ' .
    date("d/m/Y H:i") .
    ' 

                </p> 

            </div> 

        </div> 

    </body> 

    </html>';
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

  $emailService = new EmailService($conn);

  if (!$emailService->isEnabled()) {
    logMessage("El servicio de email está deshabilitado", "WARNING");

    exit(0);
  }

  // Obtener configuración usando getConfig con parámetros
  $system_url = $emailService->getConfig(
    "system_url",
    "http://10.109.17.87/projectManagement",
  );

  $dias_recordatorio = (int) $emailService->getConfig(
    "dias_recordatorio_antes",
    3,
  );

  $queued = [
    "upcoming" => 0,

    "tomorrow" => 0,

    "overdue" => 0,

    "manager_notifications" => 0,

    "skipped_duplicates" => 0,
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

            p.nombre as proyecto_nombre, 

            p.id_departamento 

        FROM tbl_tareas t 

        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 

        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 

        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 

        WHERE t.estado NOT IN ('completado', 'vencido') 

        AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL ? DAY) 

        AND (np.notif_tarea_vencimiento IS NULL OR np.notif_tarea_vencimiento = 1) 

        AND u.e_mail IS NOT NULL  

        AND u.e_mail != '' 

    ");

  $stmt->bind_param("i", $dias_recordatorio);

  $stmt->execute();

  $result = $stmt->get_result();

  while ($task = $result->fetch_assoc()) {
    $tipo_notif = "recordatorio_vencimiento_{$dias_recordatorio}d";

    // Verificar si ya se envió hoy

    if (
      notificacionYaEnviada(
        $conn,
        $tipo_notif,
        $task["id_tarea"],
        $task["id_usuario"],
      )
    ) {
      $queued["skipped_duplicates"]++;

      continue;
    }

    $html = generarEmailVencimiento(
      [
        "nombre_usuario" => $task["nombre"],

        "nombre_tarea" => $task["tarea_nombre"],

        "nombre_proyecto" => $task["proyecto_nombre"],

        "fecha_vencimiento" => date(
          "d/m/Y",
          strtotime($task["fecha_cumplimiento"]),
        ),

        "mensaje_dias" => "Vence en $dias_recordatorio días",

        "id_tarea" => $task["id_tarea"],

        "urgente" => false,
      ],
      $system_url,
    );

    $emailService->queueEmail(
      $task["e_mail"],

      $task["nombre"] . " " . $task["apellido"],

      "Recordatorio: '{$task["tarea_nombre"]}' vence en $dias_recordatorio días",

      $html,

      "tarea_vencimiento",

      "tarea",

      $task["id_tarea"],

      3,
    );

    registrarNotificacionEnviada(
      $conn,
      $tipo_notif,
      $task["id_tarea"],
      $task["id_usuario"],
    );

    $queued["upcoming"]++;
  }

  $stmt->close();

  logMessage(
    "Tareas próximas a vencer: {$queued["upcoming"]} notificaciones en cola",
  );

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

            p.nombre as proyecto_nombre, 

            p.id_departamento 

        FROM tbl_tareas t 

        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 

        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 

        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 

        WHERE t.estado NOT IN ('completado', 'vencido') 

        AND t.fecha_cumplimiento = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 

        AND (np.notif_tarea_vencimiento IS NULL OR np.notif_tarea_vencimiento = 1) 

        AND u.e_mail IS NOT NULL  

        AND u.e_mail != '' 

    ");

  $stmt->execute();

  $result = $stmt->get_result();

  while ($task = $result->fetch_assoc()) {
    $tipo_notif = "recordatorio_vencimiento_1d";

    if (
      notificacionYaEnviada(
        $conn,
        $tipo_notif,
        $task["id_tarea"],
        $task["id_usuario"],
      )
    ) {
      $queued["skipped_duplicates"]++;

      continue;
    }

    $html = generarEmailVencimiento(
      [
        "nombre_usuario" => $task["nombre"],

        "nombre_tarea" => $task["tarea_nombre"],

        "nombre_proyecto" => $task["proyecto_nombre"],

        "fecha_vencimiento" => date(
          "d/m/Y",
          strtotime($task["fecha_cumplimiento"]),
        ),

        "mensaje_dias" => "¡VENCE MAÑANA!",

        "id_tarea" => $task["id_tarea"],

        "urgente" => true,
      ],
      $system_url,
    );

    $emailService->queueEmail(
      $task["e_mail"],

      $task["nombre"] . " " . $task["apellido"],

      "URGENTE: '{$task["tarea_nombre"]}' vence MAÑANA",

      $html,

      "tarea_vencimiento_urgente",

      "tarea",

      $task["id_tarea"],

      1, // Alta prioridad
    );

    registrarNotificacionEnviada(
      $conn,
      $tipo_notif,
      $task["id_tarea"],
      $task["id_usuario"],
    );

    $queued["tomorrow"]++;
  }

  $stmt->close();

  logMessage(
    "Tareas que vencen mañana: {$queued["tomorrow"]} notificaciones en cola",
  );

  logMessage("Buscando tareas vencidas...");

  $dia_semana = date("N"); // 1 = Lunes

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

            p.nombre as proyecto_nombre, 

            p.id_departamento 

        FROM tbl_tareas t 

        JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 

        LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 

        LEFT JOIN tbl_notificacion_preferencias np ON u.id_usuario = np.id_usuario 

        WHERE t.estado NOT IN ('completado') 

        AND t.fecha_cumplimiento < CURDATE() 

        AND t.fecha_cumplimiento != '0000-00-00' 

        AND (np.notif_tarea_vencida IS NULL OR np.notif_tarea_vencida = 1) 

        AND u.e_mail IS NOT NULL  

        AND u.e_mail != '' 

    ");

  $stmt->execute();

  $result = $stmt->get_result();

  while ($task = $result->fetch_assoc()) {
    // Solo notificar si es lunes (resumen semanal) o si acaba de vencer (1 día)

    if ($dia_semana != 1 && $task["dias_vencidos"] != 1) {
      continue;
    }

    $tipo_notif = "tarea_vencida_" . ($dia_semana == 1 ? "semanal" : "nuevo");

    if (
      notificacionYaEnviada(
        $conn,
        $tipo_notif,
        $task["id_tarea"],
        $task["id_usuario"],
      )
    ) {
      $queued["skipped_duplicates"]++;

      continue;
    }

    $html = generarEmailVencida(
      [
        "nombre_usuario" => $task["nombre"],

        "nombre_tarea" => $task["tarea_nombre"],

        "nombre_proyecto" => $task["proyecto_nombre"],

        "fecha_vencimiento" => date(
          "d/m/Y",
          strtotime($task["fecha_cumplimiento"]),
        ),

        "dias_vencidos" => $task["dias_vencidos"],

        "id_tarea" => $task["id_tarea"],
      ],
      $system_url,
    );

    $emailService->queueEmail(
      $task["e_mail"],

      $task["nombre"] . " " . $task["apellido"],

      "Tarea vencida: '{$task["tarea_nombre"]}' (hace {$task["dias_vencidos"]} días)",

      $html,

      "tarea_vencida",

      "tarea",

      $task["id_tarea"],

      2,
    );

    registrarNotificacionEnviada(
      $conn,
      $tipo_notif,
      $task["id_tarea"],
      $task["id_usuario"],
    );

    $queued["overdue"]++;
  }

  $stmt->close();

  logMessage("Tareas vencidas: {$queued["overdue"]} notificaciones en cola");

  if ($dia_semana == 1) {
    logMessage("Enviando resumen semanal a gerentes de departamento...");

    $stmt = $conn->prepare(" 

            SELECT DISTINCT  

                ur.id_usuario,  

                ur.id_departamento, 

                u.nombre,  

                u.apellido,  

                u.e_mail, 

                d.nombre as departamento_nombre 

            FROM tbl_usuario_roles ur 

            JOIN tbl_usuarios u ON ur.id_usuario = u.id_usuario 

            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 

            WHERE ur.id_rol = 2  -- Gerente 

            AND ur.activo = 1 

            AND u.e_mail IS NOT NULL  

            AND u.e_mail != '' 

        ");

    $stmt->execute();

    $gerentes = $stmt->get_result();

    while ($gerente = $gerentes->fetch_assoc()) {
      $tipo_notif = "resumen_semanal_" . date("W"); // Semana del año

      if (
        notificacionYaEnviada(
          $conn,
          $tipo_notif,
          $gerente["id_departamento"],
          $gerente["id_usuario"],
        )
      ) {
        $queued["skipped_duplicates"]++;

        continue;
      }

      // Obtener estadísticas del departamento

      $stats_stmt = $conn->prepare(" 

                SELECT  

                    COUNT(*) as total_tareas, 

                    SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as completadas, 

                    SUM(CASE WHEN t.estado IN ('pendiente', 'en proceso') AND t.fecha_cumplimiento >= CURDATE() THEN 1 ELSE 0 END) as pendientes, 

                    SUM(CASE WHEN t.fecha_cumplimiento < CURDATE() AND t.estado NOT IN ('completado') THEN 1 ELSE 0 END) as vencidas 

                FROM tbl_tareas t 

                JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 

                WHERE p.id_departamento = ? 

            ");

      $stats_stmt->bind_param("i", $gerente["id_departamento"]);

      $stats_stmt->execute();

      $stats = $stats_stmt->get_result()->fetch_assoc();

      $stats_stmt->close();

      // Solo notificar si hay tareas en el departamento

      if ($stats["total_tareas"] == 0) {
        continue;
      }

      $html = generarEmailResumenSemanal(
        [
          "nombre_usuario" => $gerente["nombre"],

          "departamento" => $gerente["departamento_nombre"],

          "total_tareas" => $stats["total_tareas"],

          "completadas" => $stats["completadas"],

          "pendientes" => $stats["pendientes"],

          "vencidas" => $stats["vencidas"],
        ],
        $system_url,
      );

      $asunto = "Resumen semanal: {$gerente["departamento_nombre"]}";

      if ($stats["vencidas"] > 0) {
        $asunto .= " - {$stats["vencidas"]} tarea(s) vencida(s)";
      }

      $emailService->queueEmail(
        $gerente["e_mail"],

        $gerente["nombre"] . " " . $gerente["apellido"],

        $asunto,

        $html,

        "resumen_semanal",

        "departamento",

        $gerente["id_departamento"],

        4,
      );

      registrarNotificacionEnviada(
        $conn,
        $tipo_notif,
        $gerente["id_departamento"],
        $gerente["id_usuario"],
      );

      $queued["manager_notifications"]++;
    }

    $stmt->close();

    logMessage(
      "Notificaciones a gerentes: {$queued["manager_notifications"]} en cola",
    );
  } else {
    logMessage(
      "Resumen a gerentes solo se envía los lunes (hoy es día $dia_semana)",
    );
  }

  $total =
    $queued["upcoming"] +
    $queued["tomorrow"] +
    $queued["overdue"] +
    $queued["manager_notifications"];

  logMessage("=== Verificación completada ===");

  logMessage("Total de notificaciones en cola: $total");

  logMessage(
    "  - Próximas a vencer ($dias_recordatorio días): {$queued["upcoming"]}",
  );

  logMessage("  - Vencen mañana: {$queued["tomorrow"]}");

  logMessage("  - Vencidas: {$queued["overdue"]}");

  logMessage(
    "  - Notificaciones a gerentes: {$queued["manager_notifications"]}",
  );

  logMessage("  - Duplicados omitidos: {$queued["skipped_duplicates"]}");

  $conn->close();
} catch (Exception $e) {
  logMessage("ERROR CRÍTICO: " . $e->getMessage(), "CRITICAL");

  exit(1);
}

exit(0);
