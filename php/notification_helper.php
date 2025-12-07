<?php
/* notification_helper.php para crear y manejar notificaciones*/

require_once(__DIR__ . '/db_config.php');

class NotificationHelper {
    
    private $conn;
    
    // Tipos de notificación disponibles
    const TIPO_TAREA_ASIGNADA = 'tarea_asignada';
    const TIPO_PROYECTO_ASIGNADO = 'proyecto_asignado';
    const TIPO_PROYECTO_VENCIDO = 'proyecto_vencido';
    const TIPO_TAREA_VENCIDA = 'tarea_vencida';
    const TIPO_INACTIVIDAD_PROYECTO = 'inactividad_proyecto';
    const TIPO_INACTIVIDAD_TAREA = 'inactividad_tarea';
    
    // Tipos de referencia
    const REF_PROYECTO = 'proyecto';
    const REF_TAREA = 'tarea';
    const REF_OBJETIVO = 'objetivo';
    
    public function __construct($connection = null) {
        if ($connection) {
            $this->conn = $connection;
        } else {
            $this->conn = getDBConnection();
        }
    }
    
    public function crearNotificacion($id_usuario, $tipo, $titulo, $mensaje, $id_referencia = null, $tipo_referencia = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO tbl_notificaciones 
                (id_usuario, tipo, titulo, mensaje, id_referencia, tipo_referencia) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            if (!$stmt) {
                throw new Exception("Error preparando consulta: " . $this->conn->error);
            }
            
            $stmt->bind_param("isssss", $id_usuario, $tipo, $titulo, $mensaje, $id_referencia, $tipo_referencia);
            
            if ($stmt->execute()) {
                $id_notificacion = $this->conn->insert_id;
                $stmt->close();
                return [
                    'success' => true,
                    'id_notificacion' => $id_notificacion,
                    'message' => 'Notificación creada exitosamente'
                ];
            } else {
                throw new Exception("Error ejecutando consulta: " . $stmt->error);
            }
            
        } catch (Exception $e) {
            error_log("NotificationHelper::crearNotificacion Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    public function notificacionYaEnviada($tipo_evento, $id_referencia, $id_usuario) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM tbl_notificaciones_enviadas 
                WHERE tipo_evento = ? AND id_referencia = ? AND id_usuario = ?
            ");
            $stmt->bind_param("sii", $tipo_evento, $id_referencia, $id_usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            $existe = $result->num_rows > 0;
            $stmt->close();
            return $existe;
        } catch (Exception $e) {
            error_log("NotificationHelper::notificacionYaEnviada Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function registrarNotificacionEnviada($tipo_evento, $id_referencia, $id_usuario) {
        try {
            $stmt = $this->conn->prepare("
                INSERT IGNORE INTO tbl_notificaciones_enviadas 
                (tipo_evento, id_referencia, id_usuario) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("sii", $tipo_evento, $id_referencia, $id_usuario);
            $stmt->execute();
            $stmt->close();
            return true;
        } catch (Exception $e) {
            error_log("NotificationHelper::registrarNotificacionEnviada Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function notificarTareaAsignada($id_tarea, $id_usuario_asignado, $nombre_tarea, $nombre_proyecto) {
        $titulo = "Nueva tarea asignada";
        $mensaje = "Se te ha asignado la tarea '{$nombre_tarea}' en el proyecto '{$nombre_proyecto}'.";
        
        return $this->crearNotificacion(
            $id_usuario_asignado,
            self::TIPO_TAREA_ASIGNADA,
            $titulo,
            $mensaje,
            $id_tarea,
            self::REF_TAREA
        );
    }
    
    public function notificarProyectoAsignado($id_proyecto, $id_usuario_asignado, $nombre_proyecto) {
        $titulo = "Nuevo proyecto asignado";
        $mensaje = "Se te ha asignado el proyecto '{$nombre_proyecto}'.";
        
        return $this->crearNotificacion(
            $id_usuario_asignado,
            self::TIPO_PROYECTO_ASIGNADO,
            $titulo,
            $mensaje,
            $id_proyecto,
            self::REF_PROYECTO
        );
    }
    
    public function notificarProyectoVencido($id_proyecto, $id_usuario, $nombre_proyecto) {
        // Verificar si ya se envió esta notificación
        if ($this->notificacionYaEnviada('proyecto_vencido', $id_proyecto, $id_usuario)) {
            return ['success' => false, 'message' => 'Notificación ya enviada'];
        }
        
        $titulo = "Proyecto vencido";
        $mensaje = "El proyecto '{$nombre_proyecto}' ha superado su fecha de entrega.";
        
        $result = $this->crearNotificacion(
            $id_usuario,
            self::TIPO_PROYECTO_VENCIDO,
            $titulo,
            $mensaje,
            $id_proyecto,
            self::REF_PROYECTO
        );
        
        if ($result['success']) {
            $this->registrarNotificacionEnviada('proyecto_vencido', $id_proyecto, $id_usuario);
        }
        
        return $result;
    }
    
    public function notificarInactividadProyecto($id_proyecto, $id_usuario, $nombre_proyecto, $dias_inactivo) {
        // Verificar si ya se envió esta notificación esta semana
        $tipo_evento = 'inactividad_proyecto_' . date('W'); // Semana del año
        if ($this->notificacionYaEnviada($tipo_evento, $id_proyecto, $id_usuario)) {
            return ['success' => false, 'message' => 'Notificación ya enviada esta semana'];
        }
        
        $titulo = "Proyecto sin actividad";
        $mensaje = "El proyecto '{$nombre_proyecto}' lleva {$dias_inactivo} días sin actividad.";
        
        $result = $this->crearNotificacion(
            $id_usuario,
            self::TIPO_INACTIVIDAD_PROYECTO,
            $titulo,
            $mensaje,
            $id_proyecto,
            self::REF_PROYECTO
        );
        
        if ($result['success']) {
            $this->registrarNotificacionEnviada($tipo_evento, $id_proyecto, $id_usuario);
        }
        
        return $result;
    }
    
    public function notificarInactividadTarea($id_tarea, $id_usuario, $nombre_tarea, $dias_inactivo) {
        $tipo_evento = 'inactividad_tarea_' . date('W');
        if ($this->notificacionYaEnviada($tipo_evento, $id_tarea, $id_usuario)) {
            return ['success' => false, 'message' => 'Notificación ya enviada esta semana'];
        }
        
        $titulo = "Tarea pendiente sin actividad";
        $mensaje = "La tarea '{$nombre_tarea}' lleva {$dias_inactivo} días pendiente sin cambios.";
        
        $result = $this->crearNotificacion(
            $id_usuario,
            self::TIPO_INACTIVIDAD_TAREA,
            $titulo,
            $mensaje,
            $id_tarea,
            self::REF_TAREA
        );
        
        if ($result['success']) {
            $this->registrarNotificacionEnviada($tipo_evento, $id_tarea, $id_usuario);
        }
        
        return $result;
    }
    
    public function obtenerNotificaciones($id_usuario, $solo_no_leidas = false, $limite = 20) {
        try {
            $sql = "SELECT * FROM tbl_notificaciones WHERE id_usuario = ?";
            if ($solo_no_leidas) {
                $sql .= " AND leido = 0";
            }
            $sql .= " ORDER BY fecha_creacion DESC LIMIT ?";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->bind_param("ii", $id_usuario, $limite);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notificaciones = [];
            while ($row = $result->fetch_assoc()) {
                $notificaciones[] = $row;
            }
            
            $stmt->close();
            return $notificaciones;
            
        } catch (Exception $e) {
            error_log("NotificationHelper::obtenerNotificaciones Error: " . $e->getMessage());
            return [];
        }
    }
    
    public function contarNoLeidas($id_usuario) {
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM tbl_notificaciones WHERE id_usuario = ? AND leido = 0");
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
            return (int)$row['total'];
        } catch (Exception $e) {
            error_log("NotificationHelper::contarNoLeidas Error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function marcarComoLeida($id_notificacion, $id_usuario) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE tbl_notificaciones 
                SET leido = 1, fecha_lectura = CURRENT_TIMESTAMP 
                WHERE id_notificacion = ? AND id_usuario = ?
            ");
            $stmt->bind_param("ii", $id_notificacion, $id_usuario);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            error_log("NotificationHelper::marcarComoLeida Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function marcarTodasComoLeidas($id_usuario) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE tbl_notificaciones 
                SET leido = 1, fecha_lectura = CURRENT_TIMESTAMP 
                WHERE id_usuario = ? AND leido = 0
            ");
            $stmt->bind_param("i", $id_usuario);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        } catch (Exception $e) {
            error_log("NotificationHelper::marcarTodasComoLeidas Error: " . $e->getMessage());
            return 0;
        }
    }
    
    public function eliminarNotificacion($id_notificacion, $id_usuario) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM tbl_notificaciones WHERE id_notificacion = ? AND id_usuario = ?");
            $stmt->bind_param("ii", $id_notificacion, $id_usuario);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected > 0;
        } catch (Exception $e) {
            error_log("NotificationHelper::eliminarNotificacion Error: " . $e->getMessage());
            return false;
        }
    }
    
    public static function getIconoPorTipo($tipo) {
        $iconos = [
            self::TIPO_TAREA_ASIGNADA => 'mdi-clipboard-check',
            self::TIPO_PROYECTO_ASIGNADO => 'mdi-folder-plus',
            self::TIPO_PROYECTO_VENCIDO => 'mdi-alert-circle',
            self::TIPO_TAREA_VENCIDA => 'mdi-clock-alert',
            self::TIPO_INACTIVIDAD_PROYECTO => 'mdi-sleep',
            self::TIPO_INACTIVIDAD_TAREA => 'mdi-timer-sand'
        ];
        return $iconos[$tipo] ?? 'mdi-bell';
    }
    
    public static function getColorPorTipo($tipo) {
        $colores = [
            self::TIPO_TAREA_ASIGNADA => 'primary',
            self::TIPO_PROYECTO_ASIGNADO => 'success',
            self::TIPO_PROYECTO_VENCIDO => 'danger',
            self::TIPO_TAREA_VENCIDA => 'danger',
            self::TIPO_INACTIVIDAD_PROYECTO => 'warning',
            self::TIPO_INACTIVIDAD_TAREA => 'warning'
        ];
        return $colores[$tipo] ?? 'secondary';
    }
}
?>