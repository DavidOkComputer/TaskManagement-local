<?php
header('Content-Type: application/json');
session_start();

require_once('db_config.php');

try {
    $conn = getDBConnection();

    $sql = "SELECT  
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.progreso,
                p.estado,
                p.fecha_cumplimiento,
                p.fecha_creacion,
                p.id_tipo_proyecto,
                d.nombre AS departamento,
                CONCAT(c.nombre, ' ', c.apellido) AS creador,
                COALESCE(
                    (SELECT CONCAT(u.nombre, ' ', u.apellido)  
                     FROM tbl_usuarios u  
                     WHERE u.id_usuario = p.id_participante),
                    'Sin asignar'
                ) AS responsable,
                tp.nombre AS tipo_proyecto,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) AS total_tareas,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND LOWER(t.estado) = 'completado') AS tareas_completadas,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND LOWER(t.estado) = 'pendiente') AS tareas_pendientes,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND LOWER(t.estado) = 'vencido') AS tareas_vencidas
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_usuarios c ON p.id_creador = c.id_usuario
            LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
            ORDER BY p.progreso DESC, p.fecha_cumplimiento ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception('Error en consulta: ' . $conn->error);
    }

    $objetivos = [];
    while ($row = $result->fetch_assoc()) {
        // Map tipo_proyecto to the format expected by JS
        $tipo = 'Global Objectives';
        if ($row['id_tipo_proyecto'] == 1) {
            $tipo = 'Global Objectives'; // Proyecto grupal
        } elseif ($row['id_tipo_proyecto'] == 2) {
            $tipo = 'Regional Objectives'; // Proyecto individual
        }

        $objetivos[] = [
            'id'                  => (int)$row['id_proyecto'],
            'nombre'              => $row['nombre'],
            'descripcion'         => $row['descripcion'] ?? '',
            'responsable'         => $row['responsable'],
            'creador'             => $row['creador'],
            'departamento'        => $row['departamento'],
            'progreso'            => (int)$row['progreso'],
            'estado'              => $row['estado'],
            'fecha_cumplimiento'  => $row['fecha_cumplimiento'],
            'fecha_creacion'      => $row['fecha_creacion'],
            'tipo'                => $tipo,
            'tipo_proyecto'       => $row['tipo_proyecto'] ?? 'Sin tipo',
            'total_tareas'        => (int)$row['total_tareas'],
            'tareas_completadas'  => (int)$row['tareas_completadas'],
            'tareas_pendientes'   => (int)$row['tareas_pendientes'],
            'tareas_vencidas'     => (int)$row['tareas_vencidas']
        ];
    }

    // Return as 'objetivos' to match JavaScript expectations
    echo json_encode([
        'success'   => true,
        'objetivos' => $objetivos,
        'total'     => count($objetivos)
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>