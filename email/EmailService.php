<?php

/*EmailService.php - Servicio principal de envío de emails. */

require_once "PHPMailer/PHPMailer.php";

require_once "PHPMailer/SMTP.php";

require_once "PHPMailer/Exception.php";

require_once __DIR__ . "/EmailConfig.php";

use PHPMailer\PHPMailer\PHPMailer;

use PHPMailer\PHPMailer\SMTP;

use PHPMailer\PHPMailer\Exception;

class EmailService
{
  private $conn;

  private $config; // EmailConfig object

  private $mailer; // PHPMailer instance

  private $lastError = "";

  public function __construct($conn)
  {
    $this->conn = $conn;

    $this->config = new EmailConfig($conn);
  }

  // Wrapper methods to maintain compatibility with existing code

  public function getConfig($key, $default = null)
  {
    return $this->config->get($key, $default);
  }

  public function setConfig($key, $value)
  {
    return $this->config->set($key, $value);
  }

  public function isEnabled()
  {
    return $this->config->isEnabled();
  }

  public function isTestMode()
  {
    return $this->config->isTestMode();
  }

  public function encryptPassword($password)
  {
    $key = $this->getEncryptionKey();

    $iv = openssl_random_pseudo_bytes(16);

    $encrypted = openssl_encrypt($password, "AES-256-CBC", $key, 0, $iv);

    return base64_encode($iv . $encrypted);
  }

  public function decryptPassword($encrypted)
  {
    $key = $this->getEncryptionKey();

    $data = base64_decode($encrypted);

    $iv = substr($data, 0, 16);

    $encrypted = substr($data, 16);

    return openssl_decrypt($encrypted, "AES-256-CBC", $key, 0, $iv);
  }

  private function getEncryptionKey()
  {
    $keyFile = __DIR__ . "/.email_key";

    if (file_exists($keyFile)) {
      return file_get_contents($keyFile);
    }

    $key = bin2hex(random_bytes(32));

    file_put_contents($keyFile, $key);

    chmod($keyFile, 0600);

    return $key;
  }

