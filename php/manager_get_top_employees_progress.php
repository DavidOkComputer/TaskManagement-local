<?php
/*manager_get_top_employees_progress_manager.php para los 5 empleados con mayor progreso en departamentos del gerente*/

header('Content-Type: application/json');
session_start();

require_once 'db_config.php';

$response = [
    'success' => false,
    'empleados' => [],
    'message' => ''
];

try {
    // Verificar autenticación
    $id_usuario = $_SESSION['user_id'] ?? $_SESSION['id_usuario'] ?? null;
    
    if (!$id_usuario) {
        throw new Exception('Usuario no autenticado');
    }

    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
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
    $role_stmt->bind_param('i', $id_usuario);
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
    
    // Verificar que tiene rol de gerente o admin
    if (empty($departamentos_gerente) && !$is_admin) {
        throw new Exception('Acceso no autorizado - Se requiere rol de gerente');
    }

    if ($is_admin) {
        // Admin ve todos los empleados
        $sql = "
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.num_empleado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
                ROUND(
                    (SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) / COUNT(t.id_tarea)) * 100, 
                    1
                ) as progreso
            FROM tbl_usuarios u
            INNER JOIN tbl_tareas t ON u.id_usuario = t.id_participante
            WHERE t.id_participante IS NOT NULL
            GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
            HAVING total_tareas > 0
            ORDER BY progreso DESC, tareas_completadas DESC
            LIMIT 5
        ";
        $stmt = $conn->prepare($sql);
    } else {
        // Gerente ve empleados de sus departamentos
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $sql = "
            SELECT 
                u.id_usuario,
                u.nombre,
                u.apellido,
                u.num_empleado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
                ROUND(
                    (SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) / COUNT(t.id_tarea)) * 100, 
                    1
                ) as progreso
            FROM tbl_usuarios u
            INNER JOIN tbl_usuario_roles ur ON u.id_usuario = ur.id_usuario
            INNER JOIN tbl_tareas t ON u.id_usuario = t.id_participante
            INNER JOIN tbl_proyectos p ON t.id_proyecto = p.id_proyecto
            WHERE ur.id_departamento IN ($placeholders)
                AND ur.activo = 1
                AND p.id_departamento IN ($placeholders)
                AND t.id_participante IS NOT NULL
            GROUP BY u.id_usuario, u.nombre, u.apellido, u.num_empleado
            HAVING total_tareas > 0
            ORDER BY progreso DESC, tareas_completadas DESC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Bind parameters twice (for ur.id_departamento and p.id_departamento)
            $types = str_repeat('i', count($departamentos_gerente) * 2);
            $params = array_merge($departamentos_gerente, $departamentos_gerente);
            $stmt->bind_param($types, ...$params);
        }
    }
    
    if (!$stmt) {
        throw new Exception('Error al preparar consulta: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error en la consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    $empleados = [];
    
    while ($row = $result->fetch_assoc()) {
        $empleados[] = [
            'id_usuario' => (int)$row['id_usuario'],
            'nombre_completo' => $row['nombre'] . ' ' . $row['apellido'],
            'nombre' => $row['nombre'],
            'apellido' => $row['apellido'],
            'num_empleado' => (int)$row['num_empleado'],
            'total_tareas' => (int)$row['total_tareas'],
            'tareas_completadas' => (int)$row['tareas_completadas'],
            'progreso' => (float)$row['progreso']
        ];
    }

    $stmt->close();
    $conn->close();

    $response['success'] = true;
    $response['empleados'] = $empleados;
    $response['managed_departments'] = $departamentos_gerente;
    $response['managed_departments_count'] = count($departamentos_gerente);
    $response['is_admin'] = $is_admin;
    
    if (empty($empleados)) {
        $response['message'] = 'No hay empleados con tareas asignadas en los departamentos';
    }
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = 'Error al cargar empleados: ' . $e->getMessage();
    error_log('get_top_employees_progress_manager.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>