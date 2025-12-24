<?php

/*get_user_role_info.php para obtener el rol de cada usuario logeado */

session_start();
header("Content-Type: application/json; charset=utf-8");
require_once "db_config.php";


// Obtener id de usuario desde la sesion
$id_usuario = isset($_SESSION["id_usuario"])
  ? $_SESSION["id_usuario"]
  : $_SESSION["user_id"];

try {
  $conn = getDBConnection();

  if (!$conn) {
    throw new Exception("Error de conexión a la base de datos");
  }

  $query = " 
        SELECT  
            u.id_usuario, 
            u.nombre, 
            u.apellido, 
            ur.id_rol, 
            ur.id_departamento, 
            ur.es_principal, 
            r.nombre AS nombre_rol, 
            d.nombre AS nombre_departamento 
        FROM tbl_usuarios u 
        LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario  
            AND ur.es_principal = 1  
            AND ur.activo = 1 
        LEFT JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        LEFT JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE u.id_usuario = ? 
        LIMIT 1 
    ";

  $stmt = $conn->prepare($query);

  if (!$stmt) {
    throw new Exception("Error preparando consulta: " . $conn->error);
  }

  $stmt->bind_param("i", $id_usuario);

  if (!$stmt->execute()) {
    throw new Exception("Error ejecutando consulta: " . $stmt->error);
  }

  $result = $stmt->get_result();
  $user = $result->fetch_assoc();

  if (!$user) {
    throw new Exception("Usuario no encontrado");
  }

  $stmt->close();

  $query_roles = " 
        SELECT  
            ur.id_usuario_roles, 
            ur.id_rol, 
            ur.id_departamento, 
            ur.es_principal, 
            r.nombre AS nombre_rol, 
            d.nombre AS nombre_departamento 
        FROM tbl_usuario_roles ur 
        JOIN tbl_roles r ON ur.id_rol = r.id_rol 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE ur.id_usuario = ? AND ur.activo = 1 
        ORDER BY ur.es_principal DESC, d.nombre ASC 
    ";

  $stmt_roles = $conn->prepare($query_roles);
  $stmt_roles->bind_param("i", $id_usuario);
  $stmt_roles->execute();
  $result_roles = $stmt_roles->get_result();
  $all_roles = [];
  $managed_departments = [];
  $is_manager_anywhere = false;
  $is_admin = false;

  while ($role_row = $result_roles->fetch_assoc()) {
    $all_roles[] = [
      "id_usuario_roles" => (int) $role_row["id_usuario_roles"],
      "id_rol" => (int) $role_row["id_rol"],
      "id_departamento" => (int) $role_row["id_departamento"],
      "nombre_rol" => $role_row["nombre_rol"],
      "nombre_departamento" => $role_row["nombre_departamento"],
      "es_principal" => (bool) $role_row["es_principal"],
    ];

    // Verificar si es admin en algún rol
    if ($role_row["id_rol"] == 1) {
      $is_admin = true;
    }

    // Verificar si es gerente en algún departamento
    if ($role_row["id_rol"] == 2) {
      $is_manager_anywhere = true;
      $managed_departments[] = [
        "id_departamento" => (int) $role_row["id_departamento"],
        "nombre_departamento" => $role_row["nombre_departamento"],
      ];
    }
  }

  $stmt_roles->close();

  // Determinar permisos basados en rol principal
  $id_rol_principal = $user["id_rol"] ?? null;
  $canViewAllDepartments = $id_rol_principal == 1; // solo admin ve todos
  $isManager = $id_rol_principal == 2; // rol principal es gerente
  $isAdmin = $id_rol_principal == 1; // rol principal es admin

  echo json_encode([
    "success" => true,
    "data" => [
      "id_usuario" => (int) $user["id_usuario"],
      "nombre" => $user["nombre"],
      "apellido" => $user["apellido"],

      // Rol y departamento principal
      "id_rol" => $id_rol_principal ? (int) $id_rol_principal : null,
      "nombre_rol" => $user["nombre_rol"],
      "id_departamento" => $user["id_departamento"]
        ? (int) $user["id_departamento"]
        : null,

      "nombre_departamento" => $user["nombre_departamento"],

      // Flags de permisos
      "can_view_all_departments" => $canViewAllDepartments,
      "is_admin" => $isAdmin,
      "is_manager" => $isManager,
      "show_department_dropdown" => $canViewAllDepartments,
      // NUEVO: Información de roles múltiples
      "is_admin_anywhere" => $is_admin,
      "is_manager_anywhere" => $is_manager_anywhere,
      "all_roles" => $all_roles,
      "managed_departments" => $managed_departments,
      "total_roles" => count($all_roles),
      "total_managed_departments" => count($managed_departments),
    ],
  ]);

  $conn->close();
} catch (Exception $e) {
  error_log("Error in get_user_role_info.php: " . $e->getMessage());

  echo json_encode([
    "success" => false,

    "message" =>
      "Error al obtener información del usuario: " . $e->getMessage(),
  ]);
}
?> 