<?php
/**
 * get_project_users.php
 * obtiene todos los usuraios asignados a un proyecto especifico de proyectos grupales
 */

header('Content-Type: application/json');
require_once 'db_config.php';

$response = ['success' => false, 'usuarios' => []];

try {
    if (!isset($_GET['id'])) {
        throw new Exception('ID de proyecto requerido');
    }

    $id_proyecto = intval($_GET['id']);

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //obtener usuarios de tabla unida
    $sql = "SELECT pu.id_usuario, 
                   u.nombre, 
                   u.apellido, 
                   u.e_mail, 
                   u.num_empleado
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            WHERE pu.id_proyecto = ?
            ORDER BY u.apellido ASC, u.nombre ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->bind_param("i", $id_proyecto);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $response['usuarios'][] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'e_mail' => $row['e_mail'],
            'num_empleado' => (int)$row['num_empleado']
        ];
    }

    $response['success'] = true;

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('Error en get_project_users.php: ' . $e->getMessage());
}

echo json_encode($response);
?>