
<?php
//para saber todas las tareas correspondientes al proyecto
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$response = ['success' => false, 'message' => '', 'tasks' => []];

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) throw new Exception('No autenticado');

    $id_proyecto = isset($_GET['id_proyecto']) ? intval($_GET['id_proyecto']) : 0;
    if ($id_proyecto <= 0) throw new Exception('ID inválido');

    $conn = getDBConnection();

    //verificar que el usuario es parte del proyecto
    $stmt = $conn->prepare("SELECT id_creador, id_participante FROM tbl_proyectos WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Proyecto no existe');
    $proj = $result->fetch_assoc();
    $stmt->close();

    $allowed = ($proj['id_creador'] == $user_id) || ($proj['id_participante'] == $user_id);

    if (!$allowed) {
        //ver membresia a grupo
        $stmt = $conn->prepare("SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_proyecto, $user_id);
        $stmt->execute();
        $allowed = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    if (!$allowed) throw new Exception('No tienes acceso a este proyecto');

    //obtener tareas
    $stmt = $conn->prepare("
        SELECT t.*, u.nombre AS participante_nombre, u.apellido AS participante_apellido
        FROM tbl_tareas t
        LEFT JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
        WHERE t.id_proyecto = ?
        ORDER BY t.fecha_cumplimiento ASC");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $res = $stmt->get_result();
    $tasks = [];
    while ($row = $res->fetch_assoc()) {
        $tasks[] = [
            'id_tarea' => (int)$row['id_tarea'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'estado' => $row['estado'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'id_participante' => (int)$row['id_participante'],
            'participante' => $row['participante_nombre'] ? ($row['participante_nombre'] . ' ' . $row['participante_apellido']) : null,
        ];
    }
    $stmt->close();

    $response['success'] = true;
    $response['tasks'] = $tasks;
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}
echo json_encode($response);