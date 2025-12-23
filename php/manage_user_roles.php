<?php

/* manage_user_roles.php para gestionar roles de usuario */

header("Content-Type: application/json");
require_once "db_config.php";
require_once "check_auth.php";

// Solo administradores pueden gestionar roles
if (!isset($_SESSION["id_rol"]) || $_SESSION["id_rol"] != 1) {
  echo json_encode([
    "success" => false,
    "message" => "No tiene permisos para gestionar roles de usuarios",
  ]);
  exit();
}

$response = [
  "success" => false,
  "message" => "",
];

try {
  $conn = getDBConnection();

  if (!$conn) {
    throw new Exception("Error de conexión a la base de datos");
  }

  $conn->set_charset("utf8mb4");

  // Determinar la acción
  $action = isset($_POST["action"])
    ? $_POST["action"]
    : (isset($_GET["action"])
      ? $_GET["action"]
      : "");

  switch ($action) {
    case "add":
      // Agregar nuevo rol a usuario
      $response = addRoleToUser($conn);
      break;
    case "remove":
      // Eliminar/desactivar rol de usuario
      $response = removeRoleFromUser($conn);
      break;
    case "set_principal":
      // Establecer rol como principal
      $response = setPrincipalRole($conn);
      break;
    case "get_available":
      // Obtener departamentos disponibles para asignar (donde el usuario no tiene rol)
      $response = getAvailableDepartments($conn);
      break;
    default:
      throw new Exception(
        "Acción no válida. Use: add, remove, set_principal, get_available",
      );
  }

  $conn->close();
} catch (Exception $e) {
  $response["success"] = false;
  $response["message"] = $e->getMessage();
  error_log("manage_user_roles.php Error: " . $e->getMessage());
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);
exit();

// FUNCIONES

function addRoleToUser($conn)
{
  $id_usuario = isset($_POST["id_usuario"]) ? intval($_POST["id_usuario"]) : 0;

  $id_departamento = isset($_POST["id_departamento"])
    ? intval($_POST["id_departamento"])
    : 0;

  $id_rol = isset($_POST["id_rol"]) ? intval($_POST["id_rol"]) : 0;

  $es_principal = isset($_POST["es_principal"])
    ? intval($_POST["es_principal"])
    : 0;

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  if ($id_departamento <= 0) {
    throw new Exception("Debe seleccionar un departamento");
  }

  if ($id_rol <= 0) {
    throw new Exception("Debe seleccionar un rol");
  }

  // Verificar que el usuario existe
  $stmt = $conn->prepare(
    "SELECT id_usuario, nombre, apellido FROM tbl_usuarios WHERE id_usuario = ?",
  );

  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("El usuario no existe");
  }

  $userData = $result->fetch_assoc();
  $stmt->close();

  // Verificar que el departamento existe
  $stmt = $conn->prepare(
    "SELECT id_departamento, nombre FROM tbl_departamentos WHERE id_departamento = ?",
  );

  $stmt->bind_param("i", $id_departamento);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("El departamento no existe");
  }

  $deptData = $result->fetch_assoc();
  $stmt->close();

  // Verificar que el rol existe
  $stmt = $conn->prepare(
    "SELECT id_rol, nombre FROM tbl_roles WHERE id_rol = ?",
  );

  $stmt->bind_param("i", $id_rol);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("El rol no existe");
  }

  $roleData = $result->fetch_assoc();
  $stmt->close();

  // Usar el procedimineto almacenado para asignar el rol
  $stmt = $conn->prepare("CALL sp_asignar_rol_usuario(?, ?, ?, ?)");
  $stmt->bind_param(
    "iiii",
    $id_usuario,
    $id_departamento,
    $id_rol,
    $es_principal,
  );

  if (!$stmt->execute()) {
    throw new Exception("Error al asignar rol: " . $stmt->error);
  }

  $stmt->close();

  // También actualizar tbl_usuarios si es el rol principal
  if ($es_principal) {
    $updateStmt = $conn->prepare(
      "UPDATE tbl_usuarios SET id_departamento = ?, id_rol = ? WHERE id_usuario = ?",
    );

    $updateStmt->bind_param("iii", $id_departamento, $id_rol, $id_usuario);
    $updateStmt->execute();
    $updateStmt->close();
  }

  return [
    "success" => true,
    "message" => "Rol '{$roleData["nombre"]}' asignado a {$userData["nombre"]} {$userData["apellido"]} en {$deptData["nombre"]}",
    "data" => [
      "id_usuario" => $id_usuario,
      "id_departamento" => $id_departamento,
      "id_rol" => $id_rol,
      "es_principal" => (bool) $es_principal,
    ],
  ];
}

