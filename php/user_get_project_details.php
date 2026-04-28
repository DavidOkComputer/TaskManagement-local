
<?php
//user_get_project_details.php para ver los detalles del proyecto como usuario
header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$response = ['success' => false, 'message' => '', 'proyecto' => null];

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) throw new Exception('No autenticado');

    $id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_proyecto <= 0) throw new Exception('ID inválido');

    $conn = getDBConnection();

    // Fetch project with full details, ensuring user is involved
    $stmt = $conn->prepare("
        SELECT p.*,
               d.nombre AS departamento_nombre,
               tp.nombre AS tipo_proyecto_nombre,
               u_creador.nombre AS creador_nombre, u_creador.apellido AS creador_apellido,
               u_part.nombre AS participante_nombre, u_part.apellido AS participante_apellido,
               (SELECT COUNT(*) FROM tbl_proyecto_usuarios WHERE id_proyecto = p.id_proyecto AND id_usuario = ?) > 0 AS es_miembro_grupo
        FROM tbl_proyectos p
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario
        LEFT JOIN tbl_usuarios u_part ON p.id_participante = u_part.id_usuario
        WHERE p.id_proyecto = ?
          AND (p.id_creador = ? OR p.id_participante = ? OR EXISTS (
              SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = p.id_proyecto AND id_usuario = ?
          ))
    ");
    $stmt->bind_param("iiiii", $user_id, $id_proyecto, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) throw new Exception('Proyecto no encontrado o sin acceso');
    $proyecto = $result->fetch_assoc();
    $stmt->close();

    // Determine user permissions
    $es_creador = (int)$proyecto['id_creador'] === $user_id;
    $puede_crear_tareas = $es_creador || ((int)$proyecto['puede_editar_otros'] === 1);

    // Task statistics
    $stmt = $conn->prepare("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estado='completado' THEN 1 ELSE 0 END) AS completadas,
        SUM(CASE WHEN estado='en proceso' THEN 1 ELSE 0 END) AS en_proceso,
        SUM(CASE WHEN estado='vencido' THEN 1 ELSE 0 END) AS vencidas
        FROM tbl_tareas WHERE id_proyecto = ?");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Get assigned users (for left panel)
    $usuarios = [];
    $stmt = $conn->prepare("
        SELECT u.id_usuario, CONCAT(u.nombre,' ',u.apellido) AS nombre_completo, u.num_empleado,
               (SELECT COUNT(*) FROM tbl_tareas WHERE id_proyecto = ? AND id_participante = u.id_usuario) AS tareas_asignadas,
               (SELECT COUNT(*) FROM tbl_tareas WHERE id_proyecto = ? AND id_participante = u.id_usuario AND estado='completado') AS tareas_completadas,
               ROUND(IFNULL((SELECT COUNT(*) FROM tbl_tareas WHERE id_proyecto = ? AND id_participante = u.id_usuario AND estado='completado') / NULLIF((SELECT COUNT(*) FROM tbl_tareas WHERE id_proyecto = ? AND id_participante = u.id_usuario), 0) * 100, 0)) AS progreso
        FROM tbl_proyecto_usuarios pu
        JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
        WHERE pu.id_proyecto = ?");
    $stmt->bind_param("iiiiii", $id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto, $id_proyecto);
    $stmt->execute();
    $res_users = $stmt->get_result();
    while ($u = $res_users->fetch_assoc()) {
        $usuarios[] = $u;
    }
    $stmt->close();

    $response['success'] = true;
    $response['proyecto'] = [
        'id_proyecto' => (int)$proyecto['id_proyecto'],
        'nombre' => $proyecto['nombre'],
        'descripcion' => $proyecto['descripcion'],
        'estado' => $proyecto['estado'],
        'progreso' => (int)$proyecto['progreso'],
        'fecha_creacion' => $proyecto['fecha_creacion'],
        'fecha_cumplimiento' => $proyecto['fecha_cumplimiento'],
        'departamento' => ['nombre' => $proyecto['departamento_nombre']],
        'tipo_proyecto' => ['id' => $proyecto['id_tipo_proyecto'], 'nombre' => $proyecto['tipo_proyecto_nombre']],
        'creador' => ['id' => $proyecto['id_creador'], 'nombre' => $proyecto['creador_nombre'] . ' ' . $proyecto['creador_apellido']],
        'participante' => $proyecto['id_participante'] ? ['nombre' => $proyecto['participante_nombre'] . ' ' . $proyecto['participante_apellido']] : null,
        'es_libre' => (int)$proyecto['es_libre'],
        'puede_editar_otros' => (int)$proyecto['puede_editar_otros'],
        'es_creador' => $es_creador,
        'puede_crear_tareas' => $puede_crear_tareas,
        'estadisticas' => [
            'total_tareas' => (int)$stats['total'],
            'tareas_completadas' => (int)$stats['completadas'],
            'tareas_en_proceso' => (int)$stats['en_proceso'],
            'tareas_vencidas' => (int)$stats['vencidas']
        ],
        'usuarios_asignados' => $usuarios
    ];
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}
echo json_encode($response);