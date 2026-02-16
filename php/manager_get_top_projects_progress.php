
<?php
/*manager_get_top_projects_progress.php Obtiene los 5 proyectos con mayor progreso para el dashboard del gerente*/

header('Content-Type: application/json; charset=utf-8');

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

$response = [
    'success' => false,
    'proyectos' => [],
    'message' => ''
];

try {
    
    require_once('db_config.php');
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Obtener roles del usuario desde tbl_usuario_roles
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
    
    ob_clean();
    
    // Construir consulta según rol
    if ($is_admin) {
        // Admin ve todos los proyectos
        $sql = "
            SELECT 
                p.id_proyecto,
                p.nombre,
                p.progreso,
                p.estado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_proyectos p
            LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
            WHERE p.estado != 'completado'
            GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
            ORDER BY p.progreso DESC, p.estado ASC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql);
    } else {
        // Gerente ve proyectos de sus departamentos
        $placeholders = implode(',', array_fill(0, count($departamentos_gerente), '?'));
        
        $sql = "
            SELECT 
                p.id_proyecto,
                p.nombre,
                p.progreso,
                p.estado,
                COUNT(t.id_tarea) as total_tareas,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas
            FROM tbl_proyectos p
            LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
            LEFT JOIN tbl_proyecto_usuarios pu ON p.id_proyecto = pu.id_proyecto
            WHERE p.estado != 'completado'
                AND (
                    p.id_departamento IN ($placeholders)
                    OR p.id_creador = ?
                    OR p.id_participante = ?
                    OR pu.id_usuario = ?
                )
            GROUP BY p.id_proyecto, p.nombre, p.progreso, p.estado
            ORDER BY p.progreso DESC, p.estado ASC
            LIMIT 5
        ";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $types = str_repeat('i', count($departamentos_gerente)) . 'iii';
            $params = array_merge($departamentos_gerente, [$id_usuario, $id_usuario, $id_usuario]);
            $stmt->bind_param($types, ...$params);
        }
    }
    
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta: ' . $conn->error);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    $proyectos = [];
    
    while ($row = $result->fetch_assoc()) {
        $total_tareas = (int)$row['total_tareas'];
        $tareas_completadas = (int)$row['tareas_completadas'];
        
        // Calcular progreso basado en tareas si hay tareas
        if ($total_tareas > 0) {
            $progreso_calculado = round(($tareas_completadas / $total_tareas) * 100, 1);
        } else {
            $progreso_calculado = (float)$row['progreso'];
        }
        
        $proyectos[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['nombre'],
            'progreso' => $progreso_calculado,
            'estado' => $row['estado'],
            'total_tareas' => $total_tareas,
            'tareas_completadas' => $tareas_completadas
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    $response['success'] = true;
    $response['proyectos'] = $proyectos;
    $response['managed_departments'] = $departamentos_gerente;
    $response['is_admin'] = $is_admin;
    
    if (empty($proyectos)) {
        $response['message'] = 'No hay proyectos en progreso en los departamentos';
    }

} catch (Exception $e) {
    ob_clean();
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_top_projects_progress.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
ob_end_flush();
?>