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

// Obtener información del usuario actual (rol y departamento principal)

$user_info_query = " 
        SELECT u.id_departamento as departamento_principal, u.id_rol as rol_principal 
        FROM tbl_usuarios u 
        WHERE u.id_usuario = ? 
    ";

$user_info_stmt = $conn->prepare($user_info_query);

$user_info_stmt->bind_param("i", $user_id);

$user_info_stmt->execute();

$user_info_result = $user_info_stmt->get_result();

$user_info = $user_info_result->fetch_assoc();

$user_info_stmt->close();

$departamento_principal_usuario =
    (int) ($user_info["departamento_principal"] ?? 0);

$rol_principal_usuario = (int) ($user_info["rol_principal"] ?? 0);

// Obtener roles del usuario desde junction table

$mis_departamentos = [];
$role_query = " 
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal 
        FROM tbl_usuario_roles ur 
        WHERE ur.id_usuario = ? 
        AND ur.activo = 1 
    ";

$role_stmt = $conn->prepare($role_query);
$role_stmt->bind_param("i", $user_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();
$is_admin = false;
$is_manager = false;

while ($row = $role_result->fetch_assoc()) {
    if ($row["id_rol"] == 1) {
        $is_admin = true;
    }

    if ($row["id_rol"] == 2) {
        $is_manager = true;
    }
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
            $departamento_principal_usuario = (int) $row["id_departamento"];
        }

    if ($row["id_rol"] == 1) {
        $is_admin = true;
    }

    if ($row["id_rol"] == 2) {
        $is_manager = true;
    }
    }

    $legacy_stmt->close();
}

$filter_rol = isset($_GET["id_rol"]) ? intval($_GET["id_rol"]) : null;

  // Construir query base
$baseFields = " 
        DISTINCT u.id_usuario, 
        u.nombre, 
        u.apellido, 
        u.usuario, 
        u.num_empleado, 
        u.acceso, 
        u.id_departamento as id_departamento_principal, 
        u.id_rol as id_rol_principal, 
        u.id_superior, 
        u.e_mail, 
        COALESCE(ur.id_departamento, u.id_departamento) as id_departamento_asignado, 
        COALESCE(ur.id_rol, u.id_rol) as id_rol_asignado, 
        COALESCE(ur.es_principal, 1) as es_principal, 
        d.nombre as area, 
        r.nombre as nombre_rol 
        {$fotoField} 
    ";

$usuarios = [];
$usuarios_vistos = [];

if ($is_admin) {
    // Admin puede ver todos los usuarios

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
    // MANAGER: Solo puede ver subordinados directos O empleados del mismo departamento principal

    if ($departamento_principal_usuario <= 0) {
    throw new Exception(
        "No se pudo determinar el departamento principal del usuario",
    );
    }

    if ($filter_rol !== null && $filter_rol > 0) {
    $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1 
                LEFT JOIN tbl_departamentos d ON COALESCE(ur.id_departamento, u.id_departamento) = d.id_departamento 
                LEFT JOIN tbl_roles r ON COALESCE(ur.id_rol, u.id_rol) = r.id_rol 
                WHERE ( 
                    u.id_superior = ? 
                    OR u.id_departamento = ? 
                ) 
                AND u.id_usuario != ? 
                AND COALESCE(ur.id_rol, u.id_rol) = ? 
                ORDER BY u.apellido ASC, u.nombre ASC";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param(
        "iiii",
        $user_id,
        $departamento_principal_usuario,
        $user_id,
        $filter_rol,
    );
    } else {
    $query = "SELECT {$baseFields} 
                FROM tbl_usuarios u 
                LEFT JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario AND ur.activo = 1 
                LEFT JOIN tbl_departamentos d ON COALESCE(ur.id_departamento, u.id_departamento) = d.id_departamento 
                LEFT JOIN tbl_roles r ON COALESCE(ur.id_rol, u.id_rol) = r.id_rol 
                WHERE ( 
                    u.id_superior = ? 
                    OR u.id_departamento = ? 
                ) 
                AND u.id_usuario != ? 
                ORDER BY u.apellido ASC, u.nombre ASC";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }
    $stmt->bind_param(
        "iii",
        $user_id,
        $departamento_principal_usuario,
        $user_id,
    );
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

    // Determinar si es subordinado directo

    $es_subordinado = ((int) $row["id_superior"]) === $user_id;
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
    "es_subordinado" => $es_subordinado,
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

echo json_encode([
    "success" => true,
    "usuarios" => $usuarios,
    "debug" => [
    "user_id" => $user_id,
    "departamento_principal_usuario" => $departamento_principal_usuario,
    "is_admin" => $is_admin,
    "is_manager" => $is_manager,
    "total_usuarios" => count($usuarios),
    "filtro_aplicado" => $is_admin
        ? "admin_todos"
        : "subordinados_o_mismo_departamento_principal",
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