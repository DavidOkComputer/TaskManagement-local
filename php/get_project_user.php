<?php
/* get_project_users.php - Obtener usuarios asignados a un proyecto específico*/

header('Content-Type: application/json');
require_once('db_config.php');

error_reporting(E_ALL);
ini_set('display_errors', 0);

$response = ['success' => false, 'usuarios' => []];

try {
    // Validar el ID del proyecto
    if (!isset($_GET['id_proyecto']) || empty($_GET['id_proyecto'])) {
        throw new Exception('El ID del proyecto es requerido');
    }

    $id_proyecto = intval($_GET['id_proyecto']);

    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }

    $conn = getDBConnection();

    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Obtener información del proyecto (tipo y participante)
    $stmt = $conn->prepare("
        SELECT 
            id_tipo_proyecto,
            id_participante
        FROM tbl_proyectos 
        WHERE id_proyecto = ?
    ");

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);

    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('El proyecto no existe');
    }

    $proyecto = $result->fetch_assoc();
    $id_tipo_proyecto = intval($proyecto['id_tipo_proyecto']);
    $id_participante_individual = $proyecto['id_participante'];
    $stmt->close();

    $usuarios = [];

    // Si es proyecto grupal (id_tipo_proyecto = 1), obtener todos los usuarios del grupo
    if ($id_tipo_proyecto == 1) {
        $sql_usuarios = "
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.num_empleado
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            WHERE pu.id_proyecto = ?
            ORDER BY u.apellido ASC, u.nombre ASC
        ";

        $stmt_usuarios = $conn->prepare($sql_usuarios);

        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
        }

        $stmt_usuarios->bind_param("i", $id_proyecto);

        if (!$stmt_usuarios->execute()) {
            throw new Exception('Error al obtener usuarios: ' . $stmt_usuarios->error);
        }

        $result_usuarios = $stmt_usuarios->get_result();

        while ($row = $result_usuarios->fetch_assoc()) {
            $usuarios[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre' => $row['nombre'],
                'apellido' => $row['apellido'],
                'num_empleado' => (int)$row['num_empleado']
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
                num_empleado
            FROM tbl_usuarios
            WHERE id_usuario = ?
        ";

        $stmt_usuario = $conn->prepare($sql_usuario);

        if (!$stmt_usuario) {
            throw new Exception('Error al preparar consulta de usuario: ' . $conn->error);
        }

        $stmt_usuario->bind_param("i", $id_participante_individual);

        if (!$stmt_usuario->execute()) {
            throw new Exception('Error al obtener usuario: ' . $stmt_usuario->error);
        }

        $result_usuario = $stmt_usuario->get_result();

        if ($result_usuario->num_rows > 0) {
            $row = $result_usuario->fetch_assoc();
            $usuarios[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre' => $row['nombre'],
                'apellido' => $row['apellido'],
                'num_empleado' => (int)$row['num_empleado']
            ];
        }

        $result_usuario->free();
        $stmt_usuario->close();
    }

    $response['success'] = true;
    $response['usuarios'] = $usuarios;

} catch (Exception $e) {
    $response['message'] = 'Error al cargar usuarios del proyecto: ' . $e->getMessage();
    error_log('get_project_users.php Error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}

echo json_encode($response);
?>