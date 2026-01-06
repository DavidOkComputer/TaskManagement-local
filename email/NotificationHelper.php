<?php
/*NotificationHelper.php Clase auxiliar para envío de notificaciones */
require_once __DIR__ . "/EmailService.php";
require_once __DIR__ . "/EmailTemplate.php";

class NotificationHelper
{
  private $conn;
  private $emailService;
  private $templates;
  private $systemUrl;

  // Constantes para tipos de notificación
  const TIPO_PROYECTO_ASIGNADO = "proyecto_asignado";
  const TIPO_TAREA_ASIGNADA = "tarea_asignada";
  const TIPO_TAREA_COMPLETADA = "tarea_completada";
  const TIPO_PROYECTO_VENCIDO = "proyecto_vencido";
  const TIPO_TAREA_VENCIDA = "tarea_vencida";
  const TIPO_INACTIVIDAD_PROYECTO = "inactividad_proyecto";
  const TIPO_INACTIVIDAD_TAREA = "inactividad_tarea";
  const TIPO_RECORDATORIO = "recordatorio";

  // Tipos de referencia
  const REF_PROYECTO = "proyecto";
  const REF_TAREA = "tarea";
  const REF_OBJETIVO = "objetivo";
  public function __construct($conn)
  {
    $this->conn = $conn;
    $this->emailService = new EmailService($conn);
    $this->templates = new EmailTemplates();
    $this->systemUrl = $this->emailService->getConfig(
      "system_url",
      "http://10.109.17.87/projectManagement",
    );
  }

  public function notificarProyectoVencido(
    $id_proyecto,
    $id_usuario,
    $nombre_proyecto,
  ) {
    // Verificar si ya se envió hoy
    if (
      $this->notificacionYaEnviada(
        self::TIPO_PROYECTO_VENCIDO,
        $id_proyecto,
        $id_usuario,
      )
    ) {
      return ["success" => false, "message" => "Notificación ya enviada hoy"];
    }

    $titulo = "Proyecto vencido";
    $mensaje = "El proyecto '{$nombre_proyecto}' ha superado su fecha de entrega y requiere atención inmediata.";
    $result = $this->crearNotificacion(
      $id_usuario,
      self::TIPO_PROYECTO_VENCIDO,
      $titulo,
      $mensaje,
      $id_proyecto,
      self::REF_PROYECTO,
    );

    if ($result["success"]) {
      $this->registrarNotificacionEnviada(
        self::TIPO_PROYECTO_VENCIDO,
        $id_proyecto,
        $id_usuario,
      );
    }
    return $result;
  }

  public function notificarTareaVencida(
    $id_tarea,
    $id_usuario,
    $nombre_tarea,
    $nombre_proyecto = null,
  ) {
    // Verificar si ya se envió hoy
    if (
      $this->notificacionYaEnviada(
        self::TIPO_TAREA_VENCIDA,
        $id_tarea,
        $id_usuario,
      )
    ) {
      return ["success" => false, "message" => "Notificación ya enviada hoy"];
    }

    $titulo = "Tarea vencida";
    $mensaje = "La tarea '{$nombre_tarea}' ha superado su fecha de entrega.";

    if ($nombre_proyecto) {
      $mensaje .= " (Proyecto: {$nombre_proyecto})";
    }

    $result = $this->crearNotificacion(
      $id_usuario,
      self::TIPO_TAREA_VENCIDA,
      $titulo,
      $mensaje,
      $id_tarea,
      self::REF_TAREA,
    );

    if ($result["success"]) {
      $this->registrarNotificacionEnviada(
        self::TIPO_TAREA_VENCIDA,
        $id_tarea,
        $id_usuario,
      );
    }
    return $result;
  }

  public function notificarInactividadProyecto(
    $id_proyecto,
    $id_usuario,
    $nombre_proyecto,
    $dias_inactividad,
  ) {
    // Usar tipo con días para evitar spam (solo una notificación por umbral de días)
    $tipo_evento = self::TIPO_INACTIVIDAD_PROYECTO . "_" . $dias_inactividad;
    if ($this->notificacionYaEnviada($tipo_evento, $id_proyecto, $id_usuario)) {
      return [
        "success" => false,
        "message" => "Notificación de inactividad ya enviada",
      ];
    }

    $titulo = "Proyecto sin actividad";
    $mensaje = "El proyecto '{$nombre_proyecto}' lleva {$dias_inactividad} días sin avances. Por favor revisa su estado.";

    $result = $this->crearNotificacion(
      $id_usuario,
      self::TIPO_INACTIVIDAD_PROYECTO,
      $titulo,
      $mensaje,
      $id_proyecto,
      self::REF_PROYECTO,
    );

    if ($result["success"]) {
      $this->registrarNotificacionEnviada(
        $tipo_evento,
        $id_proyecto,
        $id_usuario,
      );
    }
    return $result;
  }