function removeRoleFromUser($conn)
{
  $id_usuario = isset($_POST["id_usuario"]) ? intval($_POST["id_usuario"]) : 0;

  $id_departamento = isset($_POST["id_departamento"])
    ? intval($_POST["id_departamento"])
    : 0;

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  if ($id_departamento <= 0) {
    throw new Exception("ID de departamento no válido");
  }

  // Verificar que existe la asignación
  $stmt = $conn->prepare(" 
        SELECT ur.id_usuario_roles, ur.es_principal, d.nombre as departamento 
        FROM tbl_usuario_roles ur 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE ur.id_usuario = ? AND ur.id_departamento = ? AND ur.activo = 1 
    ");

  $stmt->bind_param("ii", $id_usuario, $id_departamento);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("No se encontró la asignación de rol");
  }

  $roleData = $result->fetch_assoc();
  $stmt->close();

  // Verificar que no sea el único rol activo
  $stmt = $conn->prepare(
    "SELECT COUNT(*) as total FROM tbl_usuario_roles WHERE id_usuario = ? AND activo = 1",
  );

  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $countResult = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($countResult["total"] <= 1) {
    throw new Exception(
      "No se puede eliminar el único rol del usuario. Debe tener al menos un rol activo.",
    );
  }

  // Si es el rol principal, no permitir eliminarlo directamente
  if ($roleData["es_principal"]) {
    throw new Exception(
      "No se puede eliminar el rol principal. Primero establezca otro rol como principal.",
    );
  }

  // Desactivar el rol
  $stmt = $conn->prepare(
    "UPDATE tbl_usuario_roles SET activo = 0 WHERE id_usuario = ? AND id_departamento = ?",
  );

  $stmt->bind_param("ii", $id_usuario, $id_departamento);

  if (!$stmt->execute()) {
    throw new Exception("Error al eliminar rol: " . $stmt->error);
  }

  $stmt->close();

  return [
    "success" => true,
    "message" => "Rol en {$roleData["departamento"]} eliminado correctamente",
    "data" => [
      "id_usuario" => $id_usuario,
      "id_departamento" => $id_departamento,
    ],
  ];
}

function setPrincipalRole($conn)
{
  $id_usuario = isset($_POST["id_usuario"]) ? intval($_POST["id_usuario"]) : 0;
  $id_departamento = isset($_POST["id_departamento"])
    ? intval($_POST["id_departamento"])
    : 0;

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  if ($id_departamento <= 0) {
    throw new Exception("ID de departamento no válido");
  }

  // Verificar que existe la asignación
  $stmt = $conn->prepare(" 
        SELECT ur.id_usuario_roles, ur.id_rol, d.nombre as departamento, r.nombre as rol 
        FROM tbl_usuario_roles ur 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        WHERE ur.id_usuario = ? AND ur.id_departamento = ? AND ur.activo = 1 
    ");

  $stmt->bind_param("ii", $id_usuario, $id_departamento);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("No se encontró la asignación de rol");
  }

  $roleData = $result->fetch_assoc();
  $stmt->close();

  // Usar el procedimineto amacenado
  $stmt = $conn->prepare("CALL sp_establecer_rol_principal(?, ?)");
  $stmt->bind_param("ii", $id_usuario, $id_departamento);

  if (!$stmt->execute()) {
    throw new Exception("Error al establecer rol principal: " . $stmt->error);
  }

  $stmt->close();

  // Actualizar también tbl_usuarios para compatibilidad
  $stmt = $conn->prepare(
    "UPDATE tbl_usuarios SET id_departamento = ?, id_rol = ? WHERE id_usuario = ?",
  );

  $stmt->bind_param("iii", $id_departamento, $roleData["id_rol"], $id_usuario);
  $stmt->execute();
  $stmt->close();

  return [
    "success" => true,
    "message" => "Rol principal actualizado: {$roleData["rol"]} en {$roleData["departamento"]}",
    "data" => [
      "id_usuario" => $id_usuario,
      "id_departamento" => $id_departamento,
      "id_rol" => (int) $roleData["id_rol"],
    ],
  ];
}

function getAvailableDepartments($conn)
{
  $id_usuario = isset($_GET["id_usuario"]) ? intval($_GET["id_usuario"]) : 0;

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  // Obtener departamentos donde el usuario NO tiene rol activo
  $query = " 
        SELECT d.id_departamento, d.nombre, d.descripcion 
        FROM tbl_departamentos d 
        WHERE d.id_departamento NOT IN ( 
            SELECT ur.id_departamento  
            FROM tbl_usuario_roles ur  
            WHERE ur.id_usuario = ? AND ur.activo = 1 
        ) 
        ORDER BY d.nombre ASC 
    ";

  $stmt = $conn->prepare($query);
  $stmt->bind_param("i", $id_usuario);
  $stmt->execute();
  $result = $stmt->get_result();
  $departamentos = [];

  while ($row = $result->fetch_assoc()) {
    $departamentos[] = [
      "id_departamento" => (int) $row["id_departamento"],
      "nombre" => $row["nombre"],
      "descripcion" => $row["descripcion"],
    ];
  }

  $stmt->close();

  return [
    "success" => true,
    "departamentos" => $departamentos,
    "total" => count($departamentos),
  ];
}
?>