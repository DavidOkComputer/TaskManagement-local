<?php
/* get_task_workload_by_project.php para obtener la distribución de tareas por proyecto dentro de un departamento*/

ob_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('db_config.php');

$response = [
    'success' => false,
    'message' => '',
    'data' => null,
    'department_id' => null,
    'department_name' => null
];

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Obtener id_departamento del parámetro GET
    $id_departamento = isset($_GET['id_departamento']) ? intval($_GET['id_departamento']) : null;

    if (!$id_departamento) {
        throw new Exception('ID de departamento no proporcionado');
    }

    // Verificar que el departamento existe
    $dept_check = $conn->prepare("SELECT nombre FROM tbl_departamentos WHERE id_departamento = ?");
    if (!$dept_check) {
        throw new Exception('Error preparando consulta de verificación: ' . $conn->error);
    }
    
    $dept_check->bind_param('i', $id_departamento);
    $dept_check->execute();
    $dept_result = $dept_check->get_result();
    
    if ($dept_result->num_rows === 0) {
        throw new Exception('Departamento no encontrado');
    }
    
    $dept_row = $dept_result->fetch_assoc();
    $response['department_id'] = $id_departamento;
    $response['department_name'] = $dept_row['nombre'];
    
    $dept_check->close();

    // Query para obtener tareas agrupadas por proyecto dentro del departamento
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre as nombre_proyecto,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
            SUM(CASE WHEN t.estado = 'en proceso' OR t.estado = 'en-progreso' THEN 1 ELSE 0 END) as tareas_en_proceso,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as tareas_pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as tareas_vencidas
        FROM tbl_proyectos p
        LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
        WHERE p.id_departamento = ?
        GROUP BY p.id_proyecto, p.nombre
        HAVING total_tareas > 0
        ORDER BY total_tareas DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param('i', $id_departamento);

    if (!$stmt->execute()) {
        throw new Exception('Error ejecutando consulta: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $workload_data = [];
    $total_tasks = 0;

    while ($row = $result->fetch_assoc()) {
        $total_tareas = (int)$row['total_tareas'];
        $total_tasks += $total_tareas;

        $workload_data[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre_proyecto' => $row['nombre_proyecto'],
            'total_tareas' => $total_tareas,
            'tareas_completadas' => (int)($row['tareas_completadas'] ?? 0),
            'tareas_en_proceso' => (int)($row['tareas_en_proceso'] ?? 0),
            'tareas_pendientes' => (int)($row['tareas_pendientes'] ?? 0),
            'tareas_vencidas' => (int)($row['tareas_vencidas'] ?? 0)
        ];
    }

    $stmt->close();

    if (empty($workload_data)) {
        // Si no hay tareas, retornar información del departamento vacío
        $response['success'] = true;
        $response['message'] = 'El departamento no tiene proyectos con tareas asignadas';
        $response['data'] = [
            'labels' => [],
            'data' => [],
            'percentages' => [],
            'backgroundColor' => [],
            'borderColor' => [],
            'details' => [],
            'total_tareas' => 0
        ];
        
        if (ob_get_length()) ob_clean();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        $conn->close();
        ob_end_flush();
        exit;
    }

    // Procesar datos para pie chart
    $processed_data = processProjectWorkloadData($workload_data, $total_tasks);

    if (ob_get_length()) ob_clean();
    
    $response['success'] = true;
    $response['data'] = $processed_data;
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    $conn->close();

} catch (Exception $e) {
    if (ob_get_length()) ob_clean();
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    error_log('Error en get_task_workload_by_project.php: ' . $e->getMessage());
}

ob_end_flush();

function processProjectWorkloadData($workload_data, $total_tasks) {
    $labels = [];
    $values = [];
    $colors = getProjectWorkloadColors(count($workload_data));
    $percentages = [];
    $details = [];

    foreach ($workload_data as $index => $project) {
        $project_name = $project['nombre_proyecto'];
        $total = $project['total_tareas'];
        $percentage = $total_tasks > 0 ? round(($total / $total_tasks) * 100, 1) : 0;

        $labels[] = $project_name;
        $values[] = $total;
        $percentages[] = $percentage;
        
        $details[] = [
            'id_proyecto' => $project['id_proyecto'],
            'nombre' => $project_name,
            'total_tareas' => $total,
            'completadas' => $project['tareas_completadas'],
            'en_proceso' => $project['tareas_en_proceso'],
            'pendientes' => $project['tareas_pendientes'],
            'vencidas' => $project['tareas_vencidas'],
            'porcentaje' => $percentage
        ];
    }

    return [
        'labels' => $labels,
        'data' => $values,
        'percentages' => $percentages,
        'backgroundColor' => array_map(fn($c) => $c['background'], $colors),
        'borderColor' => array_map(fn($c) => $c['border'], $colors),
        'details' => $details,
        'total_tareas' => array_sum($values)
    ];
}

function getProjectWorkloadColors($count) {
    $colors = [
        // Verde primario
        ['border' => 'rgba(34, 139, 89, 1)', 'background' => 'rgba(34, 139, 89, 0.7)'],
        // Verde claro
        ['border' => 'rgba(80, 154, 108, 1)', 'background' => 'rgba(80, 154, 108, 0.7)'],
        // Verde oscuro
        ['border' => 'rgba(24, 97, 62, 1)', 'background' => 'rgba(24, 97, 62, 0.7)'],
        // Verde secundario
        ['border' => 'rgba(45, 110, 80, 1)', 'background' => 'rgba(45, 110, 80, 0.7)'],
        ['border' => 'rgb(217, 220, 223)', 'background' => 'rgb(217, 220, 223)'],
        ['border' => 'rgb(167, 174, 181)', 'background' => 'rgb(167, 174, 181)'],
        ['border' => 'rgb(166, 175, 180)', 'background' => 'rgb(166, 175, 180)'],
        ['border' => 'rgb(110, 110, 110)', 'background' => 'rgb(110, 110, 110)'],
    ];

    // Ciclar si hay más proyectos que colores
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }

    return $result;
}
?>