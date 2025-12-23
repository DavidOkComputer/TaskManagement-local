<?php
/*create_department.php - Crear departamentos para los usuarios */

error_reporting(E_ALL);
ini_set("display_errors", 0);
header("Content-Type: application/json");

require_once "db_config.php";

$response = [
  "success" => false,
  "message" => "",
];

try {
  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    throw new Exception("Método de solicitud no válido");
  }

  // Validar y limpiar datos
  $nombre = isset($_POST["nombre"]) ? trim($_POST["nombre"]) : "";
  $descripcion = isset($_POST["descripcion"])
    ? trim($_POST["descripcion"])
    : "";
  $id_creador = isset($_POST["id_creador"]) ? intval($_POST["id_creador"]) : 0;

  // Validaciones básicas
  if (empty($nombre)) {
    throw new Exception("El nombre del departamento es requerido");
  }

  if (strlen($nombre) > 200) {
    throw new Exception(
      "El nombre del departamento no puede exceder 200 caracteres",
    );
  }

  if (empty($descripcion)) {
    throw new Exception("La descripción del departamento es requerida");
  }

  if (strlen($descripcion) > 200) {
    throw new Exception("La descripción no puede exceder 200 caracteres");
  }

  if ($id_creador <= 0) {
    throw new Exception("ID de creador no válido");
  }

  $conn = getDBConnection();

  if ($conn->connect_error) {
    throw new Exception(
      "Error de conexión a la base de datos: " . $conn->connect_error,
    );
  }

  $conn->set_charset("utf8mb4");

  // Verificar que el creador existe

  $stmt = $conn->prepare(" 
        SELECT id_usuario, nombre, apellido, id_rol as rol_legacy  
        FROM tbl_usuarios  
        WHERE id_usuario = ? 
    ");

  $stmt->bind_param("i", $id_creador);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    throw new Exception("El usuario creador no existe");
  }

  $creador = $result->fetch_assoc();
  $stmt->close();

  // Verificar permisos del creador usando la nueva estructura de roles
  // El usuario debe ser administrador en al menos un departamento
  $stmt = $conn->prepare(" 
        SELECT ur.id_usuario_roles, ur.id_departamento, d.nombre as departamento 
        FROM tbl_usuario_roles ur 
        JOIN tbl_departamentos d ON ur.id_departamento = d.id_departamento 
        WHERE ur.id_usuario = ?  
        AND ur.id_rol = 1  -- Rol de administrador 
        AND ur.activo = 1 
        LIMIT 1 
    ");

  $stmt->bind_param("i", $id_creador);
  $stmt->execute();
  $admin_result = $stmt->get_result();

  // También verificar el rol legacy en tbl_usuarios por compatibilidad
  $es_admin_nuevo = $admin_result->num_rows > 0;
  $es_admin_legacy = $creador["rol_legacy"] == 1;
  $stmt->close();

  if (!$es_admin_nuevo && !$es_admin_legacy) {
    throw new Exception("Solo los administradores pueden crear departamentos");
  }

  // Verificar si ya existe el nombre del departamento
  $stmt = $conn->prepare(" 
        SELECT id_departamento  
        FROM tbl_departamentos  
        WHERE LOWER(nombre) = LOWER(?) 
    ");

  $stmt->bind_param("s", $nombre);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    throw new Exception("Ya existe un departamento con ese nombre");
  }

  $stmt->close();
  // Insertar nuevo departamento
  $stmt = $conn->prepare(" 
        INSERT INTO tbl_departamentos (nombre, descripcion, id_creador)  
        VALUES (?, ?, ?) 
    ");

  if (!$stmt) {
    throw new Exception("Error al preparar la consulta: " . $conn->error);
  }

  $stmt->bind_param("ssi", $nombre, $descripcion, $id_creador);

  if ($stmt->execute()) {
    $nuevo_id = $stmt->insert_id;
    $response["success"] = true;
    $response["message"] = "Departamento creado exitosamente";
    $response["id_departamento"] = $nuevo_id;
    $response["nombre"] = $nombre;
    $response["descripcion"] = $descripcion;
    error_log(
      "Departamento creado: ID={$nuevo_id}, Nombre={$nombre}, " .
        "Creador={$id_creador} ({$creador["nombre"]} {$creador["apellido"]})",
    );
  } else {
    throw new Exception("Error al crear el departamento: " . $stmt->error);
  }

  $stmt->close();

  $conn->close();
} catch (Exception $e) {
  $response["success"] = false;
  $response["message"] = $e->getMessage();
  error_log("Error en create_department.php: " . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

exit();
?>