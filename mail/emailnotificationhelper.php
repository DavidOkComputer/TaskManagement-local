<?php
/**
 * NotificationHelper.php
 * Clase auxiliar para facilitar el envío de notificaciones desde el código existente
 * 
 * @package TaskManagement\Email
 * @author Sistema de Tareas
 */

require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/EmailTemplates.php';

class NotificationHelper {
    private $conn;
    private $emailService;
    private $templates;
    private $systemUrl;
    
    /**
     * Constructor
     * @param mysqli $conn Conexión a la base de datos
     */
    public function __construct($conn) {
        $this->conn = $conn;
        $this->emailService = new EmailService($conn);
        $this->templates = new EmailTemplates();
        $this->systemUrl = $this->emailService->getConfig()->get('system_url', 'http://localhost/task_management');
    }
    
    /**
     * Notificar asignación de tarea
     * 
     * @param int $tarea_id ID de la tarea
     * @param int $asignado_por_id ID del usuario que asigna
     * @return bool|int ID del email en cola o false
     */
    public function notifyTaskAssigned($tarea_id, $asignado_por_id) {
        // Obtener información de la tarea y usuario
        $stmt = $this->conn->prepare("
            SELECT 
                t.id_tarea,
                t.nombre as tarea_nombre,
                t.descripcion as tarea_descripcion,
                t.fecha_cumplimiento,
                u.id_usuario,
                u.nombre as usuario_nombre,
                u.apellido as usuario_apellido,
                u.e_mail as usuario_email,
                p.nombre as proyecto_nombre,
                a.nombre as asignador_nombre,
                a.apellido as asignador_apellido
            FROM tbl_tareas t
            JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            LEFT JOIN tbl_usuarios a ON a.id_usuario = ?
            WHERE t.id_tarea = ?
        ");
        
        $stmt->bind_param("ii", $asignado_por_id, $tarea_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$task || empty($task['usuario_email'])) {
            return false;
        }
        
        // Verificar preferencias del usuario
        if (!$this->checkUserPreference($task['id_usuario'], 'notif_tarea_asignada')) {
            return false;
        }
        
        // Renderizar email
        $html = $this->templates->render('tarea_asignada', [
            'SUBJECT' => 'Nueva tarea asignada: ' . $task['tarea_nombre'],
            'NOMBRE_USUARIO' => $task['usuario_nombre'],
            'NOMBRE_TAREA' => $task['tarea_nombre'],
            'DESCRIPCION_TAREA' => $task['tarea_descripcion'] ?? 'Sin descripción',
            'NOMBRE_PROYECTO' => $task['proyecto_nombre'] ?? 'Sin proyecto',
            'FECHA_VENCIMIENTO' => $task['fecha_cumplimiento'] 
                ? date('d/m/Y', strtotime($task['fecha_cumplimiento'])) 
                : 'Sin fecha definida',
            'ASIGNADO_POR' => trim($task['asignador_nombre'] . ' ' . $task['asignador_apellido']),
            'URL_SISTEMA' => $this->systemUrl
        ]);
        
        return $this->emailService->queueEmail(
            $task['usuario_email'],
            $task['usuario_nombre'] . ' ' . $task['usuario_apellido'],
            '📋 Nueva tarea asignada: ' . $task['tarea_nombre'],
            $html,
            'tarea_asignada',
            'tarea',
            $tarea_id,
            2 // Alta prioridad
        );
    }
    
    /**
     * Notificar asignación a proyecto
     * 
     * @param int $proyecto_id ID del proyecto
     * @param int $usuario_id ID del usuario asignado
     * @param int $creador_id ID del usuario que crea/asigna
     * @return bool|int
     */
    public function notifyProjectAssigned($proyecto_id, $usuario_id, $creador_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                p.id_proyecto,
                p.nombre as proyecto_nombre,
                p.descripcion as proyecto_descripcion,
                p.fecha_cumplimiento,
                d.nombre as departamento_nombre,
                u.nombre as usuario_nombre,
                u.apellido as usuario_apellido,
                u.e_mail as usuario_email,
                c.nombre as creador_nombre,
                c.apellido as creador_apellido
            FROM tbl_proyectos p
            JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            JOIN tbl_usuarios u ON u.id_usuario = ?
            LEFT JOIN tbl_usuarios c ON c.id_usuario = ?
            WHERE p.id_proyecto = ?
        ");
        
        $stmt->bind_param("iii", $usuario_id, $creador_id, $proyecto_id);
        $stmt->execute();
        $project = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$project || empty($project['usuario_email'])) {
            return false;
        }
        
        if (!$this->checkUserPreference($usuario_id, 'notif_proyecto_asignado')) {
            return false;
        }
        
        $html = $this->templates->render('proyecto_asignado', [
            'SUBJECT' => 'Asignado a proyecto: ' . $project['proyecto_nombre'],
            'NOMBRE_USUARIO' => $project['usuario_nombre'],
            'NOMBRE_PROYECTO' => $project['proyecto_nombre'],
            'DESCRIPCION_PROYECTO' => $project['proyecto_descripcion'] ?? 'Sin descripción',
            'NOMBRE_DEPARTAMENTO' => $project['departamento_nombre'],
            'FECHA_VENCIMIENTO' => $project['fecha_cumplimiento'] 
                ? date('d/m/Y', strtotime($project['fecha_cumplimiento'])) 
                : 'Sin fecha definida',
            'CREADO_POR' => trim($project['creador_nombre'] . ' ' . $project['creador_apellido']),
            'URL_SISTEMA' => $this->systemUrl
        ]);
        
        return $this->emailService->queueEmail(
            $project['usuario_email'],
            $project['usuario_nombre'] . ' ' . $project['usuario_apellido'],
            '📁 Asignado a proyecto: ' . $project['proyecto_nombre'],
            $html,
            'proyecto_asignado',
            'proyecto',
            $proyecto_id,
            3
        );
    }
    
