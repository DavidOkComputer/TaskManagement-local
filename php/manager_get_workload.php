<?php
/**
 * manager_get_workload.php
 * Gets task distribution by project for manager's department
 * Used by workload doughnut chart
 */

ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

require_once 'db_config.php';

$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

try {
    // Validate department ID
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Get task distribution by project in this department
    $query = "
        SELECT 
            p.id_proyecto,
            p.nombre AS proyecto_nombre,
            COUNT(t.id_tarea) AS total_tareas,
            SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) AS completadas,
            SUM(CASE WHEN t.estado = 'en proceso' THEN 1 ELSE 0 END) AS en_proceso,
            SUM(CASE WHEN t.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
            SUM(CASE WHEN t.estado = 'vencido' THEN 1 ELSE 0 END) AS vencidas
        FROM tbl_proyectos p
        INNER JOIN tbl_tareas t ON p.id_proyecto = t.id_proyecto
        WHERE p.id_departamento = ?
        GROUP BY p.id_proyecto, p.nombre
        HAVING total_tareas > 0
        ORDER BY total_tareas DESC
        LIMIT 10
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
    
    $labels = [];
    $data = [];
    $details = [];
    $totalTareas = 0;
    
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['proyecto_nombre'];
        $data[] = (int)$row['total_tareas'];
        $totalTareas += (int)$row['total_tareas'];
        
        $details[] = [
            'id_proyecto' => (int)$row['id_proyecto'],
            'nombre' => $row['proyecto_nombre'],
            'total' => (int)$row['total_tareas'],
            'completadas' => (int)$row['completadas'],
            'en_proceso' => (int)$row['en_proceso'],
            'pendientes' => (int)$row['pendientes'],
            'vencidas' => (int)$row['vencidas']
        ];
    }
    
    if (empty($labels)) {
        $response['success'] = false;
        $response['message'] = 'No hay proyectos con tareas en este departamento';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Generate colors for each project
    $colorPalette = [
        ['bg' => 'rgba(34, 139, 89, 0.7)', 'border' => 'rgba(34, 139, 89, 1)'],
        ['bg' => 'rgba(80, 154, 108, 0.7)', 'border' => 'rgba(80, 154, 108, 1)'],
        ['bg' => 'rgba(24, 97, 62, 0.7)', 'border' => 'rgba(24, 97, 62, 1)'],
        ['bg' => 'rgba(130, 140, 150, 0.7)', 'border' => 'rgba(130, 140, 150, 1)'],
        ['bg' => 'rgba(200, 205, 210, 0.7)', 'border' => 'rgba(200, 205, 210, 1)'],
        ['bg' => 'rgba(50, 50, 50, 0.7)', 'border' => 'rgba(50, 50, 50, 1)'],
        ['bg' => 'rgba(45, 110, 80, 0.7)', 'border' => 'rgba(45, 110, 80, 1)'],
        ['bg' => 'rgba(160, 170, 180, 0.7)', 'border' => 'rgba(160, 170, 180, 1)'],
        ['bg' => 'rgba(100, 120, 100, 0.7)', 'border' => 'rgba(100, 120, 100, 1)'],
        ['bg' => 'rgba(70, 90, 70, 0.7)', 'border' => 'rgba(70, 90, 70, 1)']
    ];
    
    $backgroundColor = [];
    $borderColor = [];
    
    foreach ($labels as $index => $label) {
        $color = $colorPalette[$index % count($colorPalette)];
        $backgroundColor[] = $color['bg'];
        $borderColor[] = $color['border'];
    }
    
    $response['success'] = true;
    $response['data'] = [
        'labels' => $labels,
        'data' => $data,
        'backgroundColor' => $backgroundColor,
        'borderColor' => $borderColor,
        'details' => $details,
        'total_tareas' => $totalTareas
    ];
    $response['id_departamento'] = $id_departamento;
    $response['total_proyectos'] = count($labels);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_workload.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>