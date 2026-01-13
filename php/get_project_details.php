<?php
/* get_project_details.php para saber información detallada de un proyecto específico */
header('Content-Type: application/json');
require_once('db_config.php');
 
error_reporting(E_ALL);
ini_set('display_errors', 0);
 
ob_start();
 
$response = ['success' => false, 'proyecto' => null];
 
try {
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception('El ID del proyecto es requerido');
    }
 
    $id_proyecto = intval($_GET['id']);
    if ($id_proyecto <= 0) {
        throw new Exception('El ID del proyecto no es válido');
    }
 
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
 
    // Obtener información principal del proyecto
    $sql_proyecto = "
        SELECT
            p.id_proyecto,
            p.nombre,
            p.descripcion,
            p.fecha_creacion,
            p.fecha_cumplimiento,
            p.progreso,
            p.estado,
            p.id_tipo_proyecto,
            p.id_departamento,
            p.id_creador,
            p.id_participante,
            d.nombre as departamento_nombre,
            tp.nombre as tipo_proyecto_nombre,
            CONCAT(creador.nombre, ' ', creador.apellido) as creador_nombre,
            CONCAT(participante.nombre, ' ', participante.apellido) as participante_nombre,
            participante.e_mail as participante_email,
            participante.num_empleado as participante_num_empleado
        FROM tbl_proyectos p
        LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
        LEFT JOIN tbl_tipo_proyecto tp ON p.id_tipo_proyecto = tp.id_tipo_proyecto
        LEFT JOIN tbl_usuarios creador ON p.id_creador = creador.id_usuario
        LEFT JOIN tbl_usuarios participante ON p.id_participante = participante.id_usuario
        WHERE p.id_proyecto = ?
    ";
 
    $stmt = $conn->prepare($sql_proyecto);
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
    $stmt->close();
 
    // Obtener objetivos del proyecto
    $objetivos = [];
    $sql_objetivos = "
        SELECT
            id_objetivo,
            nombre,
            descripcion,
            fecha_cumplimiento,
            estado,
            progreso
        FROM tbl_objetivos
        WHERE id_proyecto = ?
        ORDER BY fecha_cumplimiento ASC
    ";
 
    $stmt_obj = $conn->prepare($sql_objetivos);
    if ($stmt_obj) {
        $stmt_obj->bind_param("i", $id_proyecto);
        if ($stmt_obj->execute()) {
            $result_obj = $stmt_obj->get_result();
            while ($row = $result_obj->fetch_assoc()) {
                $objetivos[] = [
                    'id_objetivo' => (int)$row['id_objetivo'],
                    'nombre' => $row['nombre'],
                    'descripcion' => $row['descripcion'],
                    'fecha_cumplimiento' => $row['fecha_cumplimiento'],
                    'estado' => $row['estado'],
                    'progreso' => (int)$row['progreso']
                ];
            }
            $result_obj->free();
        }
        $stmt_obj->close();
    }
 
    // Obtener tareas del proyecto
    $tareas = [];
    $sql_tareas = "
        SELECT
            t.id_tarea,
            t.nombre,
            t.descripcion,
            t.fecha_cumplimiento,
            t.estado,
            t.prioridad,
            CONCAT(u.nombre, ' ', u.apellido) as asignado_a,
            u.num_empleado
        FROM tbl_tareas t
        LEFT JOIN tbl_usuarios u ON t.id_participante = u.id_usuario
        WHERE t.id_proyecto = ?
        ORDER BY
            CASE t.prioridad
                WHEN 'alta' THEN 1
                WHEN 'media' THEN 2
                WHEN 'baja' THEN 3
                ELSE 4
            END,
            t.fecha_cumplimiento ASC
    ";
 
    $stmt_tareas = $conn->prepare($sql_tareas);
    if ($stmt_tareas) {
        $stmt_tareas->bind_param("i", $id_proyecto);
        if ($stmt_tareas->execute()) {
            $result_tareas = $stmt_tareas->get_result();
            while ($row = $result_tareas->fetch_assoc()) {
                $tareas[] = [
                    'id_tarea' => (int)$row['id_tarea'],
                    'nombre' => $row['nombre'],
                    'descripcion' => $row['descripcion'],
                    'fecha_cumplimiento' => $row['fecha_cumplimiento'],
                    'estado' => $row['estado'],
                    'prioridad' => $row['prioridad'],
                    'asignado_a' => $row['asignado_a'] ?? 'Sin asignar',
                    'num_empleado' => $row['num_empleado']
                ];
            }
            $result_tareas->free();
        }
        $stmt_tareas->close();
    }
 
    // Si es proyecto grupal, obtener usuarios asignados
    $usuarios_asignados = [];
    if ((int)$proyecto['id_tipo_proyecto'] === 1) {
        $sql_usuarios = "
            SELECT
                u.id_usuario,
                CONCAT(u.nombre, ' ', u.apellido) as nombre_completo,
                u.e_mail,
                u.num_empleado,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = ? AND t.id_participante = u.id_usuario) as tareas_asignadas,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = ? AND t.id_participante = u.id_usuario AND t.estado = 'completado') as tareas_completadas
            FROM tbl_proyecto_usuarios pu
            JOIN tbl_usuarios u ON pu.id_usuario = u.id_usuario
            WHERE pu.id_proyecto = ?
            ORDER BY u.apellido ASC, u.nombre ASC
        ";
 
        $stmt_usuarios = $conn->prepare($sql_usuarios);
        if ($stmt_usuarios) {
            $stmt_usuarios->bind_param("iii", $id_proyecto, $id_proyecto, $id_proyecto);
            if ($stmt_usuarios->execute()) {
                $result_usuarios = $stmt_usuarios->get_result();
                while ($row = $result_usuarios->fetch_assoc()) {
                    $tareas_asig = (int)$row['tareas_asignadas'];
                    $tareas_comp = (int)$row['tareas_completadas'];
                    $progreso_usuario = $tareas_asig > 0 ? round(($tareas_comp / $tareas_asig) * 100, 1) : 0;
                    
                    $usuarios_asignados[] = [
                        'id_usuario' => (int)$row['id_usuario'],
                        'nombre_completo' => $row['nombre_completo'],
                        'e_mail' => $row['e_mail'],
                        'num_empleado' => (int)$row['num_empleado'],
                        'tareas_asignadas' => $tareas_asig,
                        'tareas_completadas' => $tareas_comp,
                        'progreso' => $progreso_usuario
                    ];
                }
                $result_usuarios->free();
            }
            $stmt_usuarios->close();
        }
    }
 
    // Calcular estadísticas
    $total_tareas = count($tareas);
    $tareas_completadas = count(array_filter($tareas, fn($t) => strtolower($t['estado']) === 'completado'));
    $tareas_pendientes = count(array_filter($tareas, fn($t) => strtolower($t['estado']) === 'pendiente'));
    $tareas_en_proceso = count(array_filter($tareas, fn($t) => strtolower($t['estado']) === 'en proceso'));
    $tareas_vencidas = count(array_filter($tareas, fn($t) => strtolower($t['estado']) === 'vencido'));
 
    $total_objetivos = count($objetivos);
    $objetivos_completados = count(array_filter($objetivos, fn($o) => strtolower($o['estado']) === 'completado'));
 
    // Construir respuesta
    $response['success'] = true;
    $response['proyecto'] = [
        'id_proyecto' => (int)$proyecto['id_proyecto'],
        'nombre' => $proyecto['nombre'],
        'descripcion' => $proyecto['descripcion'],
        'fecha_creacion' => $proyecto['fecha_creacion'],
        'fecha_cumplimiento' => $proyecto['fecha_cumplimiento'],
        'progreso' => (int)$proyecto['progreso'],
        'estado' => $proyecto['estado'],
        'tipo_proyecto' => [
            'id' => (int)$proyecto['id_tipo_proyecto'],
            'nombre' => $proyecto['tipo_proyecto_nombre'] ?? ($proyecto['id_tipo_proyecto'] == 1 ? 'Grupal' : 'Individual')
        ],
        'departamento' => [
            'id' => (int)$proyecto['id_departamento'],
            'nombre' => $proyecto['departamento_nombre'] ?? 'Sin departamento'
        ],
        'creador' => [
            'id' => (int)$proyecto['id_creador'],
            'nombre' => $proyecto['creador_nombre'] ?? 'Desconocido'
        ],
        'participante' => $proyecto['id_tipo_proyecto'] == 1 ? null : [
            'id' => (int)$proyecto['id_participante'],
            'nombre' => $proyecto['participante_nombre'],
            'email' => $proyecto['participante_email'],
            'num_empleado' => $proyecto['participante_num_empleado']
        ],
        'usuarios_asignados' => $usuarios_asignados,
        'objetivos' => $objetivos,
        'tareas' => $tareas,
        'estadisticas' => [
            'total_tareas' => $total_tareas,
            'tareas_completadas' => $tareas_completadas,
            'tareas_pendientes' => $tareas_pendientes,
            'tareas_en_proceso' => $tareas_en_proceso,
            'tareas_vencidas' => $tareas_vencidas,
            'total_objetivos' => $total_objetivos,
            'objetivos_completados' => $objetivos_completados,
            'total_usuarios' => count($usuarios_asignados)
        ]
    ];
 
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log('get_project_details.php Error: ' . $e->getMessage());
} finally {
    if (isset($conn) && $conn) {
        $conn->close();
    }
}
 
ob_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>