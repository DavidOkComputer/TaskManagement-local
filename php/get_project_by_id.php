<?php
/**
 * get_project_by_id.php - Updated to include users for group projects
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'proyecto' => null];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_GET['id']);

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Get project details
    $sql = "SELECT * FROM tbl_proyectos WHERE id_proyecto = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $proyecto = $result->fetch_assoc();

    if (!$proyecto) {
        throw new Exception('Proyecto no encontrado');
    }

    // If it's a group project, get assigned users
    if ($proyecto['id_tipo_proyecto'] == 1) {
        $sql_usuarios = "SELECT pu.id_usuario, u.nombre, u.apellido, u.e_mail, u.num_empleado
                        FROM tbl_proyecto_usuarios pu
                        JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
                        WHERE pu.id_proyecto = ?
                        ORDER BY u.apellido ASC, u.nombre ASC";

        $stmt_usuarios = $conn->prepare($sql_usuarios);
        if (!$stmt_usuarios) {
            throw new Exception('Error al preparar consulta de usuarios: ' . $conn->error);
        }

        $stmt_usuarios->bind_param("i", $id_proyecto);
        if (!$stmt_usuarios->execute()) {
            throw new Exception('Error al obtener usuarios: ' . $stmt_usuarios->error);
        }

        $result_usuarios = $stmt_usuarios->get_result();
        $usuarios_asignados = [];

        while ($row = $result_usuarios->fetch_assoc()) {
            $usuarios_asignados[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
                'e_mail' => $row['e_mail'],
                'num_empleado' => (int)$row['num_empleado']
            ];
        }

        $proyecto['usuarios_asignados'] = $usuarios_asignados;
        $stmt_usuarios->close();
    }

    $response['success'] = true;
    $response['proyecto'] = $proyecto;

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error en get_project_by_id.php: ' . $e->getMessage());
}

echo json_encode($response);
?>