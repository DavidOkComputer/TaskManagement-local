<?php

/*manager_get_department_users.php para saber los usuarios SOLO del departamento principal del usuario */

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

  // Obtener SOLO el departamento PRINCIPAL del usuario
  $id_departamento_principal = null;
  $nombre_departamento = null;
  $is_admin = false;
  $principal_query = " 
        SELECT ur.id_departamento, ur.id_rol, d.nombre as nombre_departamento 
        FROM tbl_usuario_roles ur 
        INNER JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE ur.id_usuario = ?  
          AND ur.activo = 1  
          AND ur.es_principal = 1 
        LIMIT 1 
    ";

  $principal_stmt = $conn->prepare($principal_query);
  $principal_stmt->bind_param("i", $user_id);
  $principal_stmt->execute();
  $principal_result = $principal_stmt->get_result();

  if ($row = $principal_result->fetch_assoc()) {
    $id_departamento_principal = (int) $row["id_departamento"];
    $nombre_departamento = $row["nombre_departamento"];

    if ($row["id_rol"] == 1) {
      $is_admin = true;
    }
  }

  $principal_stmt->close();

  // Fallback: Si no hay registro con es_principal=1, buscar el primer registro activo
  if (!$id_departamento_principal) {
    $fallback_query = " 
            SELECT ur.id_departamento, ur.id_rol, d.nombre as nombre_departamento 
            FROM tbl_usuario_roles ur 
            INNER JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
            WHERE ur.id_usuario = ? AND ur.activo = 1 
            ORDER BY ur.fecha_asignacion ASC 
            LIMIT 1 
        ";

    $fallback_stmt = $conn->prepare($fallback_query);
    $fallback_stmt->bind_param("i", $user_id);
    $fallback_stmt->execute();
    $fallback_result = $fallback_stmt->get_result();

    if ($row = $fallback_result->fetch_assoc()) {
      $id_departamento_principal = (int) $row["id_departamento"];
      $nombre_departamento = $row["nombre_departamento"];

      if ($row["id_rol"] == 1) {
        $is_admin = true;
      }
    }

    $fallback_stmt->close();
  }

  // Fallback final: Usar tbl_usuarios (legacy)
  if (!$id_departamento_principal) {
    $legacy_query = " 
            SELECT u.id_departamento, u.id_rol, d.nombre as nombre_departamento 
            FROM tbl_usuarios u 
            LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
            WHERE u.id_usuario = ? 
        ";

    $legacy_stmt = $conn->prepare($legacy_query);
    $legacy_stmt->bind_param("i", $user_id);
    $legacy_stmt->execute();
    $legacy_result = $legacy_stmt->get_result();
    if ($row = $legacy_result->fetch_assoc()) {
      if ($row["id_departamento"]) {
        $id_departamento_principal = (int) $row["id_departamento"];
        $nombre_departamento = $row["nombre_departamento"] ?? "Sin nombre";
      }

      if ($row["id_rol"] == 1) {
        $is_admin = true;
      }
    }

    $legacy_stmt->close();
  }

  if (!$id_departamento_principal) {
    throw new Exception(
      "No se pudo determinar el departamento principal del usuario",
    );
  }

  $filter_rol = isset($_GET["id_rol"]) ? intval($_GET["id_rol"]) : null;

  // Construir query para obtener usuarios del departamento principal
  $baseFields = " 
        DISTINCT 
        u.id_usuario, 
        u.nombre, 
        u.apellido, 
        u.usuario, 
        u.num_empleado, 
        u.acceso, 
        u.id_departamento as id_departamento_legacy, 
        u.id_rol as id_rol_legacy, 
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
  $usuarios_vistos = [];

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

    $stmt->bind_param("ii", $id_departamento_principal, $filter_rol);
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

    $stmt->bind_param("i", $id_departamento_principal);
  }

  $stmt->execute();
  $result = $stmt->get_result();

  if (!$result) {
    throw new Exception("Error en la consulta: " . $conn->error);
  }

  while ($row = $result->fetch_assoc()) {
    $userId = (int) $row["id_usuario"];

    // Evitar duplicados
    if (in_array($userId, $usuarios_vistos)) {
      continue;
    }

    $usuarios_vistos[] = $userId;
    $es_rol_secundario =
     isset($row["es_principal"]) && $row["es_principal"] == 0;
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
      "id_departamento" => $id_departamento_principal,
      "id_superior" => (int) $row["id_superior"],
      "id_rol" => (int) ($row["id_rol_asignado"] ?? $row["id_rol_legacy"]),
      "nombre_rol" => $row["nombre_rol"] ?? "N/A",
      "e_mail" => $row["e_mail"],
      "area" => $row["area"],
      "es_principal" => isset($row["es_principal"])
        ? (int) $row["es_principal"]
        : 1,
      "es_rol_secundario" => $es_rol_secundario,
    ];

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

  // Fallback a tbl_usuarios si no hay resultados
  if (empty($usuarios)) {
    $legacy_users_query = "SELECT  
            u.id_usuario, u.nombre, u.apellido, u.usuario, u.num_empleado, 
            u.acceso, u.id_departamento, u.id_rol, u.id_superior, u.e_mail, 
            d.nombre as area, r.nombre as nombre_rol 
            {$fotoField} 
            FROM tbl_usuarios u 
            LEFT JOIN tbl_departamentos d ON u.id_departamento = d.id_departamento 
            LEFT JOIN tbl_roles r ON u.id_rol = r.id_rol 
            WHERE u.id_departamento = ? 
            ORDER BY u.apellido ASC, u.nombre ASC";
    $legacy_users_stmt = $conn->prepare($legacy_users_query);
    $legacy_users_stmt->bind_param("i", $id_departamento_principal);
    $legacy_users_stmt->execute();
    $legacy_users_result = $legacy_users_stmt->get_result();
    while ($row = $legacy_users_result->fetch_assoc()) {
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
        "id_superior" => (int) $row["id_superior"],
        "id_rol" => (int) $row["id_rol"],
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

    $legacy_users_stmt->close();
  }

  echo json_encode([
    "success" => true,
    "usuarios" => $usuarios,
    "departamento" => [
      "id" => $id_departamento_principal,

      "nombre" => $nombre_departamento,
    ],

    "debug" => [
      "user_id" => $user_id,
      "id_departamento_principal" => $id_departamento_principal,
      "nombre_departamento" => $nombre_departamento,
      "is_admin" => $is_admin,
      "total_usuarios" => count($usuarios),
    ],
  ]);

  $result->free();
  $stmt->close();
  $conn->close();
} catch (Exception $e) {
  echo json_encode([
    "success" => false,
    "message" => "Error al cargar usuarios: " . $e->getMessage(),
    "usuarios" => [],
  ]);
  error_log("manager_get_department_users.php Error: " . $e->getMessage());
}
?> 