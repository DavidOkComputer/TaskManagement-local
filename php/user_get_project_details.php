<?php
/* user_get_project_details.php - Detalles del proyecto para el panel izquierdo */

header('Content-Type: application/json');
session_start();
require_once 'db_config.php';

$response = ['success' => false, 'message' => '', 'proyecto' => null];

try {
    $user_id = $_SESSION['user_id'] ?? 0;
    if (!$user_id) {
        throw new Exception('Usuario no autenticado');
    }

    $id_proyecto = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($id_proyecto <= 0) {
        throw new Exception('ID de proyecto inválido');
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    //verificar que el usuario pertenece al proyecto
    $stmt = $conn->prepare("
        SELECT id_proyecto, id_creador, id_participante, id_tipo_proyecto
        FROM tbl_proyectos
        WHERE id_proyecto = ?
    ");
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
        //si es prroyecto grupal verificar pertenencia a tbl_proyecto_usuarios
        $stmt = $conn->prepare("SELECT 1 FROM tbl_proyecto_usuarios WHERE id_proyecto = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id_proyecto, $user_id);
        $stmt->execute();
        $es_miembro = $stmt->get_result()->num_rows > 0;
        $stmt->close();
    }

    if (!$es_miembro) {
        throw new Exception('No tienes acceso a este proyecto');
    }

    //obtener los detalles completos del proyecto
    $stmt = $conn->prepare("
        SELECT p.*,
               d.nombre AS departamento_nombre,
               u_creador.nombre AS creador_nombre,
               u_creador.apellido AS creador_apellido,
               u_part.nombre AS participante_nombre,
               u_part.apellido AS participante_apellido
        FROM tbl_proyectos p
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_usuarios u_creador ON p.id_creador = u_creador.id_usuario
        LEFT JOIN tbl_usuarios u_part ON p.id_participante = u_part.id_usuario
        WHERE p.id_proyecto = ?
    ");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $proyecto = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    //estadísticas de tareas
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN estado = 'completado' THEN 1 ELSE 0 END) AS completadas,
            SUM(CASE WHEN estado = 'en proceso' THEN 1 ELSE 0 END) AS en_proceso,
            SUM(CASE WHEN estado = 'vencido' THEN 1 ELSE 0 END) AS vencidas
        FROM tbl_tareas
        WHERE id_proyecto = ?
    ");
    $stmt->bind_param("i", $id_proyecto);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    //usuarios asignados para proyectos grupoales
    $usuarios_asignados = [];
    if ((int)$proyecto['id_tipo_proyecto'] === 1) {
        $stmt = $conn->prepare("
            SELECT u.id_usuario, CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo, u.num_empleado,
                   COUNT(t.id_tarea) AS tareas_asignadas,
                   SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) AS tareas_completadas,
                   COALESCE(ROUND(
                       SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) / NULLIF(COUNT(t.id_tarea), 0) * 100
                   , 0), 0) AS progreso
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            LEFT JOIN tbl_tareas t ON t.id_proyecto = pu.id_proyecto AND t.id_participante = u.id_usuario
            WHERE pu.id_proyecto = ?
            GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
            ORDER BY u.apellido, u.nombre
        ");
        $stmt->bind_param("i", $id_proyecto);
        $stmt->execute();
        $res_users = $stmt->get_result();
        while ($u = $res_users->fetch_assoc()) {
            $usuarios_asignados[] = [
                'id_usuario' => (int)$u['id_usuario'],
                'nombre_completo' => $u['nombre_completo'],
                'num_empleado' => (int)$u['num_empleado'],
                'tareas_asignadas' => (int)$u['tareas_asignadas'],
                'tareas_completadas' => (int)$u['tareas_completadas'],
                'progreso' => (float)$u['progreso']
            ];
        }
        $stmt->close();
    }

    //VER` permisos
    $es_creador = ((int)$proyecto['id_creador'] === $user_id);
    $puede_crear_tareas = $es_creador || ((int)$proyecto['puede_editar_otros'] === 1);

    //construir la respuesta
    $tipo_nombre = ((int)$proyecto['id_tipo_proyecto'] === 1) ? 'Grupal' : 'Individual';

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
        'tipo_proyecto' => ['id' => (int)$proyecto['id_tipo_proyecto'], 'nombre' => $tipo_nombre],
        'creador' => ['id' => (int)$proyecto['id_creador'], 'nombre' => trim($proyecto['creador_nombre'] . ' ' . $proyecto['creador_apellido'])],
        'participante' => $proyecto['id_participante'] ? ['nombre' => trim($proyecto['participante_nombre'] . ' ' . $proyecto['participante_apellido'] ?? '')] : null,
        'es_libre' => (int)($proyecto['es_libre'] ?? 0),
        'puede_editar_otros' => (int)$proyecto['puede_editar_otros'],
        'es_creador' => $es_creador,
        'puede_crear_tareas' => $puede_crear_tareas,
        'estadisticas' => [
            'total_tareas' => (int)$stats['total'],
            'tareas_completadas' => (int)$stats['completadas'],
            'tareas_en_proceso' => (int)$stats['en_proceso'],
            'tareas_vencidas' => (int)$stats['vencidas']
        ],
        'usuarios_asignados' => $usuarios_asignados
    ];

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('user_get_project_details.php: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);