<?php
/**
 * EmailService.php
 * Servicio principal para envío de emails con PHPMailer
 * 
 * @package TaskManagement\Email
 * @author Sistema de Tareas
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Cargar PHPMailer - ajustar rutas según tu instalación
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';
require_once __DIR__ . '/EmailConfig.php';

class EmailService {
    private $conn;
    private $config;
    private $mailer;
    private $lastError;
    private $debugOutput = '';
    
    /**
     * Constructor
     * @param mysqli $conn Conexión a la base de datos
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->config = new EmailConfig($conn);
    }
    
    /**
     * Inicializar PHPMailer con la configuración actual
     * @return bool
     */
    private function initializeMailer() {
        $this->mailer = new PHPMailer(true);
        
        try {
            // Configuración del servidor
            $this->mailer->isSMTP();
            $this->mailer->Host       = $this->config->get('smtp_host', 'smtp.gmail.com');
            $this->mailer->SMTPAuth   = true;
            $this->mailer->Username   = $this->config->get('smtp_user');
            $this->mailer->Password   = $this->config->get('smtp_password');
            
            // Configurar encriptación
            $encryption = $this->config->get('smtp_encryption', 'tls');
            if ($encryption === 'ssl') {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            $this->mailer->Port = (int) $this->config->get('smtp_port', 587);
            
            // Configurar remitente
            $fromEmail = $this->config->get('smtp_from_email');
            $fromName = $this->config->get('smtp_from_name', 'Sistema de Tareas');
            
            if (!empty($fromEmail)) {
                $this->mailer->setFrom($fromEmail, $fromName);
            }
            
            // Reply-To opcional
            $replyTo = $this->config->get('smtp_reply_to');
            if (!empty($replyTo)) {
                $this->mailer->addReplyTo($replyTo);
            }
            
            // Codificación
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->Encoding = 'base64';
            
            // Timeout
            $this->mailer->Timeout = 30;
            
            return true;
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Agregar email a la cola
     * 
     * @param string $to_email Email del destinatario
     * @param string $to_name Nombre del destinatario
     * @param string $subject Asunto
     * @param string $html_body Cuerpo HTML
     * @param string $type Tipo de notificación
     * @param string|null $reference_type Tipo de referencia (tarea, proyecto, objetivo)
     * @param int|null $reference_id ID de la referencia
     * @param int $priority Prioridad (1-10)
     * @param string|null $scheduled_for Fecha/hora programada
     * @return int|false ID del email o false si falla
     */
    public function queueEmail($to_email, $to_name, $subject, $html_body, $type, 
                               $reference_type = null, $reference_id = null, 
                               $priority = 5, $scheduled_for = null) {
        
        // Generar versión de texto plano
        $text_body = $this->htmlToText($html_body);
        $scheduled = $scheduled_for ?? date('Y-m-d H:i:s');
        $max_intentos = (int) $this->config->get('max_reintentos', 3);
        
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_email_queue 
            (destinatario_email, destinatario_nombre, asunto, cuerpo_html, cuerpo_texto, 
             tipo_notificacion, prioridad, referencia_tipo, referencia_id, programado_para, max_intentos) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        
        if (!$stmt) {
            $this->lastError = "Error preparando consulta: " . $this->conn->error;
            return false;
        }
        
        $stmt->bind_param(
            "ssssssisssi",
            $to_email, $to_name, $subject, $html_body, $text_body,
            $type, $priority, $reference_type, $reference_id, $scheduled, $max_intentos
        );
        
        if ($stmt->execute()) {
            $email_id = $this->conn->insert_id;
            $stmt->close();
            
            $this->logEvent($email_id, 'queued', "Email en cola para: $to_email");
            return $email_id;
        }
        
        $this->lastError = "Error insertando en cola: " . $stmt->error;
        $stmt->close();
        return false;
    }
    
    /**
     * Enviar email inmediatamente (sin cola)
     * 
     * @param string $to_email Email del destinatario
     * @param string $to_name Nombre del destinatario
     * @param string $subject Asunto
     * @param string $html_body Cuerpo HTML
     * @return bool
     */
    public function sendImmediate($to_email, $to_name, $subject, $html_body) {
        // Verificar si el servicio está habilitado
        if (!$this->config->isEnabled()) {
            $this->lastError = 'El servicio de email está deshabilitado';
            return false;
        }
        
        // Modo de prueba - no enviar realmente
        if ($this->config->isTestMode()) {
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
            $this->mailer->Body    = $html_body;
            $this->mailer->AltBody = $this->htmlToText($html_body);
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
    
    /**
     * Procesar cola de emails
     * 
     * @param int $batch_size Cantidad de emails a procesar
     * @return array Resultados del procesamiento
     */
    public function processQueue($batch_size = null) {
        $batch_size = $batch_size ?? (int) $this->config->get('emails_por_lote', 20);
        
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];
        
        // Verificar si el servicio está habilitado
        if (!$this->config->isEnabled()) {
            $results['errors'][] = 'El servicio de email está deshabilitado';
            return $results;
        }
        
        // Obtener emails pendientes
        $stmt = $this->conn->prepare(
            "SELECT * FROM tbl_email_queue 
             WHERE estado = 'pendiente' 
             AND programado_para <= NOW() 
             AND intentos < max_intentos 
             ORDER BY prioridad ASC, programado_para ASC 
             LIMIT ?"
        );
        $stmt->bind_param("i", $batch_size);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($email = $result->fetch_assoc()) {
            $results['processed']++;
            
            // Marcar como procesando
            $this->updateEmailStatus($email['id_email'], 'processing');
            
            $sent = $this->sendImmediate(
                $email['destinatario_email'],
                $email['destinatario_nombre'] ?? '',
                $email['asunto'],
                $email['cuerpo_html']
            );
            
            if ($sent) {
                $this->markAsSent($email['id_email']);
                $results['sent']++;
            } else {
                $this->markAsFailed($email['id_email'], $this->lastError);
                $results['failed']++;
                $results['errors'][] = [
                    'id' => $email['id_email'],
                    'email' => $email['destinatario_email'],
                    'error' => $this->lastError
                ];
            }
            
            // Pequeña pausa para evitar rate limiting
            usleep(100000); // 100ms
        }
        
        $stmt->close();
        return $results;
    }
    
    /**
     * Actualizar estado de un email
     */
    private function updateEmailStatus($email_id, $status) {
        $stmt = $this->conn->prepare("UPDATE tbl_email_queue SET estado = ? WHERE id_email = ?");
        $stmt->bind_param("si", $status, $email_id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Marcar email como enviado
     */
    private function markAsSent($email_id) {
        $stmt = $this->conn->prepare(
            "UPDATE tbl_email_queue 
             SET estado = 'enviado', enviado_at = NOW(), intentos = intentos + 1 
             WHERE id_email = ?"
        );
        $stmt->bind_param("i", $email_id);
        $stmt->execute();
        $stmt->close();
        
        $this->logEvent($email_id, 'sent', 'Email enviado exitosamente');
    }
    
    /**
     * Marcar email como fallido
     */
    private function markAsFailed($email_id, $error) {
        $stmt = $this->conn->prepare(
            "UPDATE tbl_email_queue 
             SET intentos = intentos + 1, 
                 ultimo_error = ?,
                 estado = CASE WHEN intentos + 1 >= max_intentos THEN 'fallido' ELSE 'pendiente' END
             WHERE id_email = ?"
        );
        $stmt->bind_param("si", $error, $email_id);
        $stmt->execute();
        $stmt->close();
        
        $this->logEvent($email_id, 'failed', $error);
    }
    
    /**
     * Registrar evento en el log
     */
    private function logEvent($email_id, $event, $detail) {
        $stmt = $this->conn->prepare(
            "INSERT INTO tbl_email_log (id_email, evento, detalle) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iss", $email_id, $event, $detail);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Convertir HTML a texto plano
     */
    private function htmlToText($html) {
        // Reemplazar <br> con saltos de línea
        $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
        // Reemplazar </p>, </div>, </li> con doble salto
        $text = preg_replace('/<\/(p|div|li|tr)>/i', "\n\n", $text);
        // Eliminar todas las demás etiquetas HTML
        $text = strip_tags($text);
        // Decodificar entidades HTML
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Eliminar espacios múltiples
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Eliminar saltos de línea múltiples
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return trim($text);
    }
    
    /**
     * Obtener el último error
     */
    public function getLastError() {
        return $this->lastError;
    }
    
    /**
     * Probar conexión SMTP
     * @return array Resultado de la prueba
     */
    public function testConnection() {
        // Validar configuración primero
        $validation = $this->config->validateConfig();
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => 'Configuración incompleta',
                'errors' => $validation['errors']
            ];
        }
        
        if (!$this->initializeMailer()) {
            return [
                'success' => false,
                'message' => 'Error inicializando mailer: ' . $this->lastError
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
                    'success' => true,
                    'message' => 'Conexión SMTP exitosa',
                    'debug' => $debug_output
                ];
            }
            
            return [
                'success' => false,
                'message' => 'No se pudo establecer conexión',
                'debug' => $debug_output
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar email de prueba
     * 
     * @param string $to_email Email de destino
     * @return array Resultado del envío
     */
    public function sendTestEmail($to_email) {
        $subject = '✅ Prueba de Email - Sistema de Gestión de Tareas';
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                .success { color: #4CAF50; font-size: 48px; }
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
                        <li>Servidor SMTP: ' . htmlspecialchars($this->config->get('smtp_host')) . '</li>
                        <li>Puerto: ' . htmlspecialchars($this->config->get('smtp_port')) . '</li>
                        <li>Encriptación: ' . htmlspecialchars($this->config->get('smtp_encryption')) . '</li>
                        <li>Fecha/Hora: ' . date('d/m/Y H:i:s') . '</li>
                    </ul>
                    <p style="color: #666; font-size: 12px; margin-top: 20px;">
                        Si recibiste este correo, el sistema está configurado correctamente.
                    </p>
                </div>
            </div>
        </body>
        </html>';
        
        // Guardar modo actual
        $currentTestMode = $this->config->get('test_mode');
        
        // Temporalmente deshabilitar modo prueba para enviar realmente
        $this->config->set('test_mode', '0');
        
        // Asegurarse de que el servicio esté habilitado temporalmente
        $currentEnabled = $this->config->get('email_enabled');
        $this->config->set('email_enabled', '1');
        
        $result = $this->sendImmediate($to_email, 'Usuario de Prueba', $subject, $html);
        
        // Restaurar configuración
        $this->config->set('test_mode', $currentTestMode);
        $this->config->set('email_enabled', $currentEnabled);
        
        if ($result) {
            return [
                'success' => true,
                'message' => "Email de prueba enviado exitosamente a: $to_email"
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Error enviando email de prueba: ' . $this->getLastError()
        ];
    }
    
    /**
     * Obtener estadísticas de la cola
     * @return array
     */
    public function getQueueStats() {
        $stats = [
            'pendientes' => 0,
            'enviados_hoy' => 0,
            'fallidos_hoy' => 0,
            'total_cola' => 0
        ];
        
        $result = $this->conn->query("
            SELECT 
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                SUM(CASE WHEN estado = 'enviado' AND DATE(enviado_at) = CURDATE() THEN 1 ELSE 0 END) as enviados_hoy,
                SUM(CASE WHEN estado = 'fallido' AND DATE(fecha_creacion) = CURDATE() THEN 1 ELSE 0 END) as fallidos_hoy,
                COUNT(*) as total_cola
            FROM tbl_email_queue
        ");
        
        if ($result && $row = $result->fetch_assoc()) {
            $stats = array_merge($stats, array_map('intval', $row));
        }
        
        return $stats;
    }
    
    /**
     * Obtener instancia de configuración
     * @return EmailConfig
     */
    public function getConfig() {
        return $this->config;
    }
}