  public function queueEmail(
    $toEmail,

    $toName,

    $subject,

    $bodyHtml,

    $tipoNotificacion = "prueba",

    $referenciaTipo = null,

    $referenciaId = null,

    $prioridad = 5,
  ) {
    $bodyText = strip_tags(
      str_replace(["<br>", "<br/>", "<br />"], "\n", $bodyHtml),
    );

    $stmt = $this->conn->prepare(" 

            INSERT INTO tbl_email_queue  

            (destinatario_email, destinatario_nombre, asunto, cuerpo_html,  

             cuerpo_texto, tipo_notificacion, referencia_tipo, referencia_id,  

             prioridad, estado, intentos, max_intentos) 

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', 0, 3) 

        ");

    $stmt->bind_param(
      "ssssssiii",

      $toEmail,

      $toName,

      $subject,

      $bodyHtml,

      $bodyText,

      $tipoNotificacion,

      $referenciaTipo,

      $referenciaId,

      $prioridad,
    );

    $result = $stmt->execute();

    // Log queue event

    if ($result) {
      $emailId = $stmt->insert_id;

      $this->logEvent($emailId, "queued", "Email en cola para: $toEmail");
    }

    return $result;
  }

  public function processQueue($limit = null)
  {
    if (!$this->isEnabled()) {
      return [
        "processed" => 0,

        "success" => 0,

        "failed" => 0,

        "message" => "Sistema deshabilitado",
      ];
    }

    $batchSize = $limit ?? (int) $this->getConfig("batch_size", 10);

    $maxRetries = (int) $this->getConfig("max_intentos", 3);

    // Obtener emails pendientes usando la vista

    $stmt = $this->conn->prepare(" 

            SELECT * FROM v_emails_pendientes 

            WHERE intentos < ? 

            LIMIT ? 

        ");

    $stmt->bind_param("ii", $maxRetries, $batchSize);

    $stmt->execute();

    $result = $stmt->get_result();

    $stats = ["processed" => 0, "success" => 0, "failed" => 0];

    while ($email = $result->fetch_assoc()) {
      $stats["processed"]++;

      // Marcar como procesando

      $this->updateEmailStatus(
        $email["id_email"],

        "pendiente",

        null,

        $email["intentos"],
      );

      $this->logEvent($email["id_email"], "processing", "Iniciando envío");

      // Intentar enviar

      $sendResult = $this->sendEmail($email);

      if ($sendResult["success"]) {
        $this->updateEmailStatus($email["id_email"], "enviado");

        $this->logEvent(
          $email["id_email"],

          "sent",

          "Email enviado exitosamente",
        );

        $stats["success"]++;
      } else {
        $intentos = $email["intentos"] + 1;

        $estado = $intentos >= $maxRetries ? "fallido" : "pendiente";

        $this->updateEmailStatus(
          $email["id_email"],

          $estado,

          $sendResult["error"],

          $intentos,
        );

        $this->logEvent($email["id_email"], "failed", $sendResult["error"]);

        $stats["failed"]++;
      }
    }

    return $stats;
  }

  private function sendEmail($emailData)
  {
    $startTime = microtime(true);

    // DEBUG - Remove after testing
    $username = $this->getConfig("smtp_username");
    $password = $this->getConfig("smtp_password");
    error_log("DEBUG SMTP Username: " . $username);
    error_log("DEBUG SMTP Password length: " . strlen($password));
    error_log("DEBUG SMTP Password first 4 chars: " . substr($password, 0, 4));
    // END DEBUG

    // Modo prueba
    if ($this->isTestMode()) {
      $testEmail = $this->getConfig("test_email");

      if ($testEmail) {
        $emailData["destinatario_email"] = $testEmail;

        $emailData["asunto"] = "[TEST] " . $emailData["asunto"];
      } else {
        return [
          "success" => true,

          "time_ms" => 0,

          "message" => "Modo prueba - email simulado",
        ];
      }
    }

    $mail = new PHPMailer(true);

    try {
      // Configuración SMTP

      $mail->isSMTP();

      $mail->Host = $this->getConfig("smtp_host", "smtp.gmail.com");

      $mail->SMTPAuth = $this->getConfig("smtp_auth", "1") === "1";

      $mail->Username = $this->getConfig("smtp_username");

      $mail->Password = $this->getConfig("smtp_password");

      $mail->SMTPSecure = $this->getConfig("smtp_secure", "tls");

      $mail->Port = (int) $this->getConfig("smtp_port", 587);

      // Configuración de charset

      $mail->CharSet = "UTF-8";

      $mail->Encoding = "base64";

      // Remitente

      $mail->setFrom(
        $this->getConfig("from_email", $this->getConfig("smtp_username")),

        $this->getConfig("from_name", "Sistema de Gestión"),
      );

      // Destinatario

      $mail->addAddress(
        $emailData["destinatario_email"],

        $emailData["destinatario_nombre"] ?? "",
      );

      // Contenido

      $mail->isHTML(true);

      $mail->Subject = $emailData["asunto"];

      $mail->Body = $emailData["cuerpo_html"];

      $mail->AltBody =
        $emailData["cuerpo_texto"] ?? strip_tags($emailData["cuerpo_html"]);

      $mail->send();

      $timeMs = round((microtime(true) - $startTime) * 1000);

      return ["success" => true, "time_ms" => $timeMs];
    } catch (Exception $e) {
      $this->lastError = $mail->ErrorInfo;

      return ["success" => false, "error" => $mail->ErrorInfo];
    }
  }

  public function sendDirect($toEmail, $toName, $subject, $bodyHtml)
  {
    $emailData = [
      "destinatario_email" => $toEmail,

      "destinatario_nombre" => $toName,

      "asunto" => $subject,

      "cuerpo_html" => $bodyHtml,

      "cuerpo_texto" => strip_tags($bodyHtml),
    ];

    return $this->sendEmail($emailData);
  }

  private function updateEmailStatus(
    $id,

    $estado,

    $error = null,

    $intentos = null,
  ) {
    if ($intentos !== null) {
      $stmt = $this->conn->prepare(" 

                UPDATE tbl_email_queue 

                SET estado = ?, ultimo_error = ?, intentos = ?, 

                    enviado_at = IF(? = 'enviado', NOW(), enviado_at) 

                WHERE id_email = ? 

            ");

      $stmt->bind_param("ssisi", $estado, $error, $intentos, $estado, $id);
    } else {
      $stmt = $this->conn->prepare(" 

                UPDATE tbl_email_queue 

                SET estado = ?, enviado_at = IF(? = 'enviado', NOW(), enviado_at) 

                WHERE id_email = ? 

            ");

      $stmt->bind_param("ssi", $estado, $estado, $id);
    }

    return $stmt->execute();
  }

  private function logEvent($idEmail, $evento, $detalle = null)
  {
    $stmt = $this->conn->prepare(" 

            INSERT INTO tbl_email_log (id_email, evento, detalle) 

            VALUES (?, ?, ?) 

        ");

    $stmt->bind_param("iss", $idEmail, $evento, $detalle);

    return $stmt->execute();
  }

  public function wasNotificationSent($userId, $tipo, $referenciaId)
  {
    $stmt = $this->conn->prepare(" 

            SELECT COUNT(*) as count 

            FROM tbl_notificaciones_enviadas 

            WHERE id_usuario = ? 

              AND tipo_evento = ? 

              AND id_referencia = ? 

              AND DATE(fecha_envio) = CURDATE() 

        ");

    $stmt->bind_param("isi", $userId, $tipo, $referenciaId);

    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();

    return $result["count"] > 0;
  }

  public function markNotificationSent($userId, $tipo, $referenciaId)
  {
    $stmt = $this->conn->prepare(" 

            INSERT INTO tbl_notificaciones_enviadas 

            (tipo_evento, id_referencia, id_usuario) 

            VALUES (?, ?, ?) 

        ");

    $stmt->bind_param("sii", $tipo, $referenciaId, $userId);

    return $stmt->execute();
  }

  public function getLastError()
  {
    return $this->lastError;
  }

  public function testConnection()
  {
    // Validar configuración primero

    $validation = $this->config->validateConfig();

    if (!$validation["valid"]) {
      return [
        "success" => false,

        "message" => "Configuración incompleta",

        "errors" => $validation["errors"],
      ];
    }

    if (!$this->initializeMailer()) {
      return [
        "success" => false,

        "message" => "Error inicializando mailer: " . $this->lastError,
      ];
    }

    try {
      // Habilitar debug para capturar información

      $this->mailer->SMTPDebug = SMTP::DEBUG_CONNECTION;

      ob_start();

      $connected = $this->mailer->smtpConnect();

      $debug_output = ob_get_clean();

      if ($connected) {
        $this->mailer->smtpClose();

        return [
          "success" => true,

          "message" => "Conexión SMTP exitosa",

          "debug" => $debug_output,
        ];
      }

      return [
        "success" => false,

        "message" => "No se pudo establecer conexión",

        "debug" => $debug_output,
      ];
    } catch (Exception $e) {
      return [
        "success" => false,

        "message" => $e->getMessage(),
      ];
    }
  }

  private function initializeMailer()
  {
    $this->mailer = new PHPMailer(true);

    try {
      // Configuración del servidor

      $this->mailer->isSMTP();

      $this->mailer->Host = $this->config->get("smtp_host", "smtp.gmail.com");

      $this->mailer->SMTPAuth = true;

      $this->mailer->Username = $this->config->get("smtp_user");

      $this->mailer->Password = $this->config->get("smtp_password");

      // Configurar encriptación

      $encryption = $this->config->get("smtp_encryption", "tls");

      if ($encryption === "ssl") {
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      } else {
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
      }

      $this->mailer->Port = (int) $this->config->get("smtp_port", 587);

      // Configurar remitente

      $fromEmail = $this->config->get("smtp_from_email");

      $fromName = $this->config->get("smtp_from_name", "Sistema de Tareas");

      if (!empty($fromEmail)) {
        $this->mailer->setFrom($fromEmail, $fromName);
      }

      // Reply-To opcional

      $replyTo = $this->config->get("smtp_reply_to");

      if (!empty($replyTo)) {
        $this->mailer->addReplyTo($replyTo);
      }

      // Codificación

      $this->mailer->CharSet = "UTF-8";

      $this->mailer->Encoding = "base64";

      // Timeout

      $this->mailer->Timeout = 30;

      return true;
    } catch (Exception $e) {
      $this->lastError = $e->getMessage();

      return false;
    }
  }

  public function sendTestEmail($to_email)
  {
    $subject = "Prueba de Email - Sistema de Gestión de Tareas";

    $html =
      ' 

        <!DOCTYPE html> 

        <html> 

        <head> 

            <meta charset="UTF-8"> 

            <style> 

                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; } 

                .container { max-width: 600px; margin: 0 auto; padding: 20px; } 

                .header { background: #009b4a; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; } 

                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; } 

                .success { color: #009b4a; font-size: 48px; } 

            </style> 

        </head> 

        <body> 

            <div class="container"> 

                <div class="header"> 

                    <h1>Sistema de Gestión de Tareas</h1> 

                </div> 

                <div class="content"> 

                    <p class="success" style="text-align: center;">✓</p> 

                    <h2 style="text-align: center;">¡Configuración Exitosa!</h2> 

                    <p>Este es un correo de prueba para verificar que el sistema de notificaciones está funcionando correctamente.</p> 

                    <p><strong>Detalles de configuración:</strong></p> 

                    <ul> 

                        <li>Servidor SMTP: ' .
      htmlspecialchars($this->config->get("smtp_host")) .
      '</li> 

                        <li>Puerto: ' .
      htmlspecialchars($this->config->get("smtp_port")) .
      '</li> 

                        <li>Encriptación: ' .
      htmlspecialchars($this->config->get("smtp_encryption")) .
      '</li> 

                        <li>Fecha/Hora: ' .
      date("d/m/Y H:i:s") .
      '</li> 

                    </ul> 

                    <p style="color: #666; font-size: 12px; margin-top: 20px;"> 

                        Si recibiste este correo, el sistema está configurado correctamente. 

                    </p> 

                </div> 

            </div> 

        </body> 

        </html>';

    // Guardar modo actual

    $currentTestMode = $this->config->get("test_mode");

    $currentEnabled = $this->config->get("email_enabled");

    // Temporalmente deshabilitar modo prueba para enviar realmente

    $this->config->set("test_mode", "0");

    $this->config->set("email_enabled", "1");

    $result = $this->sendImmediate(
      $to_email,
      "Usuario de Prueba",
      $subject,
      $html,
    );

    // Restaurar configuración

    $this->config->set("test_mode", $currentTestMode);

    $this->config->set("email_enabled", $currentEnabled);

    if ($result) {
      return [
        "success" => true,

        "message" => "Email de prueba enviado exitosamente a: $to_email",
      ];
    }

    return [
      "success" => false,

      "message" => "Error enviando email de prueba: " . $this->getLastError(),
    ];
  }

  public function sendImmediate($to_email, $to_name, $subject, $html_body)
  {
    // Verificar si el servicio está habilitado

    if (!$this->isEnabled()) {
      $this->lastError = "El servicio de email está deshabilitado";

      return false;
    }

    // Modo de prueba no enviar de verdad

    if ($this->isTestMode()) {
      error_log("[EMAIL TEST MODE] Para: $to_email, Asunto: $subject");

      return true;
    }

    // Inicializar mailer

    if (!$this->initializeMailer()) {
      return false;
    }

    try {
      // Limpiar configuración anterior

      $this->mailer->clearAddresses();

      $this->mailer->clearAttachments();

      $this->mailer->clearReplyTos();

      // Configurar email

      $this->mailer->addAddress($to_email, $to_name);

      $this->mailer->isHTML(true);

      $this->mailer->Subject = $subject;

      $this->mailer->Body = $html_body;

      $this->mailer->AltBody = $this->htmlToText($html_body);

      return $this->mailer->send();
    } catch (Exception $e) {
      $this->lastError = $e->getMessage();

      return false;
    }
  }

  private function htmlToText($html)
  {
    // Reemplazar <br> con saltos de línea

    $text = preg_replace("/<br\s*\/?>/i", "\n", $html);

    // Reemplazar </p>, </div>, </li> con doble salto

    $text = preg_replace("/<\/(p|div|li|tr)>/i", "\n\n", $text);

    // Eliminar todas las demás etiquetas HTML

    $text = strip_tags($text);

    // Decodificar entidades HTML

    $text = html_entity_decode($text, ENT_QUOTES, "UTF-8");

    // Eliminar espacios múltiples

    $text = preg_replace('/[ \t]+/', " ", $text);

    // Eliminar saltos de línea múltiples

    $text = preg_replace('/\n{3,}/', "\n\n", $text);

    return trim($text);
  }

  public function getStats($days = 7)
  {
    $stmt = $this->conn->prepare(" 

            SELECT  

                COUNT(*) as total, 

                SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados, 

                SUM(CASE WHEN estado = 'fallido' THEN 1 ELSE 0 END) as fallidos, 

                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes 

            FROM tbl_email_queue 

            WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL ? DAY) 

        ");

    $stmt->bind_param("i", $days);

    $stmt->execute();

    return $stmt->get_result()->fetch_assoc();
  }

  public function getQueueCount()
  {
    $result = $this->conn->query(" 

            SELECT estado, COUNT(*) as count 

            FROM tbl_email_queue 

            GROUP BY estado 

        ");

    $counts = [];

    while ($row = $result->fetch_assoc()) {
      $counts[$row["estado"]] = $row["count"];
    }

    return $counts;
  }

  public function getDetailedStats($days = 7)
  {
    $stmt = $this->conn->prepare(" 

            SELECT * FROM v_estadisticas_email 

            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL ? DAY) 

            ORDER BY fecha DESC 

        ");

    $stmt->bind_param("i", $days);

    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
  }
}
