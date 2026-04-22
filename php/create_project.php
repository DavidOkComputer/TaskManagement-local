
  <?php
  /*create_project.php para crear nuevo proyecto (con soporte de Proyecto Libre)*/
  session_start();

  header('Content-Type: application/json');
  require_once 'db_config.php';
  require_once 'notification_triggers.php';
  require_once '../email/NotificationHelper.php';

  error_reporting(E_ALL);
  ini_set('display_errors', 0);
  ob_start();

  $response = ['success' => false, 'message' => ''];

  try {
      if (!isset($_SESSION['user_id'])) {
          throw new Exception('Usuario no autenticado. Por favor inicie sesión.');
      }

      $id_creador = intval($_SESSION['user_id']);

      if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
          throw new Exception('Método de solicitud inválido');
      }

      // Detectar si es Proyecto Libre
      $es_libre = isset($_POST['es_libre']) && intval($_POST['es_libre']) === 1 ? 1 : 0;

      // Campos requeridos base
      $required_fields = [
          'nombre',
          'descripcion',
          'fecha_creacion',
          'fecha_cumplimiento',
          'estado',
          'id_tipo_proyecto'
      ];

      // id_departamento solo es requerido si NO es libre
      if (!$es_libre) {
          $required_fields[] = 'id_departamento';
      }

      foreach ($required_fields as $field) {
          if (!isset($_POST[$field]) || (empty($_POST[$field]) && $_POST[$field] !== '0')) {
              throw new Exception("El campo {$field} es requerido");
          }
      }

      $nombre = trim($_POST['nombre']);
      $descripcion = trim($_POST['descripcion']);

      // Si es libre id_departamento será NULL si no entonces será un entero válido
      $id_departamento = $es_libre ? null : intval($_POST['id_departamento']);

      $fecha_creacion = trim($_POST['fecha_creacion']);
      $fecha_cumplimiento = trim($_POST['fecha_cumplimiento']);
      $progreso = isset($_POST['progreso']) ? intval($_POST['progreso']) : 0;
      $ar = isset($_POST['ar']) ? trim($_POST['ar']) : '';
      $estado = trim($_POST['estado']);
      $archivo_adjunto = isset($_POST['archivo_adjunto']) ? trim($_POST['archivo_adjunto']) : '';
      $id_tipo_proyecto = intval($_POST['id_tipo_proyecto']);
      $puede_editar_otros = isset($_POST['puede_editar_otros']) ? intval($_POST['puede_editar_otros']) : 0;

      if (strlen($nombre) > 100) {
          throw new Exception('El nombre no puede exceder 100 caracteres');
      }

      if (strlen($descripcion) > 200) {
          throw new Exception('La descripción no puede exceder 200 caracteres');
      }

      if (strlen($archivo_adjunto) > 300) {
          throw new Exception('La ruta del archivo no puede exceder 300 caracteres');
      }

      $estados_validos = ['pendiente', 'en proceso', 'vencido', 'completado'];
      if (!in_array($estado, $estados_validos)) {
          throw new Exception('El estado debe ser: pendiente, en proceso, vencido o completado');
      }

      if (strpos($fecha_creacion, 'T') !== false) {
          $fecha_creacion = str_replace('T', ' ', $fecha_creacion);
      }

      if (strtotime($fecha_creacion) === false) {
          throw new Exception('La fecha de creación no es válida');
      }

      if (strtotime($fecha_cumplimiento) === false) {
          throw new Exception('La fecha de cumplimiento no es válida');
      }

      if (strtotime($fecha_cumplimiento) < strtotime($fecha_creacion)) {
          throw new Exception('La fecha de entrega debe ser posterior o igual a la fecha de inicio');
      }

      $conn = getDBConnection();
      if (!$conn) {
          throw new Exception('Error de conexión a la base de datos');
      }

      $usuarios_grupo = [];
      $id_participante = 0;

      if ($id_tipo_proyecto == 1) {
          if (isset($_POST['usuarios_grupo'])) {
              $usuarios_grupo = json_decode($_POST['usuarios_grupo'], true);
              if (!is_array($usuarios_grupo) || empty($usuarios_grupo)) {
                  throw new Exception('Debes seleccionar al menos un usuario para el proyecto grupal');
              }
          } else {
              throw new Exception('Debes seleccionar usuarios para el proyecto grupal');
          }
      } else {
          if (isset($_POST['id_participante'])) {
              $id_participante = intval($_POST['id_participante']);
          }
      }

      // Construir INSERT manejando id_departamento NULL en proyectos libres
      if ($es_libre) {
          $sql = "INSERT INTO tbl_proyectos (
                      nombre, descripcion, id_departamento, fecha_inicio, fecha_cumplimiento,
                      progreso, ar, estado, archivo_adjunto, id_creador, id_participante,
                      id_tipo_proyecto, es_libre, puede_editar_otros
                  ) VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

          $types = "ssssissiiiii" . "i"; // 13 chars

          $stmt = $conn->prepare($sql);
          if (!$stmt) {
              throw new Exception('Error al preparar la consulta: ' . $conn->error);
          }

          $stmt->bind_param(
              $types,
              $nombre,
              $descripcion,
              $fecha_creacion,
              $fecha_cumplimiento,
              $progreso,
              $ar,
              $estado,
              $archivo_adjunto,
              $id_creador,
              $id_participante,
              $id_tipo_proyecto,
              $es_libre,
              $puede_editar_otros
          );
      } else {
          $sql = "INSERT INTO tbl_proyectos (
                      nombre, descripcion, id_departamento, fecha_inicio, fecha_cumplimiento,
                      progreso, ar, estado, archivo_adjunto, id_creador, id_participante,
                      id_tipo_proyecto, es_libre, puede_editar_otros
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

          $types = "ssissi" . "sssiiiiii";
          //  s  s  i  s  s  i  s  s  s  i  i  i  i  i
          //  1  2  3  4  5  6  7  8  9 10 11 12 13 14
          $types = "ssississiiiii" . "i"; // 14 chars
          $types = "ssissi" . "sss" . "iiiii";

          $stmt = $conn->prepare($sql);
          if (!$stmt) {
              throw new Exception('Error al preparar la consulta: ' . $conn->error);
          }

          $stmt->bind_param(
              $types,
              $nombre,
              $descripcion,
              $id_departamento,
              $fecha_creacion,
              $fecha_cumplimiento,
              $progreso,
              $ar,
              $estado,
              $archivo_adjunto,
              $id_creador,
              $id_participante,
              $id_tipo_proyecto,
              $es_libre,
              $puede_editar_otros
          );
      }

      if (!$stmt->execute()) {
          throw new Exception('Error al crear el proyecto: ' . $stmt->error);
      }

      $id_proyecto = $stmt->insert_id;
      $stmt->close();

      // Construir mensaje de notificación con sufijo de proyecto libre si aplica
      $proyecto_tipo_label = $es_libre ? ' (Proyecto Libre)' : '';

      // Notificar al participante si es proyecto individual y no es el mismo creador
      if ($id_tipo_proyecto == 2 && $id_participante > 0 && $id_participante != $id_creador) {
          triggerNotificacionProyectoAsignado($conn, $id_proyecto, $id_participante, null, $es_libre);
          error_log("Notificación enviada: Nuevo proyecto{$proyecto_tipo_label} {$id_proyecto} asignado a usuario {$id_participante}");

          $notifier = new NotificationHelper($conn);
          $notifier->notifyProjectAssigned($id_proyecto, $id_participante, $id_creador);
      }

      // Manejo de usuarios para proyecto grupal
      if ($id_tipo_proyecto == 1 && !empty($usuarios_grupo)) {
          $sql_usuarios = "INSERT INTO tbl_proyecto_usuarios (id_proyecto, id_usuario) VALUES (?, ?)";
          $stmt_usuarios = $conn->prepare($sql_usuarios);

          if (!$stmt_usuarios) {
              throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
          }

          foreach ($usuarios_grupo as $id_usuario) {
              $id_usuario = intval($id_usuario);
              $stmt_usuarios->bind_param("ii", $id_proyecto, $id_usuario);

              if (!$stmt_usuarios->execute()) {
                  throw new Exception('Error al asignar usuarios al proyecto: ' . $stmt_usuarios->error);
              }

              if ($id_usuario != $id_creador) {
                  triggerNotificacionProyectoGrupal($conn, $id_proyecto, $id_usuario, $es_libre);
                  error_log("Notificación enviada: Proyecto grupal{$proyecto_tipo_label} {$id_proyecto} - usuario {$id_usuario} agregado");

                  $notifier = new NotificationHelper($conn);
                  $notifier->notifyProjectAssigned($id_proyecto, $id_usuario, $id_creador);
              }
          }
          $stmt_usuarios->close();
      }

      $response['success'] = true;
      $response['message'] = $es_libre
          ? 'Proyecto Libre registrado exitosamente'
          : 'Proyecto registrado exitosamente';
      $response['id_proyecto'] = $id_proyecto;
      $response['es_libre'] = (bool)$es_libre;

      $conn->close();

  } catch (Exception $e) {
      $response['success'] = false;
      $response['message'] = $e->getMessage();
      error_log('Error in create_project.php: ' . $e->getMessage());
  }

  ob_end_clean();
  echo json_encode($response);
  exit;
  ?>