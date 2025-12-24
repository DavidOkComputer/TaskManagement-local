<?php

/*get_user_department.php saber el departamento del usuario actual  */

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

header("Content-Type: application/json");
require_once "db_config.php";
$response = ["success" => false, "department" => null];

try {
  $id_usuario = null;

  if (isset($_SESSION["id_usuario"])) {
    $id_usuario = (int) $_SESSION["id_usuario"];
  } elseif (isset($_SESSION["user_id"])) {
    $id_usuario = (int) $_SESSION["user_id"];
  } elseif (isset($_REQUEST["id_usuario"])) {
    $id_usuario = (int) $_REQUEST["id_usuario"];
  }

  // Info de debug a la respuesta
  if (!$id_usuario) {
    $response["message"] = "ID de usuario no disponible";

    $response["debug"] = [
      "session_id_usuario" => isset($_SESSION["id_usuario"])
        ? $_SESSION["id_usuario"]
        : "not set",

      "session_user_id" => isset($_SESSION["user_id"])
        ? $_SESSION["user_id"]
        : "not set",

      "session_started" =>
        session_status() === PHP_SESSION_ACTIVE ? "yes" : "no",

      "session_id" => session_id(),
    ];

    echo json_encode($response);

    exit();
  }

  $conn = getDBConnection();

  if (!$conn) {
    throw new Exception("Error de conexión a la base de datos");
  }

  $query = " 
        SELECT  
            u.id_usuario, 
            u.nombre, 
            u.apellido, 
            ur.id_departamento, 
            ur.id_rol, 
            ur.es_principal, 
            d.id_departamento as dept_id, 
            d.nombre as departamento_nombre, 
            d.descripcion as departamento_descripcion, 
            r.nombre as rol_nombre 
        FROM tbl_usuarios u 
        LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
            AND ur.es_principal = 1  
            AND ur.activo = 1 
        LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        WHERE u.id_usuario = ? 
    ";

  $stmt = $conn->prepare($query);

  if (!$stmt) {
    throw new Exception("Error al preparar la consulta: " . $conn->error);
  }

  $stmt->bind_param("i", $id_usuario);

  if (!$stmt->execute()) {
    throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
  }

  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if (!$user) {
    throw new Exception("Usuario no encontrado con ID: " . $id_usuario);
  }

  error_log("USER DATA: " . print_r($user, true));

  // Revisar si el usuario tiene un departamento asignado en tbl_usuario_roles
  if (!$user["id_departamento"] || $user["id_departamento"] == 0) {
    throw new Exception(
      "Usuario no tiene departamento asignado en tbl_usuario_roles",
    );
  }

  $stmt->close();

  $query_all_depts = " 
        SELECT  
            ur.id_departamento, 
            ur.id_rol, 
            ur.es_principal, 
            d.nombre as departamento_nombre, 
            d.descripcion as departamento_descripcion, 
            r.nombre as rol_nombre 
        FROM tbl_usuario_roles ur 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        WHERE ur.id_usuario = ? AND ur.activo = 1 
        ORDER BY ur.es_principal DESC, d.nombre ASC 
    ";

  $stmt_depts = $conn->prepare($query_all_depts);
  $stmt_depts->bind_param("i", $id_usuario);
  $stmt_depts->execute();
  $result_depts = $stmt_depts->get_result();
  $all_departments = [];

  while ($dept_row = $result_depts->fetch_assoc()) {
    $all_departments[] = [
      "id_departamento" => (int) $dept_row["id_departamento"],
      "nombre" => $dept_row["departamento_nombre"],
      "descripcion" => $dept_row["departamento_descripcion"],
      "id_rol" => (int) $dept_row["id_rol"],
      "rol_nombre" => $dept_row["rol_nombre"],
      "es_principal" => (bool) $dept_row["es_principal"],
    ];
  }

  $stmt_depts->close();
  $response["success"] = true;

  // Departamento principal
  $response["department"] = [
    "id_departamento" => (int) $user["id_departamento"],
    "nombre" => $user["departamento_nombre"],
    "descripcion" => $user["departamento_descripcion"],
    "usuario_nombre" => $user["nombre"] . " " . $user["apellido"],
    "id_rol" => (int) $user["id_rol"],
    "rol_nombre" => $user["rol_nombre"],
    "es_principal" => true,
  ];


  $response["all_departments"] = $all_departments;
  $response["total_departments"] = count($all_departments);
  $response["has_multiple_departments"] = count($all_departments) > 1;
  $conn->close();
} catch (Exception $e) {
  $response["message"] = $e->getMessage();

  error_log("Error en get_user_department.php: " . $e->getMessage());
}

echo json_encode($response);
?> 