<?php

/*manager_get_project_users.php  para obtener usuarios de un proyecto */

session_start();
header("Content-Type: application/json");
require_once "db_config.php";
error_reporting(E_ALL);
ini_set("display_errors", 0);
ob_start();

$response = ["success" => false, "usuarios" => []];

try {
    // Validar sesión del usuario
    $id_usuario = $_SESSION["user_id"] ?? ($_SESSION["id_usuario"] ?? null);
    if (!$id_usuario) {
        throw new Exception("Usuario no autenticado");
    }

    // Validar el ID del proyecto
    if (!isset($_GET["id_proyecto"]) || empty($_GET["id_proyecto"])) {
        throw new Exception("El ID del proyecto es requerido");
    }

    $id_proyecto = intval($_GET["id_proyecto"]);

    if ($id_proyecto <= 0) {
        throw new Exception("El ID del proyecto no es válido");
    }

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception("Error de conexión a la base de datos");
    }

    $role_query = " 
        SELECT ur.id_rol, ur.id_departamento, ur.es_principal 
        FROM tbl_usuario_roles ur 
        WHERE ur.id_usuario = ? AND ur.activo = 1 
        ORDER BY ur.es_principal DESC 
    ";

    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param("i", $id_usuario);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    $is_admin = false;
    $departamentos_gerente = [];

    while ($row = $role_result->fetch_assoc()) {
        if ($row["id_rol"] == 1) {
            $is_admin = true;
        }

        if ($row["id_rol"] == 2) {
            $departamentos_gerente[] = (int) $row["id_departamento"];
        }
    }

    $role_stmt->close();

    // Obtener información del proyecto
    $stmt = $conn->prepare(" 
        SELECT id_tipo_proyecto, id_participante, id_departamento, id_creador 
        FROM tbl_proyectos 
        WHERE id_proyecto = ? 
    ");

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    if (!$stmt->execute()) {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("El proyecto no existe");
    }

    $proyecto = $result->fetch_assoc();
    $id_tipo_proyecto = intval($proyecto["id_tipo_proyecto"]);
    $id_participante_individual = $proyecto["id_participante"];
    $id_departamento_proyecto = intval($proyecto["id_departamento"]);
    $id_creador_proyecto = intval($proyecto["id_creador"]);
    $stmt->close();
    $has_access = false;

    // Admin tiene acceso a todo
    if ($is_admin) {
        $has_access = true;
    }

    // Gerente tiene acceso a proyectos de sus departamentos
    elseif (in_array($id_departamento_proyecto, $departamentos_gerente)) {
        $has_access = true;
    }

    // Usuario es creador del proyecto
    elseif ($id_creador_proyecto == $id_usuario) {
        $has_access = true;
    }

    // Usuario está asignado al proyecto (individual)
    elseif ($id_participante_individual == $id_usuario) {
        $has_access = true;
    }

    // Usuario está en el grupo del proyecto
    else {
        $group_check = $conn->prepare(" 
            SELECT 1 FROM tbl_proyecto_usuarios 
            WHERE id_proyecto = ? AND id_usuario = ? 
        ");

        $group_check->bind_param("ii", $id_proyecto, $id_usuario);
        $group_check->execute();
        if ($group_check->get_result()->num_rows > 0) {
            $has_access = true;
        }
        $group_check->close();
    }

    // Verificar si el proyecto es de un subordinado del gerente
    if (!$has_access) {
        // Verificar si el creador del proyecto es subordinado del usuario actual
        $sub_check = $conn->prepare(" 
            SELECT 1 FROM tbl_usuarios  
            WHERE id_usuario = ? AND id_superior = ? 
        ");

        $sub_check->bind_param("ii", $id_creador_proyecto, $id_usuario);
        $sub_check->execute();
        if ($sub_check->get_result()->num_rows > 0) {
            $has_access = true;
        }
        $sub_check->close();
    }

    // Verificar si el participante individual es subordinado
    if (!$has_access && $id_participante_individual) {
        $sub_part_check = $conn->prepare(" 
            SELECT 1 FROM tbl_usuarios  
            WHERE id_usuario = ? AND id_superior = ? 
        ");

        $sub_part_check->bind_param(
            "ii",
            $id_participante_individual,
            $id_usuario
        );

        $sub_part_check->execute();

        if ($sub_part_check->get_result()->num_rows > 0) {
            $has_access = true;
        }
        $sub_part_check->close();
    }

    // Verificar si algún miembro del grupo del proyecto es subordinado
    if (!$has_access && $id_tipo_proyecto == 1) {
        $sub_group_check = $conn->prepare(" 
            SELECT 1 FROM tbl_proyecto_usuarios pu 
            INNER JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario 
            WHERE pu.id_proyecto = ? AND u.id_superior = ? 
            LIMIT 1 
        ");

        $sub_group_check->bind_param("ii", $id_proyecto, $id_usuario);
        $sub_group_check->execute();

        if ($sub_group_check->get_result()->num_rows > 0) {
            $has_access = true;
        }
        $sub_group_check->close();
    }

    if (!$has_access) {
        throw new Exception("No tiene permiso para acceder a este proyecto");
    }

    $usuarios = [];

    // Si es proyecto grupal (id_tipo_proyecto = 1), obtener todos los usuarios del grupo
    if ($id_tipo_proyecto == 1) {
        $sql_usuarios = " 
            SELECT  
                u.id_usuario, 
                u.nombre, 
                u.apellido, 
                u.num_empleado, 
                u.e_mail 
            FROM tbl_proyecto_usuarios pu 
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario 
            WHERE pu.id_proyecto = ? 
            ORDER BY u.apellido ASC, u.nombre ASC 
        ";

        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception(
                "Error al preparar consulta de usuarios: " . $conn->error
            );
        }

        $stmt_usuarios->bind_param("i", $id_proyecto);

        if (!$stmt_usuarios->execute()) {
            throw new Exception(
                "Error al obtener usuarios: " . $stmt_usuarios->error
            );
        }

        $result_usuarios = $stmt_usuarios->get_result();

        while ($row = $result_usuarios->fetch_assoc()) {
            $usuarios[] = [
                "id_usuario" => (int) $row["id_usuario"],
                "nombre" => $row["nombre"],
                "apellido" => $row["apellido"],
                "num_empleado" => (int) $row["num_empleado"],
                "e_mail" => $row["e_mail"],
            ];
        }

        $result_usuarios->free();
        $stmt_usuarios->close();
    }

    // Si es proyecto individual (id_tipo_proyecto = 2), obtener solo el usuario asignado
    elseif ($id_tipo_proyecto == 2 && !empty($id_participante_individual)) {
        $sql_usuario = " 
            SELECT  
                id_usuario, 
                nombre, 
                apellido, 
                num_empleado, 
                e_mail 
            FROM tbl_usuarios 
            WHERE id_usuario = ? 
        ";

        $stmt_usuario = $conn->prepare($sql_usuario);

        if (!$stmt_usuario) {
            throw new Exception(
                "Error al preparar consulta de usuario: " . $conn->error
            );
        }

        $stmt_usuario->bind_param("i", $id_participante_individual);

        if (!$stmt_usuario->execute()) {
            throw new Exception(
                "Error al obtener usuario: " . $stmt_usuario->error
            );
        }

        $result_usuario = $stmt_usuario->get_result();

        if ($result_usuario->num_rows > 0) {
            $row = $result_usuario->fetch_assoc();

            $usuarios[] = [
                "id_usuario" => (int) $row["id_usuario"],
                "nombre" => $row["nombre"],
                "apellido" => $row["apellido"],
                "num_empleado" => (int) $row["num_empleado"],
                "e_mail" => $row["e_mail"],
            ];
        }

        $result_usuario->free();
        $stmt_usuario->close();
    }

    $response["success"] = true;
    $response["usuarios"] = $usuarios;
    $response["tipo_proyecto"] = $id_tipo_proyecto;
    $response["total_usuarios"] = count($usuarios);
    $response["id_departamento"] = $id_departamento_proyecto;
} catch (Exception $e) {
    $response["message"] =
        "Error al cargar usuarios del proyecto: " . $e->getMessage();

    error_log("manager_get_project_users.php Error: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

ob_clean();
echo json_encode($response);
ob_end_flush();