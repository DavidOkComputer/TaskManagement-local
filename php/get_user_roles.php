
<?php
/* get_user_roles.php para obtener todos los roles asignados a un usuario específico */

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

  // Obtener ID del usuario
  $id_usuario = isset($_GET["id_usuario"]) ? intval($_GET["id_usuario"]) : 0;

  if ($id_usuario <= 0) {
    throw new Exception("ID de usuario no válido");
  }

  // Verificar que el usuario existe
  $checkUser = $conn->prepare(
    "SELECT id_usuario, nombre, apellido FROM tbl_usuarios WHERE id_usuario = ?",
  );

  $checkUser->bind_param("i", $id_usuario);
  $checkUser->execute();
  $userResult = $checkUser->get_result();

  if ($userResult->num_rows === 0) {
    throw new Exception("Usuario no encontrado");
  }

  $userData = $userResult->fetch_assoc();

  $checkUser->close();

  // Obtener todos los roles del usuario usando el stored procedure o query directa

  $query = "SELECT  

                ur.id_usuario_roles as id, 

                ur.id_departamento, 

                d.nombre AS departamento, 

                ur.id_rol, 

                r.nombre AS rol, 

                r.descripcion AS descripcion_rol, 

                ur.es_principal, 

                ur.activo, 

                ur.fecha_asignacion 

            FROM tbl_usuario_roles ur 

            INNER JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 

            INNER JOIN tbl_roles r ON ur.id_rol = r.id_rol 

            WHERE ur.id_usuario = ? AND ur.activo = 1 

            ORDER BY ur.es_principal DESC, d.nombre ASC";

  $stmt = $conn->prepare($query);

  if (!$stmt) {
    throw new Exception("Error al preparar la consulta: " . $conn->error);
  }

  $stmt->bind_param("i", $id_usuario);

  $stmt->execute();

  $result = $stmt->get_result();

  $roles = [];

  $rol_principal = null;

  while ($row = $result->fetch_assoc()) {
    $rolData = [
      "id" => (int) $row["id"],

      "id_departamento" => (int) $row["id_departamento"],

      "departamento" => $row["departamento"],

      "id_rol" => (int) $row["id_rol"],

      "rol" => $row["rol"],

      "descripcion_rol" => $row["descripcion_rol"],

      "es_principal" => (bool) $row["es_principal"],

      "activo" => (bool) $row["activo"],

      "fecha_asignacion" => $row["fecha_asignacion"],
    ];

    $roles[] = $rolData;

    if ($row["es_principal"]) {
      $rol_principal = $rolData;
    }
  }

  echo json_encode([
    "success" => true,

    "usuario" => [
      "id_usuario" => (int) $userData["id_usuario"],

      "nombre" => $userData["nombre"],

      "apellido" => $userData["apellido"],

      "nombre_completo" => $userData["nombre"] . " " . $userData["apellido"],
    ],

    "roles" => $roles,

    "rol_principal" => $rol_principal,

    "total_roles" => count($roles),
  ]);

  $result->free();

  $stmt->close();

  $conn->close();
} catch (Exception $e) {
  echo json_encode([
    "success" => false,

    "message" => "Error al cargar roles del usuario: " . $e->getMessage(),

    "roles" => [],
  ]);

  error_log("get_user_roles.php Error: " . $e->getMessage());
}
?> 