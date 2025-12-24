<?php
/* NotificationHelper.php Clase auxiliar para facilitar el envío de notificaciones */

require_once __DIR__ . "/EmailService.php";
require_once __DIR__ . "/EmailTemplate.php";

class NotificationHelpers
{
  private $conn;
  private $emailService;
  private $templates;
  private $systemUrl;
  public function __construct($conn)
  {
    $this->conn = $conn;
    $this->emailService = new EmailService($conn);
    $this->templates = new EmailTemplates();
    $this->systemUrl = $this->emailService
      ->getConfig()
      ->get("system_url", "http://localhost/task_management");
  }

  public function notifyTaskAssigned($tarea_id, $asignado_por_id)
  {
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
                p.id_departamento, 
                a.nombre as asignador_nombre,  
                a.apellido as asignador_apellido, 
                ur_user.id_rol as usuario_rol, 
                ur_asig.id_rol as asignador_rol 
            FROM tbl_tareas t 
            JOIN tbl_usuarios u ON t.id_participante = u.id_usuario 
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
            LEFT JOIN tbl_usuarios a ON a.id_usuario = ? 
            LEFT JOIN tbl_usuario_roles ur_user ON u.id_usuario = ur_user.id_usuario  
                AND ur_user.es_principal = 1 AND ur_user.activo = 1 
            LEFT JOIN tbl_usuario_roles ur_asig ON a.id_usuario = ur_asig.id_usuario  
                AND ur_asig.es_principal = 1 AND ur_asig.activo = 1 
            WHERE t.id_tarea = ? 
        ");

    $stmt->bind_param("ii", $asignado_por_id, $tarea_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$task || empty($task["usuario_email"])) {
      error_log(
        "NotificationHelper: No se encontró información de tarea o email del usuario",
      );
      return false;
    }

    // No enviar notificación si el usuario se asigna a sí mismo
    if ($task["id_usuario"] == $asignado_por_id) {
      return false;
    }

    // Verificar preferencias del usuario
    if (
      !$this->checkUserPreference($task["id_usuario"], "notif_tarea_asignada")
    ) {
      return false;
    }

    // Renderizar email
    $html = $this->templates->render("tarea_asignada", [
      "SUBJECT" => "Nueva tarea asignada: " . $task["tarea_nombre"],
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
      "Nueva tarea asignada: " . $task["tarea_nombre"],
      $html,
      "tarea_asignada",
      "tarea",
      $tarea_id,
      2, // Alta prioridad
    );
  }

  public function notifyProjectAssigned($proyecto_id, $usuario_ids, $creador_id)
  {
    // Convertir a array si es un solo ID
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
                    c.apellido as creador_apellido, 
                    ur_user.id_rol as usuario_rol, 
                    ur_creador.id_rol as creador_rol 
                FROM tbl_proyectos p 
                JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento 
                JOIN tbl_usuarios u ON u.id_usuario = ? 
                LEFT JOIN tbl_usuarios c ON c.id_usuario = ? 
                LEFT JOIN tbl_usuario_roles ur_user ON u.id_usuario = ur_user.id_usuario  
                    AND ur_user.es_principal = 1 AND ur_user.activo = 1 
                LEFT JOIN tbl_usuario_roles ur_creador ON c.id_usuario = ur_creador.id_usuario  
                    AND ur_creador.es_principal = 1 AND ur_creador.activo = 1 
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
      if (!$this->checkUserPreference($usuario_id, "notif_proyecto_asignado")) {
        $resultados[$usuario_id] = false;
        continue;
      }

      $html = $this->templates->render("proyecto_asignado", [
        "SUBJECT" => "Asignado a proyecto: " . $project["proyecto_nombre"],
        "NOMBRE_USUARIO" => $project["usuario_nombre"],
        "NOMBRE_PROYECTO" => $project["proyecto_nombre"],
        "DESCRIPCION_PROYECTO" => $project["proyecto_descripcion"] ?? "Sin descripción",
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
        "Asignado a proyecto: " . $project["proyecto_nombre"],
        $html,
        "proyecto_asignado",
        "proyecto",
        $proyecto_id,
        3,
      );
    }

    return $resultados;
  }

  public function notifyTaskCompleted($tarea_id, $completada_por_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT  
                t.id_tarea,  
                t.nombre as tarea_nombre,  
                t.id_creador,  
                t.id_proyecto, 
                p.nombre as proyecto_nombre, 
                p.id_creador as proyecto_creador_id, 
                u.nombre as creador_nombre,  
                u.apellido as creador_apellido, 
                u.e_mail as creador_email, 
                c.nombre as completador_nombre,  
                c.apellido as completador_apellido, 
                ur_creador.id_rol as creador_rol, 
                ur_completador.id_rol as completador_rol 
            FROM tbl_tareas t 
            JOIN tbl_usuarios u ON t.id_creador = u.id_usuario 
            LEFT JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto 
            LEFT JOIN tbl_usuarios c ON c.id_usuario = ? 
            LEFT JOIN tbl_usuario_roles ur_creador ON u.id_usuario = ur_creador.id_usuario  
                AND ur_creador.es_principal = 1 AND ur_creador.activo = 1 
            LEFT JOIN tbl_usuario_roles ur_completador ON c.id_usuario = ur_completador.id_usuario  
                AND ur_completador.es_principal = 1 AND ur_completador.activo = 1 
            WHERE t.id_tarea = ? 
        ");

    $stmt->bind_param("ii", $completada_por_id, $tarea_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$task || empty($task["creador_email"])) {
      return false;
    }

    // No notificar si el creador es quien completa
    if ($task["id_creador"] == $completada_por_id) {
      return false;
    }

    // Verificar preferencias
    if (
      !$this->checkUserPreference($task["id_creador"], "notif_tarea_completada")
    ) {
      return false;
    }

    $html = $this->templates->render("tarea_completada", [
      "SUBJECT" => "Tarea completada: " . $task["tarea_nombre"],
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
      "Tarea completada: " . $task["tarea_nombre"],
      $html,
      "tarea_completada",
      "tarea",
      $tarea_id,
     5, // Prioridad normal
    );
  }

  public function notifyProjectParticipants($proyecto_id, $tipo, $mensaje)
  {
    // Obtener todos los usuarios asignados al proyecto
    $stmt = $this->conn->prepare(" 
            SELECT DISTINCT  
                u.id_usuario,  
                u.nombre,  
                u.apellido,  
                u.e_mail, 
                ur.id_rol, 
                r.nombre as rol_nombre 
            FROM tbl_usuarios u 
            INNER JOIN tbl_proyecto_usuarios pu ON u.id_usuario = pu.id_usuario 
            LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
                AND ur.es_principal = 1 AND ur.activo = 1 
            LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            WHERE pu.id_proyecto = ? 
        ");

    $stmt->bind_param("i", $proyecto_id);
    $stmt->execute();
    $usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $resultados = [];

    foreach ($usuarios as $usuario) {
      $resultados[$usuario["id_usuario"]] = true; // Placeholder
    }
    return $resultados;
  }

  private function checkUserPreference($usuario_id, $preference)
  {
    $stmt = $this->conn->prepare(
      "SELECT $preference FROM tbl_notificacion_preferencias WHERE id_usuario = ?",
    );

    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Si no hay preferencias configuradas, asumir que está habilitado
    if (!$result) {
      return true;
    }

    return $result[$preference] == 1;
  }

  public function getUserRole($usuario_id)
  {
    $stmt = $this->conn->prepare(" 
            SELECT  
                ur.id_rol,  
                r.nombre as nombre_rol,  
                r.descripcion as descripcion_rol, 
                ur.id_departamento, 
                d.nombre as nombre_departamento, 
                ur.es_principal 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
            WHERE ur.id_usuario = ?  
                AND ur.activo = 1 
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
            SELECT  
                ur.id_usuario_roles, 
                ur.id_rol,  
                r.nombre as nombre_rol,  
                r.descripcion as descripcion_rol, 
                ur.id_departamento, 
                d.nombre as nombre_departamento, 
                ur.es_principal, 
                ur.activo 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
            WHERE ur.id_usuario = ?  
                AND ur.activo = 1 
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
            SELECT  
                ur.id_rol,  
                r.nombre as nombre_rol,  
                r.descripcion as descripcion_rol, 
                ur.es_principal 
            FROM tbl_usuario_roles ur 
            JOIN tbl_roles r ON ur.id_rol = r.id_rol 
            WHERE ur.id_usuario = ?  
                AND ur.id_departamento = ? 
                AND ur.activo = 1 
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

    // Definir permisos según rol
    $permisos = [
      1 => [
        // Administrador
        "crear_usuarios",
        "crear_departamentos",
        "asignar_proyectos",
        "asignar_tareas",
        "ver_todo",
        "editar_todo",
        "eliminar_todo",
      ],

      2 => [
        // Gerente
        "asignar_proyectos",
        "asignar_tareas",
        "crear_objetivos",
        "ver_departamento",
        "editar_departamento",
      ],

      3 => [
        // Usuario
        "crear_tareas",
        "ver_asignado",
        "editar_propio",
      ],
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
      // Verificar si es administrador (tiene acceso a todo)
      $primary_role = $this->getUserRole($usuario_id);
      if ($primary_role && $primary_role["id_rol"] == 1) {
        return true;
      }
      return false;
    }

    $id_rol = $role_info["id_rol"];

    // Definir permisos según rol
    $permisos = [
      1 => [
        // Administrador
        "crear_usuarios",
        "crear_departamentos",
        "asignar_proyectos",
        "asignar_tareas",
        "ver_todo",
        "editar_todo",
        "eliminar_todo",
      ],

      2 => [
        // Gerente
        "asignar_proyectos",
        "asignar_tareas",
        "crear_objetivos",
        "ver_departamento",
        "editar_departamento",
      ],

      3 => [
        // Usuario
        "crear_tareas",
        "ver_asignado",
        "editar_propio",
      ],
    ];

    return isset($permisos[$id_rol]) && in_array($permiso, $permisos[$id_rol]);
  }

  // Getters
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