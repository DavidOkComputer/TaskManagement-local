<?php

/*manager_get_users.php para conocer los usuarios de un departamento específico */

session_start();
header("Content-Type: application/json");
require_once "db_config.php";

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
  echo json_encode([
    "success" => false,
    "message" => "Método no permitido",
  ]);
  exit();
}

try {
  $conn = getDBConnection();

  if (!$conn) {
    throw new Exception("Error de conexión a la base de datos");
  }

  $user_id = (int) ($_SESSION["user_id"] ?? $_SESSION["id_usuario"]);

  // Verificar columna foto_perfil
  $checkColumn = $conn->query(
    "SHOW COLUMNS FROM tbl_usuarios LIKE 'foto_perfil'",
  );

  $hasFotoColumn = $checkColumn && $checkColumn->num_rows > 0;
  $fotoField = $hasFotoColumn ? ", u.foto_perfil" : "";

  // Obtener TODOS los departamentos a los que pertenece el usuario
  $mis_departamentos = [];
  $role_query = " 
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal 
        FROM tbl_usuario_roles ur 
        WHERE ur.id_usuario = ? AND ur.activo = 1 
    ";

  $role_stmt = $conn->prepare($role_query);
  $role_stmt->bind_param("i", $user_id);
  $role_stmt->execute();
  $role_result = $role_stmt->get_result();
  $is_admin = false;

  while ($row = $role_result->fetch_assoc()) {
    if ($row["id_rol"] == 1) {
      $is_admin = true;
    }

    // Agregar TODOS los departamentos del usuario
    $mis_departamentos[] = (int) $row["id_departamento"];
  }

  $role_stmt->close();

  // Fallback: Si no hay registros en junction table, usar tbl_usuarios
  if (empty($mis_departamentos)) {
    $legacy_query =
      "SELECT id_departamento, id_rol FROM tbl_usuarios WHERE id_usuario = ?";
    $legacy_stmt = $conn->prepare($legacy_query);
    $legacy_stmt->bind_param("i", $user_id);
    $legacy_stmt->execute();
    $legacy_result = $legacy_stmt->get_result();

    if ($row = $legacy_result->fetch_assoc()) {
      if ($row["id_departamento"]) {
        $mis_departamentos[] = (int) $row["id_departamento"];
      }

      if ($row["id_rol"] == 1) {
        $is_admin = true;
      }
    }

    $legacy_stmt->close();
  }

  // Verificar si se solicitan usuarios de TODOS los departamentos del usuario
  $include_all_departments =
    isset($_GET["include_all_departments"]) &&
    $_GET["include_all_departments"] == "1";

  // Determinar el departamento a filtrar
  $id_departamento = null;
  $filter_multiple_departments = false;
  if ($include_all_departments && !empty($mis_departamentos)) {
    // Obtener usuarios de TODOS los departamentos del usuario
    $filter_multiple_departments = true;
  } elseif (
    isset($_GET["id_departamento"]) &&
    !empty($_GET["id_departamento"])
  ) {
    $requested_dept = (int) $_GET["id_departamento"];

   // Admin puede ver cualquier departamento
    if ($is_admin) {
      $id_departamento = $requested_dept;
    }

    // Usuario puede ver cualquier departamento al que pertenezca (primario o secundario)
    elseif (in_array($requested_dept, $mis_departamentos)) {
      $id_departamento = $requested_dept;
    } else {

      // Solo verificar que el departamento existe
      $check_dept = $conn->prepare(
        "SELECT id_departamento FROM tbl_departamentos WHERE id_departamento = ?",
      );
      $check_dept->bind_param("i", $requested_dept);
      $check_dept->execute();
      $check_result = $check_dept->get_result();

      if ($check_result->num_rows > 0) {
        $id_departamento = $requested_dept;
      } else {
        throw new Exception("El departamento solicitado no existe");
      }

      $check_dept->close();
    }
  }

  // Usar el primer departamento del usuario
  elseif (!empty($mis_departamentos)) {
    $id_departamento = $mis_departamentos[0];
  }

  // Fallback a sesión
  elseif (
    isset($_SESSION["id_departamento"]) &&
    $_SESSION["id_departamento"] > 0
  ) {
    $id_departamento = (int) $_SESSION["id_departamento"];
  }

  if (!$id_departamento && !$is_admin && !$filter_multiple_departments) {
    throw new Exception("No se pudo determinar el departamento del usuario");
  }

  $filter_rol = isset($_GET["id_rol"]) ? intval($_GET["id_rol"]) : null;

  // Construir query base
  $baseFields = " 
        DISTINCT 
        u.id_usuario, 
        u.nombre, 
        u.apellido, 
        u.usuario, 
        u.num_empleado, 
        u.acceso, 
        u.id_departamento as id_departamento_principal, 
        u.id_rol as id_rol_principal, 
        u.id_superior, 
        u.e_mail, 
        ur.id_departamento as id_departamento_asignado, 
        ur.id_rol as id_rol_asignado, 
        ur.es_principal, 
        d.nombre as area, 
        r.nombre as nombre_rol 
        {$fotoField} 
    ";

  $usuarios = [];
  $usuarios_vistos = []; // Para evitar duplicados

  // Consulta para múltiples departamentos
  if ($filter_multiple_departments && !empty($mis_departamentos)) {
    // Crear placeholders para los departamentos

    $placeholders = implode(",", array_fill(0, count($mis_departamentos), "?"));
    $types = str_repeat("i", count($mis_departamentos));

    if ($filter_rol !== null && $filter_rol > 0) {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
                    AND ur.activo = 1  
                    AND ur.id_departamento IN ({$placeholders}) 
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                WHERE ur.id_rol = ? 
                ORDER BY d.nombre ASC, ur.es_principal DESC, u.apellido ASC, u.nombre ASC";

      $stmt = $conn->prepare($query);

      if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
      }

      $params = array_merge($mis_departamentos, [$filter_rol]);
      $types .= "i";
      $stmt->bind_param($types, ...$params);
    } else {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
                    AND ur.activo = 1  
                    AND ur.id_departamento IN ({$placeholders}) 
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                ORDER BY d.nombre ASC, ur.es_principal DESC, u.apellido ASC, u.nombre ASC";
      $stmt = $conn->prepare($query);
      if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
      }
      $stmt->bind_param($types, ...$mis_departamentos);
    }
  }

  // Para admin sin departamento específico, mostrar todos
  elseif ($is_admin && !$id_departamento) {
    if ($filter_rol !== null && $filter_rol > 0) {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1 
                LEFT JOIN tbl_departamentos d ON COALESCE(ur.id_departamento, u.id_departamento) = d.id_departamento 
                LEFT JOIN tbl_roles r ON COALESCE(ur.id_rol, u.id_rol) = r.id_rol 
                WHERE COALESCE(ur.id_rol, u.id_rol) = ? 
                ORDER BY u.apellido ASC, u.nombre ASC";
      $stmt = $conn->prepare($query);
      $stmt->bind_param("i", $filter_rol);
    } else {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1 
                LEFT JOIN tbl_departamentos d ON COALESCE(ur.id_departamento, u.id_departamento) = d.id_departamento 
                LEFT JOIN tbl_roles r ON COALESCE(ur.id_rol, u.id_rol) = r.id_rol 
                ORDER BY u.apellido ASC, u.nombre ASC";
      $stmt = $conn->prepare($query);
    }
  } else {
    // Filtrar por departamento único
    if ($filter_rol !== null && $filter_rol > 0) {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
                    AND ur.activo = 1  
                    AND ur.id_departamento = ? 
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                WHERE ur.id_rol = ? 
                ORDER BY ur.es_principal DESC, u.apellido ASC, u.nombre ASC";
      $stmt = $conn->prepare($query);

      if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
      }

      $stmt->bind_param("ii", $id_departamento, $filter_rol);
    } else {
      $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
                    AND ur.activo = 1  
                    AND ur.id_departamento = ? 
                LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
                LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
                ORDER BY ur.es_principal DESC, u.apellido ASC, u.nombre ASC";
      $stmt = $conn->prepare($query);

      if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
      }

      $stmt->bind_param("i", $id_departamento);
    }
  }
  $stmt->execute();
  $result = $stmt->get_result();

  if (!$result) {
    throw new Exception("Error en la consulta: " . $conn->error);
  }

  while ($row = $result->fetch_assoc()) {
    $userId = (int) $row["id_usuario"];

    // Evitar duplicados si un usuario aparece múltiples veces
    if (in_array($userId, $usuarios_vistos)) {
      continue;
    }

    $usuarios_vistos[] = $userId;
    // Determinar si es rol principal o secundario en este departamento
    $es_rol_secundario =
      isset($row["es_principal"]) && $row["es_principal"] == 0;

    // Determinar el departamento actual del usuario
    $dept_actual =
      (int) ($row["id_departamento_asignado"] ??
        $row["id_departamento_principal"]);

    $usuario = [
      "id_usuario" => $userId,
      "nombre" => $row["nombre"],
      "apellido" => $row["apellido"],
      "usuario" => $row["usuario"],
      "num_empleado" => (int) $row["num_empleado"],
      "nombre_completo" => $row["nombre"] . " " . $row["apellido"],
      "nombre_empleado" =>
        $row["nombre"] .
        " " .
        $row["apellido"] .
        " (#" .
        $row["num_empleado"] .
        ")",

      "acceso" => $row["acceso"],
      "id_departamento" => $dept_actual,
      "id_departamento_principal" => (int) $row["id_departamento_principal"],
      "id_superior" => (int) $row["id_superior"],
      "id_rol" => (int) ($row["id_rol_asignado"] ?? $row["id_rol_principal"]),
      "id_rol_principal" => (int) $row["id_rol_principal"],
      "nombre_rol" => $row["nombre_rol"] ?? "N/A",
      "e_mail" => $row["e_mail"],
      "area" => $row["area"],
      "es_principal" => isset($row["es_principal"])
        ? (int) $row["es_principal"]
        : 1,
      "es_rol_secundario" => $es_rol_secundario,
    ];

    // Agregar indicador visual si es rol secundario
    if ($es_rol_secundario) {
      $usuario["nombre_empleado"] .= " [Secundario]";
    }

    // Agregar campos de foto de perfil
    if ($hasFotoColumn && isset($row["foto_perfil"])) {
      $fotoPerfil = $row["foto_perfil"];
      $usuario["foto_perfil"] = $fotoPerfil;

      if (!empty($fotoPerfil)) {
        $usuario["foto_url"] = "uploads/profile_pictures/" . $fotoPerfil;
        $usuario["foto_thumbnail"] =
          "uploads/profile_pictures/thumbnails/thumb_" . $fotoPerfil;
      } else {
        $usuario["foto_url"] = null;
        $usuario["foto_thumbnail"] = null;
      }
    } else {
      $usuario["foto_perfil"] = null;
      $usuario["foto_url"] = null;
      $usuario["foto_thumbnail"] = null;
    }

    $usuarios[] = $usuario;
  }

  // Si no se encontraron usuarios en tbl_usuario_roles, hacer fallback a tbl_usuarios (legacy)
  if (empty($usuarios) && $id_departamento && !$filter_multiple_departments) {
    $legacy_query = "SELECT  
            u.id_usuario, u.nombre, u.apellido, u.usuario, u.num_empleado, 
            u.acceso, u.id_departamento, u.id_rol, u.id_superior, u.e_mail, 
            d.nombre as area, r.nombre as nombre_rol 
            {$fotoField} 
            FROM tbl_usuarios u 
            LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
            LEFT JOIN tbl_roles r ON u.id_rol = r.id_rol 
            WHERE u.id_departamento = ? 
            ORDER BY u.apellido ASC, u.nombre ASC";
    $legacy_stmt = $conn->prepare($legacy_query);
    $legacy_stmt->bind_param("i", $id_departamento);
    $legacy_stmt->execute();
    $legacy_result = $legacy_stmt->get_result();
    while ($row = $legacy_result->fetch_assoc()) {
      $usuario = [
        "id_usuario" => (int) $row["id_usuario"],
        "nombre" => $row["nombre"],
        "apellido" => $row["apellido"],
        "usuario" => $row["usuario"],
        "num_empleado" => (int) $row["num_empleado"],
        "nombre_completo" => $row["nombre"] . " " . $row["apellido"],
        "nombre_empleado" =>
          $row["nombre"] .
          " " .
          $row["apellido"] .
          " (#" .
          $row["num_empleado"] .
          ")",

        "acceso" => $row["acceso"],
        "id_departamento" => (int) $row["id_departamento"],
        "id_departamento_principal" => (int) $row["id_departamento"],
        "id_superior" => (int) $row["id_superior"],
        "id_rol" => (int) $row["id_rol"],
        "id_rol_principal" => (int) $row["id_rol"],
        "nombre_rol" => $row["nombre_rol"] ?? "N/A",
        "e_mail" => $row["e_mail"],
        "area" => $row["area"],
        "es_principal" => 1,
        "es_rol_secundario" => false,
      ];

      if ($hasFotoColumn && isset($row["foto_perfil"])) {
        $fotoPerfil = $row["foto_perfil"];
        $usuario["foto_perfil"] = $fotoPerfil;

        if (!empty($fotoPerfil)) {
          $usuario["foto_url"] = "uploads/profile_pictures/" . $fotoPerfil;
          $usuario["foto_thumbnail"] =
            "uploads/profile_pictures/thumbnails/thumb_" . $fotoPerfil;
        } else {
          $usuario["foto_url"] = null;

          $usuario["foto_thumbnail"] = null;
        }
      } else {
        $usuario["foto_perfil"] = null;
        $usuario["foto_url"] = null;
        $usuario["foto_thumbnail"] = null;
      }

      $usuarios[] = $usuario;
    }

    $legacy_stmt->close();
  }

  echo json_encode([
    "success" => true,
    "usuarios" => $usuarios,

    "debug" => [
      "id_departamento_filtro" => $id_departamento,
      "mis_departamentos" => $mis_departamentos,
      "is_admin" => $is_admin,
      "total_usuarios" => count($usuarios),
      "usando_junction_table" => true,
      "include_all_departments" => $filter_multiple_departments,
    ],
  ]);

  $result->free();

  if (isset($stmt)) {
    $stmt->close();
  }

  $conn->close();
} catch (Exception $e) {
  echo json_encode([
    "success" => false,
    "message" => "Error al cargar usuarios: " . $e->getMessage(),
    "usuarios" => [],
  ]);

  error_log("manager_get_users.php Error: " . $e->getMessage());
}
?> 