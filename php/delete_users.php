<?php

/*delete_users.php para eliminar usuarios del sistema */

header("Content-Type: application/json");
require_once "db_config.php";

$response = [
  "success" => false,
  "message" => "",
  "deleted_roles" => 0,
];

try {
  $conn = getDBConnection();

  if ($conn->connect_error) {
    throw new Exception("Error de conexión: " . $conn->connect_error);
  }

  $conn->set_charset("utf8mb4");
  $data = json_decode(file_get_contents("php://input"), true);

  if (!isset($data["id_usuario"])) {
    throw new Exception("ID de usuario no proporcionado");
  }

  $id_usuario = intval($data["id_usuario"]);

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  // Verificar que el usuario existe y obtener información
  $stmt = $conn->prepare(" 
        SELECT u.id_usuario, u.nombre, u.apellido, u.usuario, u.id_rol as rol_legacy 
        FROM tbl_usuarios u  
        WHERE u.id_usuario = ? 
    ");

  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("Usuario no encontrado");
  }

  $usuario = $result->fetch_assoc();

  $stmt->close();

  // Verificar si es el único administrador (prevenir eliminación)

  if ($usuario["rol_legacy"] == 1) {
    // Administrador

    $stmt = $conn->prepare(" 
            SELECT COUNT(*) as total_admins  
            FROM tbl_usuarios  
            WHERE id_rol = 1 AND id_usuario != ? 
        ");

    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $admin_check = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // También verificar en la nueva tabla de roles
    $stmt = $conn->prepare(" 
            SELECT COUNT(DISTINCT id_usuario) as total_admins  
            FROM tbl_usuario_roles  
            WHERE id_rol = 1 AND id_usuario != ? AND activo = 1 
        ");

    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $admin_check_new = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (
      $admin_check["total_admins"] == 0 &&
      $admin_check_new["total_admins"] == 0
    ) {
      throw new Exception(
        "No se puede eliminar el único administrador del sistema",
      );
    }
  }

  // Contar roles asignados que se eliminarán (para información)

  $stmt = $conn->prepare(" 
        SELECT COUNT(*) as total_roles  
        FROM tbl_usuario_roles  
        WHERE id_usuario = ? 
    ");

  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $roles_count = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  // Obtener detalles de roles para el log
  $stmt = $conn->prepare(" 
        SELECT ur.id_departamento, d.nombre as departamento, r.nombre as rol 
        FROM tbl_usuario_roles ur 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        WHERE ur.id_usuario = ? 
    ");

  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $roles_result = $stmt->get_result();
  $roles_info = [];

  while ($role = $roles_result->fetch_assoc()) {
    $roles_info[] = "{$role["rol"]} en {$role["departamento"]}";
  }

  $stmt->close();

  // Iniciar transacción
  $conn->begin_transaction();

  try {
    // Eliminar usuario (los roles se eliminan por CASCADE)
    $stmt = $conn->prepare("DELETE FROM tbl_usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario);
    if (!$stmt->execute()) {
      throw new Exception("Error al eliminar el usuario: " . $stmt->error);
    }
    if ($stmt->affected_rows === 0) {
      throw new Exception("No se pudo eliminar el usuario");
    }
    $stmt->close();

    // Confirmar transacción
    $conn->commit();

    // Log de la eliminación
    $roles_str = !empty($roles_info)
      ? implode(", ", $roles_info)
      : "Sin roles asignados";

    error_log(
      "Usuario eliminado: ID={$id_usuario}, Usuario={$usuario["usuario"]}, " .
        "Nombre={$usuario["nombre"]} {$usuario["apellido"]}, Roles=[{$roles_str}]",
    );

    $response["success"] = true;
    $response["message"] = "Usuario eliminado exitosamente";
    $response["deleted_roles"] = $roles_count["total_roles"];
    $response["usuario_eliminado"] = [
      "id" => $id_usuario,
      "nombre" => $usuario["nombre"] . " " . $usuario["apellido"],
      "usuario" => $usuario["usuario"],
    ];
  } catch (Exception $e) {
    $conn->rollback();

    throw $e;
  }
} catch (Exception $e) {
  $response["success"] = false;
  $response["message"] = $e->getMessage();
  error_log("Error en delete_users.php: " . $e->getMessage());
}

if (isset($conn) && $conn) {
  $conn->close();
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

exit();
?>