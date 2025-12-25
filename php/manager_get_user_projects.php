<?php
/* manager_get_user_projects.php saber proyectos de un usuario en especifico*/

session_start();
header('Content-Type: application/json');
require_once('db_config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

if (!isset($_GET['id_usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ID de usuario requerido'
    ]);
    exit;
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $id_usuario_solicitado = (int)$_GET['id_usuario'];
    $id_usuario_manager = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if (!$id_usuario_manager) {
        throw new Exception('Usuario no autenticado');
    }

    $role_query = "
        SELECT 
            ur.id_rol,
            ur.id_departamento,
            ur.es_principal
        FROM tbl_usuario_roles ur
        WHERE ur.id_usuario = ?
            AND ur.activo = 1
        ORDER BY ur.es_principal DESC
    ";
    
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param('i', $id_usuario_manager);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    $is_admin = false;
    $departamentos_gerente = [];
    
    while ($row = $role_result->fetch_assoc()) {
        if ($row['id_rol'] == 1) {
            $is_admin = true;
        }
        if ($row['id_rol'] == 2) {
            $departamentos_gerente[] = (int)$row['id_departamento'];
        }
    }
    $role_stmt->close();
    
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('No tiene permisos de gerente');
    }

    if (!$is_admin) {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        $verify_query = "
            SELECT 1 
            FROM tbl_usuario_roles ur 
            WHERE ur.id_usuario = ? 
                AND ur.id_departamento IN ($placeholders)
                AND ur.activo = 1
            LIMIT 1
        ";
        
        $verify_stmt = $conn->prepare($verify_query);
        $types = 'i' . str_repeat('i', count($departamentos_gerente));
        $params = array_merge([$id_usuario_solicitado], $departamentos_gerente);
        $verify_stmt->bind_param($types, ...$params);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No tiene permiso para ver los proyectos de este usuario'
            ]);
            exit;
        }
        $verify_stmt->close();
    }

    if ($is_admin) {
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_inicio,
                p.fecha_cumplimiento,
                p.estado,
                p.id_tipo_proyecto,
                p.id_departamento,
                d.nombre as area,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as tareas_totales,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') as tareas_completadas,
                CASE
                    WHEN (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) > 0
                    THEN ROUND(
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') * 100.0 /
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto)
                    , 1)
                    ELSE 0
                END as progreso
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE (
                p.id_participante = ?
                OR pu.id_usuario = ?
                OR EXISTS (
                    SELECT 1 FROM tbl_tareas t 
                    WHERE t.id_proyecto = p.id_proyecto AND t.id_participante = ?
                )
            )
            ORDER BY p.fecha_cumplimiento DESC, p.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $id_usuario_solicitado, $id_usuario_solicitado, $id_usuario_solicitado);
    } else {
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $query = "
            SELECT DISTINCT
                p.id_proyecto,
                p.nombre,
                p.descripcion,
                p.fecha_inicio,
                p.fecha_cumplimiento,
                p.estado,
                p.id_tipo_proyecto,
                p.id_departamento,
                d.nombre as area,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) as tareas_totales,
                (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') as tareas_completadas,
                CASE
                    WHEN (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto) > 0
                    THEN ROUND(
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto AND t.estado = 'completado') * 100.0 /
                        (SELECT COUNT(*) FROM tbl_tareas t WHERE t.id_proyecto = p.id_proyecto)
                    , 1)
                    ELSE 0
                END as progreso
            FROM tbl_proyectos p
            LEFT JOIN tbl_departamentos d ON p.id_departamento = d.id_departamento
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE p.id_departamento IN ($placeholders)
            AND (
                p.id_participante = ?
                OR pu.id_usuario = ?
                OR EXISTS (
                    SELECT 1 FROM tbl_tareas t 
                    WHERE t.id_proyecto = p.id_proyecto AND t.id_participante = ?
                )
            )
            ORDER BY p.fecha_cumplimiento DESC, p.nombre ASC
        ";
        
        $stmt = $conn->prepare($query);
        $types = str_repeat('i', count($departamentos_gerente)) . 'iii';
        $params = array_merge($departamentos_gerente, [$id_usuario_solicitado, $id_usuario_solicitado, $id_usuario_solicitado]);
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $proyectos = [];
    while ($row = $result->fetch_assoc()) {
        $progreso = (float)$row['progreso'];
        $tareas_totales = (int)$row['tareas_totales'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        $is_managed = in_array((int)$row['id_departamento'], $departamentos_gerente);
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_cumplimiento' => $row['fecha_cumplimiento'],
            'estado' => $row['estado'],
            'area' => $row['area'],
            'id_departamento' => (int)$row['id_departamento'],
            'tareas_totales' => $tareas_totales,
            'tareas_completadas' => $tareas_completadas,
            'progreso' => $progreso,
            'progreso_porcentaje' => number_format($progreso, 1),
            'is_managed_department' => $is_managed
        ];
    }

    echo json_encode([
        'success' => true,
        'proyectos' => $proyectos,
        'total_proyectos' => count($proyectos),
        'id_usuario' => $id_usuario_solicitado,
        'managed_departments' => $departamentos_gerente,
        'is_admin' => $is_admin
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al cargar proyectos: ' . $e->getMessage(),
        'proyectos' => []
    ]);
    error_log('manager_get_user_projects.php Error: ' . $e->getMessage());
}
?>