<?php
/*manager_get_task_trends.php obtener la completacion de tareas atraves del tiempo*/

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
    if (!isset($_GET['id_departamento']) || empty($_GET['id_departamento'])) {
        throw new Exception('ID de departamento requerido');
    }
    
    $id_departamento = (int)$_GET['id_departamento'];
    $weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 12;
    
    if ($id_departamento <= 0) {
        throw new Exception('ID de departamento inválido');
    }
    
    $weeks = max(4, min(52, $weeks));
    
    $conn = getDBConnection();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $labels = [];
    
    for ($i = $weeks - 1; $i >= 0; $i--) {
        $weekStart = date('Y-m-d', strtotime("-{$i} weeks monday"));
        $weekLabel = date('d/m', strtotime($weekStart));
        $labels[] = $weekLabel;
    }
    
    $proj_query = "SELECT id_proyecto, nombre FROM tbl_proyectos WHERE id_departamento = ?";
    $proj_stmt = $conn->prepare($proj_query);
    $proj_stmt->bind_param('i', $id_departamento);
    $proj_stmt->execute();
    $proj_result = $proj_stmt->get_result();
    
    $projects = [];
    $projectIds = [];
    
    while ($proj = $proj_result->fetch_assoc()) {
        $projects[] = $proj;
        $projectIds[] = $proj['id_proyecto'];
    }
    $proj_stmt->close();
    
    if (empty($projectIds)) {
        $response['success'] = true;
        $response['data'] = [
            'labels' => $labels,
            'datasets' => []
        ];
        $response['message'] = 'No hay proyectos en este departamento';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $datasets = [];
    $colorIndex = 0;
    
    $colors = [
        ['bg' => 'rgba(34, 139, 89, 0.3)', 'border' => 'rgba(34, 139, 89, 1)'],
        ['bg' => 'rgba(80, 154, 108, 0.3)', 'border' => 'rgba(80, 154, 108, 1)'],
        ['bg' => 'rgba(24, 97, 62, 0.3)', 'border' => 'rgba(24, 97, 62, 1)'],
        ['bg' => 'rgba(130, 140, 150, 0.3)', 'border' => 'rgba(130, 140, 150, 1)'],
        ['bg' => 'rgba(200, 205, 210, 0.3)', 'border' => 'rgba(200, 205, 210, 1)'],
        ['bg' => 'rgba(50, 50, 50, 0.3)', 'border' => 'rgba(50, 50, 50, 1)']
    ];
    
    foreach ($projects as $project) {
        $weeklyData = array_fill(0, count($labels), 0);
        
        $task_query = "
            SELECT 
                DATE(fecha_inicio) as fecha,
                COUNT(*) as cantidad
            FROM tbl_tareas
            WHERE id_proyecto = ?
              AND estado = 'completado'
              AND fecha_inicio >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
            GROUP BY YEARWEEK(fecha_inicio, 1)
            ORDER BY fecha ASC
        ";
        
        $task_stmt = $conn->prepare($task_query);
        
        if ($task_stmt) {
            $task_stmt->bind_param('ii', $project['id_proyecto'], $weeks);
            $task_stmt->execute();
            $task_result = $task_stmt->get_result();
            
            while ($row = $task_result->fetch_assoc()) {
                $weekLabel = date('d/m', strtotime('monday this week', strtotime($row['fecha'])));
                $weekIndex = array_search($weekLabel, $labels);
                if ($weekIndex !== false) {
                    $weeklyData[$weekIndex] += (int)$row['cantidad'];
                }
            }
            $task_stmt->close();
        }
        
        $cumulativeData = [];
        $cumulative = 0;
        foreach ($weeklyData as $value) {
            $cumulative += $value;
            $cumulativeData[] = $cumulative;
        }
        
        if ($cumulative > 0) {
            $color = $colors[$colorIndex % count($colors)];
            
            $datasets[] = [
                'label' => mb_substr($project['nombre'], 0, 25) . (mb_strlen($project['nombre']) > 25 ? '...' : ''),
                'data' => $cumulativeData,
                'backgroundColor' => $color['bg'],
                'borderColor' => $color['border'],
                'fill' => true,
                'tension' => 0.4
            ];
            
            $colorIndex++;
        }
    }
    
    $response['success'] = true;
    $response['data'] = [
        'labels' => $labels,
        'datasets' => $datasets
    ];
    $response['id_departamento'] = $id_departamento;
    $response['weeks'] = $weeks;
    
    $conn->close();
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('manager_get_task_trends.php Error: ' . $e->getMessage());
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>