    /**
     * Notificar tarea completada al creador/gerente
     * 
     * @param int $tarea_id ID de la tarea
     * @param int $completada_por_id ID del usuario que completó
     * @return bool|int
     */
    public function notifyTaskCompleted($tarea_id, $completada_por_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                t.id_tarea,
                t.nombre as tarea_nombre,
                t.id_creador,
                p.nombre as proyecto_nombre,
                u.nombre as creador_nombre,
                u.apellido as creador_apellido,
                u.e_mail as creador_email,
                c.nombre as completador_nombre,
                c.apellido as completador_apellido
            FROM tbl_tareas t
            JOIN tbl_usuarios u ON t.id_creador = u.id_usuario
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            LEFT JOIN tbl_usuarios c ON c.id_usuario = ?
            WHERE t.id_tarea = ?
        ");
        
        $stmt->bind_param("ii", $completada_por_id, $tarea_id);
        $stmt->execute();
        $task = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$task || empty($task['creador_email'])) {
            return false;
        }
        
        // No notificar si el creador es quien completa
        if ($task['id_creador'] == $completada_por_id) {
            return false;
        }
        
        if (!$this->checkUserPreference($task['id_creador'], 'notif_tarea_completada')) {
            return false;
        }
        
        $html = $this->templates->render('tarea_completada', [
            'SUBJECT' => 'Tarea completada: ' . $task['tarea_nombre'],
            'NOMBRE_USUARIO' => $task['creador_nombre'],
            'NOMBRE_TAREA' => $task['tarea_nombre'],
            'NOMBRE_PROYECTO' => $task['proyecto_nombre'] ?? 'Sin proyecto',
            'COMPLETADA_POR' => trim($task['completador_nombre'] . ' ' . $task['completador_apellido']),
            'FECHA_COMPLETADO' => date('d/m/Y H:i'),
            'URL_SISTEMA' => $this->systemUrl
        ]);
        
        return $this->emailService->queueEmail(
            $task['creador_email'],
            $task['creador_nombre'] . ' ' . $task['creador_apellido'],
            '✅ Tarea completada: ' . $task['tarea_nombre'],
            $html,
            'tarea_completada',
            'tarea',
            $tarea_id,
            5 // Prioridad normal
        );
    }
    
    /**
     * Verificar preferencia de notificación del usuario
     * 
     * @param int $usuario_id ID del usuario
     * @param string $preference Nombre de la preferencia
     * @return bool
     */
    private function checkUserPreference($usuario_id, $preference) {
        $stmt = $this->conn->prepare(
            "SELECT $preference FROM tbl_notificacion_preferencias WHERE id_usuario = ?"
        );
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Si no hay preferencias configuradas, asumir habilitado
        if (!$result) {
            return true;
        }
        
        return $result[$preference] == 1;
    }
    
    /**
     * Obtener instancia del servicio de email
     * @return EmailService
     */
    public function getEmailService() {
        return $this->emailService;
    }
    
    /**
     * Obtener instancia de templates
     * @return EmailTemplates
     */
    public function getTemplates() {
        return $this->templates;
    }
}