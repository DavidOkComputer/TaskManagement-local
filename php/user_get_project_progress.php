<?php
/* get_my_projects_progress.php
   Obtiene el progreso del usuario en sus proyectos,
   incluyendo proyectos regulares del departamento
*/

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';

$response = [
    'success' => false,
    'message' => '',
    'proyectos' => []
];

try {
    // Verificar autenticación
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Usuario no autenticado');
    }

    $id_usuario     = (int)$_SESSION['user_id'];
    $id_departamento = (int)($_SESSION['user_department'] ?? 0);

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $query = "
        SELECT
            p.id_proyecto,
            p.nombre,
            p.estado,
            p.progreso                          AS progreso_proyecto,
            p.es_libre,
            COUNT(t.id_tarea)                   AS total_tareas,
            SUM(
                CASE WHEN t.estado = 'completado'
                     THEN 1 ELSE 0 END
            )                                   AS tareas_completadas
        FROM tbl_proyectos p
        -- Contar solo tareas del usuario en ese proyecto
        LEFT JOIN tbl_tareas t
               ON  p.id_proyecto   = t.id_proyecto
               AND t.id_participante = ?
        WHERE
        (
            /*proyectos regulares del departamento*/
            (
                p.id_departamento = ?
                AND (
                    p.id_participante = ?
                    OR p.id_creador   = ?
                    OR EXISTS (
                        SELECT 1
                        FROM tbl_proyecto_usuarios pu
                        WHERE pu.id_proyecto = p.id_proyecto
                          AND pu.id_usuario  = ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM tbl_tareas t2
                        WHERE t2.id_proyecto    = p.id_proyecto
                          AND t2.id_participante = ?
                    )
                )
            )
            OR
            /*proyectos libres*/
            (
                p.es_libre = 1
                AND (
                    p.id_creador      = ?
                    OR p.id_participante = ?
                    OR EXISTS (
                        SELECT 1
                        FROM tbl_proyecto_usuarios pu2
                        WHERE pu2.id_proyecto = p.id_proyecto
                          AND pu2.id_usuario  = ?
                    )
                    OR EXISTS (
                        SELECT 1
                        FROM tbl_tareas t3
                        WHERE t3.id_proyecto    = p.id_proyecto
                          AND t3.id_participante = ?
                    )
                )
            )
        )
        GROUP BY
            p.id_proyecto,
            p.nombre,
            p.estado,
            p.progreso,
            p.es_libre
        ORDER BY
            CASE
                WHEN p.estado = 'en proceso' THEN 1
                WHEN p.estado = 'pendiente'  THEN 2
                WHEN p.estado = 'vencido'    THEN 3
                ELSE 4
            END,
            p.fecha_cumplimiento ASC
        LIMIT 10
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(
            'Error al preparar la consulta: ' . $conn->error
        );
    }

    $stmt->bind_param(
        'iiiiiiiiii',
        $id_usuario,        // 1
        $id_departamento,   // 2
        $id_usuario,        // 3
        $id_usuario,        // 4
        $id_usuario,        // 5
        $id_usuario,        // 6
        $id_usuario,        // 7
        $id_usuario,        // 8
        $id_usuario,        // 9
        $id_usuario         // 10
    );

    if (!$stmt->execute()) {
        throw new Exception(
            'Error al ejecutar la consulta: ' . $stmt->error
        );
    }

    $result    = $stmt->get_result();
    $proyectos = [];

    while ($row = $result->fetch_assoc()) {
        $total_tareas      = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];

        // Progreso personal del usuario en este proyecto
        $mi_progreso = $total_tareas > 0
            ? round(($tareas_completadas / $total_tareas) * 100, 1)
            : 0;

        $proyectos[] = [
            'id_proyecto'          => (int)$row['id_proyecto'],
            'nombre'               => $row['nombre'],
            'estado'               => $row['estado'],
            'progreso_proyecto'    => (int)$row['progreso_proyecto'],
            'mi_progreso'          => $mi_progreso,
            'mis_tareas_total'     => $total_tareas,
            'mis_tareas_completadas' => $tareas_completadas,
            'es_libre'             => (bool)$row['es_libre']
        ];
    }

    $stmt->close();
    $conn->close();

    $response['success']   = true;
    $response['proyectos'] = $proyectos;

} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar progreso: ' . $e->getMessage();
    error_log('get_my_projects_progress.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>