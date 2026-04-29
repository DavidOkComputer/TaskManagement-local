<?php
/* user_get_project_users.php para obtener usuarios del proyecto  */

header('Content-Type: application/json');
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once 'db_config.php';

$response = ['success' => false, 'message' => '', 'usuarios' => [], 'es_libre' => 0];

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

    $id_proyecto = isset($_GET['id_proyecto']) ? intval($_GET['id_proyecto'])
                 : (isset($_GET['id']) ? intval($_GET['id']) : 0);
    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Verificar que el usuario tiene acceso al proyecto
    $stmt = $conn->prepare("SELECT id_creador, id_participante, id_tipo_proyecto, es_libre FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Proyecto no encontrado');
    }
    $proj = $result->fetch_assoc();
    $stmt->close();

    $es_miembro = ($proj['id_creador'] == $user_id) || ($proj['id_participante'] == $user_id);
    if (!$es_miembro && (int)$proj['id_tipo_proyecto'] === 1) {
        $stmt = $conn->prepare("SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_proyecto, $user_id);
        $stmt->execute();
        $es_miembro = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }
    if (!$es_miembro) {
        throw new Exception('No tienes acceso a este proyecto');
    }

    // Obtener usuarios según tipo de proyecto
    $usuarios = [];
    if ((int)$proj['id_tipo_proyecto'] === 1) {
        //proyecto grupal todos los miembros del grupo
        $stmt = $conn->prepare("
            SELECT u.id_usuario, u.nombre, u.apellido, u.num_empleado, u.e_mail
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            WHERE pu.id_proyecto = ?
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $usuarios[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre' => $row['nombre'],
                'apellido' => $row['apellido'],
                'num_empleado' => (int)$row['num_empleado'],
                'e_mail' => $row['e_mail']
            ];
        }
        $stmt->close();
    } elseif ((int)$proj['id_tipo_proyecto'] === 2 && !empty($proj['id_participante'])) {
        //individual solo el participante asignado
        $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, num_empleado, e_mail FROM tbl_usuarios WHERE id_usuario = ?");
        $stmt->bind_param("i", $proj['id_participante']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $usuarios[] = [
                'id_usuario' => (int)$row['id_usuario'],
                'nombre' => $row['nombre'],
                'apellido' => $row['apellido'],
                'num_empleado' => (int)$row['num_empleado'],
                'e_mail' => $row['e_mail']
            ];
        }
        $stmt->close();
    }

    $response['success'] = true;
    $response['usuarios'] = $usuarios;
    $response['es_libre'] = (int)$proj['es_libre'];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('user_get_project_users.php: ' . $e->getMessage());
}

ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();