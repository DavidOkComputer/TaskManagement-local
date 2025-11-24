<?php
/*get_task_workload_by_department.php para obtener la distribución de tareas por departamento*/

ob_start();
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once('db_config.php');

$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Query para obtener tareas agrupadas por departamento
    // Cuenta todas las tareas no completadas por departamento
    $query = "
        SELECT 
            d.id_departamento,
            d.nombre as nombre_departamento,
            COUNT(t.id_tarea) as total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) as tareas_completadas,
            SUM(CASE WHEN t.estado = 'en proceso' OR t.estado = 'en-progreso' THEN 1 ELSE 0 END) as tareas_en_proceso,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) as tareas_pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) as tareas_vencidas
        FROM tbl_departamentos d
        LEFT JOIN tbl_proyectos p ON d.id_departamento = p.id_departamento
        LEFT JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
        GROUP BY d.id_departamento, d.nombre
        HAVING total_tareas > 0
        ORDER BY total_tareas DESC
    ";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

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
            'id_departamento' => (int)$row['id_departamento'],
            'nombre_departamento' => $row['nombre_departamento'],
            'total_tareas' => $total_tareas,
            'tareas_completadas' => (int)($row['tareas_completadas'] ?? 0),
            'tareas_en_proceso' => (int)($row['tareas_en_proceso'] ?? 0),
            'tareas_pendientes' => (int)($row['tareas_pendientes'] ?? 0),
            'tareas_vencidas' => (int)($row['tareas_vencidas'] ?? 0)
        ];
    }

    $stmt->close();

    if (empty($workload_data)) {
        throw new Exception('No hay datos de carga de trabajo disponibles');
    }

    // Procesar datos para grafica de pastel de distribucion de carga
    $processed_data = processWorkloadData($workload_data, $total_tasks);

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
    error_log('Error en get_task_workload_by_department.php: ' . $e->getMessage());
}

ob_end_flush();

function processWorkloadData($workload_data, $total_tasks) {
    $labels = [];
    $values = [];
    $colors = getWorkloadColors(count($workload_data));
    $percentages = [];
    $details = [];

    foreach ($workload_data as $index => $dept) {
        $dept_name = $dept['nombre_departamento'];
        $total = $dept['total_tareas'];
        $percentage = $total_tasks > 0 ? round(($total / $total_tasks) * 100, 1) : 0;

        $labels[] = $dept_name;
        $values[] = $total;
        $percentages[] = $percentage;
        
        $details[] = [
            'id_departamento' => $dept['id_departamento'],
            'nombre' => $dept_name,
            'total_tareas' => $total,
            'completadas' => $dept['tareas_completadas'],
            'en_proceso' => $dept['tareas_en_proceso'],
            'pendientes' => $dept['tareas_pendientes'],
            'vencidas' => $dept['tareas_vencidas'],
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

function getWorkloadColors($count) {
    $colors = [
        // Verde primario
        ['border' => 'rgba(34, 139, 89, 1)', 'background' => 'rgba(34, 139, 89, 0.7)'],
        // Verde claro
        ['border' => 'rgba(80, 154, 108, 1)', 'background' => 'rgba(80, 154, 108, 0.7)'],
        // Verde oscuro
        ['border' => 'rgba(24, 97, 62, 1)', 'background' => 'rgba(24, 97, 62, 0.7)'],
        // Verde secundario
        ['border' => 'rgba(45, 110, 80, 1)', 'background' => 'rgba(45, 110, 80, 0.7)'],
        // Gris
        ['border' => 'rgba(130, 140, 150, 1)', 'background' => 'rgba(130, 140, 150, 0.7)'],
        // Gris claro
        ['border' => 'rgba(160, 170, 180, 1)', 'background' => 'rgba(160, 170, 180, 0.7)'],
        // Ice/Gris claro
        ['border' => 'rgba(200, 205, 210, 1)', 'background' => 'rgba(200, 205, 210, 0.7)'],
        // Negro
        ['border' => 'rgba(50, 50, 50, 1)', 'background' => 'rgba(50, 50, 50, 0.7)'],
    ];

    // Ciclar si hay más departamentos que colores
    $result = [];
    for ($i = 0; $i < $count; $i++) {
        $result[] = $colors[$i % count($colors)];
    }

    return $result;
}
?>