  public function notificarInactividadTarea(
    $id_tarea,
    $id_usuario,
    $nombre_tarea,
    $dias_inactividad,
  ) {
    $tipo_evento = self::TIPO_INACTIVIDAD_TAREA . "_" . $dias_inactividad;

    if ($this->notificacionYaEnviada($tipo_evento, $id_tarea, $id_usuario)) {
      return [
        "success" => false,
        "message" => "Notificación de inactividad ya enviada",
      ];
    }

    $titulo = "Tarea sin actividad";
    $mensaje = "La tarea '{$nombre_tarea}' lleva {$dias_inactividad} días sin avances. Por favor actualiza su estado.";
    $result = $this->crearNotificacion(
      $id_usuario,
      self::TIPO_INACTIVIDAD_TAREA,
      $titulo,
      $mensaje,
      $id_tarea,
      self::REF_TAREA,
    );
    if ($result["success"]) {
      $this->registrarNotificacionEnviada($tipo_evento, $id_tarea, $id_usuario);
    }
    return $result;
  }

  public function crearNotificacion(
    $id_usuario,
    $tipo,
    $titulo,
    $mensaje,
    $id_referencia = null,
    $tipo_referencia = null,
  ) {
    try {
      //Crear notificación dentro de la app
      $stmt = $this->conn->prepare(" 
                INSERT INTO tbl_notificaciones  
                (id_usuario, tipo, titulo, mensaje, id_referencia, tipo_referencia, leido, fecha_creacion) 
                VALUES (?, ?, ?, ?, ?, ?, 0, NOW()) 
            ");

      $stmt->bind_param(
        "isssis",
        $id_usuario,
        $tipo,
        $titulo,
        $mensaje,
        $id_referencia,
        $tipo_referencia,
      );
      $stmt->execute();
      $id_notificacion = $stmt->insert_id;
      $stmt->close();

      //Obtener datos del usuario para email
      $stmt = $this->conn->prepare(" 
                SELECT u.nombre, u.apellido, u.e_mail  
                FROM tbl_usuarios u  
                WHERE u.id_usuario = ? 
            ");

      $stmt->bind_param("i", $id_usuario);
      $stmt->execute();
      $usuario = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      $email_enviado = false;

      if ($usuario && !empty($usuario["e_mail"])) {
        //Verificar preferencias del usuario para este tipo de notificación
        if ($this->verificarPreferenciaEmail($id_usuario, $tipo)) {
          //Generar HTML del email
          $html = $this->generarEmailHTML(
            $tipo,
            $titulo,
            $mensaje,
            $usuario["nombre"],
            $id_referencia,
            $tipo_referencia,
          );

          //Determinar prioridad según tipo
          $prioridad = $this->obtenerPrioridadEmail($tipo);
          //Encolar email
          $email_enviado = $this->emailService->queueEmail(
            $usuario["e_mail"],
            trim($usuario["nombre"] . " " . $usuario["apellido"]),
            $this->limpiarTituloEmail($titulo),
            $html,
            $tipo,
            $tipo_referencia,
            $id_referencia,
            $prioridad,
          );

          if ($email_enviado) {
            error_log("Email encolado para usuario {$id_usuario}: {$titulo}");
          }
        }
      }

      return [
        "success" => true,
        "id_notificacion" => $id_notificacion,
        "email_encolado" => $email_enviado,
      ];
    } catch (Exception $e) {
      error_log("Error creando notificación: " . $e->getMessage());
      return ["success" => false, "message" => $e->getMessage()];
    }
  }

  public function notificacionYaEnviada($tipo, $id_referencia, $id_usuario)
  {
    $stmt = $this->conn->prepare(" 
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

  public function registrarNotificacionEnviada(
    $tipo,
    $id_referencia,
    $id_usuario,
  ) {
    $stmt = $this->conn->prepare(" 
            INSERT INTO tbl_notificaciones_enviadas  
            (tipo_evento, id_referencia, id_usuario, fecha_envio) 
            VALUES (?, ?, ?, NOW()) 
        ");

    $stmt->bind_param("sii", $tipo, $id_referencia, $id_usuario);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
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

  public function notifyTaskAssigned($tarea_id, $asignado_por_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT t.id_tarea, t.nombre as tarea_nombre, t.descripcion as tarea_descripcion, 
                   t.fecha_cumplimiento, t.id_participante, 
                   u.id_usuario, u.nombre as usuario_nombre, u.apellido as usuario_apellido, u.e_mail as usuario_email, 
                   p.nombre as proyecto_nombre, 
                   a.nombre as asignador_nombre, a.apellido as asignador_apellido 
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
    if (!$task || empty($task["usuario_email"])) {
      return false;
    }

    // No notificar si se asigna a sí mismo
    if ($task["id_usuario"] == $asignado_por_id) {
      return false;
    }

    // Verificar preferencias
    if (
      !$this->verificarPreferenciaEmail(
        $task["id_usuario"],
        "notif_tarea_asignada",
      )
    ) {
      return false;
    }

    $titulo = "Nueva tarea asignada: " . $task["tarea_nombre"];
    $mensaje = "Se te ha asignado la tarea '{$task["tarea_nombre"]}'";

    if ($task["proyecto_nombre"]) {
      $mensaje .= " en el proyecto '{$task["proyecto_nombre"]}'";
    }

    // Crear notificación dentro de la app
    $this->crearNotificacionSimple(
      $task["id_usuario"],
      self::TIPO_TAREA_ASIGNADA,
      $titulo,
      $mensaje,
      $tarea_id,
      self::REF_TAREA,
    );

    // Generar y encolar email
    $html = $this->templates->render("tarea_asignada", [
      "SUBJECT" => $titulo,
      "NOMBRE_USUARIO" => $task["usuario_nombre"],
      "NOMBRE_TAREA" => $task["tarea_nombre"],
      "DESCRIPCION_TAREA" => $task["tarea_descripcion"] ?? "Sin descripción",
      "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
      "FECHA_VENCIMIENTO" => $task["fecha_cumplimiento"]
        ? date("d/m/Y", strtotime($task["fecha_cumplimiento"]))
        : "Sin fecha definida",
      "ASIGNADO_POR" => trim(
        $task["asignador_nombre"] . " " . $task["asignador_apellido"],
      ),
      "URL_SISTEMA" => $this->systemUrl,
    ]);

    return $this->emailService->queueEmail(
      $task["usuario_email"],
      $task["usuario_nombre"] . " " . $task["usuario_apellido"],
      $titulo,
      $html,
      self::TIPO_TAREA_ASIGNADA,
      self::REF_TAREA,
      $tarea_id,
      2, // Alta prioridad
    );
  }

  public function notifyProjectAssigned($proyecto_id, $usuario_ids, $creador_id)
  {
    if (!is_array($usuario_ids)) {
      $usuario_ids = [$usuario_ids];
    }

    $resultados = [];
    foreach ($usuario_ids as $usuario_id) {
      // No notificar al creador

      if ($usuario_id == $creador_id) {
        continue;
      }

      $stmt = $this->conn->prepare(" 
                SELECT p.id_proyecto, p.nombre as proyecto_nombre, p.descripcion as proyecto_descripcion, 
                       p.fecha_cumplimiento, 
                       d.nombre as departamento_nombre, 
                       u.nombre as usuario_nombre, u.apellido as usuario_apellido, u.e_mail as usuario_email, 
                       c.nombre as creador_nombre, c.apellido as creador_apellido 
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

      if (!$project || empty($project["usuario_email"])) {
        $resultados[$usuario_id] = false;
        continue;
      }

      // Verificar preferencias
      if (
        !$this->verificarPreferenciaEmail(
          $usuario_id,
          "notif_proyecto_asignado",
        )
      ) {
        $resultados[$usuario_id] = false;
        continue;
      }

      $titulo = "Asignado a proyecto: " . $project["proyecto_nombre"];
      $mensaje = "Has sido asignado al proyecto '{$project["proyecto_nombre"]}' en el departamento '{$project["departamento_nombre"]}'";

      // Crear notificación in-app

      $this->crearNotificacionSimple(
        $usuario_id,
        self::TIPO_PROYECTO_ASIGNADO,
        $titulo,
        $mensaje,
        $proyecto_id,
        self::REF_PROYECTO,
      );

      // Generar y encolar email
      $html = $this->templates->render("proyecto_asignado", [
        "SUBJECT" => $titulo,
        "NOMBRE_USUARIO" => $project["usuario_nombre"],
        "NOMBRE_PROYECTO" => $project["proyecto_nombre"],
        "DESCRIPCION_PROYECTO" =>
          $project["proyecto_descripcion"] ?? "Sin descripción",
        "NOMBRE_DEPARTAMENTO" => $project["departamento_nombre"],
        "FECHA_VENCIMIENTO" => $project["fecha_cumplimiento"]
          ? date("d/m/Y", strtotime($project["fecha_cumplimiento"]))
          : "Sin fecha definida",
        "CREADO_POR" => trim(
          $project["creador_nombre"] . " " . $project["creador_apellido"],
        ),
        "URL_SISTEMA" => $this->systemUrl,
      ]);

      $resultados[$usuario_id] = $this->emailService->queueEmail(
        $project["usuario_email"],
        $project["usuario_nombre"] . " " . $project["usuario_apellido"],
        $titulo,
        $html,
        self::TIPO_PROYECTO_ASIGNADO,
        self::REF_PROYECTO,
        $proyecto_id,
        3,
      );
    }
    return $resultados;
  }

  public function notifyTaskCompleted($tarea_id, $completada_por_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT t.id_tarea, t.nombre as tarea_nombre, t.id_creador, 
                   p.nombre as proyecto_nombre, 
                   u.nombre as creador_nombre, u.apellido as creador_apellido, u.e_mail as creador_email, 
                   c.nombre as completador_nombre, c.apellido as completador_apellido 
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

    if (!$task || empty($task["creador_email"])) {
      return false;
    }

    // No notificar si el creador completa su propia tarea
    if ($task["id_creador"] == $completada_por_id) {
      return false;
    }

    // Verificar preferencias
    if (
      !$this->verificarPreferenciaEmail(
        $task["id_creador"],
        "notif_tarea_completada",
      )
    ) {
      return false;
    }

    $titulo = "Tarea completada: " . $task["tarea_nombre"];
    $mensaje =
      "La tarea '{$task["tarea_nombre"]}' ha sido completada por " .
      trim($task["completador_nombre"] . " " . $task["completador_apellido"]);

    // Crear notificación dentor de la app
    $this->crearNotificacionSimple(
      $task["id_creador"],
      self::TIPO_TAREA_COMPLETADA,
      $titulo,
      $mensaje,
      $tarea_id,
      self::REF_TAREA,
    );

    // Generar y encolar email
    $html = $this->templates->render("tarea_completada", [
      "SUBJECT" => $titulo,
      "NOMBRE_USUARIO" => $task["creador_nombre"],
      "NOMBRE_TAREA" => $task["tarea_nombre"],
      "NOMBRE_PROYECTO" => $task["proyecto_nombre"] ?? "Sin proyecto",
      "COMPLETADA_POR" => trim(
        $task["completador_nombre"] . " " . $task["completador_apellido"],
      ),
      "FECHA_COMPLETADO" => date("d/m/Y H:i"),
      "URL_SISTEMA" => $this->systemUrl,
    ]);

    return $this->emailService->queueEmail(
      $task["creador_email"],
      $task["creador_nombre"] . " " . $task["creador_apellido"],
      $this->limpiarTituloEmail($titulo),
      $html,
      self::TIPO_TAREA_COMPLETADA,
      self::REF_TAREA,
      $tarea_id,
      5, // Prioridad normal
    );
  }

  private function crearNotificacionSimple(
    $id_usuario,
    $tipo,
    $titulo,
    $mensaje,
    $id_referencia = null,
    $tipo_referencia = null,
  ) {
    $stmt = $this->conn->prepare(" 
            INSERT INTO tbl_notificaciones  
            (id_usuario, tipo, titulo, mensaje, id_referencia, tipo_referencia, leido, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW()) 
        ");

    $stmt->bind_param(
      "isssis",
      $id_usuario,
      $tipo,
      $titulo,
      $mensaje,
      $id_referencia,
      $tipo_referencia,
    );

    $result = $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    return $result ? $id : false;
  }

  private function verificarPreferenciaEmail($id_usuario, $tipo)
  {
    // Mapear tipo de notificación a columna de preferencia
    $preferenceMap = [
      self::TIPO_PROYECTO_VENCIDO => "notif_proyecto_vencido",
      self::TIPO_TAREA_VENCIDA => "notif_tarea_vencida",
      self::TIPO_INACTIVIDAD_PROYECTO => "notif_inactividad",
      self::TIPO_INACTIVIDAD_TAREA => "notif_inactividad",
      self::TIPO_PROYECTO_ASIGNADO => "notif_proyecto_asignado",
      self::TIPO_TAREA_ASIGNADA => "notif_tarea_asignada",
      self::TIPO_TAREA_COMPLETADA => "notif_tarea_completada",
      "notif_tarea_asignada" => "notif_tarea_asignada",
      "notif_proyecto_asignado" => "notif_proyecto_asignado",
      "notif_tarea_completada" => "notif_tarea_completada",
    ];
    $column = $preferenceMap[$tipo] ?? null;

    // Si no hay mapeo, por defecto enviar

    if (!$column) {
      return true;
    }

    // Verificar si la columna existe en la tabla
    $checkColumn = $this->conn->query(
      "SHOW COLUMNS FROM tbl_notificacion_preferencias LIKE '{$column}'",
    );

    if ($checkColumn->num_rows === 0) {
      return true; // Columna no existe, enviar por defecto
    }

    $stmt = $this->conn->prepare(
      "SELECT {$column} FROM tbl_notificacion_preferencias WHERE id_usuario = ?",
    );

    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Si no hay preferencias configuradas, asumir habilitado

    if (!$result) {
      return true;
    }
    return $result[$column] == 1;
  }

  private function obtenerPrioridadEmail($tipo)
  {
    $prioridades = [
      self::TIPO_PROYECTO_VENCIDO => 1, // Máxima prioridad
      self::TIPO_TAREA_VENCIDA => 1,
      self::TIPO_TAREA_ASIGNADA => 2,
      self::TIPO_PROYECTO_ASIGNADO => 3,
      self::TIPO_INACTIVIDAD_PROYECTO => 4,
      self::TIPO_INACTIVIDAD_TAREA => 4,
      self::TIPO_TAREA_COMPLETADA => 5,
      self::TIPO_RECORDATORIO => 3,
    ];
    return $prioridades[$tipo] ?? 5;
  }


  private function limpiarTituloEmail($titulo)
  {
    return preg_replace('/[\x{1F600}-\x{1F64F}]/u', '', $titulo);
    return $titulo;
  }

  private function generarEmailHTML(
    $tipo,
    $titulo,
    $mensaje,
    $nombre_usuario,
    $id_referencia = null,
    $tipo_referencia = null,
  ) {
    // Determinar color según tipo

    $colores = [
      self::TIPO_PROYECTO_VENCIDO => "#dc3545", // Rojo
      self::TIPO_TAREA_VENCIDA => "#dc3545",
      self::TIPO_INACTIVIDAD_PROYECTO => "#ffc107", // Amarillo
      self::TIPO_INACTIVIDAD_TAREA => "#ffc107",
      self::TIPO_TAREA_COMPLETADA => "#009b4a", // Verde
      self::TIPO_PROYECTO_ASIGNADO => "#009b4a", // Azul
      self::TIPO_TAREA_ASIGNADA => "#009b4a",
    ];

    $color = $colores[$tipo] ?? "#009b4a";

    // Generar URL específica si hay referencia
    $url_accion = $this->systemUrl;

    if ($id_referencia && $tipo_referencia) {
      if ($tipo_referencia === self::REF_PROYECTO) {
        $url_accion = $this->systemUrl;
      } elseif ($tipo_referencia === self::REF_TAREA) {
        $url_accion = $this->systemUrl;
      }
    }

    return ' 
        <!DOCTYPE html> 
        <html> 
        <head> 
            <meta charset="UTF-8"> 
            <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
        </head> 
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4;"> 
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;"> 
                <div style="background: ' .
      $color .
      '; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0;"> 
                    <h1 style="margin: 0; font-size: 24px;">Sistema de Gestión de Tareas</h1> 
                </div> 
                <div style="background: #ffffff; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"> 
                    <p style="font-size: 16px;">Hola <strong>' .
      htmlspecialchars($nombre_usuario) .
      '</strong>,</p> 
                    <div style="background: #f8f9fa; border-left: 4px solid ' .
      $color .
      '; padding: 15px; margin: 20px 0;"> 
                        <h2 style="margin: 0 0 10px 0; color: ' .
      $color .
      '; font-size: 18px;">' .
      htmlspecialchars($this->limpiarTituloEmail($titulo)) .
      '</h2> 
                        <p style="margin: 0; font-size: 14px;">' .
      htmlspecialchars($mensaje) .
      '</p> 
                    </div> 
                    <p style="text-align: center; margin-top: 25px;"> 
                        <a href="' .
      htmlspecialchars($url_accion) .
      '"  
                           style="display: inline-block; background: ' .
      $color .
      '; color: white; padding: 12px 30px;  
                                  text-decoration: none; border-radius: 5px; font-weight: bold;"> 
                            Ver en el Sistema 
                        </a> 
                    </p> 
                    <hr style="border: none; border-top: 1px solid #eee; margin: 25px 0;"> 
                    <p style="color: #666; font-size: 12px; text-align: center; margin: 0;"> 
                        Este es un mensaje automático del Sistema de Gestión de Tareas.<br> 
                        Por favor no responda a este correo. 
                    </p> 
                </div> 
            </div> 
        </body> 
        </html>';
  }

  public function getUserRole($usuario_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT ur.id_rol, r.nombre as nombre_rol, r.descripcion as descripcion_rol, 
                   ur.id_departamento, d.nombre as nombre_departamento, ur.es_principal 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
            WHERE ur.id_usuario = ? AND ur.activo = 1 
            ORDER BY ur.es_principal DESC 
            LIMIT 1 
        ");

    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
  }

  public function getAllUserRoles($usuario_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT ur.id_usuario_roles, ur.id_rol, r.nombre as nombre_rol, r.descripcion as descripcion_rol, 
                   ur.id_departamento, d.nombre as nombre_departamento, ur.es_principal, ur.activo 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
            WHERE ur.id_usuario = ? AND ur.activo = 1 
            ORDER BY ur.es_principal DESC, d.nombre ASC 
        ");

    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $result;
  }

  public function getUserRoleInDepartment($usuario_id, $id_departamento)
  {
    $stmt = $this->conn->prepare(" 
            SELECT ur.id_rol, r.nombre as nombre_rol, r.descripcion as descripcion_rol, ur.es_principal 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            WHERE ur.id_usuario = ? AND ur.id_departamento = ? AND ur.activo = 1 
        ");

    $stmt->bind_param("ii", $usuario_id, $id_departamento);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
  }

  public function checkUserPermission($usuario_id, $permiso)
  {
    $role_info = $this->getUserRole($usuario_id);
    if (!$role_info) {
      return false;
    }

    $id_rol = $role_info["id_rol"];
    $permisos = [
      1 => [
        "crear_usuarios",
        "crear_departamentos",
        "asignar_proyectos",
        "asignar_tareas",
        "ver_todo",
        "editar_todo",
        "eliminar_todo",
      ],

      2 => [
        "asignar_proyectos",
        "asignar_tareas",
        "crear_objetivos",
        "ver_departamento",
        "editar_departamento",
      ],

      3 => ["crear_tareas", "ver_asignado", "editar_propio"],
    ];
    return isset($permisos[$id_rol]) && in_array($permiso, $permisos[$id_rol]);
  }

  public function checkUserPermissionInDepartment(
    $usuario_id,
    $permiso,
    $id_departamento,
  ) {
    $role_info = $this->getUserRoleInDepartment($usuario_id, $id_departamento);

    if (!$role_info) {
      $primary_role = $this->getUserRole($usuario_id);

      if ($primary_role && $primary_role["id_rol"] == 1) {
        return true;
      }
      return false;
    }

    $id_rol = $role_info["id_rol"];

    $permisos = [
      1 => [
        "crear_usuarios",
        "crear_departamentos",
        "asignar_proyectos",
        "asignar_tareas",
        "ver_todo",
        "editar_todo",
        "eliminar_todo",
      ],

      2 => [
        "asignar_proyectos",
        "asignar_tareas",
        "crear_objetivos",
        "ver_departamento",
        "editar_departamento",
      ],

      3 => ["crear_tareas", "ver_asignado", "editar_propio"],
    ];

    return isset($permisos[$id_rol]) && in_array($permiso, $permisos[$id_rol]);
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

  public function getEmailService()
  {
    return $this->emailService;
  }

  public function getTemplates()
  {
    return $this->templates;
  }

  public function getSystemUrl()
  {
    return $this->systemUrl;
  }
}
